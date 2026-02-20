<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Controllers;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Feedback\Repositories\FeedbackRepository;
use WeCoza\Feedback\Services\AIFeedbackService;

final class FeedbackController
{
    private const NONCE_ACTION = 'wecoza_feedback';
    private const MAX_FOLLOWUP_ROUNDS = 3;
    private const MAX_SCREENSHOT_BYTES = 2 * 1024 * 1024; // 2MB

    private FeedbackRepository $repository;
    private AIFeedbackService $aiService;

    public function __construct(
        ?FeedbackRepository $repository = null,
        ?AIFeedbackService $aiService = null
    ) {
        $this->repository = $repository ?? new FeedbackRepository();
        $this->aiService  = $aiService ?? new AIFeedbackService();
    }

    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self();
        add_action('wp_ajax_wecoza_feedback_submit', [$instance, 'handleSubmit']);
        add_action('wp_ajax_wecoza_feedback_followup', [$instance, 'handleFollowup']);
    }

    public function handleSubmit(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $user = wp_get_current_user();
        if (!$user->exists()) {
            wp_send_json_error(['message' => 'Authentication required'], 401);
        }

        // Sanitize inputs
        $category     = wecoza_sanitize_value($_POST['category'] ?? '', 'string');
        $feedbackText = wecoza_sanitize_value($_POST['feedback_text'] ?? '', 'string');
        $pageUrl      = wecoza_sanitize_value($_POST['page_url'] ?? '', 'string');
        $pageTitle    = wecoza_sanitize_value($_POST['page_title'] ?? '', 'string');
        $shortcode    = wecoza_sanitize_value($_POST['shortcode'] ?? '', 'string');
        $browserInfo  = wecoza_sanitize_value($_POST['browser_info'] ?? '', 'string');
        $viewport     = wecoza_sanitize_value($_POST['viewport'] ?? '', 'string');

        // Validate required fields
        if (!in_array($category, ['bug_report', 'feature_request', 'comment'], true)) {
            wp_send_json_error(['message' => 'Invalid category'], 400);
        }
        if (empty(trim($feedbackText))) {
            wp_send_json_error(['message' => 'Feedback text is required'], 400);
        }

        // Handle URL params
        $urlParams = '{}';
        if (!empty($_POST['url_params'])) {
            $decoded = json_decode(stripslashes($_POST['url_params']), true);
            if (is_array($decoded)) {
                $urlParams = wp_json_encode($decoded);
            }
        }

        // Save screenshot
        $screenshotPath = null;
        if (!empty($_POST['screenshot'])) {
            $screenshotPath = $this->saveScreenshot($_POST['screenshot']);
        }

        // Save to database
        $feedbackId = $this->repository->insert([
            'user_id'         => $user->ID,
            'user_email'      => $user->user_email,
            'category'        => $category,
            'feedback_text'   => $feedbackText,
            'page_url'        => $pageUrl,
            'page_title'      => $pageTitle,
            'shortcode'       => $shortcode,
            'url_params'      => $urlParams,
            'browser_info'    => $browserInfo,
            'viewport'        => $viewport,
            'screenshot_path' => $screenshotPath,
        ]);

        if ($feedbackId === null) {
            wp_send_json_error(['message' => 'Failed to save feedback'], 500);
        }

        // AI vagueness check
        $vaguenessResult = $this->aiService->checkVagueness(
            $feedbackText,
            $category,
            $shortcode ?: null,
            $pageUrl ?: null
        );

        if (!$vaguenessResult['is_clear'] && $vaguenessResult['follow_up']) {
            $conversation = [['question' => $vaguenessResult['follow_up']]];
            $this->repository->update($feedbackId, [
                'ai_conversation' => wp_json_encode($conversation),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            wp_send_json_success([
                'status'      => 'follow_up',
                'feedback_id' => $feedbackId,
                'follow_up'   => $vaguenessResult['follow_up'],
                'round'       => 1,
            ]);
            return;
        }

        // Feedback is clear - enrich and save
        $this->enrichAndSave($feedbackId, $feedbackText, $category, $shortcode, $pageUrl);
    }

    public function handleFollowup(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $user = wp_get_current_user();
        if (!$user->exists()) {
            wp_send_json_error(['message' => 'Authentication required'], 401);
        }

        $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
        $answer     = wecoza_sanitize_value($_POST['answer'] ?? '', 'string');
        $round      = (int) ($_POST['round'] ?? 1);

        if ($feedbackId <= 0 || empty(trim($answer))) {
            wp_send_json_error(['message' => 'Invalid follow-up data'], 400);
        }

        $record = $this->repository->findById($feedbackId);
        if (!$record || (int) $record['user_id'] !== $user->ID) {
            wp_send_json_error(['message' => 'Feedback not found'], 404);
        }

        // Update conversation history
        $conversation = json_decode($record['ai_conversation'] ?? '[]', true);
        if (!is_array($conversation)) {
            $conversation = [];
        }

        $lastIdx = count($conversation) - 1;
        if ($lastIdx >= 0) {
            $conversation[$lastIdx]['answer'] = $answer;
        }

        // If max rounds reached, go straight to enrichment
        if ($round >= self::MAX_FOLLOWUP_ROUNDS) {
            $this->repository->update($feedbackId, [
                'ai_conversation' => wp_json_encode($conversation),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->enrichAndSave($feedbackId, $record['feedback_text'], $record['category'], $record['shortcode'], $record['page_url']);
            return;
        }

        // Re-check vagueness with conversation history (original text, not concatenated)
        $vaguenessResult = $this->aiService->checkVagueness(
            $record['feedback_text'],
            $record['category'],
            $record['shortcode'] ?: null,
            $record['page_url'] ?: null,
            $conversation
        );

        if (!$vaguenessResult['is_clear'] && $vaguenessResult['follow_up']) {
            $conversation[] = ['question' => $vaguenessResult['follow_up']];
            $this->repository->update($feedbackId, [
                'ai_conversation' => wp_json_encode($conversation),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            wp_send_json_success([
                'status'      => 'follow_up',
                'feedback_id' => $feedbackId,
                'follow_up'   => $vaguenessResult['follow_up'],
                'round'       => $round + 1,
            ]);
            return;
        }

        // Clear - enrich and save
        $this->repository->update($feedbackId, [
            'ai_conversation' => wp_json_encode($conversation),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->enrichAndSave($feedbackId, $record['feedback_text'], $record['category'], $record['shortcode'], $record['page_url']);
    }

    /**
     * Enrich feedback via AI and save results.
     */
    private function enrichAndSave(
        int $feedbackId,
        string $feedbackText,
        string $category,
        ?string $shortcode,
        ?string $pageUrl
    ): void {
        $record = $this->repository->findById($feedbackId);
        $conversation = json_decode($record['ai_conversation'] ?? '[]', true);

        $enrichment = $this->aiService->enrich(
            $feedbackText,
            $category,
            $shortcode ?: null,
            $pageUrl ?: null,
            is_array($conversation) ? $conversation : []
        );

        $this->repository->update($feedbackId, [
            'ai_generated_title'    => $enrichment['title'],
            'ai_suggested_priority' => $enrichment['priority'],
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        wp_send_json_success([
            'status'  => 'submitted',
            'message' => 'Feedback submitted, thank you!',
        ]);
    }

    /**
     * Save base64 screenshot to uploads directory.
     */
    private function saveScreenshot(string $base64Data): ?string
    {
        if (str_contains($base64Data, ',')) {
            $base64Data = explode(',', $base64Data, 2)[1];
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            wecoza_log('FeedbackController: Invalid base64 screenshot data');
            return null;
        }

        if (strlen($decoded) > self::MAX_SCREENSHOT_BYTES) {
            wecoza_log('FeedbackController: Screenshot exceeds 2MB limit');
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decoded);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            wecoza_log("FeedbackController: Invalid screenshot MIME type: {$mimeType}");
            return null;
        }

        $uploadsDir = wp_upload_dir();
        $feedbackDir = $uploadsDir['basedir'] . '/wecoza-feedback/' . date('Y/m');

        if (!wp_mkdir_p($feedbackDir)) {
            wecoza_log('FeedbackController: Failed to create screenshot directory');
            return null;
        }

        $ext = match ($mimeType) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $filename = 'feedback-' . wp_generate_uuid4() . '.' . $ext;
        $filepath = $feedbackDir . '/' . $filename;

        if (file_put_contents($filepath, $decoded) === false) {
            wecoza_log('FeedbackController: Failed to write screenshot file');
            return null;
        }

        return $filepath;
    }
}
