<?php
declare(strict_types=1);

namespace WeCoza\Events\Admin;

use WeCoza\Events\Support\OpenAIConfig;
use WeCoza\Events\Services\NotificationSettings;
use WeCoza\Events\Views\Presenters\NotificationEmailPresenter;
use WeCoza\Events\Enums\EventType;

use function add_action;
use function add_settings_error;
use function add_settings_field;
use function add_settings_section;
use function admin_url;
use function array_filter;
use function array_map;
use function check_ajax_referer;
use function checked;
use function current_time;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_js;
use function esc_textarea;
use function explode;
use function filter_var;
use function get_option;
use function implode;
use function is_admin;
use function is_array;
use function is_wp_error;
use function register_setting;
use function sanitize_email;
use function sanitize_text_field;
use function settings_errors;
use function settings_fields;
use function sprintf;
use function submit_button;
use function trim;
use function wecoza_view;
use function wp_create_nonce;
use function wp_mail;
use function wp_safe_redirect;
use function wp_get_referer;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_remote_get;
use function wp_remote_retrieve_response_code;
use const FILTER_VALIDATE_EMAIL;

final class SettingsPage
{
    private const OPTION_GROUP = 'wecoza_events_notifications';
    private const PAGE_SLUG = 'wecoza-events-notifications';
    private const SECTION_AI = 'wecoza_events_ai_summaries_section';
    private const OPTION_AI_ENABLED = OpenAIConfig::OPTION_ENABLED;
    private const OPTION_AI_API_KEY = OpenAIConfig::OPTION_API_KEY;
    private const NONCE_ACTION_TEST = 'wecoza_ai_summary_test';
    private const NONCE_NAME = 'wecoza_ai_summary_nonce';
    private const SECTION_RECIPIENTS = 'wecoza_notification_recipients_section';
    private const OPTION_RECIPIENTS = 'wecoza_notification_recipients';
    private const NONCE_ACTION_TEST_NOTIFICATION = 'wecoza_test_notification';

    /**
     * Event types available for notification configuration
     *
     * @return array<string, string> Map of event type value to label
     */
    private static function getNotifiableEventTypes(): array
    {
        return [
            EventType::CLASS_INSERT->value => EventType::CLASS_INSERT->label(),
            EventType::CLASS_UPDATE->value => EventType::CLASS_UPDATE->label(),
            EventType::LEARNER_ADD->value => EventType::LEARNER_ADD->label(),
            EventType::LEARNER_REMOVE->value => EventType::LEARNER_REMOVE->label(),
            EventType::STATUS_CHANGE->value => EventType::STATUS_CHANGE->label(),
        ];
    }

    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_post_wecoza_ai_summary_test', [self::class, 'handleTestConnection']);
        add_action('wp_ajax_wecoza_send_test_notification', [self::class, 'handleTestNotification']);
    }

    public static function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_AI_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitizeCheckbox'],
            'default' => false,
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_AI_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeApiKey'],
            'default' => '',
            'autoload' => false,
        ]);

        add_settings_section(
            self::SECTION_AI,
            esc_html__('AI Summaries', 'wecoza-events'),
            [self::class, 'renderAiSectionIntro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_AI_ENABLED,
            esc_html__('Enable AI summaries', 'wecoza-events'),
            [self::class, 'renderAiEnabledField'],
            self::PAGE_SLUG,
            self::SECTION_AI
        );

        add_settings_field(
            self::OPTION_AI_API_KEY,
            esc_html__('OpenAI API key', 'wecoza-events'),
            [self::class, 'renderAiApiKeyField'],
            self::PAGE_SLUG,
            self::SECTION_AI
        );

        // Multi-recipient settings section
        register_setting(self::OPTION_GROUP, self::OPTION_RECIPIENTS, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitizeRecipientSettings'],
            'default' => [],
        ]);

        add_settings_section(
            self::SECTION_RECIPIENTS,
            esc_html__('Event-Type Notification Recipients', 'wecoza-events'),
            [self::class, 'renderRecipientsSectionIntro'],
            self::PAGE_SLUG
        );

        foreach (self::getNotifiableEventTypes() as $type => $label) {
            add_settings_field(
                self::OPTION_RECIPIENTS . '_' . $type,
                esc_html($label),
                [self::class, 'renderRecipientsField'],
                self::PAGE_SLUG,
                self::SECTION_RECIPIENTS,
                ['event_type' => $type, 'label' => $label]
            );
        }
    }

    public static function renderAiSectionIntro(): void
    {
        echo '<p>' . esc_html__('Configure the AI-generated summary workflow and credentials.', 'wecoza-events') . '</p>';
    }

    public static function renderAiEnabledField(): void
    {
        $value = (bool) get_option(self::OPTION_AI_ENABLED, false);
        ?>
        <input type="hidden" name="<?php echo esc_attr(self::OPTION_AI_ENABLED); ?>" value="0" />
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_AI_ENABLED); ?>" value="1" <?php checked($value); ?> />
            <?php echo esc_html__('Enable AI summaries for eligible notifications.', 'wecoza-events'); ?>
        </label>
        <p class="description"><?php echo esc_html__('When disabled, notification emails fall back to the legacy JSON payload.', 'wecoza-events'); ?></p>
        <?php
    }

    public static function renderAiApiKeyField(): void
    {
        $config = new OpenAIConfig();
        $masked = $config->maskApiKey($config->getApiKey());
        ?>
        <input type="password" name="<?php echo esc_attr(self::OPTION_AI_API_KEY); ?>" value="" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php echo esc_html__('Paste a valid OpenAI API key (sk-...) or leave blank to remove the stored key.', 'wecoza-events'); ?>
            <?php
            if ($masked !== null) {
                echo '<br />' . esc_html(sprintf(__('Current key: %s', 'wecoza-events'), $masked));
            }
            ?>
        </p>
        <?php
    }

    public static function renderRecipientsSectionIntro(): void
    {
        echo '<p>' . esc_html__('Configure email addresses to receive notifications for each event type. Enter multiple emails separated by commas.', 'wecoza-events') . '</p>';
    }

    /**
     * Render recipient field for a specific event type
     *
     * @param array{event_type: string, label: string} $args Field arguments
     */
    public static function renderRecipientsField(array $args): void
    {
        $eventType = $args['event_type'];
        $label = $args['label'];
        $settings = new NotificationSettings();
        $allRecipients = $settings->getAllRecipientSettings();
        $currentRecipients = $allRecipients[$eventType] ?? [];
        $value = implode(', ', $currentRecipients);
        $fieldId = 'recipients_' . $eventType;
        $fieldName = self::OPTION_RECIPIENTS . '[' . $eventType . ']';
        $nonce = wp_create_nonce(self::NONCE_ACTION_TEST_NOTIFICATION);
        ?>
        <textarea
            id="<?php echo esc_attr($fieldId); ?>"
            name="<?php echo esc_attr($fieldName); ?>"
            rows="2"
            cols="50"
            class="large-text"
            placeholder="email1@example.com, email2@example.com"
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php echo esc_html(sprintf(__('Recipients for %s notifications', 'wecoza-events'), $label)); ?>
        </p>
        <button type="button"
            class="button button-secondary wecoza-test-notification"
            data-event-type="<?php echo esc_attr($eventType); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>">
            <?php esc_html_e('Send Test', 'wecoza-events'); ?>
        </button>
        <?php
    }

    /**
     * Sanitize recipient settings array
     *
     * @param mixed $value Input value
     * @return array<string, array<int, string>> Sanitized recipient settings
     */
    public static function sanitizeRecipientSettings($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];
        $invalidEmails = [];

        foreach ($value as $eventType => $emails) {
            $eventType = sanitize_text_field((string) $eventType);

            // Parse comma-separated emails
            $emailList = explode(',', (string) $emails);
            $validEmails = [];

            foreach ($emailList as $email) {
                $email = trim($email);
                if ($email === '') {
                    continue;
                }

                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validEmails[] = sanitize_email($email);
                } else {
                    $invalidEmails[] = $email;
                }
            }

            $sanitized[$eventType] = $validEmails;
        }

        // Show admin notice for invalid emails
        if (!empty($invalidEmails)) {
            add_settings_error(
                self::OPTION_GROUP,
                'wecoza_invalid_emails',
                sprintf(
                    esc_html__('The following email addresses were invalid and were not saved: %s', 'wecoza-events'),
                    esc_html(implode(', ', $invalidEmails))
                ),
                'error'
            );
        }

        // Also save to NotificationSettings service
        $notificationSettings = new NotificationSettings();
        foreach ($sanitized as $eventType => $emails) {
            $type = EventType::tryFrom($eventType);
            if ($type !== null) {
                $notificationSettings->setRecipientsForEventType($type, $emails);
            }
        }

        return $sanitized;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WeCoza Event Notifications', 'wecoza-events'); ?></h1>
            <?php settings_errors(self::OPTION_GROUP); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>

        <script>
        (function($) {
            $(document).on('click', '.wecoza-test-notification', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var eventType = $btn.data('event-type');
                var nonce = $btn.data('nonce');
                var originalText = $btn.text();

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'wecoza-events')); ?>');

                $.post(ajaxurl, {
                    action: 'wecoza_send_test_notification',
                    event_type: eventType,
                    nonce: nonce
                }).done(function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Test sent!', 'wecoza-events')); ?> ' + response.data.message);
                    } else {
                        alert('<?php echo esc_js(__('Failed:', 'wecoza-events')); ?> ' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Unknown error', 'wecoza-events')); ?>'));
                    }
                }).fail(function() {
                    alert('<?php echo esc_js(__('Request failed. Please try again.', 'wecoza-events')); ?>');
                }).always(function() {
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function sanitizeCheckbox($value): string
    {
        return (string) ((int) (!empty($value)));
    }

    public static function sanitizeApiKey($value): string
    {
        $config = new OpenAIConfig();
        return $config->sanitizeApiKey((string) $value);
    }

    public static function handleTestConnection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $nonce = isset($_GET[self::NONCE_NAME]) ? wp_unslash((string) $_GET[self::NONCE_NAME]) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION_TEST)) {
            add_settings_error(self::OPTION_GROUP, 'wecoza_ai_summary_test_nonce', esc_html__('Nonce verification failed.', 'wecoza-events'));
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $config = new OpenAIConfig();
        $apiKey = $config->getApiKey();

        if ($apiKey === null) {
            add_settings_error(self::OPTION_GROUP, 'wecoza_ai_summary_test_missing', esc_html__('No OpenAI API key is configured.', 'wecoza-events'));
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $response = wp_remote_get('https://api.openai.com/v1/models?limit=1', [
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                self::OPTION_GROUP,
                'wecoza_ai_summary_test_request',
                esc_html(sprintf(__('OpenAI request failed: %s', 'wecoza-events'), $response->get_error_message()))
            );
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            add_settings_error(
                self::OPTION_GROUP,
                'wecoza_ai_summary_test_status',
                esc_html(sprintf(__('OpenAI returned HTTP %d during connection test.', 'wecoza-events'), $status))
            );
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        add_settings_error(
            self::OPTION_GROUP,
            'wecoza_ai_summary_test_success',
            esc_html__('OpenAI connection succeeded.', 'wecoza-events'),
            'updated'
        );

        wp_safe_redirect(self::redirectUrl());
        exit;
    }

    private static function redirectUrl(): string
    {
        $referer = wp_get_referer();
        if (is_string($referer) && $referer !== '') {
            return $referer;
        }

        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }

    /**
     * Handle AJAX request to send test notification
     */
    public static function handleTestNotification(): void
    {
        check_ajax_referer(self::NONCE_ACTION_TEST_NOTIFICATION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $eventType = sanitize_text_field($_POST['event_type'] ?? '');
        if ($eventType === '') {
            wp_send_json_error(['message' => 'Invalid event type'], 400);
        }

        $settings = new NotificationSettings();
        $recipients = $settings->getRecipientsForEventType($eventType);

        if (empty($recipients)) {
            wp_send_json_error(['message' => 'No recipients configured for this event type'], 400);
        }

        // Get label for the event type
        $type = EventType::tryFrom($eventType);
        $eventLabel = $type !== null ? $type->label() : $eventType;

        // Create test event data
        $testData = [
            'event_type' => $eventType,
            'entity_type' => 'class',
            'entity_id' => 0,
            'class_code' => 'TEST-001',
            'class_subject' => 'Test Notification',
            'changed_at' => current_time('mysql'),
        ];

        // Prepare email content
        $presenter = new NotificationEmailPresenter();
        $mailData = $presenter->present([
            'operation' => $eventType,
            'new_row' => $testData,
            'row' => ['class_id' => 0],
            'diff' => [],
            'summary' => ['summary' => sprintf('This is a test notification for: %s', $eventLabel)],
            'email_context' => [],
        ]);

        // Send test email to each validated recipient
        $sent = 0;
        foreach ($recipients as $recipient) {
            if (!is_email($recipient)) {
                continue;
            }
            $subject = '[TEST] ' . $mailData['subject'];
            if (wp_mail($recipient, $subject, $mailData['body'], $mailData['headers'])) {
                $sent++;
            }
        }

        if ($sent === 0) {
            wp_send_json_error(['message' => 'Failed to send test emails'], 500);
        }

        wp_send_json_success([
            'message' => sprintf('Sent to %d of %d recipients', $sent, count($recipients))
        ]);
    }
}
