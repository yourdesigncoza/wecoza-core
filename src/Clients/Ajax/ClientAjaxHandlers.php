<?php
declare(strict_types=1);

namespace WeCoza\Clients\Ajax;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Clients\Services\ClientService;
use WeCoza\Clients\Models\SitesModel;

/**
 * AJAX Handlers for Clients Management
 *
 * @package WeCoza\Clients\Ajax
 * @since 1.0.0
 */
class ClientAjaxHandlers {

    private ClientService $clientService;

    public function __construct()
    {
        $this->clientService = new ClientService();
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
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        try {
            $clientId = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $result = $this->clientService->handleClientSubmission($_POST, $clientId);

            if (!$result['success']) {
                AjaxSecurity::sendError('Validation errors', 400, ['errors' => $result['errors']]);
            }

            AjaxSecurity::sendSuccess([
                'client' => $result['client'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            wecoza_log('Error saving client: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred while saving the client.', 500);
        }
    }

    /**
     * AJAX handler to get a client
     */
    public function getClient() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        $clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.', 400);
        }

        $client = $this->clientService->getClient($clientId);

        if (!$client) {
            AjaxSecurity::sendError('Client not found.', 404);
        }

        AjaxSecurity::sendSuccess(['client' => $client]);
    }

    /**
     * AJAX handler to get client details for modal
     */
    public function getClientDetails() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.', 400);
        }

        $data = $this->clientService->getClientDetails($clientId);

        if (!$data) {
            AjaxSecurity::sendError('Client not found.', 404);
        }

        AjaxSecurity::sendSuccess($data);
    }

    /**
     * AJAX handler to delete a client
     */
    public function deleteClient() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('manage_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        $clientId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.', 400);
        }

        try {
            $success = $this->clientService->deleteClient($clientId);

            if ($success) {
                AjaxSecurity::sendSuccess(['message' => 'Client deleted successfully.']);
            } else {
                AjaxSecurity::sendError('Failed to delete client.', 500);
            }
        } catch (\Throwable $e) {
            wecoza_log('Error deleting client: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('An error occurred while deleting the client.', 500);
        }
    }

    /**
     * AJAX handler to search clients
     */
    public function searchClients() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : AppConstants::SEARCH_RESULT_LIMIT;

        $clients = $this->clientService->searchClients($search, $limit);

        AjaxSecurity::sendSuccess(['clients' => $clients]);
    }

    /**
     * AJAX handler to get branch clients (sites)
     */
    public function getBranchClients() {
        AjaxSecurity::requireNonce('clients_nonce_action');

        if (!current_user_can('view_wecoza_clients')) {
            AjaxSecurity::sendError('Permission denied.', 403);
        }

        $clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID.', 400);
        }

        $sites = $this->clientService->getBranchClients($clientId);

        AjaxSecurity::sendSuccess(['sites' => $sites]);
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

        $csvData = $this->clientService->exportClientsAsCsv();

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clients-export-' . wp_date('Y-m-d') . '.csv');

        // Create output
        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add headers
        fputcsv($output, $csvData['headers']);

        // Add data rows
        foreach ($csvData['rows'] as $row) {
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

        $hierarchy = $this->clientService->getLocationHierarchy();

        if (!is_array($hierarchy)) {
            AjaxSecurity::sendError('Unable to load locations right now. Please try again shortly.', 500);
        }

        AjaxSecurity::sendSuccess(['hierarchy' => $hierarchy]);
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

}
