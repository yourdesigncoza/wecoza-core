<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\Enums\EventType;
use WeCoza\Events\Repositories\ClassEventRepository;
use WeCoza\Events\Support\OpenAIConfig;

use function as_enqueue_async_action;
use function get_transient;
use function microtime;
use function set_transient;
use function delete_transient;
use function sprintf;
use function gc_collect_cycles;
use function wecoza_log;

/**
 * Batch processor for pending notification events
 *
 * Reads pending events from class_events table, determines whether
 * AI enrichment is needed, and schedules appropriate Action Scheduler jobs.
 *
 * @since 1.2.0
 */
final class NotificationProcessor
{
    private const LOCK_KEY = 'wecoza_ai_summary_lock';
    private const LOCK_TTL = 120;  // 2 minutes for 50+ item batches
    private const MAX_RUNTIME_SECONDS = 90;  // Room for 50 items
    private const MIN_REMAINING_SECONDS = 5;
    private const BATCH_LIMIT = 50;
    private const MEMORY_CLEANUP_INTERVAL = 50;  // Every 50 records

    private ClassEventRepository $eventRepository;

    public function __construct(
        private readonly NotificationSettings $settings,
        private readonly OpenAIConfig $openAIConfig
    ) {
        $this->eventRepository = new ClassEventRepository();
    }

    public static function boot(): self
    {
        $openAIConfig = new OpenAIConfig();
        return new self(new NotificationSettings(), $openAIConfig);
    }

    public function process(): void
    {
        if (!$this->acquireLock()) {
            return;
        }

        $start = microtime(true);

        try {
            $events = $this->eventRepository->findPendingForProcessing(self::BATCH_LIMIT);
            $iteration = 0;

            foreach ($events as $event) {
                $iteration++;
                if ($this->shouldStop($start)) {
                    break;
                }

                $eventId = $event->eventId;
                if ($eventId === null) {
                    continue;
                }

                // Get recipients for this event type (supports multiple recipients)
                $recipients = $this->settings->getRecipientsForEventType($event->eventType->value);
                if (empty($recipients)) {
                    continue;
                }

                // Skip AI for event types that don't benefit from enrichment
                $skipAI = $event->eventType === EventType::CLASS_INSERT
                       || $event->eventType === EventType::LEARNER_ADD
                       || $event->eventType === EventType::LEARNER_REMOVE
                       || $event->eventType === EventType::CLASS_DELETE;

                // Check AI eligibility for remaining event types
                $needsAI = false;
                if (!$skipAI) {
                    $eligibility = $this->openAIConfig->assessEligibility($eventId);
                    $needsAI = $eligibility['eligible'] !== false;
                }

                if ($needsAI) {
                    // Update status to 'enriching' before scheduling
                    $this->eventRepository->updateStatus($eventId, 'enriching');

                    // Schedule AI enrichment first (enricher will chain to emails for each recipient)
                    as_enqueue_async_action(
                        'wecoza_process_event',
                        ['event_id' => $eventId],
                        'wecoza-notifications'
                    );
                } else {
                    // Update status to 'sending' before scheduling
                    $this->eventRepository->updateStatus($eventId, 'sending');

                    // Skip AI, schedule email directly for each recipient
                    foreach ($recipients as $recipient) {
                        as_enqueue_async_action(
                            'wecoza_send_notification_email',
                            ['event_id' => $eventId, 'recipient' => $recipient, 'email_context' => []],
                            'wecoza-notifications'
                        );
                    }
                }

                // Periodic memory cleanup
                if ($this->shouldCleanupMemory($iteration)) {
                    gc_collect_cycles();

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        wecoza_log(sprintf(
                            'NotificationProcessor: Memory cleanup at iteration %d, usage: %s MB',
                            $iteration,
                            round(memory_get_usage(true) / 1048576, 2)
                        ), 'debug');
                    }
                }
            }

            // Final memory cleanup after batch
            gc_collect_cycles();
        } finally {
            $this->releaseLock();
        }
    }

    private function shouldStop(float $start): bool
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed >= self::MAX_RUNTIME_SECONDS) {
            return true;
        }

        return (self::MAX_RUNTIME_SECONDS - $elapsed) < self::MIN_REMAINING_SECONDS;
    }

    private function acquireLock(): bool
    {
        $existing = get_transient(self::LOCK_KEY);
        if ($existing !== false) {
            return false;
        }

        return set_transient(self::LOCK_KEY, '1', self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Check if memory cleanup should run based on iteration count.
     */
    private function shouldCleanupMemory(int $iteration): bool
    {
        return $iteration > 0 && ($iteration % self::MEMORY_CLEANUP_INTERVAL === 0);
    }
}
