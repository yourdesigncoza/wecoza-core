/**
 * WeCoza NLQ - AI Query Builder Script
 *
 * Handles the [wecoza_nlq_input] shortcode:
 *   1. User types question → calls AI to generate SQL
 *   2. Preview results in DataTable
 *   3. Refine with AI or edit manually
 *   4. Save the query → get shortcode
 *
 * @package WeCoza\NLQ
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    var NLQ = window.wecozaNLQ || {};
    var currentQuestion = '';
    var currentSql = '';

    $(document).ready(function () {
        bindEvents();
    });

    function bindEvents() {
        // Generate SQL from question
        $('#nlq-ask-btn').on('click', handleGenerate);
        $('#nlq-question').on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                handleGenerate();
            }
        });

        // Preview results
        $('#nlq-preview-btn').on('click', handlePreview);

        // Refine
        $('#nlq-refine-toggle').on('click', function () {
            $('#nlq-refine-area').slideToggle();
            $('#nlq-refine-input').focus();
        });
        $('#nlq-refine-btn').on('click', handleRefine);
        $('#nlq-refine-input').on('keydown', function (e) {
            if (e.key === 'Enter') handleRefine();
        });

        // New query
        $('#nlq-new-query-btn').on('click', resetAll);

        // Save
        $('#nlq-save-query-btn').on('click', handleSave);

        // Copy shortcode
        $('#nlq-copy-saved-shortcode').on('click', function () {
            var sc = $('#nlq-saved-shortcode').text();
            copyToClipboard(sc);
        });
    }

    /* ─── Generate SQL from Natural Language ──────────────── */

    function handleGenerate() {
        var question = $('#nlq-question').val().trim();
        if (!question) {
            showToast('Please enter a question.', 'warning');
            return;
        }

        currentQuestion = question;
        var module = $('#nlq-module-hint').val() || '';

        var $btn = $('#nlq-ask-btn');
        setBtnLoading($btn, 'Generating…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_generate_sql',
                _ajax_nonce: NLQ.nonce,
                question: question,
                module: module,
            },
            success: function (response) {
                if (response.success) {
                    currentSql = response.data.sql;
                    $('#nlq-generated-sql').val(response.data.sql);

                    // Show reformulated query
                    if (response.data.reformulated_query) {
                        $('#nlq-reformulated-text').text(response.data.reformulated_query);
                        $('#nlq-reformulated').show();
                    } else {
                        $('#nlq-reformulated').hide();
                    }

                    // Show explanation
                    if (response.data.explanation) {
                        $('#nlq-explanation-text').text(response.data.explanation);
                        $('#nlq-explanation').show();
                    } else {
                        $('#nlq-explanation').hide();
                    }

                    // Show detected module
                    if (response.data.module) {
                        $('#nlq-detected-module').text(response.data.module.charAt(0).toUpperCase() + response.data.module.slice(1)).show();
                        // Auto-fill save category
                        $('#nlq-save-category').val(response.data.module.charAt(0).toUpperCase() + response.data.module.slice(1));
                    } else {
                        $('#nlq-detected-module').hide();
                    }

                    // Show result step
                    $('#nlq-step-result').slideDown();

                    // Hide previous preview/save
                    $('#nlq-preview-card').hide();
                    $('#nlq-save-card').hide();
                    $('#nlq-save-success').hide();
                    $('#nlq-refine-area').hide();

                    // Auto-populate save name from question
                    if (!$('#nlq-save-name').val()) {
                        var name = question.length > 60 ? question.substring(0, 60) + '…' : question;
                        $('#nlq-save-name').val(name);
                    }

                    // Scroll to result
                    $('html, body').animate({
                        scrollTop: $('#nlq-step-result').offset().top - 100
                    }, 400);
                } else {
                    showToast(response.data.message || 'Generation failed.', 'danger');
                }
            },
            error: function () {
                showToast('Network error. Please try again.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-stars me-1"></i> Generate');
            },
        });
    }

    /* ─── Preview Results ─────────────────────────────────── */

    function handlePreview() {
        var sql = $('#nlq-generated-sql').val().trim();
        if (!sql) {
            showToast('No SQL to preview.', 'warning');
            return;
        }

        currentSql = sql;
        var $btn = $('#nlq-preview-btn');
        setBtnLoading($btn, 'Running…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_preview_sql',
                _ajax_nonce: NLQ.nonce,
                sql_query: sql,
            },
            success: function (response) {
                if (response.success) {
                    renderPreviewTable(response.data);
                    $('#nlq-preview-card').slideDown();
                    $('#nlq-save-card').slideDown();
                } else {
                    showToast(response.data.message || 'Preview failed.', 'danger');
                }
            },
            error: function () {
                showToast('Network error.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-play-circle me-1"></i> Preview Results');
            },
        });
    }

    function renderPreviewTable(data) {
        var $thead = $('#nlq-ai-preview-table thead tr');
        var $tbody = $('#nlq-ai-preview-table tbody');

        if ($.fn.DataTable.isDataTable('#nlq-ai-preview-table')) {
            $('#nlq-ai-preview-table').DataTable().destroy();
        }

        $('#nlq-preview-count').text(data.row_count + ' rows');
        $thead.empty();
        $tbody.empty();

        if (data.columns && data.columns.length) {
            data.columns.forEach(function (col) {
                $thead.append('<th class="border-0">' + escapeHtml(formatColumnName(col)) + '</th>');
            });

            if (data.data && data.data.length) {
                data.data.forEach(function (row) {
                    var tr = '<tr>';
                    data.columns.forEach(function (col, colIdx) {
                        var val = escapeHtml(String(row[col] ?? ''));
                        if (colIdx === 0 && /^\d+$/.test(val)) {
                            tr += '<td class="py-2 align-middle text-center"><span class="badge fs-10 badge-phoenix badge-phoenix-secondary">#' + val + '</span></td>';
                        } else {
                            tr += '<td class="py-2 align-middle">' + val + '</td>';
                        }
                    });
                    tr += '</tr>';
                    $tbody.append(tr);
                });
            }

            var dt = $('#nlq-ai-preview-table').DataTable({
                pageLength: 25,
                autoWidth: false,
                destroy: true,
                dom: 'rt<"d-flex justify-content-between align-items-center mt-2"<"text-body-tertiary fs-9"i>p>',
                language: {
                    paginate: {
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                    },
                },
            });
            // Ensure columns recalculate after the card is visible
            setTimeout(function() { dt.columns.adjust(); }, 150);
        }
    }

    /* ─── Refine with AI ──────────────────────────────────── */

    function handleRefine() {
        var refinement = $('#nlq-refine-input').val().trim();
        if (!refinement) {
            showToast('Please describe what you want to change.', 'warning');
            return;
        }

        var sql = $('#nlq-generated-sql').val().trim();
        var $btn = $('#nlq-refine-btn');
        setBtnLoading($btn, 'Refining…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_refine_sql',
                _ajax_nonce: NLQ.nonce,
                current_sql: sql,
                refinement: refinement,
                original_prompt: currentQuestion,
            },
            success: function (response) {
                if (response.success) {
                    currentSql = response.data.sql;
                    $('#nlq-generated-sql').val(response.data.sql);

                    if (response.data.reformulated_query) {
                        $('#nlq-reformulated-text').text(response.data.reformulated_query);
                        $('#nlq-reformulated').show();
                    }

                    if (response.data.explanation) {
                        $('#nlq-explanation-text').text(response.data.explanation);
                        $('#nlq-explanation').show();
                    }

                    // Hide old preview so user re-previews
                    $('#nlq-preview-card').hide();
                    $('#nlq-refine-input').val('');

                    showToast('Query refined! Click "Preview Results" to see the updated output.', 'success');
                } else {
                    showToast(response.data.message || 'Refinement failed.', 'danger');
                }
            },
            error: function () {
                showToast('Network error.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-stars me-1"></i> Refine');
            },
        });
    }

    /* ─── Save Query ──────────────────────────────────────── */

    function handleSave() {
        var name = $('#nlq-save-name').val().trim();
        var sql = $('#nlq-generated-sql').val().trim();

        if (!name) {
            showToast('Please enter a query name.', 'warning');
            $('#nlq-save-name').focus();
            return;
        }
        if (!sql) {
            showToast('No SQL to save.', 'warning');
            return;
        }

        var $btn = $('#nlq-save-query-btn');
        setBtnLoading($btn, 'Saving…');

        $.ajax({
            url: NLQ.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_nlq_save_query',
                _ajax_nonce: NLQ.nonce,
                query_name: name,
                description: $('#nlq-save-description').val().trim(),
                natural_language: currentQuestion,
                sql_query: sql,
                category: $('#nlq-save-category').val().trim(),
            },
            success: function (response) {
                if (response.success) {
                    var shortcode = '[wecoza_nlq_table query_id="' + response.data.id + '"]';
                    $('#nlq-saved-shortcode').text(shortcode);
                    $('#nlq-save-success').slideDown();
                    $('#nlq-save-card').slideUp();

                    showToast('Query saved! ID: #' + response.data.id, 'success');
                } else {
                    showToast(response.data.message || 'Save failed.', 'danger');
                }
            },
            error: function () {
                showToast('Network error.', 'danger');
            },
            complete: function () {
                resetBtn($btn, '<i class="bi bi-save me-1"></i> Save Query');
            },
        });
    }

    /* ─── Reset ───────────────────────────────────────────── */

    function resetAll() {
        $('#nlq-question').val('').focus();
        $('#nlq-step-result').slideUp(function () {
            $('#nlq-generated-sql').val('');
            $('#nlq-reformulated').hide();
            $('#nlq-explanation').hide();
            $('#nlq-preview-card').hide();
            $('#nlq-save-card').hide();
            $('#nlq-save-success').hide();
            $('#nlq-refine-area').hide();
            $('#nlq-refine-input').val('');
            $('#nlq-save-name').val('');
            $('#nlq-save-description').val('');
            $('#nlq-save-category').val('');
        });
        currentQuestion = '';
        currentSql = '';
    }

    /* ─── Utilities ───────────────────────────────────────── */

    function formatColumnName(col) {
        return col.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function setBtnLoading($btn, text) {
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> ' + text);
    }

    function resetBtn($btn, html) {
        $btn.prop('disabled', false).html(html);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                showToast('Copied to clipboard!', 'success', 2000);
            });
        } else {
            var $t = $('<input>');
            $('body').append($t);
            $t.val(text).select();
            document.execCommand('copy');
            $t.remove();
            showToast('Copied!', 'success', 2000);
        }
    }

    function showToast(message, type, autoHide) {
        type = type || 'info';
        autoHide = autoHide || 4000;
        var iconMap = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill',
        };
        var $alert = $(
            '<div class="alert alert-subtle-' + type + ' alert-dismissible fade show position-fixed d-flex align-items-center" ' +
            'style="top: 80px; right: 20px; z-index: 99999; min-width: 300px; max-width: 450px;" role="alert">' +
            '<i class="bi ' + (iconMap[type] || iconMap.info) + ' me-2 fs-5"></i>' +
            '<div>' + message + '</div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
        );
        $('body').append($alert);
        setTimeout(function () { $alert.alert('close'); }, autoHide);
    }

})(jQuery);
