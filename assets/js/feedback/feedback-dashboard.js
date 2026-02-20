/**
 * Feedback Dashboard - Resolve toggle + Copy report + Dev comments
 */
(function ($) {
    'use strict';

    // ── Copy Report to Clipboard ───────────────────────────────────────
    $(document).on('click', '.wecoza-feedback-copy-btn', function () {
        var $btn = $(this);
        var data = $btn.data('report');
        if (!data) return;

        var lines = [];
        lines.push('## ' + data.ref + ' — ' + data.title);
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

        if (data.comments && data.comments.length) {
            lines.push('');
            lines.push('### Developer Comments');
            data.comments.forEach(function (c) {
                var author = c.author_email.split('@')[0];
                var date = c.created_at ? c.created_at.substring(0, 16).replace('T', ' ') : '';
                lines.push('- **' + author + '** (' + date + '): ' + c.comment_text);
            });
        }

        var text = lines.join('\n');

        navigator.clipboard.writeText(text).then(function () {
            var origHtml = $btn.html();
            $btn.html('<span class="fas fa-check me-1"></span>Copied!');
            setTimeout(function () { $btn.html(origHtml); }, 2000);
        });
    });

    // ── Comment Submit ──────────────────────────────────────────────────
    $(document).on('click', '.wecoza-comment-submit', function () {
        var $btn = $(this);
        var feedbackId = $btn.data('feedback-id');
        var $textarea = $('.wecoza-comment-input[data-feedback-id="' + feedbackId + '"]');
        var text = $.trim($textarea.val());

        if (!text) return;

        $btn.prop('disabled', true);

        $.post(wecozaFeedbackDashboard.ajaxUrl, {
            action: 'wecoza_feedback_comment',
            nonce: wecozaFeedbackDashboard.nonce,
            feedback_id: feedbackId,
            comment_text: text
        })
            .done(function (response) {
                if (!response.success) {
                    alert(response.data?.message || 'Failed to save comment');
                    return;
                }

                var c = response.data;
                var author = c.author_email.split('@')[0];
                var dateStr = c.created_at.substring(0, 16).replace('T', ' ');
                // Format nicely if possible
                try {
                    var d = new Date(c.created_at);
                    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    dateStr = months[d.getMonth()] + ' ' + d.getDate() + ', ' +
                              String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                } catch (e) {}

                var html = '<div class="bg-body-highlight rounded-2 p-2 mb-2">' +
                    '<p class="mb-1 fs-9">' + $('<span>').text(c.comment_text).html().replace(/\n/g, '<br>') + '</p>' +
                    '<small class="text-body-tertiary fs-10">' + $('<span>').text(author).html() +
                    ' &middot; ' + dateStr + '</small></div>';

                var $list = $('.wecoza-feedback-comment-list[data-feedback-id="' + feedbackId + '"]');
                $list.append(html);
                $textarea.val('');

                // Update badge count
                var $badge = $('.wecoza-comment-count-' + feedbackId);
                var count = $list.children('.bg-body-highlight').length;
                $badge.text(count);

                // Update report data on copy button to include new comment
                var $copyBtn = $list.closest('.accordion-body').find('.wecoza-feedback-copy-btn');
                var reportData = $copyBtn.data('report');
                if (reportData) {
                    if (!reportData.comments) reportData.comments = [];
                    reportData.comments.push(c);
                    $copyBtn.data('report', reportData);
                }
            })
            .fail(function () {
                alert('Network error. Please try again.');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

    // Ctrl+Enter shortcut on comment textarea
    $(document).on('keydown', '.wecoza-comment-input', function (e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('.mt-2').find('.wecoza-comment-submit').trigger('click');
        }
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
