<?php
/**
 * Notification Settings Template
 *
 * Renders the notification recipient configuration form.
 * Used by SettingsPage for the Event-Type Notification Recipients section.
 *
 * @var array<string, string> $eventTypes Event types with labels (event_type_value => label)
 * @var array<string, array<int, string>> $currentSettings Current recipient settings per event type
 * @var string $nonce CSRF nonce for test notification AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wecoza-notification-settings">
    <h2><?php esc_html_e('Event-Type Notification Recipients', 'wecoza-events'); ?></h2>
    <p class="description">
        <?php esc_html_e('Configure email addresses to receive notifications for each event type. Enter multiple emails separated by commas.', 'wecoza-events'); ?>
    </p>

    <table class="form-table" role="presentation">
        <tbody>
        <?php foreach ($eventTypes as $type => $label): ?>
            <?php
            $fieldId = 'recipients_' . esc_attr($type);
            $fieldName = 'wecoza_notification_recipients[' . esc_attr($type) . ']';
            $recipients = $currentSettings[$type] ?? [];
            $value = implode(', ', $recipients);
            ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($fieldId); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                </th>
                <td>
                    <textarea
                        id="<?php echo esc_attr($fieldId); ?>"
                        name="<?php echo esc_attr($fieldName); ?>"
                        rows="2"
                        cols="50"
                        class="large-text"
                        placeholder="email1@example.com, email2@example.com"
                    ><?php echo esc_textarea($value); ?></textarea>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: event type label */
                            esc_html__('Recipients for %s notifications', 'wecoza-events'),
                            esc_html($label)
                        );
                        ?>
                    </p>
                    <button type="button"
                        class="button button-secondary wecoza-test-notification"
                        data-event-type="<?php echo esc_attr($type); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                        <?php esc_html_e('Send Test', 'wecoza-events'); ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.wecoza-notification-settings .button.wecoza-test-notification {
    margin-top: 5px;
}
.wecoza-notification-settings textarea.large-text {
    width: 100%;
    max-width: 400px;
}
</style>

<script>
(function($) {
    'use strict';

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
                var message = response.data && response.data.message
                    ? response.data.message
                    : '<?php echo esc_js(__('Unknown error', 'wecoza-events')); ?>';
                alert('<?php echo esc_js(__('Failed:', 'wecoza-events')); ?> ' + message);
            }
        }).fail(function() {
            alert('<?php echo esc_js(__('Request failed. Please try again.', 'wecoza-events')); ?>');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
})(jQuery);
</script>
