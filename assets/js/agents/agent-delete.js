/**
 * Agent Delete Functionality
 *
 * Handles delete button clicks for agents in the display table with AJAX.
 * Includes confirmation dialog, button loading states, and statistics updates.
 *
 * @package WeCozaCore
 * @since 3.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Handle delete button clicks
     */
    $(document).on('click', 'button[data-agent-id]', function(e) {
        e.preventDefault();

        const button = $(this);
        const agentId = button.data('agent-id');
        const row = button.closest('tr');

        // Only handle delete buttons (with trash icon)
        if (!button.find('.bi-trash').length) {
            return;
        }

        // Show confirmation dialog
        if (!confirm(wecozaAgents.confirmDeleteText)) {
            return;
        }

        // Disable button and show loading state
        button.prop('disabled', true);
        const originalIcon = button.find('i');
        originalIcon.removeClass('bi-trash').addClass('bi-arrow-clockwise');

        // Make AJAX request
        $.ajax({
            url: wecozaAgents.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_agents_delete',
                agent_id: agentId,
                nonce: wecozaAgents.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove row with fade effect
                    row.fadeOut(300, function() {
                        $(this).remove();

                        // Update statistics if they exist
                        updateStatistics();

                        // Show success message
                        showMessage('success', response.data.message || wecozaAgents.deleteSuccessText);
                    });
                } else {
                    // Show error message
                    showMessage('error', response.data.message || wecozaAgents.deleteErrorText);

                    // Re-enable button
                    button.prop('disabled', false);
                    originalIcon.removeClass('bi-arrow-clockwise').addClass('bi-trash');
                }
            },
            error: function() {
                // Show error message
                showMessage('error', wecozaAgents.deleteErrorText);

                // Re-enable button
                button.prop('disabled', false);
                originalIcon.removeClass('bi-arrow-clockwise').addClass('bi-trash');
            }
        });
    });

    /**
     * Update statistics after delete
     */
    function updateStatistics() {
        const statsContainer = $('.agents-statistics');
        if (statsContainer.length === 0) {
            return;
        }

        // Count visible rows (exclude header)
        const visibleRows = $('#agents-display-data tbody tr:visible').length;

        // Update total count
        statsContainer.find('.total-agents .stat-number').text(visibleRows);

        // Recalculate other stats if needed
        let activeCount = 0;
        let inactiveCount = 0;

        $('#agents-display-data tbody tr:visible').each(function() {
            const statusCell = $(this).find('td').eq(6); // Assuming status is in 7th column
            const status = statusCell.text().trim().toLowerCase();

            if (status === 'active') {
                activeCount++;
            } else if (status === 'inactive') {
                inactiveCount++;
            }
        });

        statsContainer.find('.active-agents .stat-number').text(activeCount);
        statsContainer.find('.inactive-agents .stat-number').text(inactiveCount);
    }

    /**
     * Show success/error message
     */
    function showMessage(type, message) {
        // Create message element
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const messageHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show mt-3" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        // Insert message at top of agents container
        const container = $('.agents-container, .wecoza-agents-display').first();
        if (container.length) {
            container.prepend(messageHtml);

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    container.find('.alert-success').fadeOut();
                }, 5000);
            }
        }
    }
});
