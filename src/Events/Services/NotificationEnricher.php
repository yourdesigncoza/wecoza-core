<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Repositories\ClassEventRepository;
use WeCoza\Events\Support\OpenAIConfig;

use function is_string;
use function strtolower;
use function absint;
use function do_action;
use function gmdate;
use function wecoza_log;

/**
 * Handles AI enrichment for individual notification events
 *
 * Designed to run as an Action Scheduler job. On success, schedules
 * the email sending job. On failure, logs error and leaves notification
 * for manual review or retry.
 *
 * @since 1.2.0
 */
final class NotificationEnricher
{
    private ClassEventRepository $eventRepository;

    public function __construct(
        private readonly AISummaryService $aiSummaryService,
        private readonly OpenAIConfig $openAIConfig,
        private readonly NotificationSettings $settings
    ) {
        $this->eventRepository = new ClassEventRepository();
    }

    public static function boot(): self
    {
        $openAIConfig = new OpenAIConfig();
        $aiSummaryService = new AISummaryService($openAIConfig);
        $settings = new NotificationSettings();

        return new self($aiSummaryService, $openAIConfig, $settings);
    }

    /**
     * Enrich a single event with AI summary
     *
     * @param int $eventId The event_id to enrich
     * @return array{success: bool, should_email: bool, recipients: array<string>, email_context: array}
     */
    public function enrich(int $eventId): array
    {
        $event = $this->eventRepository->findByEventId($eventId);
        if ($event === null) {
            wecoza_log("NotificationEnricher: Event not found for event_id {$eventId}", 'warning');
            return ['success' => false, 'should_email' => false, 'recipients' => [], 'email_context' => []];
        }

        // Get recipients for event type (supports multiple recipients)
        $recipients = $this->settings->getRecipientsForEventType($event->eventType->value);

        if (empty($recipients)) {
            wecoza_log("NotificationEnricher: No recipients for event type {$event->eventType->value} on event_id {$eventId}", 'debug');
            return ['success' => true, 'should_email' => false, 'recipients' => [], 'email_context' => []];
        }

        // Map EventType to operation for AI summary context
        $operation = $this->mapEventTypeToOperation($event);

        // Extract data from eventData JSONB
        $newRow = $event->eventData['new_row'] ?? [];
        $oldRow = $event->eventData['old_row'] ?? [];
        $diff = $event->eventData['diff'] ?? [];
        $summaryRecord = $event->aiSummary ?? [];

        $emailContext = ['alias_map' => [], 'obfuscated' => []];
        $eligibility = $this->openAIConfig->assessEligibility($eventId);

        if ($eligibility['eligible'] === false) {
            if ($this->shouldMarkFailure($summaryRecord)) {
                $reason = is_string($eligibility['reason']) ? $eligibility['reason'] : 'feature_disabled';
                $summaryRecord = $this->finalizeSkippedSummary($summaryRecord, $reason);
                $this->persistSummary($eventId, $summaryRecord);
                $this->emitSummaryMetrics($eventId, $summaryRecord);
            }
            // Update status to 'sending' (enriched and ready to send)
            $this->eventRepository->updateStatus($eventId, 'sending');
        } elseif ($this->shouldGenerateSummary($summaryRecord)) {
            $result = $this->aiSummaryService->generateSummary([
                'event_id' => $eventId,
                'operation' => $operation,
                'changed_at' => $event->createdAt,
                'class_id' => $event->entityId,
                'new_row' => $newRow,
                'old_row' => $oldRow,
                'diff' => $diff,
            ], $summaryRecord);

            $summaryRecord = $result->record->toArray();
            $emailContext = $result->emailContext->toArray();
            $this->persistSummary($eventId, $summaryRecord);
            $this->emitSummaryMetrics($eventId, $summaryRecord);

            // Update status to 'sending' (enriched and ready to send)
            $this->eventRepository->updateStatus($eventId, 'sending');
        }

        return [
            'success' => true,
            'should_email' => true,
            'recipients' => $recipients,
            'email_context' => $emailContext,
        ];
    }

    /**
     * Map EventType enum to operation string for NotificationSettings lookup.
     *
     * @param ClassEventDTO $event Event DTO
     * @return string Operation string ('INSERT', 'UPDATE', etc.)
     */
    private function mapEventTypeToOperation(ClassEventDTO $event): string
    {
        return match ($event->eventType->value) {
            'CLASS_INSERT', 'LEARNER_ADD' => 'INSERT',
            'CLASS_UPDATE', 'LEARNER_UPDATE', 'STATUS_CHANGE' => 'UPDATE',
            'CLASS_DELETE', 'LEARNER_REMOVE' => 'DELETE',
            default => 'UPDATE',
        };
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

    /**
     * Persist AI summary to class_events table
     *
     * @param int $eventId Event ID
     * @param array $summary Summary record to persist
     */
    private function persistSummary(int $eventId, array $summary): void
    {
        $this->eventRepository->updateAiSummary($eventId, $summary);
    }

    private function emitSummaryMetrics(int $eventId, array $summary): void
    {
        do_action('wecoza_ai_summary_generated', [
            'event_id' => $eventId,
            'status' => $summary['status'] ?? 'pending',
            'model' => $summary['model'] ?? null,
            'tokens_used' => $summary['tokens_used'] ?? 0,
            'processing_time_ms' => $summary['processing_time_ms'] ?? 0,
            'attempts' => $summary['attempts'] ?? 0,
        ]);
    }
}
