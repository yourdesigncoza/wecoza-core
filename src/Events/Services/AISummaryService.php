<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\Services\Traits\DataObfuscator;
use WeCoza\Events\Support\OpenAIConfig;
use WeCoza\Events\DTOs\RecordDTO;
use WeCoza\Events\DTOs\EmailContextDTO;
use WeCoza\Events\DTOs\SummaryResultDTO;
use WeCoza\Events\DTOs\ObfuscatedDataDTO;
use WeCoza\Events\Enums\SummaryStatus;

use function array_merge;
use function count;
use function gmdate;
use function is_array;
use function is_wp_error;
use function json_decode;
use function max;
use function microtime;
use function preg_replace;
use function sprintf;
use function strtoupper;
use function trim;
use function usleep;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use const JSON_PRETTY_PRINT;

final class AISummaryService
{
    use DataObfuscator;

    private const TIMEOUT_SECONDS = 30;

    /**
     * @var callable
     */
    private $httpClient;

    /**
     * @var array{attempts:int,success:int,failed:int,total_tokens:int,processing_time_ms:int}
     */
    private array $metrics = [
        'attempts' => 0,
        'success' => 0,
        'failed' => 0,
        'total_tokens' => 0,
        'processing_time_ms' => 0,
    ];

    public function __construct(
        private readonly OpenAIConfig $config,
        ?callable $httpClient = null,
        private readonly int $maxAttempts = 3
    ) {
        $this->httpClient = $httpClient ?? $this->defaultHttpClient();
    }

    /**
     * Generate AI summary for class change notification
     *
     * @param array<string,mixed> $context Change context (new_row, diff, old_row, operation, etc.)
     * @param array<string,mixed>|null $existing Existing record if retrying
     * @return SummaryResultDTO
     */
    public function generateSummary(array $context, ?array $existing = null): SummaryResultDTO
    {
        $record = $this->normaliseRecord($existing);

        // Early exit for already-complete or max-attempts records
        if ($this->shouldSkipGeneration($record)) {
            return $this->buildSkippedResult($record);
        }

        // Obfuscate PII from context
        $obfuscatedData = $this->obfuscateContext($context);

        // Prepare for API call with backoff delay
        $delaySeconds = $this->backoffDelaySeconds($record->attempts);
        if ($delaySeconds > 0) {
            usleep($delaySeconds * 1_000_000);
        }

        // Build prompt messages
        $messages = $this->buildMessages(
            (string) ($context['operation'] ?? ''),
            $context,
            $obfuscatedData->newRow,
            $obfuscatedData->diff,
            $obfuscatedData->oldRow
        );

        // Call OpenAI API
        $start = microtime(true);
        $response = $this->callOpenAI($messages);
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        // Update record with attempt info
        $record = $record->incrementAttempts();

        $this->metrics['attempts']++;
        $this->metrics['processing_time_ms'] += $elapsed;

        // Process response and return result
        return $this->processApiResponse($response, $record, $obfuscatedData, $elapsed);
    }

    /**
     * Generate summary returning array format (backward compatibility)
     *
     * @deprecated Use generateSummary() which returns SummaryResultDTO
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $existing
     * @return array{record:array<string,mixed>, email_context:array<string,mixed>, status:string}
     */
    public function generateSummaryArray(array $context, ?array $existing = null): array
    {
        return $this->generateSummary($context, $existing)->toArray();
    }

    /**
     * @return array<string,int>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @param array<int,array<string,string>> $messages
     * @return array{success:bool,content:string,error_code:?string,error_message:?string,retryable:bool,model:?string,tokens:int}
     */
    private function callOpenAI(array $messages): array
    {
        $apiKey = $this->config->getApiKey();
        if ($apiKey === null) {
            return [
                'success' => false,
                'content' => '',
                'error_code' => 'config_missing',
                'error_message' => 'OpenAI API key is not configured.',
                'retryable' => false,
                'model' => null,
                'tokens' => 0,
            ];
        }

        $apiUrl = $this->config->getApiUrl();
        $model = $this->config->getModel();

        $payload = [
            'model' => $model,
            'messages' => $messages
            // 'temperature' => 0.1,
            // 'max_completion_tokens' => 350,
        ];

        $response = ($this->httpClient)([
            'url' => $apiUrl,
            'timeout' => self::TIMEOUT_SECONDS,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body' => $payload,
        ]);

        if ($response instanceof \WP_Error) {
            return [
                'success' => false,
                'content' => '',
                'error_code' => $this->mapErrorCode($response->get_error_code(), 0),
                'error_message' => $this->sanitizeErrorMessage($response->get_error_message()),
                'retryable' => true,
                'model' => $model,
                'tokens' => 0,
            ];
        }

        $statusCode = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = $this->extractErrorMessage($body) ?: sprintf('OpenAI HTTP %d', $statusCode);

            return [
                'success' => false,
                'content' => '',
                'error_code' => $this->mapErrorCode('', $statusCode),
                'error_message' => $errorMessage,
                'retryable' => $statusCode >= 500 || $statusCode === 429,
                'model' => $model,
                'tokens' => 0,
            ];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'content' => '',
                'error_code' => 'validation_failed',
                'error_message' => 'Unable to decode OpenAI response.',
                'retryable' => true,
                'model' => $model,
                'tokens' => 0,
            ];
        }

        $choices = $decoded['choices'][0]['message']['content'] ?? '';
        $tokens = (int) ($decoded['usage']['total_tokens'] ?? 0);

        return [
            'success' => true,
            'content' => (string) $choices,
            'error_code' => null,
            'error_message' => null,
            'retryable' => false,
            'model' => $model,
            'tokens' => $tokens,
        ];
    }

    /**
     * Normalise existing record into RecordDTO
     *
     * @param array<string,mixed>|null $existing
     */
    private function normaliseRecord(?array $existing): RecordDTO
    {
        return RecordDTO::fromArray($existing);
    }

    /**
     * Check if summary generation should be skipped
     */
    private function shouldSkipGeneration(RecordDTO $record): bool
    {
        return $record->status === SummaryStatus::SUCCESS->value
            || $record->attempts >= $this->maxAttempts;
    }

    /**
     * Build result for skipped generation (already success or max attempts)
     */
    private function buildSkippedResult(RecordDTO $record): SummaryResultDTO
    {
        $emailContext = EmailContextDTO::empty();

        if ($record->status === SummaryStatus::SUCCESS->value) {
            return SummaryResultDTO::success($record, $emailContext);
        }

        // Max attempts reached
        $record = $record->withStatus(SummaryStatus::FAILED->value);
        return SummaryResultDTO::failed($record, $emailContext);
    }

    /**
     * Obfuscate PII from context data
     *
     * @param array<string,mixed> $context Raw context with potential PII
     * @return ObfuscatedDataDTO Obfuscated payloads with alias mappings
     */
    private function obfuscateContext(array $context): ObfuscatedDataDTO
    {
        $state = null;

        $newRowResult = $this->obfuscatePayloadWithLabels(
            (array) ($context['new_row'] ?? []),
            $state
        );
        $state = $newRowResult['state'];

        $diffResult = $this->obfuscatePayloadWithLabels(
            (array) ($context['diff'] ?? []),
            $state
        );
        $state = $diffResult['state'];

        $oldRowResult = $this->obfuscatePayloadWithLabels(
            (array) ($context['old_row'] ?? []),
            $state
        );

        return ObfuscatedDataDTO::fromResults($newRowResult, $diffResult, $oldRowResult);
    }

    /**
     * Process OpenAI API response and build result DTO
     *
     * @param array{success:bool,content:string,error_code:?string,error_message:?string,model:?string,tokens:int} $response
     * @param RecordDTO $record Current record state
     * @param ObfuscatedDataDTO $obfuscatedData Obfuscation results
     * @param int $elapsed Processing time in milliseconds
     * @return SummaryResultDTO
     */
    private function processApiResponse(
        array $response,
        RecordDTO $record,
        ObfuscatedDataDTO $obfuscatedData,
        int $elapsed
    ): SummaryResultDTO {
        $emailContext = $obfuscatedData->toEmailContext();

        if ($response['success'] === true) {
            $summaryText = $this->normaliseSummaryText($response['content']);

            $record = $record
                ->withStatus(SummaryStatus::SUCCESS->value)
                ->withSummary($summaryText)
                ->withError(null, null)
                ->withGeneratedAt(gmdate('c'))
                ->withModel($response['model'])
                ->withTokensUsed($response['tokens'])
                ->withProcessingTimeMs($elapsed);

            $this->metrics['success']++;
            $this->metrics['total_tokens'] += $response['tokens'];

            return SummaryResultDTO::success($record, $emailContext);
        }

        // Handle failure
        $record = $record
            ->withError($response['error_code'], $response['error_message'])
            ->withModel($record->model ?? $response['model'])
            ->withProcessingTimeMs($elapsed);

        $newStatus = $record->attempts >= $this->maxAttempts
            ? SummaryStatus::FAILED->value
            : SummaryStatus::PENDING->value;

        $record = $record->withStatus($newStatus);

        if ($newStatus === SummaryStatus::FAILED->value) {
            $this->metrics['failed']++;
            return SummaryResultDTO::failed($record, $emailContext);
        }

        return SummaryResultDTO::pending($record, $emailContext);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string|int,mixed> $newRow
     * @param array<string|int,mixed> $diff
     * @param array<string|int,mixed> $oldRow
     * @return array<int,array<string,string>>
     */
    private function buildMessages(string $operation, array $context, array $newRow, array $diff, array $oldRow): array
    {
        $operation = strtoupper(trim($operation));

        // Identifying fields always included
        $identifyingFields = [
            'operation' => $operation,
            'changed_at' => $context['changed_at'] ?? null,
            'class_id' => $context['class_id'] ?? null,
            'class_code' => $context['new_row']['class_code'] ?? null,
            'class_subject' => $context['new_row']['class_subject'] ?? null,
            'learner_count' => is_array($context['new_row']['learner_ids'] ?? null)
                ? count($context['new_row']['learner_ids'])
                : 0,
        ];

        if ($operation === 'UPDATE') {
            // For UPDATE: only send diff + identifying fields — no full row data
            $summaryContext = array_merge($identifyingFields, [
                'diff' => $diff,
            ]);
        } else {
            // For INSERT/DELETE: send full new_row for complete picture
            $summaryContext = array_merge($identifyingFields, [
                'exam_class' => $context['new_row']['exam_class'] ?? false,
                'diff' => $diff,
                'new_row' => $newRow,
            ]);
        }

        $prompt = match ($operation) {
            'INSERT' => "Summarize this new WeCoza class in 2-3 bullet points covering: class code, subject, schedule pattern, learner count (use the top-level learner_count field), and assigned agent. Then check for ACTUAL issues only — do not flag something as missing if the data is present under a different key name. Only flag: truly empty required fields (class code, agent, start date), zero learners, or scheduling conflicts. If no real issues, state 'No issues detected.' Max 5 bullets total. Use learner aliases instead of real names.",
            'UPDATE' => "Describe ONLY the changes made to this WeCoza class based on the diff provided. Rules:\n"
                . "1. ONLY describe fields that actually changed (present in the diff). Do NOT mention any unchanged fields.\n"
                . "2. For each change, briefly state what changed: old value → new value.\n"
                . "3. If learner_ids changed, summarize as learners added/removed (use aliases, not real names).\n"
                . "4. If event_dates changed, highlight date shifts or new events.\n"
                . "5. Flag CONFIRMED issues only (e.g., start date moved to the past, zero learners after removal).\n"
                . "6. If no issues, do NOT add an issues bullet.\n"
                . "7. Max 4 bullets total. Be concise.",
            default => sprintf(
                "Summarize this WeCoza class %s in 2-3 bullet points. Reference learners using aliases. Flag only CONFIRMED issues. Max 4 bullets.",
                strtolower($operation)
            ),
        };

        $systemMessage = $operation === 'UPDATE'
            ? 'You are an assistant helping WeCoza operations understand class changes. ONLY report what changed — never mention unchanged fields. Be brief, factual, and actionable.'
            : 'You are an assistant helping WeCoza operations understand class changes. Be brief, factual, and actionable.';

        $payload = wp_json_encode($summaryContext, JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $prompt . "\n\n" . $payload],
        ];
    }

    private function backoffDelaySeconds(int $attempts): int
    {
        return match ($attempts) {
            0 => 0,
            1 => 1,
            2 => 2,
            default => 4,
        };
    }

    private function normaliseSummaryText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'No summary content returned.';
        }

        return $value;
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Unknown error.';
        }

        // Redact OpenAI API keys (sk-... and sk-proj-... patterns)
        $message = preg_replace('/sk-[A-Za-z0-9\-_]{20,}/', 'sk-REDACTED', $message) ?? $message;
        // Redact Bearer tokens
        $message = preg_replace('/Bearer\s+[A-Za-z0-9\-_\.]{20,}/', 'Bearer REDACTED', $message) ?? $message;
        // Redact Authorization header values
        $message = preg_replace('/Authorization:\s*[^\s,;]+/', 'Authorization: REDACTED', $message) ?? $message;

        return $message;
    }

    private function mapErrorCode(string $code, int $status): string
    {
        if ($code === 'http_request_failed' || $status === 408) {
            return 'openai_timeout';
        }

        if ($status === 429 || $code === 'rest_post_dispatch') {
            return 'quota_exceeded';
        }

        if ($status >= 400 && $status < 500) {
            return 'validation_failed';
        }

        return 'unknown_error';
    }

    private function extractErrorMessage(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        $message = $decoded['error']['message'] ?? null;
        return is_string($message) ? $this->sanitizeErrorMessage($message) : null;
    }

    private function defaultHttpClient(): callable
    {
        return static function (array $request) {
            $response = wp_remote_post($request['url'], [
                'timeout' => $request['timeout'],
                'headers' => $request['headers'],
                'body' => wp_json_encode($request['body']),
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            return [
                'status' => wp_remote_retrieve_response_code($response),
                'body' => wp_remote_retrieve_body($response),
            ];
        };
    }
}
