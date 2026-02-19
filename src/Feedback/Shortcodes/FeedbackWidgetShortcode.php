<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Shortcodes;

final class FeedbackWidgetShortcode
{
    private bool $assetsEnqueued = false;

    public static function register(?self $instance = null): void
    {
        $widget = $instance ?? new self();

        // Only on frontend, for logged-in users
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$widget, 'enqueueAssets']);
        add_action('wp_footer', [$widget, 'renderWidget']);
    }

    public function enqueueAssets(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        // html2canvas from CDN
        wp_enqueue_script(
            'html2canvas',
            'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
            [],
            '1.4.1',
            true
        );

        wp_enqueue_script(
            'wecoza-feedback-widget',
            wecoza_js_url('feedback/feedback-widget.js'),
            ['jquery', 'html2canvas'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-feedback-widget', 'wecozaFeedback', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wecoza_feedback'),
            'user'    => wp_get_current_user()->user_email,
        ]);

        $this->assetsEnqueued = true;
    }

    public function renderWidget(): void
    {
        if (!is_user_logged_in() || !$this->assetsEnqueued) {
            return;
        }

        echo wecoza_view('feedback/widget', [], true);
    }
}
