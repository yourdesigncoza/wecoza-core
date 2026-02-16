<?php
declare(strict_types=1);

/**
 * Agents Controller
 *
 * Handles shortcodes, asset enqueuing, and frontend rendering for the Agents module.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Agents\Services\AgentService;
use WeCoza\Agents\Services\WorkingAreasService;
use WeCoza\Agents\Helpers\FormHelpers;
use WeCoza\Agents\Services\AgentDisplayService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agents Controller
 *
 * @since 3.0.0
 */
class AgentsController extends BaseController
{
    /**
     * Service instance (lazily loaded)
     *
     * @var AgentService|null
     */
    private ?AgentService $agentService = null;

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Get service instance on-demand
     *
     * @return AgentService
     */
    protected function getAgentService(): AgentService
    {
        if ($this->agentService === null) {
            $this->agentService = new AgentService();
        }
        return $this->agentService;
    }

    /**
     * Register shortcodes
     *
     * @return void
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_capture_agents', [$this, 'renderCaptureForm']);
        add_shortcode('wecoza_display_agents', [$this, 'renderAgentsList']);
        add_shortcode('wecoza_single_agent', [$this, 'renderSingleAgent']);
    }

    /**
     * Render agent capture/edit form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderCaptureForm(array|string $atts = []): string
    {
        // Check permissions - editors and above
        if (!current_user_can('edit_others_posts')) {
            return '<div class="alert alert-subtle-danger">' . __('You do not have permission to manage agents.', 'wecoza-core') . '</div>';
        }

        // Parse attributes
        $atts = shortcode_atts([
            'mode' => 'add',
            'agent_id' => 0,
            'redirect_after_save' => '',
        ], $atts);

        // Detect agent ID from URL parameters
        $agent_id = $this->detectAgentIdFromUrl($atts);
        $mode = $this->determineFormMode($agent_id, $atts);

        $agent = null;
        $errors = [];
        $current_agent = null;

        // Load agent data if editing
        if ($mode === 'edit' && $agent_id > 0) {
            $current_agent = $this->getAgentService()->getAgent($agent_id);
            if (!$current_agent) {
                return '<div class="alert alert-subtle-danger">' . sprintf(__('Agent with ID %d not found.', 'wecoza-core'), $agent_id) . '</div>';
            }
            $agent = $current_agent;
        }

        // Handle form submission (non-AJAX fallback) via service
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_agents_form_nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['wecoza_agents_form_nonce'], 'submit_agent_form')) {
                $errors['general'] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->getAgentService()->handleAgentFormSubmission(
                    $_POST, $_FILES, $agent_id > 0 ? $agent_id : null, $current_agent
                );

                if ($result['success']) {
                    // Show success message or redirect
                    if (!empty($atts['redirect_after_save'])) {
                        wp_safe_redirect($atts['redirect_after_save']);
                        exit;
                    }
                    $agent = $result['agent'];
                    $current_agent = $agent;
                } else {
                    $errors = $result['errors'];
                    $agent = $result['submitted_data'] ?? $agent;
                }
            }
        }

        // Get working areas for dropdown
        $working_areas = WorkingAreasService::get_working_areas();

        // Render view
        return $this->render('agents/components/agent-capture-form', [
            'agent' => $agent,
            'errors' => $errors,
            'mode' => $mode,
            'atts' => $atts,
            'working_areas' => $working_areas,
        ], true);
    }

    /**
     * Render agents list table shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderAgentsList(array|string $atts = []): string
    {
        // Parse attributes
        $atts = shortcode_atts([
            'per_page' => 10,
            'show_search' => true,
            'show_filters' => true,
            'show_pagination' => true,
            'show_actions' => true,
            'columns' => '',
        ], $atts);

        // Get query parameters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : (int) $atts['per_page'];
        $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $sort_column = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
        $sort_order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';

        // Validate sort order
        if (!in_array($sort_order, ['ASC', 'DESC'])) {
            $sort_order = 'ASC';
        }

        // Get pagination data from service
        $paginationData = $this->getAgentService()->getPaginatedAgents(
            $current_page, $per_page, $search_query, $sort_column, $sort_order
        );

        // Determine display columns
        $columns = AgentDisplayService::getDisplayColumns($atts['columns']);

        // Render view
        return $this->render('agents/display/agent-display-table', [
            'agents' => $paginationData['agents'],
            'total_agents' => $paginationData['total_agents'],
            'current_page' => $current_page,
            'per_page' => $per_page,
            'total_pages' => $paginationData['total_pages'],
            'start_index' => $paginationData['start_index'],
            'end_index' => $paginationData['end_index'],
            'search_query' => $search_query,
            'sort_column' => AgentDisplayService::mapSortColumn($sort_column),
            'sort_order' => $sort_order,
            'columns' => $columns,
            'atts' => $atts,
            'can_manage' => current_user_can('edit_others_posts'),
            'statistics' => $paginationData['statistics'],
        ], true);
    }

    /**
     * Render single agent display shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function renderSingleAgent(array|string $atts = []): string
    {
        // Parse attributes
        $atts = shortcode_atts([
            'agent_id' => 0,
        ], $atts);

        // Get agent ID from shortcode or URL
        $agent_id = $atts['agent_id'];
        if (empty($agent_id)) {
            $agent_id = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
        }
        $agent_id = intval($agent_id);

        $agent = false;
        $error = false;

        // Validate agent ID
        if ($agent_id <= 0) {
            $error = __('Invalid agent ID provided.', 'wecoza-core');
        } else {
            // Load agent data via service
            $agent_data = $this->getAgentService()->getAgent($agent_id);
            if ($agent_data) {
                // Transform database fields to form fields
                $agent = FormHelpers::map_database_to_form($agent_data);
            } else {
                $error = __('Agent not found.', 'wecoza-core');
            }
        }

        // Render view
        return $this->render('agents/display/agent-single-display', [
            'agent_id' => $agent_id,
            'agent' => $agent,
            'error' => $error,
            'loading' => false,
            'back_url' => $this->getBackUrl(),
            'edit_url' => $agent ? $this->getEditUrl($agent_id) : '',
            'can_manage' => current_user_can('edit_others_posts'),
            'date_format' => get_option('date_format'),
        ], true);
    }

    /**
     * Enqueue assets conditionally
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        // Only enqueue if shortcode is present
        if (!$this->shouldEnqueueAssets()) {
            return;
        }

        // Enqueue Google Maps API if key is available
        $google_maps_api_key = get_option('wecoza_google_maps_api_key');
        if ($google_maps_api_key) {
            wp_enqueue_script(
                'google-maps-api',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_maps_api_key) . '&libraries=places&loading=async&v=weekly',
                [],
                WECOZA_CORE_VERSION,
                true
            );
        }

        // Enqueue agents app (base script)
        wp_enqueue_script(
            'wecoza-agents-app',
            WECOZA_CORE_URL . 'assets/js/agents/agents-app.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue agent form validation
        wp_enqueue_script(
            'wecoza-agent-form-validation',
            WECOZA_CORE_URL . 'assets/js/agents/agent-form-validation.js',
            ['jquery', 'google-maps-api'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue AJAX pagination
        wp_enqueue_script(
            'wecoza-agents-ajax-pagination',
            WECOZA_CORE_URL . 'assets/js/agents/agents-ajax-pagination.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue table search
        wp_enqueue_script(
            'wecoza-agents-table-search',
            WECOZA_CORE_URL . 'assets/js/agents/agents-table-search.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Enqueue delete functionality
        wp_enqueue_script(
            'wecoza-agents-delete',
            WECOZA_CORE_URL . 'assets/js/agents/agent-delete.js',
            ['jquery', 'wecoza-agents-app'],
            WECOZA_CORE_VERSION,
            true
        );

        // Unified localization object (Bug #3 fix)
        wp_localize_script('wecoza-agents-app', 'wecozaAgents', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('agents_nonce_action'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'loadingText' => __('Loading...', 'wecoza-core'),
            'errorText' => __('Error loading agents. Please try again.', 'wecoza-core'),
            'confirmDeleteText' => __('Are you sure you want to delete this agent? This action cannot be undone.', 'wecoza-core'),
            'deleteSuccessText' => __('Agent deleted successfully.', 'wecoza-core'),
            'deleteErrorText' => __('Error deleting agent. Please try again.', 'wecoza-core'),
            'urls' => [
                'displayAgents' => home_url('/app/all-agents/'),
                'viewAgent' => home_url('/app/agent-view/'),
                'captureAgent' => home_url('/new-agents/'),
            ],
        ]);
    }

    /**
     * Check if assets should be enqueued
     *
     * @return bool
     */
    protected function shouldEnqueueAssets(): bool
    {
        global $post;
        if (!$post) {
            return false;
        }

        $shortcodes = ['wecoza_capture_agents', 'wecoza_display_agents', 'wecoza_single_agent'];
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | URL Helper Methods (Presentation/Routing)
    |--------------------------------------------------------------------------
    */

    /**
     * Get edit URL for agent
     *
     * @param int $agent_id Agent ID
     * @return string Edit URL
     */
    private function getEditUrl(int $agent_id): string
    {
        return add_query_arg([
            'update' => '',
            'agent_id' => $agent_id
        ], home_url('/new-agents/'));
    }

    /**
     * Get view URL for agent
     *
     * @param int $agent_id Agent ID
     * @return string View URL
     */
    private function getViewUrl(int $agent_id): string
    {
        return add_query_arg('agent_id', $agent_id, home_url('/app/agent-view/'));
    }

    /**
     * Get back URL to agents list
     *
     * @return string Back URL
     */
    private function getBackUrl(): string
    {
        return home_url('/app/all-agents/');
    }

    /**
     * Detect agent ID from URL parameters
     *
     * @param array $atts Shortcode attributes
     * @return int Agent ID or 0
     */
    private function detectAgentIdFromUrl(array $atts): int
    {
        // Method 1: Check for "update" parameter with agent_id
        if (isset($_GET['update']) && isset($_GET['agent_id'])) {
            return absint($_GET['agent_id']);
        }

        // Method 2: Direct agent_id parameter
        if (isset($_GET['agent_id'])) {
            return absint($_GET['agent_id']);
        }

        // Method 3: Check shortcode attributes
        if (!empty($atts['agent_id'])) {
            return absint($atts['agent_id']);
        }

        return 0;
    }

    /**
     * Determine form mode based on agent ID
     *
     * @param int $agent_id Agent ID
     * @param array $atts Shortcode attributes
     * @return string Form mode ('add' or 'edit')
     */
    private function determineFormMode(int $agent_id, array $atts): string
    {
        if ($agent_id > 0) {
            return 'edit';
        }

        return !empty($atts['mode']) ? $atts['mode'] : 'add';
    }
}
