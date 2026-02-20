<?php
declare(strict_types=1);

namespace WeCoza\Events\Shortcodes;

use WeCoza\Agents\Services\AgentService;
use WeCoza\Clients\Services\ClientService;
use WeCoza\Events\Services\NotificationDashboardService;
use WeCoza\Learners\Services\LearnerService;

use function add_shortcode;
use function esc_html__;
use function is_user_logged_in;

if (!defined('ABSPATH')) {
    exit;
}

final class SystemPulseShortcode
{
    public static function register(): void
    {
        $instance = new self();
        add_shortcode('wecoza_system_pulse', [$instance, 'render']);
    }

    private const CACHE_KEY = 'wecoza_system_pulse_data';
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to view this content.', 'wecoza-core') . '</p>';
        }

        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            $kpis           = $cached['kpis'];
            $latestClasses  = $cached['latestClasses'];
            $attentionItems = $cached['attentionItems'];
            $cachedAt       = $cached['cached_at'] ?? time();
        } else {
            $kpis           = $this->gatherKpis();
            $latestClasses  = $this->gatherLatestClasses();
            $attentionItems = $this->gatherAttentionItems();
            $cachedAt       = time();

            set_transient(self::CACHE_KEY, [
                'kpis'           => $kpis,
                'latestClasses'  => $latestClasses,
                'attentionItems' => $attentionItems,
                'cached_at'      => $cachedAt,
            ], self::CACHE_TTL);
        }

        $cacheAge = time() - $cachedAt;

        $viewPath = WECOZA_CORE_PATH . 'views/events/system-pulse/card.php';
        if (!file_exists($viewPath)) {
            return '<p>System Pulse view not found.</p>';
        }

        ob_start();
        extract([
            'kpis'           => $kpis,
            'latestClasses'  => $latestClasses,
            'attentionItems' => $attentionItems,
            'cacheAge'       => $cacheAge,
        ], EXTR_SKIP);
        include $viewPath;
        return (string) ob_get_clean();
    }

    private function gatherKpis(): array
    {
        $kpis = [
            'learners' => 0,
            'classes' => 0,
            'agents' => 0,
            'clients' => 0,
        ];

        try {
            $kpis['learners'] = (new LearnerService())->getLearnerCount();
        } catch (\Throwable) {}

        try {
            $kpis['agents'] = (new AgentService())->countAgents([]);
        } catch (\Throwable) {}

        try {
            $kpis['clients'] = (new ClientService())->getClientCount();
        } catch (\Throwable) {}

        try {
            $pdo = wecoza_db()->getPdo();
            $stmt = $pdo->query('SELECT count(*) FROM public.classes');
            $kpis['classes'] = (int) $stmt->fetchColumn();
        } catch (\Throwable) {}

        return $kpis;
    }

    private function gatherLatestClasses(): array
    {
        try {
            $pdo = wecoza_db()->getPdo();
            $sql = "
                SELECT c.class_id, c.class_code, c.class_subject,
                       cl.client_name,
                       jsonb_array_length(COALESCE(c.learner_ids, '[]'::jsonb)) AS learner_count,
                       c.original_start_date,
                       c.created_at
                FROM public.classes c
                LEFT JOIN public.clients cl ON c.client_id = cl.client_id
                ORDER BY c.created_at DESC
                LIMIT 3
            ";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function gatherAttentionItems(): array
    {
        $items = [];

        // Urgent deliveries (due within 3 days)
        try {
            $pdo = wecoza_db()->getPdo();
            $sql = "
                SELECT COUNT(*) FROM classes c
                CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, '[]'::jsonb)) AS events(elem)
                WHERE elem->>'type' = 'Deliveries'
                  AND LOWER(elem->>'status') = 'pending'
                  AND (elem->>'date')::date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '3 days'
            ";
            $n = (int) $pdo->query($sql)->fetchColumn();
            if ($n > 0) {
                $items[] = [
                    'icon' => 'bi-box-seam',
                    'color' => 'warning',
                    'value' => $n,
                    'label' => 'Urgent ' . ($n === 1 ? 'Delivery' : 'Deliveries'),
                ];
            } else {
                $items[] = [
                    'icon' => 'bi-box-seam',
                    'color' => 'success',
                    'value' => '',
                    'label' => 'No Urgent Deliveries',
                ];
            }
        } catch (\Throwable) {}

        // Unread notifications
        try {
            $n = NotificationDashboardService::boot()->getUnreadCount();
            $items[] = [
                'icon' => 'bi-bell',
                'color' => $n > 0 ? 'danger' : 'success',
                'value' => $n,
                'label' => 'Unread ' . ($n === 1 ? 'Alert' : 'Alerts'),
            ];
        } catch (\Throwable) {}

        // Classes ending within 7 days
        try {
            $pdo = wecoza_db()->getPdo();
            $sql = "
                SELECT count(*) FROM public.classes
                WHERE original_start_date IS NOT NULL
                  AND class_duration IS NOT NULL
                  AND (original_start_date::date + (class_duration || ' days')::interval)
                      BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'
            ";
            $stmt = $pdo->query($sql);
            $n = (int) $stmt->fetchColumn();
            $items[] = [
                'icon' => 'bi-calendar-event',
                'color' => $n > 0 ? 'info' : 'secondary',
                'value' => $n,
                'label' => ($n === 1 ? 'Class' : 'Classes') . ' Ending This Week',
            ];
        } catch (\Throwable) {}

        return $items;
    }

}
