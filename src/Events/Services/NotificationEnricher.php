<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RuntimeException;
use WeCoza\Core\Database\PostgresConnection;
use WeCoza\Events\Support\OpenAIConfig;

use function error_log;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function strtolower;
use function strtoupper;
use function absint;
use function do_action;
use function gmdate;
use function wecoza_log;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Handles AI enrichment for individual notifications.
 *
 * Designed to run as an Action Scheduler job. On success, schedules
 * the email sending job. On failure, logs error and leaves notification
 * for manual review or retry.
 */
final class NotificationEnricher
{
    private PostgresConnection $db;

    public function __construct(
        private readonly AISummaryService $aiSummaryService,
        private readonly OpenAIConfig $openAIConfig,
        private readonly NotificationSettings $settings
    ) {
        $this->db = PostgresConnection::getInstance();
    }

    public static function boot(): self
    {
        $openAIConfig = new OpenAIConfig();
        $aiSummaryService = new AISummaryService($openAIConfig);
        $settings = new NotificationSettings();

        return new self($aiSummaryService, $openAIConfig, $settings);
    }

    /**
     * Enrich a single notification with AI summary.
     *
     * @param int $logId The log_id to enrich
     * @return array{success: bool, should_email: bool, recipient: ?string, email_context: array}
     */
    public function enrich(int $logId): array
    {
        $row = $this->fetchRow($logId);
        if ($row === null) {
            wecoza_log("NotificationEnricher: Row not found for log_id {$logId}", 'warning');
            return ['success' => false, 'should_email' => false, 'recipient' => null, 'email_context' => []];
        }

        $operation = strtoupper((string) ($row['operation'] ?? ''));
        $recipient = $this->settings->getRecipientForOperation($operation);

        if ($recipient === null) {
            wecoza_log("NotificationEnricher: No recipient for operation {$operation} on log_id {$logId}", 'debug');
            return ['success' => true, 'should_email' => false, 'recipient' => null, 'email_context' => []];
        }

        $newRow = $this->decodeJson($row['new_row'] ?? null);
        $oldRow = $this->decodeJson($row['old_row'] ?? null);
        $diff = $this->decodeJson($row['diff'] ?? null);
        $summaryRecord = $this->decodeJson($row['ai_summary'] ?? null);

        $emailContext = ['alias_map' => [], 'obfuscated' => []];
        $eligibility = $this->openAIConfig->assessEligibility($logId);

        if ($eligibility['eligible'] === false) {
            if ($this->shouldMarkFailure($summaryRecord)) {
                $reason = is_string($eligibility['reason']) ? $eligibility['reason'] : 'feature_disabled';
                $summaryRecord = $this->finalizeSkippedSummary($summaryRecord, $reason);
                $this->persistSummary($logId, $summaryRecord);
                $this->emitSummaryMetrics($logId, $summaryRecord);
            }
        } elseif ($this->shouldGenerateSummary($summaryRecord)) {
            $result = $this->aiSummaryService->generateSummary([
                'log_id' => $logId,
                'operation' => $operation,
                'changed_at' => $row['changed_at'] ?? null,
                'class_id' => $row['class_id'] ?? null,
                'new_row' => $newRow,
                'old_row' => $oldRow,
                'diff' => $diff,
            ], $summaryRecord);

            $summaryRecord = $result->record->toArray();
            $emailContext = $result->emailContext->toArray();
            $this->persistSummary($logId, $summaryRecord);
            $this->emitSummaryMetrics($logId, $summaryRecord);
        }

        return [
            'success' => true,
            'should_email' => true,
            'recipient' => $recipient,
            'email_context' => $emailContext,
        ];
    }

    private function fetchRow(int $logId): ?array
    {
        $sql = <<<SQL
SELECT
    log_id,
    operation,
    changed_at,
    class_id,
    new_row,
    old_row,
    diff,
    ai_summary
FROM class_change_logs
WHERE log_id = :log_id
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare notification query.');
        }

        $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute notification query.');
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function decodeJson(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function shouldGenerateSummary(array $summary): bool
    {
        $status = is_string($summary['status'] ?? null) ? strtolower((string) $summary['status']) : 'pending';
        if ($status === 'success' || $status === 'failed') {
            return false;
        }

        $attempts = absint($summary['attempts'] ?? 0);
        return $attempts < $this->aiSummaryService->getMaxAttempts();
    }

    private function shouldMarkFailure(array $summary): bool
    {
        $status = is_string($summary['status'] ?? null) ? strtolower((string) $summary['status']) : 'pending';
        return $status !== 'failed' && $status !== 'success';
    }

    private function finalizeSkippedSummary(array $summary, string $reason): array
    {
        $normalised = $this->normaliseSummaryPayload($summary);
        $normalised['status'] = 'failed';
        $normalised['error_code'] = $reason;
        $normalised['error_message'] = $this->getSkipMessage($reason);
        if (!is_string($normalised['generated_at']) || $normalised['generated_at'] === '') {
            $normalised['generated_at'] = gmdate('c');
        }

        return $normalised;
    }

    private function getSkipMessage(string $reason): string
    {
        $messages = [
            'config_missing' => 'OpenAI configuration missing or invalid.',
            'feature_disabled' => 'AI summaries disabled via admin settings.',
        ];
        return $messages[$reason] ?? 'AI summary skipped.';
    }

    private function normaliseSummaryPayload(array $summary): array
    {
        return [
            'summary' => $summary['summary'] ?? null,
            'status' => (string) ($summary['status'] ?? 'pending'),
            'error_code' => $summary['error_code'] ?? null,
            'error_message' => $summary['error_message'] ?? null,
            'attempts' => absint($summary['attempts'] ?? 0),
            'viewed' => (bool) ($summary['viewed'] ?? false),
            'viewed_at' => $summary['viewed_at'] ?? null,
            'generated_at' => $summary['generated_at'] ?? null,
            'model' => $summary['model'] ?? null,
            'tokens_used' => isset($summary['tokens_used']) ? (int) $summary['tokens_used'] : 0,
            'processing_time_ms' => isset($summary['processing_time_ms']) ? (int) $summary['processing_time_ms'] : 0,
        ];
    }

    private function persistSummary(int $logId, array $summary): void
    {
        $stmt = $this->db->getPdo()->prepare('UPDATE class_change_logs SET ai_summary = :summary WHERE log_id = :log_id');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare AI summary update.');
        }

        $payload = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = '{}';
        }
        $stmt->bindValue(':summary', $payload, PDO::PARAM_STR);
        $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);

        $stmt->execute();
    }

    private function emitSummaryMetrics(int $logId, array $summary): void
    {
        do_action('wecoza_ai_summary_generated', [
            'log_id' => $logId,
            'status' => $summary['status'] ?? 'pending',
            'model' => $summary['model'] ?? null,
            'tokens_used' => $summary['tokens_used'] ?? 0,
            'processing_time_ms' => $summary['processing_time_ms'] ?? 0,
            'attempts' => $summary['attempts'] ?? 0,
        ]);
    }
}
