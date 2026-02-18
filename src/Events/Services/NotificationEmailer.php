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
use function wecoza_db;
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
     * @return array{success: bool, error_code?: string, error_message?: string}
     */
    public function send(int $eventId, string $recipient, array $emailContext = []): array
    {
        $event = $this->eventRepository->findByEventId($eventId);
        if ($event === null) {
            wecoza_log("NotificationEmailer: Event not found for event_id {$eventId}", 'warning');
            return [
                'success' => false,
                'error_code' => 'event_not_found',
                'error_message' => "Event not found for event_id {$eventId}",
            ];
        }

        // Validate recipient email before building the email
        if (!is_email($recipient)) {
            wecoza_log("NotificationEmailer: Invalid recipient email for event {$eventId}", 'error');
            return [
                'success' => false,
                'error_code' => 'invalid_recipient',
                'error_message' => "Invalid recipient email for event {$eventId}",
            ];
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

        // Resolve IDs to display names for email templates
        $resolvedNames = $this->resolveDisplayNames($newRow, $event->eventData);

        $mailData = $this->presenter->present([
            'event_type' => $event->eventType->value,
            'operation' => $operation,
            'row' => $rowContext,
            'recipient' => $recipient,
            'new_row' => $newRow,
            'old_row' => $oldRow,
            'diff' => $diff,
            'summary' => $summaryRecord,
            'email_context' => $emailContext,
            'event_data' => $event->eventData,
            'resolved_names' => $resolvedNames,
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
            $this->eventRepository->updateStatus($eventId, 'failed');
            return [
                'success' => false,
                'error_code' => 'send_failed',
                'error_message' => sprintf('Email failed for event %d to %s%s', $eventId, $recipient, $errorDetail),
            ];
        }

        // Mark event as sent with timestamp
        $this->eventRepository->markSent($eventId);
        return ['success' => true];
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

    /**
     * Resolve IDs in class data to human-readable names.
     *
     * @param array $newRow Class data (may contain client_id, class_agent, site_id)
     * @param array $eventData Raw event data (for learner events with class_id)
     * @return array{client_name:string,agent_name:string,site_name:string,site_address:string}
     */
    private function resolveDisplayNames(array $newRow, array $eventData): array
    {
        $resolved = [
            'client_name'  => '',
            'agent_name'   => '',
            'site_name'    => '',
            'site_address' => '',
        ];

        try {
            $db = wecoza_db();
            if ($db === null) {
                return $resolved;
            }

            // Resolve client name
            $clientId = (int) ($newRow['client_id'] ?? 0);
            if ($clientId > 0) {
                $stmt = $db->prepare('SELECT client_name FROM public.clients WHERE client_id = :id LIMIT 1');
                $stmt->execute([':id' => $clientId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $resolved['client_name'] = (string) $row['client_name'];
                }
            }

            // Resolve agent name
            $agentId = (int) ($newRow['class_agent'] ?? 0);
            if ($agentId > 0) {
                $stmt = $db->prepare('SELECT first_name, surname FROM public.agents WHERE agent_id = :id LIMIT 1');
                $stmt->execute([':id' => $agentId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $resolved['agent_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                }
            }

            // Resolve site name and address
            $siteId = (int) ($newRow['site_id'] ?? 0);
            if ($siteId > 0) {
                $stmt = $db->prepare(
                    'SELECT s.site_name, l.street_address
                     FROM public.sites s
                     LEFT JOIN public.locations l ON s.place_id = l.location_id
                     WHERE s.site_id = :id
                     LIMIT 1'
                );
                $stmt->execute([':id' => $siteId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $resolved['site_name']    = (string) ($row['site_name'] ?? '');
                    $resolved['site_address'] = (string) ($row['street_address'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            wecoza_log('NotificationEmailer: Failed to resolve display names - ' . $e->getMessage(), 'warning');
        }

        return $resolved;
    }
}
