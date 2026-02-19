<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Shortcodes;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Feedback\Repositories\FeedbackRepository;

final class FeedbackDashboardShortcode
{
    private const ADMIN_EMAIL = 'laudes.michael@gmail.com';
    private const NONCE_ACTION = 'wecoza_feedback_dashboard';

    public static function register(): void
    {
        add_shortcode('wecoza_feedback_dashboard', [new self(), 'render']);
        add_action('wp_ajax_wecoza_feedback_resolve', [new self(), 'handleResolve']);
    }

    public function render(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view feedback.</p>';
        }

        $repository = new FeedbackRepository();
        $filter = sanitize_text_field($_GET['feedback_filter'] ?? 'open');
        $items = $repository->findAllForDashboard($filter);

        $user = wp_get_current_user();
        $isAdmin = ($user->user_email === self::ADMIN_EMAIL);

        wp_enqueue_script(
            'wecoza-feedback-dashboard',
            wecoza_js_url('feedback/feedback-dashboard.js'),
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-feedback-dashboard', 'wecozaFeedbackDashboard', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'isAdmin'  => $isAdmin,
        ]);

        return wecoza_view('feedback/dashboard', [
            'items'   => $items,
            'filter'  => $filter,
            'isAdmin' => $isAdmin,
        ], true);
    }

    public function handleResolve(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $user = wp_get_current_user();
        if ($user->user_email !== self::ADMIN_EMAIL) {
            wp_send_json_error(['message' => 'Only the project admin can resolve feedback'], 403);
            return;
        }

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        if ($feedbackId <= 0) {
            wp_send_json_error(['message' => 'Invalid feedback ID'], 400);
            return;
        }

        $repository = new FeedbackRepository();
        $success = $repository->toggleResolved($feedbackId, $user->user_email);

        if ($success) {
            $record = $repository->findById($feedbackId);
            wp_send_json_success([
                'is_resolved' => (bool) ($record['is_resolved'] ?? false),
                'resolved_at' => $record['resolved_at'] ?? null,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to update'], 500);
        }
    }
}
