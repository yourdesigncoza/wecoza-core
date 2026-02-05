<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Repositories\ClassEventRepository;
use WeCoza\Events\Views\Presenters\NotificationEmailPresenter;

use function add_action;
use function error_log;
use function remove_action;
use function sprintf;
use function wp_mail;
use function wecoza_log;

/**
 * Handles email sending for individual notification events
 *
 * Designed to run as an Action Scheduler job. Fetches event data,
 * formats email via presenter, and sends via wp_mail.
 *
 * @since 1.2.0
 */
final class NotificationEmailer
{
    private ClassEventRepository $eventRepository;

    public function __construct(
        private readonly NotificationEmailPresenter $presenter
    ) {
        $this->eventRepository = new ClassEventRepository();
    }

    public static function boot(): self
    {
        $presenter = new NotificationEmailPresenter();
        return new self($presenter);
    }

    /**
     * Send email for a single notification event
     *
     * @param int $eventId The event_id to send email for
     * @param string $recipient Email recipient
     * @param array $emailContext Context from AI enrichment (alias_map, obfuscated)
     * @return bool True if email sent successfully
     */
    public function send(int $eventId, string $recipient, array $emailContext = []): bool
    {
        $event = $this->eventRepository->findByEventId($eventId);
        if ($event === null) {
            wecoza_log("NotificationEmailer: Event not found for event_id {$eventId}", 'warning');
            return false;
        }

        // Map EventType to operation for presenter
        $operation = $this->mapEventTypeToOperation($event);

        // Extract data from eventData JSONB
        $newRow = $event->eventData['new_row'] ?? [];
        $oldRow = $event->eventData['old_row'] ?? [];
        $diff = $event->eventData['diff'] ?? [];
        $summaryRecord = $event->aiSummary ?? [];

        // Build row context for presenter (matches legacy format)
        $rowContext = [
            'event_id' => $event->eventId,
            'class_id' => $event->entityId,
            'changed_at' => $event->createdAt,
            'event_type' => $event->eventType->value,
        ];

        $mailData = $this->presenter->present([
            'operation' => $operation,
            'row' => $rowContext,
            'recipient' => $recipient,
            'new_row' => $newRow,
            'old_row' => $oldRow,
            'diff' => $diff,
            'summary' => $summaryRecord,
            'email_context' => $emailContext,
        ]);

        $subject = $mailData['subject'];
        $body = $mailData['body'];
        $headers = $mailData['headers'];

        // Capture wp_mail_failed errors for debugging
        $lastMailError = null;
        $errorHandler = function (\WP_Error $error) use (&$lastMailError) {
            $lastMailError = $error->get_error_message();
            $errorData = $error->get_error_data();
            if (!empty($errorData['phpmailer_exception_code'])) {
                $lastMailError .= ' (code: ' . $errorData['phpmailer_exception_code'] . ')';
            }
        };
        add_action('wp_mail_failed', $errorHandler);

        $sent = wp_mail($recipient, $subject, $body, $headers);

        remove_action('wp_mail_failed', $errorHandler);

        if (!$sent) {
            $errorDetail = $lastMailError ? " - Error: {$lastMailError}" : '';
            error_log(sprintf('WeCoza notification failed for event %d to %s%s', $eventId, $recipient, $errorDetail));
            wecoza_log(sprintf('NotificationEmailer: Email failed for event %d to %s%s', $eventId, $recipient, $errorDetail), 'error');
            // Update status to 'failed' on email failure
            $this->eventRepository->updateStatus($eventId, 'failed');
        } else {
            // Mark event as sent with timestamp
            $this->eventRepository->markSent($eventId);
        }

        return $sent;
    }

    /**
     * Map EventType enum to operation string for presenter.
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
}
