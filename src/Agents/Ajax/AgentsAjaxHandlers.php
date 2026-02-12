<?php
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
use WeCoza\Agents\Repositories\AgentRepository;
use WeCoza\Agents\Helpers\FormHelpers;

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
     * Repository instance
     *
     * @var AgentRepository
     */
    private AgentRepository $repository;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->repository = new AgentRepository();
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

        // Map frontend column to database column
        $orderby = $this->mapSortColumn($orderby);

        // Build query args
        $args = [
            'status' => 'all',
            'orderby' => $orderby,
            'order' => $order,
            'limit' => max(1, min(100, $per_page)),
            'offset' => (max(1, $page) - 1) * max(1, min(100, $per_page)),
            'search' => $search,
        ];

        // Get agents
        $agents_raw = $this->repository->getAgents($args);
        $agents = [];
        foreach ($agents_raw as $agent) {
            $agents[] = $this->mapAgentFields($agent);
        }

        // Get total count
        $total_agents = $this->repository->countAgents(['status' => 'all', 'search' => $search]);

        // Calculate pagination
        $total_pages = ceil($total_agents / $args['limit']);
        $start_index = ($page - 1) * $args['limit'] + 1;
        $end_index = min($start_index + $args['limit'] - 1, $total_agents);

        // Get statistics
        $statistics = $this->getAgentStatistics();

        // Capture table rows HTML
        ob_start();
        wecoza_view('agents/display/agent-display-table-rows', [
            'agents' => $agents,
            'columns' => $this->getDisplayColumns(''),
            'can_manage' => current_user_can('edit_others_posts'),
            'show_actions' => true,
        ], false);
        $table_html = ob_get_clean();

        // Capture pagination HTML
        ob_start();
        wecoza_view('agents/display/agent-pagination', [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $args['limit'],
            'start_index' => $start_index,
            'end_index' => $end_index,
            'total_agents' => $total_agents,
        ], false);
        $pagination_html = ob_get_clean();

        // Generate statistics HTML
        $statistics_html = $this->getStatisticsHtml($statistics);

        // Send success response (Bug #4 fix: use AjaxSecurity)
        AjaxSecurity::sendSuccess([
            'agents' => $agents,
            'total_agents' => $total_agents,
            'current_page' => $page,
            'per_page' => $args['limit'],
            'total_pages' => $total_pages,
            'start_index' => $start_index,
            'end_index' => $end_index,
            'statistics' => $statistics,
            'table_html' => $table_html,
            'pagination_html' => $pagination_html,
            'statistics_html' => $statistics_html,
        ]);
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
            // Attempt to delete agent (soft delete)
            $success = $this->repository->deleteAgent($agent_id);

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
     * Get agent statistics
     *
     * @return array Statistics data
     */
    private function getAgentStatistics(): array
    {
        try {
            $db = wecoza_db();

            // Get total agents count
            $total_sql = "SELECT COUNT(*) as count FROM agents WHERE status != 'deleted'";
            $total_result = $db->query($total_sql);
            $total_agents = $total_result ? $total_result->fetch()['count'] : 0;

            // Get active agents count
            $active_sql = "SELECT COUNT(*) as count FROM agents WHERE status = 'active'";
            $active_result = $db->query($active_sql);
            $active_agents = $active_result ? $active_result->fetch()['count'] : 0;

            // Get SACE registered count
            $sace_sql = "SELECT COUNT(*) as count FROM agents WHERE sace_number IS NOT NULL AND sace_number != '' AND status != 'deleted'";
            $sace_result = $db->query($sace_sql);
            $sace_registered = $sace_result ? $sace_result->fetch()['count'] : 0;

            // Get quantum qualified count
            $quantum_sql = "SELECT COUNT(*) as count FROM agents WHERE (quantum_maths_score > 0 OR quantum_science_score > 0) AND status != 'deleted'";
            $quantum_result = $db->query($quantum_sql);
            $quantum_qualified = $quantum_result ? $quantum_result->fetch()['count'] : 0;

            return [
                'total_agents' => [
                    'label' => __('Total Agents', 'wecoza-core'),
                    'count' => $total_agents,
                    'badge' => null,
                    'badge_type' => null
                ],
                'active_agents' => [
                    'label' => __('Active Agents', 'wecoza-core'),
                    'count' => $active_agents,
                    'badge' => null,
                    'badge_type' => null
                ],
                'sace_registered' => [
                    'label' => __('SACE Registered', 'wecoza-core'),
                    'count' => $sace_registered,
                    'badge' => null,
                    'badge_type' => null
                ],
                'quantum_qualified' => [
                    'label' => __('Quantum Qualified', 'wecoza-core'),
                    'count' => $quantum_qualified,
                    'badge' => null,
                    'badge_type' => null
                ]
            ];
        } catch (\Exception $e) {
            wecoza_log('Error fetching agent statistics: ' . $e->getMessage(), 'error');

            // Return zeros on error
            return [
                'total_agents' => [
                    'label' => __('Total Agents', 'wecoza-core'),
                    'count' => 0,
                    'badge' => null,
                    'badge_type' => null
                ],
                'active_agents' => [
                    'label' => __('Active Agents', 'wecoza-core'),
                    'count' => 0,
                    'badge' => null,
                    'badge_type' => null
                ],
                'sace_registered' => [
                    'label' => __('SACE Registered', 'wecoza-core'),
                    'count' => 0,
                    'badge' => null,
                    'badge_type' => null
                ],
                'quantum_qualified' => [
                    'label' => __('Quantum Qualified', 'wecoza-core'),
                    'count' => 0,
                    'badge' => null,
                    'badge_type' => null
                ]
            ];
        }
    }

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

    /**
     * Map database agent fields to frontend display fields
     *
     * @param array $agent Agent data from database
     * @return array Mapped agent data
     */
    private function mapAgentFields(array $agent): array
    {
        return [
            'id' => $agent['agent_id'],
            'first_name' => $agent['first_name'],
            'initials' => $agent['initials'] ?? '',
            'last_name' => $agent['surname'],
            'gender' => $agent['gender'] ?? '',
            'race' => $agent['race'] ?? '',
            'phone' => $agent['tel_number'],
            'email' => $agent['email_address'],
            'city' => $agent['city'] ?? '',
            'status' => $agent['status'] ?? 'active',
            'sa_id_no' => $agent['sa_id_no'] ?? '',
            'sace_number' => $agent['sace_number'] ?? '',
            'quantum_maths_score' => intval($agent['quantum_maths_score'] ?? 0),
            'quantum_science_score' => intval($agent['quantum_science_score'] ?? 0),
        ];
    }

    /**
     * Map frontend sort column to database column
     *
     * @param string $column Frontend column name
     * @return string Database column name
     */
    private function mapSortColumn(string $column): string
    {
        $map = [
            'last_name' => 'surname',
            'phone' => 'tel_number',
            'email' => 'email_address',
        ];

        return $map[$column] ?? $column;
    }

    /**
     * Get display columns configuration
     *
     * @param string $columns_setting Columns setting from shortcode
     * @return array Display columns
     */
    private function getDisplayColumns(string $columns_setting): array
    {
        $default_columns = [
            'first_name' => __('First Name', 'wecoza-core'),
            'initials' => __('Initials', 'wecoza-core'),
            'last_name' => __('Surname', 'wecoza-core'),
            'gender' => __('Gender', 'wecoza-core'),
            'race' => __('Race', 'wecoza-core'),
            'phone' => __('Tel Number', 'wecoza-core'),
            'email' => __('Email Address', 'wecoza-core'),
            'city' => __('City/Town', 'wecoza-core'),
        ];

        // If specific columns are requested, filter the default set
        if (!empty($columns_setting)) {
            $requested = array_map('trim', explode(',', $columns_setting));
            $columns = [];

            foreach ($requested as $col) {
                if (isset($default_columns[$col])) {
                    $columns[$col] = $default_columns[$col];
                }
            }

            return !empty($columns) ? $columns : $default_columns;
        }

        return $default_columns;
    }
}
