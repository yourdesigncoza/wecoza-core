<?php
declare(strict_types=1);

namespace WeCoza\Events\Views\Presenters;

use function esc_html;
use function gmdate;
use function wp_date;

/**
 * Presenter for material tracking dashboard data
 */
final class MaterialTrackingPresenter
{
    /**
     * Format tracking records for display
     *
     * @param array<int, array<string, mixed>> $records Raw tracking records
     * @return array<int, array<string, mixed>> Formatted records
     */
    public function presentRecords(array $records): array
    {
        $presented = [];

        foreach ($records as $record) {
            $eventStatus = strtolower((string) ($record['event_status'] ?? 'pending'));

            $presented[] = [
                'class_id' => (int) $record['class_id'],
                'class_code' => esc_html((string) ($record['class_code'] ?? 'N/A')),
                'class_subject' => esc_html((string) ($record['class_subject'] ?? 'N/A')),
                'client_name' => esc_html((string) ($record['client_name'] ?? 'N/A')),
                'site_name' => esc_html((string) ($record['site_name'] ?? 'N/A')),
                'original_start_date' => $this->formatDate($record['original_start_date'] ?? null),
                'event_date' => $this->formatDate($record['event_date'] ?? null),
                'event_description' => esc_html((string) ($record['event_description'] ?? '')),
                'event_index' => (int) ($record['event_index'] ?? 0),
                'event_status' => $eventStatus,
                'notification_type' => (string) ($record['notification_type'] ?? ''),
                'notification_sent_at' => $this->formatDateTime($record['notification_sent_at'] ?? null),
                'notification_badge_html' => $this->getNotificationBadge($record['notification_type'] ?? null),
                'status_badge_html' => $this->getEventStatusBadge($eventStatus),
                'delivery_status' => $this->mapEventStatus($eventStatus),
                'urgency_class' => $this->calculateUrgency((string) ($record['event_date'] ?? ''), $eventStatus),
            ];
        }

        return $presented;
    }

    /**
     * Format statistics for display
     *
     * @param array<string, int> $stats Raw statistics
     * @return array<string, mixed> Formatted statistics
     */
    public function presentStatistics(array $stats): array
    {
        return [
            'total' => [
                'count' => $stats['total'],
                'label' => 'Total Deliveries',
                'sublabel' => 'events',
                'icon' => '',
                'color' => 'secondary',
            ],
            'pending' => [
                'count' => $stats['pending'],
                'label' => 'Pending',
                'sublabel' => 'awaiting delivery',
                'icon' => '',
                'color' => 'warning',
            ],
            'completed' => [
                'count' => $stats['completed'],
                'label' => 'Completed',
                'sublabel' => 'delivered',
                'icon' => '',
                'color' => 'success',
            ],
        ];
    }

    /**
     * Map event status to delivery status
     *
     * @param string $eventStatus Event status from event_dates
     * @return string Delivery status (pending or delivered)
     */
    private function mapEventStatus(string $eventStatus): string
    {
        return match ($eventStatus) {
            'completed' => 'delivered',
            'pending' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Calculate urgency class based on delivery date proximity
     *
     * @param string $eventDate Raw date string from event_dates
     * @param string $eventStatus Lowercased event status
     * @return string CSS class name or empty string
     */
    private function calculateUrgency(string $eventDate, string $eventStatus): string
    {
        // Only apply urgency to pending rows
        if ($eventStatus !== 'pending') {
            return '';
        }

        // Validate date
        if ($eventDate === '' || strtotime($eventDate) === false) {
            return '';
        }

        // Calculate days until delivery
        $daysUntil = (int) ((strtotime($eventDate) - strtotime(gmdate('Y-m-d'))) / 86400);

        // Two-tier urgency system
        return match (true) {
            $daysUntil <= 0 => 'urgency-overdue',      // Today or past = red
            $daysUntil <= 3 => 'urgency-approaching',  // 1-3 days = orange
            default => '',                             // 4+ days = no border
        };
    }

    /**
     * Get event status badge HTML
     *
     * @param string $status Event status
     * @return string Badge HTML
     */
    private function getEventStatusBadge(string $status): string
    {
        return match ($status) {
            'pending' => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">Pending</span>',
            'completed' => '<span class="badge badge-phoenix badge-phoenix-success fs-10">Completed</span>',
            default => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">' . esc_html($status) . '</span>',
        };
    }

    /**
     * Get notification type badge HTML
     *
     * @param string|null $type Notification type (orange or red)
     * @return string Badge HTML
     */
    private function getNotificationBadge(?string $type): string
    {
        if ($type === 'orange') {
            return '<span class="badge badge-phoenix badge-phoenix-warning fs-10">7d</span>';
        }

        if ($type === 'red') {
            return '<span class="badge badge-phoenix badge-phoenix-danger fs-10">5d</span>';
        }

        return '';
    }

    /**
     * Format date for display
     *
     * @param mixed $date Date value
     * @return string Formatted date or empty string
     */
    private function formatDate($date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return wp_date('M j, Y', strtotime((string) $date));
    }

    /**
     * Format datetime for display
     *
     * @param mixed $datetime Datetime value
     * @return string Formatted datetime or empty string
     */
    private function formatDateTime($datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        return wp_date('M j, Y g:i A', strtotime((string) $datetime));
    }
}
