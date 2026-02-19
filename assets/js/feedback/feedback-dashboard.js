/**
 * Feedback Dashboard - Resolve toggle + Copy report
 */
(function ($) {
    'use strict';

    // ── Copy Report to Clipboard ───────────────────────────────────────
    $(document).on('click', '.wecoza-feedback-copy-btn', function () {
        var $btn = $(this);
        var data = $btn.data('report');
        if (!data) return;

        var lines = [];
        lines.push('## ' + data.category + ' Report: ' + data.title);
        lines.push('**Priority:** ' + data.priority + ' | **Category:** ' + data.category + ' | **Date:** ' + data.date);
        lines.push('');
        lines.push('**Reporter:** ' + data.reporter);
        lines.push('**Page:** ' + data.page);
        if (data.url) lines.push('**URL:** ' + data.url);
        if (data.shortcode) lines.push('**Shortcode:** `' + data.shortcode + '`');
        lines.push('');
        lines.push('### Description');
        lines.push(data.description);

        if (data.conversation && data.conversation.length) {
            lines.push('');
            lines.push('### AI Clarification');
            data.conversation.forEach(function (round) {
                lines.push('- **Q:** ' + (round.question || ''));
                if (round.answer) lines.push('  **A:** ' + round.answer);
            });
        }

        if (data.screenshot) {
            lines.push('');
            lines.push('### Screenshot');
            lines.push(data.screenshot);
        }

        var text = lines.join('\n');

        navigator.clipboard.writeText(text).then(function () {
            var origHtml = $btn.html();
            $btn.html('<span class="fas fa-check me-1"></span>Copied!');
            setTimeout(function () { $btn.html(origHtml); }, 2000);
        });
    });

    // ── Resolve Toggle ─────────────────────────────────────────────────
    $(document).on('click', '.wecoza-feedback-resolve-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const feedbackId = $btn.data('feedback-id');
        const $row = $('#feedback-row-' + feedbackId);

        $btn.css('pointer-events', 'none');

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
                const $icon = $btn.find('span').first();
                const $title = $row.find('strong').first();

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
                $btn.css('pointer-events', '');
            });
    });

})(jQuery);
