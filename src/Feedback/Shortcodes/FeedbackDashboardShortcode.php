<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Shortcodes;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Feedback\Repositories\FeedbackRepository;
use WeCoza\Feedback\Repositories\FeedbackCommentRepository;
use WeCoza\Feedback\Services\TrelloService;

final class FeedbackDashboardShortcode
{
    private const ADMIN_EMAIL = 'laudes.michael@gmail.com';
    private const NONCE_ACTION = 'wecoza_feedback_dashboard';

    public static function register(): void
    {
        add_shortcode('wecoza_feedback_dashboard', [new self(), 'render']);
        add_action('wp_ajax_wecoza_feedback_resolve', [new self(), 'handleResolve']);
        add_action('wp_ajax_wecoza_feedback_comment', [new self(), 'handleComment']);
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

        // Batch-load comments for all feedback items (avoids N+1)
        $commentsByFeedback = [];
        if (!empty($items)) {
            $feedbackIds = array_column($items, 'id');
            $commentRepo = new FeedbackCommentRepository();
            $commentsByFeedback = $commentRepo->findByFeedbackIds(array_map('intval', $feedbackIds));
        }

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
            'items'              => $items,
            'filter'             => $filter,
            'isAdmin'            => $isAdmin,
            'commentsByFeedback' => $commentsByFeedback,
        ], true);
    }

    public function handleComment(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $user = wp_get_current_user();
        if ($user->user_email !== self::ADMIN_EMAIL) {
            wp_send_json_error(['message' => 'Only the project admin can add comments'], 403);
            return;
        }

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $commentText = sanitize_textarea_field($_POST['comment_text'] ?? '');

        if ($feedbackId <= 0 || $commentText === '') {
            wp_send_json_error(['message' => 'Feedback ID and comment text are required'], 400);
            return;
        }

        $repo = new FeedbackCommentRepository();
        $commentId = $repo->insert([
            'feedback_id'  => $feedbackId,
            'author_email' => $user->user_email,
            'comment_text' => $commentText,
        ]);

        if ($commentId) {
            // Sync comment to Trello card (best-effort)
            $feedbackRepo = new FeedbackRepository();
            $feedback = $feedbackRepo->findById($feedbackId);
            if (!empty($feedback['trello_card_id'])) {
                $trello = new TrelloService();
                if ($trello->isConfigured()) {
                    $trello->addComment(
                        $feedback['trello_card_id'],
                        "**Dev comment by {$user->user_email}:**\n\n{$commentText}"
                    );
                }
            }

            wp_send_json_success([
                'id'           => $commentId,
                'author_email' => $user->user_email,
                'comment_text' => $commentText,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save comment'], 500);
        }
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
            $isResolved = (bool) ($record['is_resolved'] ?? false);

            // Sync status to Trello card (best-effort)
            if (!empty($record['trello_card_id'])) {
                $trello = new TrelloService();
                if ($trello->isConfigured()) {
                    $trello->moveCardToList(
                        $record['trello_card_id'],
                        $isResolved ? 'Resolved' : 'Open'
                    );
                }
            }

            wp_send_json_success([
                'is_resolved' => $isResolved,
                'resolved_at' => $record['resolved_at'] ?? null,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to update'], 500);
        }
    }
}
