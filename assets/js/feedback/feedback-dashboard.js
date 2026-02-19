/**
 * Feedback Dashboard - Resolve toggle
 */
(function ($) {
    'use strict';

    $(document).on('click', '.wecoza-feedback-resolve-btn', function () {
        const $btn = $(this);
        const feedbackId = $btn.data('feedback-id');
        const $row = $('#feedback-row-' + feedbackId);

        $btn.prop('disabled', true);

        $.post(wecozaFeedbackDashboard.ajaxUrl, {
            action: 'wecoza_feedback_resolve',
            nonce: wecozaFeedbackDashboard.nonce,
            feedback_id: feedbackId
        })
            .done(function (response) {
                if (!response.success) {
                    alert(response.data?.message || 'Failed to update');
                    return;
                }

                const resolved = response.data.is_resolved;
                const $icon = $btn.find('span');
                const $title = $row.find('strong');

                if (resolved) {
                    $icon.removeClass('far fa-circle text-body-tertiary')
                         .addClass('fas fa-check-circle text-success');
                    $row.addClass('opacity-50');
                    $title.addClass('text-decoration-line-through');
                    $btn.attr('title', 'Mark as open');
                } else {
                    $icon.removeClass('fas fa-check-circle text-success')
                         .addClass('far fa-circle text-body-tertiary');
                    $row.removeClass('opacity-50');
                    $title.removeClass('text-decoration-line-through');
                    $btn.attr('title', 'Mark as resolved');
                }
            })
            .fail(function () {
                alert('Network error. Please try again.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

})(jQuery);
