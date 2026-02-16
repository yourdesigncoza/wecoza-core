<?php
declare(strict_types=1);

namespace WeCoza\Clients\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Clients\Repositories\ClientRepository;
use WeCoza\Clients\Repositories\LocationRepository;
use WeCoza\Clients\Models\ClientsModel;
use WeCoza\Clients\Models\SitesModel;

/**
 * AJAX Handlers for Clients Management
 *
 * @package WeCoza\Clients\Ajax
 * @since 1.0.0
 */
class ClientAjaxHandlers {

    private ClientRepository $clientRepository;
    private LocationRepository $locationRepository;

    public function __construct()
    {
        $this->clientRepository = new ClientRepository();
        $this->locationRepository = new LocationRepository();
        $this->registerHandlers();
    }

    protected function registerHandlers()
    {
        // Client AJAX handlers
        add_action('wp_ajax_wecoza_save_client', array($this, 'saveClient'));
        add_action('wp_ajax_wecoza_get_client', array($this, 'getClient'));
        add_action('wp_ajax_wecoza_get_client_details', array($this, 'getClientDetails'));
        add_action('wp_ajax_wecoza_delete_client', array($this, 'deleteClient'));
        add_action('wp_ajax_wecoza_search_clients', array($this, 'searchClients'));
        add_action('wp_ajax_wecoza_get_branch_clients', array($this, 'getBranchClients'));
        add_action('wp_ajax_wecoza_export_clients', array($this, 'exportClients'));

        // Location AJAX handlers
        add_action('wp_ajax_wecoza_get_locations', array($this, 'getLocations'));
        add_action('wp_ajax_wecoza_check_location_duplicates', array($this, 'checkLocationDuplicates'));
    }

    /**
     * AJAX handler to save a client
     */
    public function saveClient() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('manage_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $clientId = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // Use ClientsModel for form handling
        $model = new ClientsModel();
        $sitesModel = $model->getSitesModel();

        // Build payload from POST data
        $payload = $this->sanitizeClientFormData($_POST);
        $clientData = $payload['client'];
        $siteData = $payload['site'];
        $communicationType = $clientData['client_status'] ?? '';
        $isNew = ((int) $clientId) <= 0;

        $errors = $model->validate($clientData, $clientId);

        // Validate site based on type
        if (!empty($siteData['parent_site_id'])) {
            $expectedClientId = null;
            if ($isNew && !empty($clientData['main_client_id'])) {
                $expectedClientId = $clientData['main_client_id'];
            }
            $siteErrors = $sitesModel->validateSubSite($clientId, $siteData['parent_site_id'], $siteData, $expectedClientId);
        } else {
            $siteErrors = $sitesModel->validateHeadSite($siteData);
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
            AjaxSecurity::sendError('Validation errors', array('errors' => $errors));
        }

        if (!$isNew) {
            $updated = $model->update($clientId, $clientData);
            if (!$updated) {
                AjaxSecurity::sendError('Failed to update client. Please try again.');
            }
        } else {
            $clientId = $model->create($clientData);
            if (!$clientId) {
                AjaxSecurity::sendError('Failed to create client. Please try again.');
            }
        }

        $siteData['site_name_fallback'] = $clientData['client_name'] ?? '';
        if (!empty($siteData['site_id']) && !$sitesModel->ensureSiteBelongsToClient($siteData['site_id'], $clientId)) {
            AjaxSecurity::sendError('Selected site does not belong to this client.');
        }

        // Save site based on type
        if (!empty($siteData['parent_site_id'])) {
            $saveOptions = array('fallback_to_head_site' => true);
            if (!empty($expectedClientId)) {
                $saveOptions['expected_client_id'] = (int) $expectedClientId;
            }

            $subSiteResult = $sitesModel->saveSubSite($clientId, $siteData['parent_site_id'], $siteData, $saveOptions);
            if (!$subSiteResult) {
                AjaxSecurity::sendError('Failed to save sub-site details. Please try again.');
            }

            if (is_array($subSiteResult)) {
                $siteId = isset($subSiteResult['site_id']) ? (int) $subSiteResult['site_id'] : 0;
            } else {
                $siteId = (int) $subSiteResult;
            }

            if ($siteId <= 0) {
                AjaxSecurity::sendError('Failed to save sub-site details. Please try again.');
            }
        } else {
            $siteId = $sitesModel->saveHeadSite($clientId, $siteData);
            if (!$siteId) {
                AjaxSecurity::sendError('Failed to save site details. Please try again.');
            }
        }

        if ($communicationType !== '') {
            $communicationsModel = $model->getCommunicationsModel();
            $latestType = $communicationsModel->getLatestCommunicationType($clientId);
            if ($latestType !== $communicationType) {
                $communicationsModel->logCommunication($clientId, $siteId, $communicationType);
            }
        }

        $client = $model->getById($clientId);

        AjaxSecurity::sendSuccess(array(
            'client' => $client,
            'message' => $isNew ? 'Client created successfully!' : 'Client saved successfully!',
        ));
    }

    /**
     * AJAX handler to get a client
     */
    public function getClient() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.');
        }

        $model = new ClientsModel();
        $client = $model->getById($clientId);

        if (!$client) {
            AjaxSecurity::sendError('Client not found.');
        }

        AjaxSecurity::sendSuccess(array('client' => $client));
    }

    /**
     * AJAX handler to get client details for modal
     */
    public function getClientDetails() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.');
        }

        $model = new ClientsModel();
        $client = $model->getById($clientId);

        if (!$client) {
            AjaxSecurity::sendError('Client not found.');
        }

        // Build edit URL
        $editUrl = site_url('/client-management', is_ssl() ? 'https' : 'http');
        $editUrl = add_query_arg(['mode' => 'update', 'id' => $clientId], $editUrl);

        // site_name already hydrated by getById() -> hydrateClients()
        $data = array_merge($client, array(
            'site_name' => $client['site_name'] ?? '',
            'edit_url' => $editUrl,
        ));

        // Get main client name if applicable
        if (!empty($client['main_client_id'])) {
            $mainClient = $model->getById($client['main_client_id']);
            $data['main_client_name'] = $mainClient['client_name'] ?? '';
        }

        AjaxSecurity::sendSuccess($data);
    }

    /**
     * AJAX handler to delete a client
     */
    public function deleteClient() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('manage_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $clientId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.');
        }

        $model = new ClientsModel();
        $success = $model->delete($clientId);

        if ($success) {
            AjaxSecurity::sendSuccess('Client deleted successfully.');
        } else {
            AjaxSecurity::sendError('Failed to delete client.');
        }
    }

    /**
     * AJAX handler to search clients
     */
    public function searchClients() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

        $model = new ClientsModel();
        $clients = $model->getAll(array(
            'search' => $search,
            'limit' => $limit,
        ));

        AjaxSecurity::sendSuccess(array('clients' => $clients));
    }

    /**
     * AJAX handler to get branch clients (sites)
     */
    public function getBranchClients() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.');
        }

        $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.');
        }

        $sitesModel = new SitesModel();
        $sites = $sitesModel->getSitesByClient($clientId);

        AjaxSecurity::sendSuccess(array('sites' => $sites));
    }

    /**
     * AJAX handler to export clients
     */
    public function exportClients() {
        // Check nonce manually for export
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'clients_nonce_action')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('export_wecoza_clients')) {
            wp_die('Permission denied.');
        }

        $model = new ClientsModel();
        $clients = $model->getAll();

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clients-export-' . wp_date('Y-m-d') . '.csv');

        // Create output
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add headers
        $headers = array(
            'ID',
            'Client Name',
            'Company Registration Nr',
            'Contact Person',
            'Email',
            'Cellphone',
            'Town',
            'Status',
            'SETA',
            'Created Date',
        );
        fputcsv($output, $headers);

        // Add data
        foreach ($clients as $client) {
            $row = array(
                $client['id'],
                $client['client_name'],
                $client['company_registration_nr'],
                $client['contact_person'],
                $client['contact_person_email'],
                $client['contact_person_cellphone'],
                $client['client_town'],
                $client['client_status'],
                $client['seta'],
                $client['created_at'],
            );
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX handler to fetch locations lazily
     */
    public function getLocations() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        $sitesModel = new SitesModel();
        $hierarchy = $sitesModel->getLocationHierarchy();

        if (!is_array($hierarchy)) {
            AjaxSecurity::sendError('Unable to load locations right now. Please try again shortly.', 500);
        }

        AjaxSecurity::sendSuccess(array('hierarchy' => $hierarchy));
    }

    /**
     * AJAX handler to check location duplicates
     */
    public function checkLocationDuplicates() {
        // Custom nonce check for locations
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'submit_locations_form')) {
            AjaxSecurity::sendError('Security check failed. Please reload the page and try again.', 403);
        }

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('You do not have permission to perform this action.', 403);
        }

        $streetAddress = isset($_POST['street_address']) ? sanitize_text_field(wp_unslash($_POST['street_address'])) : '';
        $suburb = isset($_POST['suburb']) ? sanitize_text_field(wp_unslash($_POST['suburb'])) : '';
        $town = isset($_POST['town']) ? sanitize_text_field(wp_unslash($_POST['town'])) : '';

        if ($streetAddress === '' && $suburb === '' && $town === '') {
            AjaxSecurity::sendError('Please provide a street address, suburb, or town before checking for duplicates.', 400);
        }

        $locationsModel = new \WeCoza\Clients\Models\LocationsModel();
        $duplicates = $locationsModel->checkDuplicates($streetAddress, $suburb, $town);

        AjaxSecurity::sendSuccess(array('duplicates' => $duplicates));
    }

    /**
     * Sanitize client form data
     *
     * @param array $data Raw form data
     * @return array
     */
    protected function sanitizeClientFormData($data) {
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

        // Contact person fields
        $client['contact_person'] = isset($data['contact_person']) ? sanitize_text_field($data['contact_person']) : '';
        $client['contact_person_email'] = isset($data['contact_person_email']) ? sanitize_email($data['contact_person_email']) : '';
        $client['contact_person_cellphone'] = isset($data['contact_person_cellphone']) ? sanitize_text_field($data['contact_person_cellphone']) : '';
        $client['contact_person_tel'] = isset($data['contact_person_tel']) ? sanitize_text_field($data['contact_person_tel']) : '';
        $client['contact_person_position'] = isset($data['contact_person_position']) ? sanitize_text_field($data['contact_person_position']) : '';

        // Place ID reference
        $placeId = isset($data['client_town_id']) ? (int) $data['client_town_id'] : 0;
        $client['client_town_id'] = $placeId;

        // Initialize site array
        $site = array(
            'site_id' => isset($data['head_site_id']) ? (int) $data['head_site_id'] : 0,
            'site_name' => isset($data['site_name']) ? sanitize_text_field($data['site_name']) : '',
            'place_id' => $placeId,
        );

        // Handle sub-client relationship for site
        if (!empty($client['main_client_id'])) {
            $sitesModel = new SitesModel();
            $mainClientSite = $sitesModel->getHeadSite($client['main_client_id']);
            if ($mainClientSite && !empty($mainClientSite['site_id'])) {
                $site['parent_site_id'] = $mainClientSite['site_id'];
            }
        } else {
            $site['parent_site_id'] = null;
        }

        return array(
            'client' => $client,
            'site' => $site,
        );
    }
}
