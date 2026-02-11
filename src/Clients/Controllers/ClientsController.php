<?php

namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Models\ClientsModel;
use WeCoza\Clients\Models\SitesModel;
use WeCoza\Clients\Repositories\ClientRepository;
use WeCoza\Clients\Helpers\ViewHelpers;

/**
 * Clients Controller for handling client operations
 *
 * @package WeCoza\Clients
 * @since 1.0.0
 */
class ClientsController extends BaseController {

    /**
     * Model instance (lazily loaded)
     *
     * @var ClientsModel|null
     */
    protected $model = null;

    /**
     * Register hooks (required by BaseController)
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Get model instance on-demand
     *
     * @return ClientsModel
     */
    protected function getModel() {
        if ($this->model === null) {
            $this->model = new ClientsModel();
        }

        return $this->model;
    }

    /**
     * Get sites model instance
     *
     * @return SitesModel
     */
    protected function getSitesModel() {
        return $this->getModel()->getSitesModel();
    }


    /**
     * Register shortcodes
     */
    public function registerShortcodes() {
        add_shortcode('wecoza_capture_clients', array($this, 'captureClientShortcode'));
        add_shortcode('wecoza_display_clients', array($this, 'displayClientsShortcode'));
        add_shortcode('wecoza_update_clients', array($this, 'updateClientShortcode'));
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueueAssets() {
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
    public function captureClientShortcode($atts) {
        // Check permissions
        if (!current_user_can('manage_wecoza_clients')) {
            return '<p>' . __('You do not have permission to create clients.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        $client = null;
        $errors = array();
        $success = false;
        $submittedClient = array();
        $submittedSite = array();

        // Get client data if editing
        if ($atts['id']) {
            $client = $this->getModel()->getById($atts['id']);
            if (!$client) {
                return '<p>' . __('Client not found.', 'wecoza-core') . '</p>';
            }
        }

        // Handle form submission (non-AJAX fallback)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
                $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->handleFormSubmission($atts['id']);
                if ($result['success']) {
                    $success = true;
                    $client = $result['client'];
                } else {
                    $errors = $result['errors'];
                    if (!empty($result['data']['client']) && is_array($result['data']['client'])) {
                        $submittedClient = $result['data']['client'];
                    }
                    if (!empty($result['data']['site']) && is_array($result['data']['site'])) {
                        $submittedSite = $result['data']['site'];
                    }
                }
            }
        }

        if (!empty($submittedClient)) {
            $client = array_merge(is_array($client) ? $client : array(), $submittedClient);
        }

        // Get dropdown data
        $config = wecoza_config('clients');
        $seta_options = $config['seta_options'];
        $status_options = $config['client_status_options'];

        $sitesData = array('head' => null, 'sub_sites' => array());
        if (!empty($client['id'])) {
            $sitesData = $this->getSitesModel()->getSitesByClient($client['id']);
        if (!empty($submittedSite)) {
            $sitesData['head'] = array_merge($sitesData['head'] ?? array(), $submittedSite);
            if (!empty($submittedSite['place_id'])) {
                $client['client_town_id'] = $submittedSite['place_id'];
            }
            // Address fields are stored in locations table and retrieved via SitesModel hydration
            // No need to store address fields directly in clients table
            if (!empty($submittedSite['site_name'])) {
                $client['site_name'] = $submittedSite['site_name'];
            }
        }
        }

        $selectedProvince = $client['client_province'] ?? ($client['client_location']['province'] ?? '');
        $selectedTown = $client['client_town'] ?? ($client['client_location']['town'] ?? '');
        $selectedSuburb = $client['client_suburb'] ?? ($client['client_location']['suburb'] ?? '');
        $selectedLocationId = !empty($client['client_town_id']) ? (int) $client['client_town_id'] : null;
        $selectedPostal = $client['client_postal_code'] ?? ($client['client_location']['postal_code'] ?? '');

        $hierarchy = $this->getSitesModel()->getLocationHierarchy();

        $locationData = array(
            'hierarchy' => $hierarchy,
            'selected' => array(
                'province' => $selectedProvince,
                'town' => $selectedTown,
                'suburb' => $selectedSuburb,
                'locationId' => $selectedLocationId,
                'postalCode' => $selectedPostal,
            ),
        );

        // Get main clients for sub-client dropdown
        $main_clients_raw = $this->getModel()->getMainClients();

        // Transform to proper format for select options: id => client_name
        $main_clients = ['' => 'Select Main Client'];
        foreach ($main_clients_raw as $mainClient) {
            if (isset($mainClient['id']) && isset($mainClient['client_name'])) {
                $main_clients[$mainClient['id']] = $mainClient['client_name'];
            }
        }

        // Load view
        return wecoza_view('clients/components/client-capture-form', array(
            'client' => $client,
            'errors' => $errors,
            'success' => $success,
            'seta_options' => $seta_options,
            'status_options' => $status_options,
            'location_data' => $locationData,
            'sites' => $sitesData,
            'main_clients' => $main_clients,
            'main_clients_raw' => $main_clients_raw,
        ), true);
    }

    /**
     * Display clients table shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function displayClientsShortcode($atts) {
        // Check permissions
        if (!current_user_can('view_wecoza_clients')) {
            return '<p>' . __('You do not have permission to view clients.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_search' => true,
            'show_filters' => true,
            'show_export' => true,
            'edit_url' => '/app/all-clients',
            'add_url' => '/app/all-clients',
        ), $atts);

        // Get query parameters
        $page = isset($_GET['client_page']) ? max(1, intval($_GET['client_page'])) : 1;
        $search = isset($_GET['client_search']) ? sanitize_text_field($_GET['client_search']) : '';
        $status = isset($_GET['client_status']) ? sanitize_text_field($_GET['client_status']) : '';
        $seta = isset($_GET['client_seta']) ? sanitize_text_field($_GET['client_seta']) : '';

        // Build query parameters
        $params = array(
            'search' => $search,
            'status' => $status,
            'seta' => $seta,
            'limit' => $atts['per_page'],
            'offset' => ($page - 1) * $atts['per_page'],
        );

        // Get clients
        $clients = $this->getModel()->getAll($params);
        $total = $this->getModel()->count($params);
        $totalPages = ceil($total / $atts['per_page']);

        // Get statistics
        $stats = $this->getModel()->getStatistics();

        // Get filter options
        $config = wecoza_config('clients');
        $seta_options = $config['seta_options'];
        $status_options = $config['client_status_options'];

        // Load view
        return wecoza_view('clients/display/clients-display', array(
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
        ), true);
    }

    /**
     * Client update form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function updateClientShortcode($atts) {
        // Check permissions
        if (!current_user_can('edit_wecoza_clients')) {
            return '<p>' . __('You do not have permission to update clients.', 'wecoza-core') . '</p>';
        }

        // Get client ID from URL parameters
        $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : '';
        $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

        // Validate update mode and client ID
        if ($mode !== 'update' || $clientId <= 0) {
            return '<p>' . __('Invalid update request. Please access via the clients table edit button.', 'wecoza-core') . '</p>';
        }

        $client = null;
        $errors = array();
        $success = false;
        $submittedClient = array();
        $submittedSite = array();

        // Get client data
        $client = $this->getModel()->getById($clientId);
        if (!$client) {
            return '<p>' . __('Client not found.', 'wecoza-core') . '</p>';
        }


        // Filter client data to only include known safe scalar fields
        $client = $this->filterClientDataForForm($client);

        // Handle form submission (non-AJAX fallback)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nonce']) && !wp_doing_ajax()) {
            if (!wp_verify_nonce($_POST['nonce'], 'clients_nonce_action')) {
                $errors[] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->handleFormSubmission($clientId);
                if ($result['success']) {
                    $success = true;
                    $client = $result['client'];
                } else {
                    $errors = $result['errors'];
                    if (!empty($result['data']['client']) && is_array($result['data']['client'])) {
                        $submittedClient = $result['data']['client'];
                    }
                    if (!empty($result['data']['site']) && is_array($result['data']['site'])) {
                        $submittedSite = $result['data']['site'];
                    }
                }
            }
        }

        if (!empty($submittedClient)) {
            $client = array_merge($client, $submittedClient);
        }

        // Apply filtering after merging to ensure no array values slip through
        $client = $this->filterClientDataForForm($client);

        // Get dropdown data
        $config = wecoza_config('clients');
        $seta_options = $config['seta_options'];
        $status_options = $config['client_status_options'];

        $sitesData = array('head' => null, 'sub_sites' => array());
        $sitesData = $this->getSitesModel()->getSitesByClient($client['id']);
        if (!empty($submittedSite)) {
            $sitesData['head'] = array_merge($sitesData['head'] ?? array(), $submittedSite);
            if (!empty($submittedSite['place_id'])) {
                $client['client_town_id'] = $submittedSite['place_id'];
            }
            // Address fields are stored in locations table and retrieved via SitesModel hydration
            // No need to store address fields directly in clients table
            if (!empty($submittedSite['site_name'])) {
                $client['site_name'] = $submittedSite['site_name'];
            }
        }

        $selectedProvince = $client['client_province'] ?? ($client['client_location']['province'] ?? '');
        $selectedTown = $client['client_town'] ?? ($client['client_location']['town'] ?? '');
        $selectedSuburb = $client['client_suburb'] ?? ($client['client_location']['suburb'] ?? '');
        $selectedLocationId = !empty($client['client_town_id']) ? (int) $client['client_town_id'] : null;
        $selectedPostal = $client['client_postal_code'] ?? ($client['client_location']['postal_code'] ?? '');

        $hierarchy = $this->getSitesModel()->getLocationHierarchy();

        $locationData = array(
            'hierarchy' => $hierarchy,
            'selected' => array(
                'province' => $selectedProvince,
                'town' => $selectedTown,
                'suburb' => $selectedSuburb,
                'locationId' => $selectedLocationId,
                'postalCode' => $selectedPostal,
            ),
        );

        // Get main clients for sub-client dropdown
        $main_clients_raw = $this->getModel()->getMainClients();

        // Transform to proper format for select options: id => client_name
        $main_clients = ['' => 'Select Main Client'];
        foreach ($main_clients_raw as $mainClient) {
            if (isset($mainClient['id']) && isset($mainClient['client_name'])) {
                $main_clients[$mainClient['id']] = $mainClient['client_name'];
            }
        }

        // Load view with update-specific flag
        return wecoza_view('clients/components/client-update-form', array(
            'client' => $client,
            'errors' => $errors,
            'success' => $success,
            'seta_options' => $seta_options,
            'status_options' => $status_options,
            'location_data' => $locationData,
            'sites' => $sitesData,
            'main_clients' => $main_clients,
            'is_update_mode' => true,
        ), true);
    }

    /**
     * Handle form submission
     *
     * @param int $clientId Client ID (for updates)
     * @return array
     */
    protected function handleFormSubmission($clientId = 0) {
        $payload = $this->sanitizeFormData($_POST);
        $clientData = $payload['client'];
        $siteData = $payload['site'];
        $communicationType = $clientData['client_status'] ?? '';
        $isNew = ((int) $clientId) <= 0;

        $errors = $this->getModel()->validate($clientData, $clientId);

        // Validate site based on type
        if (!empty($siteData['parent_site_id'])) {
            // This is a sub-site - for new sub-clients, validate parent site belongs to main client
            $expectedClientId = null;
            if ($isNew && !empty($clientData['main_client_id'])) {
                // For new sub-clients, parent site should belong to the main client
                $expectedClientId = $clientData['main_client_id'];
            }
            $siteErrors = $this->getSitesModel()->validateSubSite($clientId, $siteData['parent_site_id'], $siteData, $expectedClientId);
        } else {
            // This is a head site
            $siteErrors = $this->getSitesModel()->validateHeadSite($siteData);
        }

        if ($siteErrors) {
            foreach ($siteErrors as $field => $message) {
                switch ($field) {
                    case 'site_name':
                        $errors['site_name'] = $message;
                        break;
                    case 'place_id':
                        $errors['client_town_id'] = $message;
                        $errors['site_place_id'] = $message;
                        break;
                    default:
                        $errors['site_' . $field] = $message;
                        break;
                }
            }
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'errors' => $errors,
                'data' => array(
                    'client' => $clientData,
                    'site' => $siteData,
                ),
            );
        }

        if (!$isNew) {
            $updated = $this->getModel()->update($clientId, $clientData);
            if (!$updated) {
                return array(
                    'success' => false,
                    'errors' => array('general' => __('Failed to update client. Please try again.', 'wecoza-core')),
                    'data' => array(
                        'client' => $clientData,
                        'site' => $siteData,
                    ),
                );
            }
        } else {
            $clientId = $this->getModel()->create($clientData);
            if (!$clientId) {
                return array(
                    'success' => false,
                    'errors' => array('general' => __('Failed to create client. Please try again.', 'wecoza-core')),
                    'data' => array(
                        'client' => $clientData,
                        'site' => $siteData,
                    ),
                );
            }
        }

        $siteData['site_name_fallback'] = $clientData['client_name'] ?? '';
        if (!empty($siteData['site_id']) && !$this->getSitesModel()->ensureSiteBelongsToClient($siteData['site_id'], $clientId)) {
            return array(
                'success' => false,
                'errors' => array('site_site_id' => __('Selected site does not belong to this client.', 'wecoza-core')),
                'data' => array(
                    'client' => $clientData,
                    'site' => $siteData,
                ),
            );
        }

        // Save site based on type (head site or sub-site)
        if (!empty($siteData['parent_site_id'])) {
            // This is a sub-site
            $saveOptions = array(
                'fallback_to_head_site' => true,
            );
            if (!empty($expectedClientId)) {
                $saveOptions['expected_client_id'] = (int) $expectedClientId;
            }

            $subSiteResult = $this->getSitesModel()->saveSubSite($clientId, $siteData['parent_site_id'], $siteData, $saveOptions);
            if (!$subSiteResult) {
                return array(
                    'success' => false,
                    'errors' => array('general' => __('Failed to save sub-site details. Please try again.', 'wecoza-core')),
                    'data' => array(
                        'client' => $clientData,
                        'site' => $siteData,
                    ),
                );
            }

            if (is_array($subSiteResult)) {
                $siteId = isset($subSiteResult['site_id']) ? (int) $subSiteResult['site_id'] : 0;
                if (($subSiteResult['mode'] ?? '') === 'head') {
                    $siteData['parent_site_id'] = null;
                }
            } else {
                $siteId = (int) $subSiteResult;
            }

            if ($siteId <= 0) {
                return array(
                    'success' => false,
                    'errors' => array('general' => __('Failed to save sub-site details. Please try again.', 'wecoza-core')),
                    'data' => array(
                        'client' => $clientData,
                        'site' => $siteData,
                    ),
                );
            }
        } else {
            // This is a head site
            $siteId = $this->getSitesModel()->saveHeadSite($clientId, $siteData);
            if (!$siteId) {
                return array(
                    'success' => false,
                    'errors' => array('general' => __('Failed to save site details. Please try again.', 'wecoza-core')),
                    'data' => array(
                        'client' => $clientData,
                        'site' => $siteData,
                    ),
                );
            }
        }



        if ($communicationType !== '') {
            $communicationsModel = $this->getModel()->getCommunicationsModel();
            $latestType = $communicationsModel->getLatestCommunicationType($clientId);
            if ($latestType !== $communicationType) {
                $communicationsModel->logCommunication($clientId, $siteId, $communicationType);
            }
        }

        $client = $this->getModel()->getById($clientId);

        return array(
            'success' => true,
            'client' => $client,
            'message' => $isNew ? __('Client created successfully!', 'wecoza-core') : __('Client saved successfully!', 'wecoza-core'),
        );
    }

    /**
     * Sanitize form data
     *
     * @param array $data Raw form data
     * @return array
     */
    protected function sanitizeFormData($data) {
        $client = array();

        $client['client_name'] = isset($data['client_name']) ? sanitize_text_field($data['client_name']) : '';
        $client['company_registration_nr'] = isset($data['company_registration_nr']) ? sanitize_text_field($data['company_registration_nr']) : '';
        $client['seta'] = isset($data['seta']) ? sanitize_text_field($data['seta']) : '';
        $client['client_status'] = isset($data['client_status']) ? sanitize_text_field($data['client_status']) : '';
        $client['financial_year_end'] = isset($data['financial_year_end']) ? sanitize_text_field($data['financial_year_end']) : '';
        $client['bbbee_verification_date'] = isset($data['bbbee_verification_date']) ? sanitize_text_field($data['bbbee_verification_date']) : '';

        // Handle sub-client relationship
        $isSubClient = isset($data['is_sub_client']) && $data['is_sub_client'] === 'on';
        if ($isSubClient && !empty($data['main_client_id'])) {
            $client['main_client_id'] = (int) $data['main_client_id'];
            if ($client['main_client_id'] <= 0) {
                $client['main_client_id'] = null;
            }
        } else {
            $client['main_client_id'] = null;
        }

        // Contact person fields now go directly into client array (consolidated approach)
        $client['contact_person'] = isset($data['contact_person']) ? sanitize_text_field($data['contact_person']) : '';
        $client['contact_person_email'] = isset($data['contact_person_email']) ? sanitize_email($data['contact_person_email']) : '';
        $client['contact_person_cellphone'] = isset($data['contact_person_cellphone']) ? sanitize_text_field($data['contact_person_cellphone']) : '';
        $client['contact_person_tel'] = isset($data['contact_person_tel']) ? sanitize_text_field($data['contact_person_tel']) : '';
        $client['contact_person_position'] = isset($data['contact_person_position']) ? sanitize_text_field($data['contact_person_position']) : '';

        // Only store the place_id reference in the clients table
        // Location data will be retrieved via SitesModel hydration
        $placeId = isset($data['client_town_id']) ? (int) $data['client_town_id'] : 0;
        $client['client_town_id'] = $placeId;

        // Initialize site array with default values
        $site = array(
            'site_id' => isset($data['head_site_id']) ? (int) $data['head_site_id'] : 0,
            'site_name' => isset($data['site_name']) ? sanitize_text_field($data['site_name']) : '',
            'place_id' => $placeId,
        );

        // Handle sub-client relationship - if this is a sub-client, link to main client's site
        if (!empty($client['main_client_id'])) {
            // This is a sub-client, get the main client's head site
            $mainClientSite = $this->getSitesModel()->getHeadSite($client['main_client_id']);
            if ($mainClientSite && !empty($mainClientSite['site_id'])) {
                $site['parent_site_id'] = $mainClientSite['site_id'];
            }
        } else {
            // This is a main client, create a head site
            $site['parent_site_id'] = null;
        }

        if ($placeId > 0) {
            $location = $this->getSitesModel()->getLocationById($placeId);
            // Address data will be retrieved via SitesModel hydration
            // No need to store location fields directly in the clients table
        }

        return array(
            'client' => $client,
            'site' => $site,
        );
    }

    /**
     * Filter and process client data for form rendering
     *
     * @param array $client Raw client data from database
     * @return array Processed client data with safe fields
     */
    protected function filterClientDataForForm($client) {
        if (!is_array($client)) {
            return array();
        }

        $processed = array();

        // First, extract basic scalar fields
        $scalarFields = array(
            'id', 'client_name', 'company_registration_nr', 'seta', 'client_status',
            'financial_year_end', 'bbbee_verification_date', 'main_client_id',
            'client_town_id', // Reference to place_id in locations table
            'created_at', 'updated_at',
            'contact_person', 'contact_person_email', 'contact_person_cellphone',
            'contact_person_tel', 'contact_person_position',
        );

        foreach ($scalarFields as $field) {
            if (array_key_exists($field, $client)) {
                $value = $client[$field];
                if (is_scalar($value) || is_null($value)) {
                    $processed[$field] = $value;
                }
            }
        }

        // Extract site data from head_site array if available
        if (!empty($client['head_site']) && is_array($client['head_site'])) {
            $headSite = $client['head_site'];

            // Map site fields to form fields
            if (!empty($headSite['site_id']) && !isset($processed['site_id'])) {
                $processed['site_id'] = $headSite['site_id'];
            }
            if (!empty($headSite['site_name']) && !isset($processed['site_name'])) {
                $processed['site_name'] = $headSite['site_name'];
            }
            // Address fields removed - now get address from location data via place_id
            if (!empty($headSite['place_id']) && !isset($processed['client_town_id'])) {
                $processed['client_town_id'] = $headSite['place_id'];
            }

            // Location data will be hydrated by SitesModel from locations table
            // No need to store location fields directly in clients table
        }

        // Keep client_location array if it exists and is properly structured
        if (!empty($client['client_location']) && is_array($client['client_location'])) {
            $processed['client_location'] = $client['client_location'];
        }

        // Log what fields were processed vs filtered for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $processedFields = array_keys($processed);
            $allFields = array_keys($client);
            $filteredFields = array_diff($allFields, $processedFields);
            if (!empty($filteredFields)) {
                wecoza_log('Processed fields: ' . implode(', ', $processedFields));
                wecoza_log('Filtered out fields: ' . print_r($filteredFields, true));
            }
        }

        return $processed;
    }
}
