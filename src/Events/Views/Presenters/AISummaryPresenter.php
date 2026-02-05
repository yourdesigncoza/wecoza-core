<?php
declare(strict_types=1);

namespace WeCoza\Events\Views\Presenters;

use function esc_attr;
use function esc_html;
use function strtolower;
use function strtoupper;
use function wp_date;

/**
 * Presenter for notification dashboard data
 *
 * Transforms notification data from NotificationDashboardService into
 * display-ready format for templates. Handles:
 * - Event type labeling and badge styling
 * - AI summary formatting
 * - Read/acknowledged state indicators
 * - Search index generation
 * - Acknowledge button rendering
 *
 * @since 1.2.0
 */
final class AISummaryPresenter
{
    /**
     * Event type to display configuration mapping
     */
    private const EVENT_TYPE_CONFIG = [
        'CLASS_INSERT' => [
            'label' => 'New Class',
            'badge_class' => 'badge-phoenix badge-phoenix-success',
        ],
        'CLASS_UPDATE' => [
            'label' => 'Class Updated',
            'badge_class' => 'badge-phoenix badge-phoenix-warning',
        ],
        'CLASS_DELETE' => [
            'label' => 'Class Deleted',
            'badge_class' => 'badge-phoenix badge-phoenix-danger',
        ],
        'LEARNER_ADD' => [
            'label' => 'Learner Added',
            'badge_class' => 'badge-phoenix badge-phoenix-info',
        ],
        'LEARNER_REMOVE' => [
            'label' => 'Learner Removed',
            'badge_class' => 'badge-phoenix badge-phoenix-danger',
        ],
        'LEARNER_UPDATE' => [
            'label' => 'Learner Updated',
            'badge_class' => 'badge-phoenix badge-phoenix-warning',
        ],
        'STATUS_CHANGE' => [
            'label' => 'Status Changed',
            'badge_class' => 'badge-phoenix badge-phoenix-primary',
        ],
    ];

    /**
     * Present multiple records for display
     *
     * @param array<int, array<string, mixed>> $records Transformed event data from service
     * @return array<int, array<string, mixed>>
     */
    public function present(array $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $result[] = $this->presentSingle($record);
        }

        return $result;
    }

    /**
     * Present a single record for display
     *
     * @param array<string, mixed> $record Transformed event data from NotificationDashboardService
     * @return array<string, mixed>
     */
    private function presentSingle(array $record): array
    {
        $eventType = $record['event_type'] ?? '';
        $eventTypeConfig = $this->getEventTypeConfig($eventType);
        $isRead = (bool) ($record['is_read'] ?? false);
        $isAcknowledged = (bool) ($record['is_acknowledged'] ?? false);
        $eventId = $record['event_id'] ?? null;

        $classCode = esc_html($record['class_code'] ?? '');
        $classSubject = esc_html($record['class_subject'] ?? '');
        $aiSummaryText = $record['ai_summary_text'] ?? '';

        return [
            'event_id' => $eventId,
            'entity_type' => $record['entity_type'] ?? 'class',
            'entity_id' => $record['entity_id'] ?? null,
            'class_code' => $classCode,
            'class_subject' => $classSubject,
            'class_name' => esc_html($record['class_name'] ?? ''),

            'event_type' => $eventType,
            'event_type_label' => $eventTypeConfig['label'],
            'event_type_badge_class' => $eventTypeConfig['badge_class'],

            'operation' => $this->mapEventTypeToOperation($eventType),
            'operation_label' => $eventTypeConfig['label'],
            'operation_badge_class' => $eventTypeConfig['badge_class'],

            'priority' => $record['priority'] ?? 2,
            'notification_status' => $record['notification_status'] ?? 'pending',

            'created_at' => $record['created_at'] ?? '',
            'created_at_formatted' => $record['created_at_formatted'] ?? '',
            'sent_at' => $record['sent_at'] ?? '',
            'sent_at_formatted' => $record['sent_at_formatted'] ?? '',
            'viewed_at' => $record['viewed_at'] ?? '',
            'viewed_at_formatted' => $record['viewed_at_formatted'] ?? '',
            'acknowledged_at' => $record['acknowledged_at'] ?? '',
            'acknowledged_at_formatted' => $record['acknowledged_at_formatted'] ?? '',

            'is_read' => $isRead,
            'is_acknowledged' => $isAcknowledged,
            'is_sent' => (bool) ($record['is_sent'] ?? false),
            'is_enriched' => (bool) ($record['is_enriched'] ?? false),

            'unread_badge' => $this->formatUnreadBadge($isRead),
            'read_state_class' => $isRead ? 'notification-read' : 'notification-unread',

            'has_summary' => (bool) ($record['has_ai_summary'] ?? false),
            'summary_text' => $aiSummaryText,
            'summary_html' => $this->formatSummaryAsHtml($aiSummaryText),
            'summary_status' => $record['ai_summary_status'] ?? 'unknown',
            'summary_model' => $record['ai_summary_model'] ?? '',
            'tokens_used' => $record['ai_tokens_used'] ?? null,
            'summary_status_badge_class' => $this->getStatusBadgeClass($record['ai_summary_status'] ?? ''),

            'acknowledge_button' => $this->formatAcknowledgeButton($eventId, $isAcknowledged),

            'search_index' => $this->buildSearchIndex(
                $eventTypeConfig['label'],
                $classCode,
                $classSubject,
                $aiSummaryText
            ),

            'data_attributes' => $this->buildDataAttributes($eventId, $eventType, $isRead),
        ];
    }

    /**
     * Get event type display configuration
     *
     * @param string $eventType Event type value
     * @return array{label: string, badge_class: string}
     */
    private function getEventTypeConfig(string $eventType): array
    {
        $type = strtoupper($eventType);

        if (isset(self::EVENT_TYPE_CONFIG[$type])) {
            return self::EVENT_TYPE_CONFIG[$type];
        }

        return [
            'label' => ucwords(str_replace('_', ' ', strtolower($type))),
            'badge_class' => 'badge-phoenix badge-phoenix-secondary',
        ];
    }

    /**
     * Map new event type to legacy operation for backward compatibility
     *
     * @param string $eventType Event type value
     * @return string Legacy operation string
     */
    private function mapEventTypeToOperation(string $eventType): string
    {
        return match (strtoupper($eventType)) {
            'CLASS_INSERT', 'LEARNER_ADD' => 'INSERT',
            'CLASS_UPDATE', 'LEARNER_UPDATE', 'STATUS_CHANGE' => 'UPDATE',
            'CLASS_DELETE', 'LEARNER_REMOVE' => 'DELETE',
            default => 'UNKNOWN',
        };
    }

    /**
     * Format unread indicator badge
     *
     * @param bool $isRead Whether notification has been viewed
     * @return string Badge HTML or empty string
     */
    private function formatUnreadBadge(bool $isRead): string
    {
        if ($isRead) {
            return '';
        }

        return '<span class="badge badge-phoenix badge-phoenix-primary fs-10 ms-2">New</span>';
    }

    /**
     * Format acknowledge button HTML
     *
     * @param int|null $eventId Event ID
     * @param bool $isAcknowledged Whether already acknowledged
     * @return string Button HTML
     */
    private function formatAcknowledgeButton(?int $eventId, bool $isAcknowledged): string
    {
        if ($eventId === null) {
            return '';
        }

        if ($isAcknowledged) {
            return '<button type="button" class="btn btn-sm btn-outline-secondary" disabled>Acknowledged</button>';
        }

        return sprintf(
            '<button type="button" class="btn btn-sm btn-outline-success" data-role="acknowledge-btn" data-event-id="%d">Acknowledge</button>',
            $eventId
        );
    }

    /**
     * Build search index string for client-side filtering
     *
     * @param string $eventTypeLabel Event type label
     * @param string $classCode Class code
     * @param string $classSubject Class subject
     * @param string $aiSummary AI summary text
     * @return string Lowercase search index
     */
    private function buildSearchIndex(
        string $eventTypeLabel,
        string $classCode,
        string $classSubject,
        string $aiSummary
    ): string {
        $parts = array_filter([
            $eventTypeLabel,
            $classCode,
            $classSubject,
            $aiSummary,
        ]);

        return strtolower(implode(' ', $parts));
    }

    /**
     * Build data attributes string for template rendering
     *
     * @param int|null $eventId Event ID
     * @param string $eventType Event type
     * @param bool $isRead Read state
     * @return string Data attributes for HTML element
     */
    private function buildDataAttributes(?int $eventId, string $eventType, bool $isRead): string
    {
        $attrs = [];

        if ($eventId !== null) {
            $attrs[] = sprintf('data-event-id="%d"', $eventId);
        }

        $attrs[] = sprintf('data-operation="%s"', esc_attr($this->mapEventTypeToOperation($eventType)));
        $attrs[] = sprintf('data-is-read="%s"', $isRead ? '1' : '0');

        return implode(' ', $attrs);
    }

    /**
     * Format AI summary text as HTML list
     *
     * @param string $summary Summary text with newline-separated items
     * @return string HTML list or empty string
     */
    private function formatSummaryAsHtml(string $summary): string
    {
        if ($summary === '') {
            return '';
        }

        $lines = explode("\n", $summary);
        $html = '<ul class="list-unstyled mb-0">';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, '- ') === 0) {
                $line = substr($line, 2);
            }

            $html .= '<li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>' . esc_html($line) . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Get badge class for AI summary status
     *
     * @param string|null $status Status value
     * @return string Badge class string
     */
    private function getStatusBadgeClass(?string $status): string
    {
        $status = strtolower($status ?? '');

        return match ($status) {
            'success' => 'badge-phoenix badge-phoenix-success',
            'error' => 'badge-phoenix badge-phoenix-danger',
            'pending' => 'badge-phoenix badge-phoenix-warning',
            default => 'badge-phoenix badge-phoenix-secondary',
        };
    }
}
