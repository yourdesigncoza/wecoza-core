/**
 * Feedback Widget - UI Logic
 *
 * Handles screenshot capture, context collection, AJAX submission,
 * AI follow-up rounds, and toast notifications.
 */
(function ($) {
    'use strict';

    const MAX_SCREENSHOT_BYTES = 2 * 1024 * 1024; // 2MB
    const MAX_FOLLOWUP_ROUNDS = 2;

    let state = {
        category: 'bug_report',
        screenshotBase64: null,
        feedbackId: null,
        round: 0,
        submitting: false
    };

    /**
     * Collect page context
     */
    function collectContext() {
        const shortcodeEl = document.querySelector('[data-wecoza-shortcode]');
        const params = Object.fromEntries(new URLSearchParams(window.location.search));

        return {
            page_url: window.location.href,
            page_title: document.title,
            shortcode: shortcodeEl ? shortcodeEl.getAttribute('data-wecoza-shortcode') : '',
            url_params: JSON.stringify(params),
            browser_info: navigator.userAgent.substring(0, 500),
            viewport: window.innerWidth + 'x' + window.innerHeight
        };
    }

    /**
     * Capture screenshot via html2canvas
     */
    async function captureScreenshot() {
        if (typeof html2canvas !== 'function') {
            return null;
        }

        try {
            // Hide the FAB during capture
            const fab = document.getElementById('wecoza-feedback-fab');
            if (fab) fab.style.display = 'none';

            const canvas = await html2canvas(document.documentElement, {
                useCORS: true,
                allowTaint: true,
                logging: false,
                scale: 1,
                x: 0,
                y: window.scrollY,
                width: window.innerWidth,
                height: window.innerHeight,
                windowWidth: window.innerWidth,
                windowHeight: window.innerHeight
            });

            if (fab) fab.style.display = '';

            // Resize to max 1920px wide (keeps full viewport width at 1x)
            const maxWidth = 1920;
            let width = canvas.width;
            let height = canvas.height;
            if (width > maxWidth) {
                height = Math.round(height * (maxWidth / width));
                width = maxWidth;
            }

            const resized = document.createElement('canvas');
            resized.width = width;
            resized.height = height;
            const ctx = resized.getContext('2d');
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(canvas, 0, 0, width, height);

            // Try JPEG at 90% quality first
            let base64 = resized.toDataURL('image/jpeg', 0.9);

            // If > 2MB, reduce to 75%
            if (base64.length > MAX_SCREENSHOT_BYTES) {
                base64 = resized.toDataURL('image/jpeg', 0.75);
            }

            // If still too large, reduce further
            if (base64.length > MAX_SCREENSHOT_BYTES) {
                base64 = resized.toDataURL('image/jpeg', 0.6);
            }

            // If still too large, skip screenshot
            if (base64.length > MAX_SCREENSHOT_BYTES) {
                console.warn('Feedback widget: Screenshot too large, skipping');
                return null;
            }

            return base64;
        } catch (err) {
            console.warn('Feedback widget: Screenshot capture failed', err);
            return null;
        }
    }

    /**
     * Show toast notification
     */
    function showToast(message, isError) {
        const toast = document.getElementById('wecoza-feedback-toast');
        const body = document.getElementById('wecoza-feedback-toast-body');

        body.textContent = message;
        toast.className = 'toast align-items-center border-0 text-white ' +
            (isError ? 'bg-danger' : 'bg-success');

        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
    }

    /**
     * Set loading state on a button
     */
    function setLoading(btn, loading) {
        if (loading) {
            btn.data('original-html', btn.html());
            btn.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...'
            );
        } else {
            btn.prop('disabled', false).html(btn.data('original-html'));
        }
    }

    /**
     * Reset modal to initial state
     */
    function resetModal() {
        state = {
            category: 'bug_report',
            screenshotBase64: null,
            feedbackId: null,
            round: 0,
            submitting: false
        };

        $('#wecoza-feedback-text').val('');
        $('#wecoza-feedback-followup-area').addClass('d-none');
        $('#wecoza-feedback-followup-answer').val('');
        $('#wecoza-feedback-footer').removeClass('d-none');
        $('#wecoza-feedback-screenshot-wrapper').addClass('d-none');

        // Reset category pills
        $('#wecoza-feedback-category-pills .nav-link').removeClass('active');
        $('#wecoza-feedback-category-pills .nav-link[data-category="bug_report"]').addClass('active');
    }

    /**
     * Handle initial feedback submission
     */
    function submitFeedback() {
        if (state.submitting) return;

        const feedbackText = $('#wecoza-feedback-text').val().trim();
        if (!feedbackText) {
            showToast('Please enter some feedback text.', true);
            return;
        }

        state.submitting = true;
        const $btn = $('#wecoza-feedback-submit');
        setLoading($btn, true);

        const context = collectContext();
        const data = {
            action: 'wecoza_feedback_submit',
            nonce: wecozaFeedback.nonce,
            category: state.category,
            feedback_text: feedbackText,
            screenshot: state.screenshotBase64 || '',
            ...context
        };

        $.post(wecozaFeedback.ajaxUrl, data)
            .done(function (response) {
                if (!response.success) {
                    showToast(response.data?.message || 'Submission failed.', true);
                    return;
                }

                const result = response.data;
                if (result.status === 'follow_up') {
                    handleFollowUp(result);
                } else {
                    handleSuccess(result);
                }
            })
            .fail(function () {
                showToast('Network error. Please try again.', true);
            })
            .always(function () {
                state.submitting = false;
                setLoading($btn, false);
            });
    }

    /**
     * Handle AI follow-up question
     */
    function handleFollowUp(result) {
        state.feedbackId = result.feedback_id;
        state.round = result.round;

        // Show follow-up area, hide main submit
        $('#wecoza-feedback-followup-area').removeClass('d-none');
        $('#wecoza-feedback-followup-question').text(result.follow_up);
        $('#wecoza-feedback-followup-answer').val('').focus();
        $('#wecoza-feedback-footer').addClass('d-none');

        const remaining = MAX_FOLLOWUP_ROUNDS - state.round;
        if (remaining <= 0) {
            $('#wecoza-feedback-round-info').html(
                '<span class="badge badge-phoenix badge-phoenix-warning px-3 py-2 fs-9">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="height:14px;width:14px;"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>' +
                '<span class="badge-label">Last chance to add detail</span></span>'
            );
        } else {
            $('#wecoza-feedback-round-info').text('Round ' + state.round + ' of ' + MAX_FOLLOWUP_ROUNDS);
        }
    }

    /**
     * Submit follow-up answer
     */
    function submitFollowUp() {
        if (state.submitting) return;

        const answer = $('#wecoza-feedback-followup-answer').val().trim();
        if (!answer) {
            showToast('Please provide a response.', true);
            return;
        }

        state.submitting = true;
        const $btn = $('#wecoza-feedback-followup-submit');
        setLoading($btn, true);

        $.post(wecozaFeedback.ajaxUrl, {
            action: 'wecoza_feedback_followup',
            nonce: wecozaFeedback.nonce,
            feedback_id: state.feedbackId,
            answer: answer,
            round: state.round,
            skip: 0
        })
            .done(function (response) {
                if (!response.success) {
                    showToast(response.data?.message || 'Submission failed.', true);
                    return;
                }

                const result = response.data;
                if (result.status === 'follow_up') {
                    handleFollowUp(result);
                } else {
                    handleSuccess(result);
                }
            })
            .fail(function () {
                showToast('Network error. Please try again.', true);
            })
            .always(function () {
                state.submitting = false;
                setLoading($btn, false);
            });
    }

    /**
     * Submit as-is (skip further AI follow-ups)
     */
    function submitAsIs() {
        if (state.submitting) return;

        state.submitting = true;
        const $btn = $('#wecoza-feedback-skip-submit');
        setLoading($btn, true);

        $.post(wecozaFeedback.ajaxUrl, {
            action: 'wecoza_feedback_followup',
            nonce: wecozaFeedback.nonce,
            feedback_id: state.feedbackId,
            answer: $('#wecoza-feedback-followup-answer').val().trim() || '(submitted as-is)',
            round: state.round,
            skip: 1
        })
            .done(function (response) {
                if (!response.success) {
                    showToast(response.data?.message || 'Submission failed.', true);
                    return;
                }
                handleSuccess(response.data);
            })
            .fail(function () {
                showToast('Network error. Please try again.', true);
            })
            .always(function () {
                state.submitting = false;
                setLoading($btn, false);
            });
    }

    /**
     * Handle successful submission
     */
    function handleSuccess(result) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('wecoza-feedback-modal'));
        if (modal) modal.hide();

        showToast(result.message || 'Feedback submitted, thank you!', false);
        resetModal();
    }

    // --- Event Bindings ---

    $(document).ready(function () {
        // FAB click - capture screenshot and open modal
        $('#wecoza-feedback-fab').on('click', async function () {
            // Set context info
            const context = collectContext();
            $('#wecoza-feedback-page-title').text(context.page_title || window.location.pathname);

            if (context.shortcode) {
                $('#wecoza-feedback-shortcode-text').text(context.shortcode);
                $('#wecoza-feedback-shortcode-badge').removeClass('d-none');
            } else {
                $('#wecoza-feedback-shortcode-badge').addClass('d-none');
            }

            // Capture screenshot
            const screenshot = await captureScreenshot();
            state.screenshotBase64 = screenshot;

            if (screenshot) {
                $('#wecoza-feedback-screenshot-preview').attr('src', screenshot);
                $('#wecoza-feedback-screenshot-wrapper').removeClass('d-none');
            } else {
                $('#wecoza-feedback-screenshot-wrapper').addClass('d-none');
            }

            // Open modal
            const modal = new bootstrap.Modal(document.getElementById('wecoza-feedback-modal'));
            modal.show();
        });

        // Category pill selection
        $('#wecoza-feedback-category-pills').on('click', '.nav-link', function () {
            $('#wecoza-feedback-category-pills .nav-link').removeClass('active');
            $(this).addClass('active');
            state.category = $(this).data('category');
        });

        // Submit feedback
        $('#wecoza-feedback-submit').on('click', submitFeedback);

        // Submit follow-up
        $('#wecoza-feedback-followup-submit').on('click', submitFollowUp);

        // Submit as-is (skip AI)
        $('#wecoza-feedback-skip-submit').on('click', submitAsIs);

        // Reset on modal close
        $('#wecoza-feedback-modal').on('hidden.bs.modal', resetModal);

        // Enter key in textarea submits (Shift+Enter for newline)
        $('#wecoza-feedback-text').on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitFeedback();
            }
        });

        $('#wecoza-feedback-followup-answer').on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitFollowUp();
            }
        });
    });

})(jQuery);
