<?php
declare(strict_types=1);

/**
 * Agents AJAX Handlers
 *
 * Handles AJAX requests for the Agents module using AjaxSecurity pattern.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Agents\Services\AgentService;
use WeCoza\Agents\Services\AgentDisplayService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agents AJAX Handlers
 *
 * @since 3.0.0
 */
class AgentsAjaxHandlers
{
    /**
     * Service instance
     *
     * @var AgentService
     */
    private AgentService $agentService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->agentService = new AgentService();
        $this->registerHandlers();
    }

    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function registerHandlers(): void
    {
        // Bug #10 fix: standardized wecoza_agents_ prefix
        add_action('wp_ajax_wecoza_agents_paginate', [$this, 'handlePagination']);
        add_action('wp_ajax_wecoza_agents_delete', [$this, 'handleDelete']);
        // NO nopriv handlers (Bug #12 fix: entire WP requires login)
    }

    /**
     * Handle AJAX pagination request
     *
     * @return void
     */
    public function handlePagination(): void
    {
        // Verify nonce (Bug #4 fix: use AjaxSecurity)
        AjaxSecurity::requireNonce('agents_nonce_action');

        try {
            // Get request parameters
            $page = AjaxSecurity::post('page', 'int', 1);
            $per_page = AjaxSecurity::post('per_page', 'int', 10);
            $search = AjaxSecurity::post('search', 'string', '');
            $orderby = AjaxSecurity::post('orderby', 'string', 'surname');
            $order = strtoupper(AjaxSecurity::post('order', 'string', 'ASC'));

            // Validate sort order
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'ASC';
            }

            // Get pagination data from service
            $data = $this->agentService->getPaginatedAgents($page, $per_page, $search, $orderby, $order);

            // Capture table rows HTML (presentation stays in handler)
            ob_start();
            wecoza_view('agents/display/agent-display-table-rows', [
                'agents' => $data['agents'],
                'columns' => AgentDisplayService::getDisplayColumns(''),
                'can_manage' => current_user_can('edit_others_posts'),
                'show_actions' => true,
            ], false);
            $table_html = ob_get_clean();

            // Capture pagination HTML
            ob_start();
            wecoza_view('agents/display/agent-pagination', [
                'current_page' => $page,
                'total_pages' => $data['total_pages'],
                'per_page' => $per_page,
                'start_index' => $data['start_index'],
                'end_index' => $data['end_index'],
                'total_agents' => $data['total_agents'],
            ], false);
            $pagination_html = ob_get_clean();

            // Generate statistics HTML
            $statistics_html = $this->getStatisticsHtml($data['statistics']);

            // Send success response (Bug #4 fix: use AjaxSecurity)
            AjaxSecurity::sendSuccess([
                'agents' => $data['agents'],
                'total_agents' => $data['total_agents'],
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => $data['total_pages'],
                'start_index' => $data['start_index'],
                'end_index' => $data['end_index'],
                'statistics' => $data['statistics'],
                'table_html' => $table_html,
                'pagination_html' => $pagination_html,
                'statistics_html' => $statistics_html,
            ]);
        } catch (\Throwable $e) {
            wecoza_log('Error loading agents: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError(__('An error occurred while loading agents.', 'wecoza-core'), 500);
        }
    }

    /**
     * Handle AJAX delete request
     *
     * @return void
     */
    public function handleDelete(): void
    {
        // Verify nonce - using same agents_nonce_action for consistency
        AjaxSecurity::requireNonce('agents_nonce_action');

        // Check permissions
        AjaxSecurity::requireCapability('edit_others_posts');

        // Get and validate agent ID
        $agent_id = AjaxSecurity::post('agent_id', 'int', 0);
        if ($agent_id <= 0) {
            AjaxSecurity::sendError(__('Invalid agent ID.', 'wecoza-core'), 400);
        }

        try {
            // Attempt to delete agent (soft delete) via service
            $success = $this->agentService->deleteAgent($agent_id);

            if ($success) {
                AjaxSecurity::sendSuccess([
                    'message' => __('Agent deleted successfully.', 'wecoza-core'),
                    'agent_id' => $agent_id
                ]);
            } else {
                AjaxSecurity::sendError(__('Failed to delete agent. Please try again.', 'wecoza-core'), 500);
            }
        } catch (\Exception $e) {
            wecoza_log('Error deleting agent: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError(__('An error occurred while deleting the agent.', 'wecoza-core'), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get statistics HTML
     *
     * @param array $statistics Statistics data
     * @return string HTML
     */
    private function getStatisticsHtml(array $statistics): string
    {
        ob_start();
        $stat_keys = array_keys($statistics);
        $last_key = end($stat_keys);
        foreach ($statistics as $stat_key => $stat_data) {
            $border_class = $stat_key === 'total_agents' ? 'border-end pe-4' : ($stat_key === $last_key ? 'ps-4' : 'px-4 border-end');
            ?>
            <div class="col-auto <?php echo esc_attr($border_class); ?>">
                <h6 class="text-body-tertiary">
                    <?php echo esc_html($stat_data['label']); ?> : <?php echo esc_html($stat_data['count']); ?>
                    <?php if (!empty($stat_data['badge'])) : ?>
                        <div class="badge badge-phoenix fs-10 badge-phoenix-<?php echo esc_attr($stat_data['badge_type']); ?>">
                            <?php echo esc_html($stat_data['badge']); ?>
                        </div>
                    <?php endif; ?>
                </h6>
            </div>
            <?php
        }
        return ob_get_clean();
    }

}
