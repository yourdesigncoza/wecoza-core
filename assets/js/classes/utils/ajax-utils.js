/**
 * AJAX Utilities - Standardized AJAX response handling for WeCoza Classes Plugin
 *
 * Provides consistent patterns for:
 * - WordPress AJAX requests with nonce handling
 * - Standardized success/error response processing
 * - Loading states and UI feedback
 * - Retry logic for transient failures
 *
 * @package WeCozaClasses
 * @since 1.0.0
 *
 * Usage:
 * ```javascript
 * // Simple request
 * WeCozaAjax.post('save_class', formData)
 *     .then(data => console.log('Success:', data))
 *     .catch(error => console.error('Error:', error));
 *
 * // With loading indicator
 * WeCozaAjax.post('get_calendar_events', { start: date, end: date }, {
 *     loadingTarget: '#calendar-container',
 *     loadingText: 'Loading events...'
 * });
 * ```
 */

(function(global, $) {
    'use strict';

    /**
     * Default configuration
     */
    const DEFAULT_CONFIG = {
        // AJAX URL (WordPress admin-ajax.php)
        ajaxUrl: null,

        // Default nonce key in localized object
        nonceKey: 'nonce',

        // Timeout in milliseconds
        timeout: 30000,

        // Retry configuration
        maxRetries: 0,
        retryDelay: 1000,

        // Loading indicator options
        loadingTarget: null,
        loadingText: 'Loading...',
        loadingClass: 'wecoza-loading',

        // Debug mode
        debug: false
    };

    /**
     * Loading indicator HTML template
     */
    const LOADING_TEMPLATE = `
        <div class="wecoza-ajax-loading text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">{text}</span>
            </div>
            <div class="mt-2 text-muted small">{text}</div>
        </div>
    `;

    /**
     * WeCozaAjax utility object
     */
    const WeCozaAjax = {
        /**
         * Global configuration
         */
        config: { ...DEFAULT_CONFIG },

        /**
         * Active requests map (for cancellation)
         */
        activeRequests: new Map(),

        /**
         * Configure global settings
         * @param {Object} options Configuration options
         */
        configure: function(options) {
            this.config = { ...this.config, ...options };
        },

        /**
         * Make a POST request (most common for WordPress AJAX)
         * @param {string} action WordPress AJAX action
         * @param {Object|FormData} data Request data
         * @param {Object} options Request options
         * @returns {Promise}
         */
        post: function(action, data = {}, options = {}) {
            return this.request('POST', action, data, options);
        },

        /**
         * Make a GET request
         * @param {string} action WordPress AJAX action
         * @param {Object} data Request data
         * @param {Object} options Request options
         * @returns {Promise}
         */
        get: function(action, data = {}, options = {}) {
            return this.request('GET', action, data, options);
        },

        /**
         * Core request method
         * @param {string} method HTTP method
         * @param {string} action WordPress AJAX action
         * @param {Object|FormData} data Request data
         * @param {Object} options Request options
         * @returns {Promise}
         */
        request: function(method, action, data = {}, options = {}) {
            const config = { ...this.config, ...options };

            return new Promise((resolve, reject) => {
                // Determine AJAX URL
                const ajaxUrl = config.ajaxUrl || this.getAjaxUrl();
                if (!ajaxUrl) {
                    reject(new Error('AJAX URL not configured'));
                    return;
                }

                // Get nonce
                const nonce = config.nonce || this.getNonce(config.nonceKey);

                // Build request data
                let requestData;
                let processData = true;
                let contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

                if (data instanceof FormData) {
                    requestData = data;
                    requestData.append('action', action);
                    if (nonce) requestData.append('nonce', nonce);
                    processData = false;
                    contentType = false;
                } else {
                    requestData = {
                        action: action,
                        nonce: nonce,
                        ...data
                    };
                }

                // Show loading indicator
                if (config.loadingTarget) {
                    this.showLoading(config.loadingTarget, config.loadingText);
                }

                // Generate request ID for tracking/cancellation
                const requestId = `${action}-${Date.now()}`;

                // Debug logging
                if (config.debug) {
                    console.log(`[WeCozaAjax] ${method} ${action}`, requestData);
                }

                // Make request
                const xhr = $.ajax({
                    url: ajaxUrl,
                    type: method,
                    data: requestData,
                    processData: processData,
                    contentType: contentType,
                    timeout: config.timeout,
                    success: (response) => {
                        this.activeRequests.delete(requestId);
                        this.hideLoading(config.loadingTarget);

                        if (config.debug) {
                            console.log(`[WeCozaAjax] Response for ${action}:`, response);
                        }

                        // Process WordPress-style response
                        const result = this.processResponse(response);
                        if (result.success) {
                            resolve(result.data);
                        } else {
                            reject(new Error(result.message || 'Request failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        this.activeRequests.delete(requestId);
                        this.hideLoading(config.loadingTarget);

                        if (config.debug) {
                            console.error(`[WeCozaAjax] Error for ${action}:`, { xhr, status, error });
                        }

                        // Handle retry logic
                        if (config.maxRetries > 0 && this.shouldRetry(xhr, status)) {
                            setTimeout(() => {
                                this.request(method, action, data, {
                                    ...options,
                                    maxRetries: config.maxRetries - 1
                                }).then(resolve).catch(reject);
                            }, config.retryDelay);
                            return;
                        }

                        // Process error
                        const errorInfo = this.processError(xhr, status, error);
                        reject(errorInfo);
                    }
                });

                // Track active request
                this.activeRequests.set(requestId, xhr);
            });
        },

        /**
         * Process WordPress AJAX response
         * @param {Object} response Raw response
         * @returns {Object} Processed response
         */
        processResponse: function(response) {
            // WordPress returns { success: true/false, data: ... }
            if (typeof response === 'object' && response !== null) {
                if ('success' in response) {
                    return {
                        success: response.success,
                        data: response.data,
                        message: response.data?.message || (response.success ? '' : response.data)
                    };
                }
                // Non-standard response, assume success
                return { success: true, data: response, message: '' };
            }

            // String or other response
            return { success: true, data: response, message: '' };
        },

        /**
         * Process AJAX error
         * @param {Object} xhr XMLHttpRequest object
         * @param {string} status Status string
         * @param {string} error Error message
         * @returns {Error}
         */
        processError: function(xhr, status, error) {
            let message = 'An error occurred';

            if (status === 'timeout') {
                message = 'Request timed out. Please try again.';
            } else if (status === 'abort') {
                message = 'Request was cancelled';
            } else if (xhr.status === 0) {
                message = 'Network error. Please check your connection.';
            } else if (xhr.status === 403) {
                message = 'Access denied. Please refresh the page and try again.';
            } else if (xhr.status === 404) {
                message = 'The requested resource was not found.';
            } else if (xhr.status >= 500) {
                message = 'Server error. Please try again later.';
            } else if (xhr.responseJSON?.data) {
                message = xhr.responseJSON.data;
            } else if (error) {
                message = error;
            }

            const err = new Error(message);
            err.status = xhr.status;
            err.statusText = status;
            err.xhr = xhr;

            return err;
        },

        /**
         * Check if request should be retried
         * @param {Object} xhr XMLHttpRequest object
         * @param {string} status Status string
         * @returns {boolean}
         */
        shouldRetry: function(xhr, status) {
            // Retry on network errors, rate limiting, or server errors
            return status === 'timeout' ||
                   xhr.status === 0 ||
                   xhr.status === 429 ||  // Rate limited - retry after delay
                   xhr.status >= 500;
        },

        /**
         * Get AJAX URL from localized scripts
         * @returns {string|null}
         */
        getAjaxUrl: function() {
            // Try common WordPress localization patterns
            if (typeof wecozaClass !== 'undefined' && wecozaClass.ajaxUrl) {
                return wecozaClass.ajaxUrl;
            }
            if (typeof qaAjax !== 'undefined' && qaAjax.url) {
                return qaAjax.url;
            }
            if (typeof WeCozaSingleClass !== 'undefined' && WeCozaSingleClass.ajaxUrl) {
                return WeCozaSingleClass.ajaxUrl;
            }
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            return null;
        },

        /**
         * Get nonce from localized scripts
         * @param {string} key Nonce key
         * @returns {string|null}
         */
        getNonce: function(key = 'nonce') {
            // Try common WordPress localization patterns
            if (typeof wecozaClass !== 'undefined' && wecozaClass[key]) {
                return wecozaClass[key];
            }
            if (typeof qaAjax !== 'undefined' && qaAjax[key]) {
                return qaAjax[key];
            }
            if (typeof WeCozaSingleClass !== 'undefined') {
                return WeCozaSingleClass.classNonce || WeCozaSingleClass.calendarNonce;
            }
            return null;
        },

        /**
         * Show loading indicator
         * @param {string|Element} target Target element or selector
         * @param {string} text Loading text
         */
        showLoading: function(target, text = 'Loading...') {
            if (!target) return;

            const $target = $(target);
            if ($target.length === 0) return;

            // Store original content
            $target.data('wecoza-original-content', $target.html());

            // Show loading indicator
            const loadingHtml = LOADING_TEMPLATE.replace(/\{text\}/g, text);
            $target.html(loadingHtml).addClass(this.config.loadingClass);
        },

        /**
         * Hide loading indicator
         * @param {string|Element} target Target element or selector
         */
        hideLoading: function(target) {
            if (!target) return;

            const $target = $(target);
            if ($target.length === 0) return;

            // Restore original content if saved
            const originalContent = $target.data('wecoza-original-content');
            if (originalContent !== undefined) {
                $target.html(originalContent).removeData('wecoza-original-content');
            }

            $target.removeClass(this.config.loadingClass);
        },

        /**
         * Cancel a request by ID
         * @param {string} requestId Request ID
         */
        cancel: function(requestId) {
            const xhr = this.activeRequests.get(requestId);
            if (xhr) {
                xhr.abort();
                this.activeRequests.delete(requestId);
            }
        },

        /**
         * Cancel all active requests
         */
        cancelAll: function() {
            this.activeRequests.forEach((xhr, id) => {
                xhr.abort();
            });
            this.activeRequests.clear();
        },

        /**
         * Convenience method for form submission
         * @param {string} action WordPress AJAX action
         * @param {HTMLFormElement|string} form Form element or selector
         * @param {Object} options Request options
         * @returns {Promise}
         */
        submitForm: function(action, form, options = {}) {
            const $form = $(form);
            if ($form.length === 0) {
                return Promise.reject(new Error('Form not found'));
            }

            const formData = new FormData($form[0]);
            return this.post(action, formData, options);
        },

        /**
         * Batch multiple requests
         * @param {Array} requests Array of { action, data, options } objects
         * @returns {Promise<Array>}
         */
        batch: function(requests) {
            return Promise.all(
                requests.map(req => this.post(req.action, req.data, req.options))
            );
        },

        /**
         * Show success toast/notification
         * @param {string} message Success message
         * @param {Object} options Toast options
         */
        showSuccess: function(message, options = {}) {
            this.showNotification('success', message, options);
        },

        /**
         * Show error toast/notification
         * @param {string} message Error message
         * @param {Object} options Toast options
         */
        showError: function(message, options = {}) {
            this.showNotification('danger', message, options);
        },

        /**
         * Show toast notification
         * @param {string} type Bootstrap alert type (success, danger, warning, info)
         * @param {string} message Message text
         * @param {Object} options Options
         */
        showNotification: function(type, message, options = {}) {
            const defaults = {
                duration: 5000,
                container: '#wecoza-notifications',
                dismissible: true
            };
            const config = { ...defaults, ...options };

            // Create container if it doesn't exist
            let $container = $(config.container);
            if ($container.length === 0) {
                $container = $('<div id="wecoza-notifications" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>');
                $('body').append($container);
            }

            // Create alert
            const alertId = `alert-${Date.now()}`;
            const dismissBtn = config.dismissible
                ? '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                : '';
            const alertHtml = `
                <div id="${alertId}" class="alert alert-${type} ${config.dismissible ? 'alert-dismissible' : ''} fade show" role="alert">
                    ${message}
                    ${dismissBtn}
                </div>
            `;

            $container.append(alertHtml);

            // Auto-dismiss
            if (config.duration > 0) {
                setTimeout(() => {
                    $(`#${alertId}`).alert('close');
                }, config.duration);
            }
        }
    };

    // Export to global scope
    global.WeCozaAjax = WeCozaAjax;

})(window, jQuery);
