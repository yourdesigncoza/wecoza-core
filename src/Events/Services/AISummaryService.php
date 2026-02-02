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

    private const MODEL = 'gpt-5-mini';
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT_SECONDS = 60;

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
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $existing
     * @return array{record:array<string,mixed>, email_context:array<string,mixed>, status:string}
     */
    public function generateSummary(array $context, ?array $existing = null): array
    {
        $record = $this->normaliseRecord($existing);

        if ($record['status'] === 'success') {
            return [
                'record' => $record,
                'email_context' => ['alias_map' => [], 'obfuscated' => []],
                'status' => 'success',
            ];
        }

        if ($record['attempts'] >= $this->maxAttempts) {
            $record['status'] = 'failed';
            return [
                'record' => $record,
                'email_context' => ['alias_map' => [], 'obfuscated' => []],
                'status' => 'failed',
            ];
        }

        $state = null;
        $newRowResult = $this->obfuscatePayloadWithLabels((array) ($context['new_row'] ?? []), $state);
        $state = $newRowResult['state'];
        $diffResult = $this->obfuscatePayloadWithLabels((array) ($context['diff'] ?? []), $state);
        $state = $diffResult['state'];
        $oldRowResult = $this->obfuscatePayloadWithLabels((array) ($context['old_row'] ?? []), $state);

        $aliasMap = $oldRowResult['state']['aliases'];
        $fieldLabels = array_merge(
            $newRowResult['field_labels'],
            $diffResult['field_labels'],
            $oldRowResult['field_labels']
        );

        $attemptNumber = $record['attempts'] + 1;
        $delaySeconds = $this->backoffDelaySeconds($record['attempts']);
        if ($delaySeconds > 0) {
            usleep($delaySeconds * 1_000_000);
        }

        $messages = $this->buildMessages(
            (string) ($context['operation'] ?? ''),
            $context,
            $newRowResult['payload'],
            $diffResult['payload'],
            $oldRowResult['payload']
        );

        $start = microtime(true);
        $response = $this->callOpenAI($messages, self::MODEL);

        $elapsed = (int) round((microtime(true) - $start) * 1000);

        $record['attempts'] = $attemptNumber;
        $record['processing_time_ms'] = $elapsed;

        $this->metrics['attempts']++;
        $this->metrics['processing_time_ms'] += $elapsed;

        if ($response['success'] === true) {
            $summaryText = $this->normaliseSummaryText($response['content']);

            $record['status'] = 'success';
            $record['summary'] = $summaryText;
            $record['error_code'] = null;
            $record['error_message'] = null;
            $record['generated_at'] = gmdate('c');
            $record['model'] = $response['model'];
            $record['tokens_used'] = $response['tokens'];

            $this->metrics['success']++;
            $this->metrics['total_tokens'] += $response['tokens'];

            return [
                'record' => $record,
                'email_context' => [
                    'alias_map' => $aliasMap,
                    'field_labels' => $fieldLabels,
                    'obfuscated' => [
                        'new_row' => $newRowResult['payload'],
                        'diff' => $diffResult['payload'],
                        'old_row' => $oldRowResult['payload'],
                    ],
                ],
                'status' => 'success',
            ];
        }

        $record['error_code'] = $response['error_code'];
        $record['error_message'] = $response['error_message'];
        $record['model'] = $record['model'] ?? $response['model'];
        $record['status'] = $record['attempts'] >= $this->maxAttempts ? 'failed' : 'pending';

        if ($record['status'] === 'failed') {
            $this->metrics['failed']++;
        }

        return [
            'record' => $record,
            'email_context' => [
                'alias_map' => $aliasMap,
                'field_labels' => $fieldLabels,
                'obfuscated' => [
                    'new_row' => $newRowResult['payload'],
                    'diff' => $diffResult['payload'],
                    'old_row' => $oldRowResult['payload'],
                ],
            ],
            'status' => $record['status'],
        ];
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
    private function callOpenAI(array $messages, string $model): array
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

        $payload = [
            'model' => $model,
            'messages' => $messages
            // 'temperature' => 0.1,
            // 'max_completion_tokens' => 350,
        ];

        $response = ($this->httpClient)([
            'url' => self::API_URL,
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

        $summaryContext = [
            'operation' => $operation,
            'changed_at' => $context['changed_at'] ?? null,
            'class_id' => $context['class_id'] ?? null,
            'class_code' => $newRow['class_code'] ?? null,
            'class_subject' => $newRow['class_subject'] ?? null,
            'diff' => $diff,
            'new_row' => $newRow,
        ];

        if ($operation === 'UPDATE') {
            $summaryContext['old_row'] = $oldRow;
        }

        $prompt = sprintf(
            "Provide a concise summary (maximum five bullet points) explaining the key aspects of the WeCoza class %s. Highlight scheduling, learner, or staffing changes and flag risks requiring follow-up. Reference learners using the aliases provided. Avoid exposing personal data.",
            strtolower($operation)
        );

        $payload = wp_json_encode($summaryContext, JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => 'You are an assistant helping WeCoza operations understand class changes. Be brief, factual, and actionable.'],
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

        $message = preg_replace('/sk-[A-Za-z0-9]{20,}/', 'sk-REDACTED', $message) ?? $message;

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
