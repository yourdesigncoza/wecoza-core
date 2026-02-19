<?php
declare(strict_types=1);

namespace WeCoza\Events\Shortcodes;

use WeCoza\Events\DTOs\ClassEventDTO;
use WeCoza\Events\Repositories\ClassEventRepository;
use WeCoza\Events\Views\TemplateRenderer;

use function add_shortcode;
use function absint;
use function esc_html;
use function esc_html__;
use function get_userdata;
use function is_user_logged_in;
use function shortcode_atts;
use function sprintf;

final class LPCollisionAuditShortcode
{
    private const DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ClassEventRepository $repository,
        private readonly TemplateRenderer $renderer,
    ) {}

    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self(
            new ClassEventRepository(),
            new TemplateRenderer(),
        );

        add_shortcode('wecoza_lp_collision_audit', [$instance, 'render']);
    }

    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return $this->wrapMessage(
                esc_html__('You must be logged in to view this content.', 'wecoza-events')
            );
        }

        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
        ], $atts, 'wecoza_lp_collision_audit');

        $limit = absint($atts['limit']);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        try {
            $events = $this->repository->findBy(
                ['event_type' => 'LP_COLLISION', 'deleted_at' => null],
                $limit,
                0,
                'created_at',
                'DESC'
            );
        } catch (\Throwable $e) {
            wecoza_log('LP Collision Audit error: ' . $e->getMessage(), 'error');
            return $this->wrapMessage(
                esc_html__('Unable to load LP collision audit data.', 'wecoza-events')
            );
        }

        $records = array_map(
            fn(array $row) => $this->presentEvent(ClassEventDTO::fromRow($row)),
            $events
        );

        return $this->renderer->render('lp-collision-audit/main', [
            'records' => $records,
        ]);
    }

    /**
     * Transform a ClassEventDTO into a presentable array for the view.
     */
    private function presentEvent(ClassEventDTO $dto): array
    {
        $data = $dto->eventData;

        // Resolve WP user display name
        $userName = 'Unknown';
        $acknowledgedBy = $data['acknowledged_by'] ?? $dto->userId;
        if ($acknowledgedBy) {
            $user = get_userdata((int) $acknowledgedBy);
            if ($user) {
                $userName = $user->display_name;
            }
        }

        // Format affected learners
        $learners = [];
        foreach ($data['affected_learners'] ?? [] as $learner) {
            $lp = $learner['active_lp'] ?? null;
            $learners[] = [
                'learner_id'   => $learner['learner_id'] ?? 0,
                'name'         => esc_html($learner['name'] ?? 'Unknown'),
                'subject_name' => $lp ? esc_html($lp['subject_name'] ?? '') : '',
                'subject_code' => $lp ? esc_html($lp['subject_code'] ?? '') : '',
            ];
        }

        // Format date
        $createdAt = $dto->createdAt;
        $formattedDate = '';
        if ($createdAt) {
            $ts = strtotime($createdAt);
            $formattedDate = $ts ? wp_date('j M Y, H:i', $ts) : $createdAt;
        }

        return [
            'event_id'        => $dto->eventId,
            'date'            => $formattedDate,
            'acknowledged_by' => esc_html($userName),
            'class_code'      => esc_html($data['class_code'] ?? '—'),
            'class_type'      => esc_html($data['class_type'] ?? ''),
            'learners'        => $learners,
            'learner_count'   => count($learners),
            'search_index'    => $this->buildSearchIndex($formattedDate, $userName, $data, $learners),
        ];
    }

    /**
     * Build a lowercase search string for client-side filtering.
     */
    private function buildSearchIndex(string $date, string $user, array $data, array $learners): string
    {
        $parts = [
            $date,
            $user,
            $data['class_code'] ?? '',
            $data['class_type'] ?? '',
        ];

        foreach ($learners as $l) {
            $parts[] = $l['name'];
            $parts[] = $l['subject_name'];
            $parts[] = $l['subject_code'];
        }

        return esc_attr(mb_strtolower(implode(' ', $parts)));
    }

    private function wrapMessage(string $message): string
    {
        return sprintf(
            '<div class="alert alert-info">%s</div>',
            $message
        );
    }
}
