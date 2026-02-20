<?php
declare(strict_types=1);

namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Services\ClientService;
use WeCoza\Clients\Helpers\ViewHelpers;

/**
 * Clients Controller for handling client operations
 *
 * @package WeCoza\Clients
 * @since 1.0.0
 */
class ClientsController extends BaseController {

    /**
     * Service instance (lazily loaded)
     *
     * @var ClientService|null
     */
    private ?ClientService $clientService = null;

    /**
     * Register hooks (required by BaseController)
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Get service instance on-demand
     *
     * @return ClientService
     */
    protected function getClientService(): ClientService
    {
        if ($this->clientService === null) {
            $this->clientService = new ClientService();
        }

        return $this->clientService;
    }


    /**
     * Register shortcodes
     */
    public function registerShortcodes(): void {
        add_shortcode('wecoza_capture_clients', array($this, 'captureClientShortcode'));
        add_shortcode('wecoza_display_clients', array($this, 'displayClientsShortcode'));
        add_shortcode('wecoza_update_clients', array($this, 'updateClientShortcode'));
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueueAssets(): void {
        global $post;

        // Check if we're on a page with our shortcodes
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_capture_form = has_shortcode($post->post_content, 'wecoza_capture_clients');
        $has_update_form = has_shortcode($post->post_content, 'wecoza_update_clients');
        $has_display_table = has_shortcode($post->post_content, 'wecoza_display_clients');
        $nonce = wp_create_nonce('clients_nonce_action');

        // Enqueue scripts based on shortcode presence
        if ($has_capture_form || $has_update_form) {
            wp_enqueue_script(
                'wecoza-client-capture',
                wecoza_js_url('clients/client-capture.js'),
                array('jquery'),
                WECOZA_CORE_VERSION,
                true
            );

            // Localize script
            wp_localize_script(
                'wecoza-client-capture',
                'wecozaClients',
                $this->getLocalizationPayload($nonce, array(
                    'locations' => array(
                        'hierarchy' => array(),
                        'lazyLoad' => true,
                    ),
                ))
            );
        }

        if ($has_display_table) {
            wp_enqueue_script(
                'wecoza-clients-table',
                wecoza_js_url('clients/clients-table.js'),
                array('jquery'),
                WECOZA_CORE_VERSION,
                true
            );

            wp_enqueue_script(
                'wecoza-clients-display',
                wecoza_js_url('clients/clients-display.js'),
                array('jquery'),
                WECOZA_CORE_VERSION,
                true
            );

            wp_enqueue_script(
                'wecoza-client-search',
                wecoza_js_url('clients/client-search.js'),
                array('jquery'),
                WECOZA_CORE_VERSION,
                true
            );

            // Localize script
            $localization = $this->getLocalizationPayload($nonce);
            wp_localize_script('wecoza-clients-table', 'wecozaClients', $localization);
        }
    }

    /**
     * Build localization payload for frontend scripts
     *
     * @param string $nonce Nonce value shared across scripts
     * @param array $overrides Additional data to merge
     * @return array
     */
    protected function getLocalizationPayload($nonce, array $overrides = array()) {
        $base = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'actions' => array(
                'save' => 'wecoza_save_client',
                'get' => 'wecoza_get_client',
                'delete' => 'wecoza_delete_client',
                'search' => 'wecoza_search_clients',
                'branches' => 'wecoza_get_branch_clients',
                'export' => 'wecoza_export_clients',
                'locations' => 'wecoza_get_locations',
            ),
            'clear_form_on_success' => true,
            'messages' => array(
                'form' => array(
                    'saving' => __('Saving client...', 'wecoza-core'),
                    'saved' => __('Client saved successfully!', 'wecoza-core'),
                    'error' => __('An error occurred. Please try again.', 'wecoza-core'),
                ),
                'list' => array(
                    'confirmDelete' => __('Are you sure you want to delete this client?', 'wecoza-core'),
                    'deleting' => __('Deleting client...', 'wecoza-core'),
                    'deleted' => __('Client deleted successfully!', 'wecoza-core'),
                    'exporting' => __('Preparing export...', 'wecoza-core'),
                    'error' => __('An error occurred. Please try again.', 'wecoza-core'),
                ),
                'general' => array(
                    'error' => __('Something went wrong. Please try again.', 'wecoza-core'),
                ),
            ),
            'locations' => array(
                'hierarchy' => array(),
            ),
        );

        return array_replace_recursive($base, $overrides);
    }

    /**
     * Client capture form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function captureClientShortcode($atts): string {
        // Check permissions
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to create clients.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts(['id' => 0], $atts);

        $client = null;
        $errors = [];
        $success = false;

        // Get client data if editing
        if ($atts['id']) {
            $client = $this->getClientService()->getClient($atts['id']);
            if (!$client) {
                return '<p>' . __('Client not found.', 'wecoza-core') . '</p>';
            }
        }

        // Handle form submission (non-AJAX fallback)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
                $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->getClientService()->handleClientSubmission($_POST, (int) $atts['id']);
                $success = $result['success'];
                if ($success) {
                    $client = $result['client'];
                } else {
                    $errors = $result['errors'];
                    // Merge submitted data for form re-population
                    if (!empty($result['data']['client'])) {
                        $client = array_merge(is_array($client) ? $client : [], $result['data']['client']);
                    }
                }
            }
        }

        // Get form data (dropdowns, locations, sites, main clients)
        $formData = $this->getClientService()->prepareFormData($client['id'] ?? 0);

        // Add selected location data if we have a client
        if ($client) {
            $formData['location_data']['selected'] = [
                'province' => $client['client_province'] ?? ($client['client_location']['province'] ?? ''),
                'town' => $client['client_town'] ?? ($client['client_location']['town'] ?? ''),
                'suburb' => $client['client_suburb'] ?? ($client['client_location']['suburb'] ?? ''),
                'locationId' => !empty($client['client_town_id']) ? (int) $client['client_town_id'] : null,
                'postalCode' => $client['client_postal_code'] ?? ($client['client_location']['postal_code'] ?? ''),
            ];
        }

        return wecoza_view('clients/components/client-capture-form', array_merge(
            ['client' => $client, 'errors' => $errors, 'success' => $success],
            $formData
        ), true);
    }

    /**
     * Display clients table shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function displayClientsShortcode($atts): string {
        // Check permissions
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to view clients.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts([
            'per_page' => AppConstants::SEARCH_RESULT_LIMIT,
            'show_search' => true,
            'show_filters' => true,
            'show_export' => true,
            'edit_url' => '/app/all-clients',
            'add_url' => '/app/all-clients',
        ], $atts);

        // Get query parameters
        $page = isset($_GET['client_page']) ? max(1, intval($_GET['client_page'])) : 1;
        $search = isset($_GET['client_search']) ? sanitize_text_field($_GET['client_search']) : '';
        $status = isset($_GET['client_status']) ? sanitize_text_field($_GET['client_status']) : '';
        $seta = isset($_GET['client_seta']) ? sanitize_text_field($_GET['client_seta']) : '';

        // Build query parameters
        $params = [
            'search' => $search,
            'status' => $status,
            'seta' => $seta,
            'limit' => $atts['per_page'],
            'offset' => ($page - 1) * $atts['per_page'],
        ];

        // Get clients and statistics via service
        $clients = $this->getClientService()->getClients($params);
        $total = $this->getClientService()->getClientCount($params);
        $totalPages = ceil($total / $atts['per_page']);
        $stats = $this->getClientService()->getStatistics();

        // Get filter options
        $config = wecoza_config('clients');
        $seta_options = $config['seta_options'];
        $status_options = $config['client_status_options'];

        // Load view
        return wecoza_view('clients/display/clients-display', [
            'clients' => $clients,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'status' => $status,
            'seta' => $seta,
            'stats' => $stats,
            'seta_options' => $seta_options,
            'status_options' => $status_options,
            'atts' => $atts,
        ], true);
    }

    /**
     * Client update form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function updateClientShortcode($atts): string {
        // Check permissions
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to update clients.', 'wecoza-core') . '</p>';
        }

        // Get client ID from URL parameters
        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
        $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

        // Validate update mode and client ID
        if ($mode !== 'update' || $clientId <= 0) {
            return '<p>' . __('Invalid update request. Please access via the clients table edit button.', 'wecoza-core') . '</p>';
        }

        $errors = [];
        $success = false;

        // Get client data
        $client = $this->getClientService()->getClient($clientId);
        if (!$client) {
            return '<p>' . __('Client not found.', 'wecoza-core') . '</p>';
        }

        // Filter client data to only include known safe scalar fields
        $client = $this->getClientService()->filterClientDataForForm($client);

        // Handle form submission (non-AJAX fallback)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
                $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->getClientService()->handleClientSubmission($_POST, $clientId);
                $success = $result['success'];
                if ($success) {
                    $client = $result['client'];
                } else {
                    $errors = $result['errors'];
                    // Merge submitted data for form re-population
                    if (!empty($result['data']['client'])) {
                        $client = array_merge($client, $result['data']['client']);
                    }
                }
            }
        }

        // Apply filtering after merging to ensure no array values slip through
        $client = $this->getClientService()->filterClientDataForForm($client);

        // Get form data (dropdowns, locations, sites, main clients)
        $formData = $this->getClientService()->prepareFormData($client['id']);

        // Add selected location data
        $formData['location_data']['selected'] = [
            'province' => $client['client_province'] ?? ($client['client_location']['province'] ?? ''),
            'town' => $client['client_town'] ?? ($client['client_location']['town'] ?? ''),
            'suburb' => $client['client_suburb'] ?? ($client['client_location']['suburb'] ?? ''),
            'locationId' => !empty($client['client_town_id']) ? (int) $client['client_town_id'] : null,
            'postalCode' => $client['client_postal_code'] ?? ($client['client_location']['postal_code'] ?? ''),
        ];

        // Load view with update-specific flag
        return wecoza_view('clients/components/client-update-form', array_merge(
            ['client' => $client, 'errors' => $errors, 'success' => $success, 'is_update_mode' => true],
            $formData
        ), true);
    }

}
