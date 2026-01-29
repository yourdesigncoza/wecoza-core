/**
 * WeCoza Classes Plugin - Admin JavaScript
 *
 * JavaScript functionality for the admin area of the WeCoza Classes Plugin
 *
 * @package WeCozaClasses
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        // Initialize database connection test
        initDatabaseTest();
        
        // Initialize settings form
        initSettingsForm();
        
        // Initialize plugin info refresh
        initPluginInfoRefresh();
    }

    /**
     * Initialize database connection test
     */
    function initDatabaseTest() {
        const $testButton = $('#test-database-connection');
        const $statusContainer = $('#database-status');

        $testButton.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.text();

            // Show loading state
            $button.text('Testing...').prop('disabled', true);

            // Make AJAX request to test database connection
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wecoza_classes_test_database',
                    nonce: wecozaClassesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showDatabaseStatus(response.data, 'success');
                    } else {
                        showDatabaseStatus(response.data, 'error');
                    }
                },
                error: function() {
                    showDatabaseStatus('Failed to test database connection', 'error');
                },
                complete: function() {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        /**
         * Show database status
         */
        function showDatabaseStatus(data, type) {
            const statusClass = type === 'success' ? 'notice-success' : 'notice-error';
            let statusHtml = `<div class="notice ${statusClass} is-dismissible"><p>`;

            if (typeof data === 'string') {
                statusHtml += data;
            } else if (data && typeof data === 'object') {
                statusHtml += 'Database Connection Status:<br>';
                for (const [key, value] of Object.entries(data)) {
                    statusHtml += `<strong>${key}:</strong> ${value}<br>`;
                }
            }

            statusHtml += '</p></div>';
            $statusContainer.html(statusHtml);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $statusContainer.find('.notice').fadeOut();
            }, 5000);
        }
    }

    /**
     * Initialize settings form
     */
    function initSettingsForm() {
        const $form = $('#wecoza-classes-settings-form');

        if ($form.length === 0) {
            return;
        }

        // Add confirmation for sensitive settings
        $form.on('submit', function(e) {
            const $passwordFields = $form.find('input[type="password"]');
            let hasPasswordChanges = false;

            $passwordFields.each(function() {
                if ($(this).val() !== $(this).data('original-value')) {
                    hasPasswordChanges = true;
                    return false;
                }
            });

            if (hasPasswordChanges) {
                if (!confirm('You are about to change database connection settings. This may affect plugin functionality. Are you sure you want to continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Store original values for comparison
        $form.find('input[type="password"]').each(function() {
            $(this).data('original-value', $(this).val());
        });
    }

    /**
     * Initialize plugin info refresh
     */
    function initPluginInfoRefresh() {
        const $refreshButton = $('#refresh-plugin-info');
        const $infoContainer = $('#plugin-info-container');

        $refreshButton.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.text();

            // Show loading state
            $button.text('Refreshing...').prop('disabled', true);

            // Make AJAX request to refresh plugin info
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wecoza_classes_refresh_info',
                    nonce: wecozaClassesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $infoContainer.html(response.data);
                        showNotice('Plugin information refreshed successfully.', 'success');
                    } else {
                        showNotice('Failed to refresh plugin information.', 'error');
                    }
                },
                error: function() {
                    showNotice('Failed to refresh plugin information.', 'error');
                },
                complete: function() {
                    // Restore button state
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const noticeHtml = `
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

        // Add notice after the page title
        $('.wrap h1').after(noticeHtml);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);

        // Handle manual dismiss
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut();
        });
    }

    /**
     * Initialize dismissible notices
     */
    function initDismissibleNotices() {
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut();
        });
    }

    /**
     * Initialize tooltips (if available)
     */
    function initTooltips() {
        if (typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }
    }

    /**
     * Initialize confirmation dialogs
     */
    function initConfirmationDialogs() {
        $(document).on('click', '[data-confirm]', function(e) {
            const message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initAdmin();
        initDismissibleNotices();
        initTooltips();
        initConfirmationDialogs();
    });

})(jQuery);
