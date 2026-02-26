<?php
/**
 * Feedback Widget - FAB + Modal
 *
 * Injected via wp_footer on all frontend pages for logged-in users.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Feedback Modal -->
<div class="modal fade" id="wecoza-feedback-modal" tabindex="-1" aria-labelledby="wecoza-feedback-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wecoza-feedback-modal-label">
                    <span class="fas fa-comment-dots me-2"></span>Send Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Context Banner -->
                <div class="alert alert-soft-info py-2 px-3 mb-3" id="wecoza-feedback-context">
                    <small>
                        <span class="fas fa-info-circle me-1"></span>
                        You're on: <strong id="wecoza-feedback-page-title"></strong>
                        <span id="wecoza-feedback-shortcode-badge" class="d-none">
                            &mdash; <span class="badge badge-phoenix badge-phoenix-secondary" id="wecoza-feedback-shortcode-text"></span>
                        </span>
                    </small>
                </div>

                <!-- Screenshot Preview -->
                <div class="mb-3 d-none" id="wecoza-feedback-screenshot-wrapper">
                    <label class="form-label fw-semibold mb-1">
                        <span class="fas fa-camera me-1"></span>Screenshot captured
                    </label>
                    <div class="wecoza-feedback-screenshot-container">
                        <img id="wecoza-feedback-screenshot-preview" class="wecoza-feedback-screenshot-img" alt="Page screenshot" />
                    </div>
                </div>

                <!-- Category Pills -->
                <div class="mb-3">
                    <label class="form-label fw-semibold mb-2">Category</label>
                    <ul class="nav nav-pills" role="tablist" id="wecoza-feedback-category-pills">
                        <li class="nav-item">
                            <button class="nav-link active" type="button" data-category="bug_report">
                                <span class="fas fa-bug me-1"></span>Bug Report
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" type="button" data-category="feature_request">
                                <span class="fas fa-lightbulb me-1"></span>Feature Request
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" type="button" data-category="comment">
                                <span class="fas fa-comment me-1"></span>Comment
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Feedback Text -->
                <div class="mb-3">
                    <div class="form-floating">
                        <textarea class="form-control" id="wecoza-feedback-text" placeholder="Describe your feedback..." style="height: 120px"></textarea>
                        <label for="wecoza-feedback-text">Describe what you'd like to report...</label>
                    </div>
                </div>

                <!-- AI Follow-up Area (hidden by default) -->
                <div class="d-none" id="wecoza-feedback-followup-area">
                    <div class="alert alert-soft-warning py-2 px-3 mb-3">
                        <small>
                            <span class="fas fa-robot me-1"></span>
                            <span id="wecoza-feedback-followup-question"></span>
                        </small>
                    </div>
                    <div class="mb-3">
                        <div class="form-floating">
                            <textarea class="form-control" id="wecoza-feedback-followup-answer" placeholder="Your response..." style="height: 80px"></textarea>
                            <label for="wecoza-feedback-followup-answer">Your response...</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-phoenix-primary btn-sm" id="wecoza-feedback-followup-submit">
                        <span class="fas fa-paper-plane me-1"></span>Send Response
                    </button>
                    <button type="button" class="btn btn-phoenix-secondary btn-sm ms-2" id="wecoza-feedback-skip-submit">
                        <span class="fas fa-forward me-1"></span>Submit As-Is
                    </button>
                    <small class="text-body-tertiary ms-2" id="wecoza-feedback-round-info"></small>
                </div>
            </div>
            <div class="modal-footer" id="wecoza-feedback-footer">
                <button type="button" class="btn btn-phoenix-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-phoenix-primary" id="wecoza-feedback-submit">
                    <span class="fas fa-paper-plane me-1"></span>Submit Feedback
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3 wecoza-feedback-toast-container" style="z-index: 1090;">
    <div class="toast align-items-center border-0" id="wecoza-feedback-toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="wecoza-feedback-toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
