<?php
declare(strict_types=1);

namespace WeCoza\Clients\Services;

use WeCoza\Clients\Models\ClientsModel;
use WeCoza\Clients\Models\SitesModel;

/**
 * Client Service - Unified business logic for client operations
 *
 * Single source of truth consolidating duplicate logic from
 * ClientsController::handleFormSubmission() and ClientAjaxHandlers::saveClient()
 *
 * @package WeCoza\Clients\Services
 * @since 1.0.0
 */
class ClientService
{
    private ClientsModel $model;

    public function __construct()
    {
        $this->model = new ClientsModel();
    }

    /**
     * Get SitesModel instance
     *
     * @return SitesModel
     */
    private function getSitesModel(): SitesModel
    {
        return $this->model->getSitesModel();
    }

    /**
     * Handle client form submission (create or update)
     *
     * Single source of truth for both controller and AJAX handler.
     * Consolidates ~160 lines from ClientsController::handleFormSubmission()
     * and ~120 lines from ClientAjaxHandlers::saveClient() into one method.
     *
     * @param array $rawData Raw POST data
     * @param int $clientId Client ID (0 for new)
     * @return array ['success' => bool, 'client' => array|null, 'errors' => array, 'message' => string, 'data' => array]
     */
    public function handleClientSubmission(array $rawData, int $clientId = 0): array
    {
        // Sanitize form data
        $payload = $this->sanitizeFormData($rawData);
        $clientData = $payload['client'];
        $siteData = $payload['site'];
        $communicationType = $clientData['client_status'] ?? '';
        $isNew = $clientId <= 0;

        // Validate client data
        $errors = $this->model->validate($clientData, $clientId);

        // Validate site based on type (head site vs sub-site)
        if (!empty($siteData['parent_site_id'])) {
            // Sub-site validation
            $expectedClientId = null;
            if ($isNew && !empty($clientData['main_client_id'])) {
                // For new sub-clients, parent site should belong to the main client
                $expectedClientId = $clientData['main_client_id'];
            }
            $siteErrors = $this->getSitesModel()->validateSubSite(
                $clientId,
                $siteData['parent_site_id'],
                $siteData,
                $expectedClientId
            );
        } else {
            // Head site validation
            $siteErrors = $this->getSitesModel()->validateHeadSite($siteData);
        }

        // Merge site errors into client errors with proper field mapping
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

        // Return early if validation errors
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'data' => [
                    'client' => $clientData,
                    'site' => $siteData,
                ],
                'client' => null,
                'message' => '',
            ];
        }

        // Create or update client
        if (!$isNew) {
            $updated = $this->model->update($clientId, $clientData);
            if (!$updated) {
                return [
                    'success' => false,
                    'errors' => ['general' => __('Failed to update client. Please try again.', 'wecoza-core')],
                    'data' => [
                        'client' => $clientData,
                        'site' => $siteData,
                    ],
                    'client' => null,
                    'message' => '',
                ];
            }
        } else {
            $clientId = $this->model->create($clientData);
            if (!$clientId) {
                return [
                    'success' => false,
                    'errors' => ['general' => __('Failed to create client. Please try again.', 'wecoza-core')],
                    'data' => [
                        'client' => $clientData,
                        'site' => $siteData,
                    ],
                    'client' => null,
                    'message' => '',
                ];
            }
        }

        // Add fallback for site name
        $siteData['site_name_fallback'] = $clientData['client_name'] ?? '';

        // Verify site ownership if site_id is provided
        if (!empty($siteData['site_id']) && !$this->getSitesModel()->ensureSiteBelongsToClient($siteData['site_id'], $clientId)) {
            return [
                'success' => false,
                'errors' => ['site_site_id' => __('Selected site does not belong to this client.', 'wecoza-core')],
                'data' => [
                    'client' => $clientData,
                    'site' => $siteData,
                ],
                'client' => null,
                'message' => '',
            ];
        }

        // Save site based on type (head site or sub-site)
        $siteId = 0;
        if (!empty($siteData['parent_site_id'])) {
            // This is a sub-site
            $saveOptions = [
                'fallback_to_head_site' => true,
            ];
            if (!empty($expectedClientId)) {
                $saveOptions['expected_client_id'] = (int) $expectedClientId;
            }

            $subSiteResult = $this->getSitesModel()->saveSubSite(
                $clientId,
                $siteData['parent_site_id'],
                $siteData,
                $saveOptions
            );

            if (!$subSiteResult) {
                return [
                    'success' => false,
                    'errors' => ['general' => __('Failed to save sub-site details. Please try again.', 'wecoza-core')],
                    'data' => [
                        'client' => $clientData,
                        'site' => $siteData,
                    ],
                    'client' => null,
                    'message' => '',
                ];
            }

            if (is_array($subSiteResult)) {
                $siteId = isset($subSiteResult['site_id']) ? (int) $subSiteResult['site_id'] : 0;
            } else {
                $siteId = (int) $subSiteResult;
            }

            if ($siteId <= 0) {
                return [
                    'success' => false,
                    'errors' => ['general' => __('Failed to save sub-site details. Please try again.', 'wecoza-core')],
                    'data' => [
                        'client' => $clientData,
                        'site' => $siteData,
                    ],
                    'client' => null,
                    'message' => '',
                ];
            }
        } else {
            // This is a head site
            $siteId = $this->getSitesModel()->saveHeadSite($clientId, $siteData);
            if (!$siteId) {
                return [
                    'success' => false,
                    'errors' => ['general' => __('Failed to save site details. Please try again.', 'wecoza-core')],
                    'data' => [
                        'client' => $clientData,
                        'site' => $siteData,
                    ],
                    'client' => null,
                    'message' => '',
                ];
            }
        }

        // Log communication type change if applicable
        if ($communicationType !== '') {
            $communicationsModel = $this->model->getCommunicationsModel();
            $latestType = $communicationsModel->getLatestCommunicationType($clientId);
            if ($latestType !== $communicationType) {
                $communicationsModel->logCommunication($clientId, $siteId, $communicationType);
            }
        }

        // Reload and return client
        $client = $this->model->getById($clientId);

        return [
            'success' => true,
            'client' => $client,
            'errors' => [],
            'message' => $isNew ? __('Client created successfully!', 'wecoza-core') : __('Client saved successfully!', 'wecoza-core'),
            'data' => [],
        ];
    }

    /**
     * Sanitize form data
     *
     * Consolidates ClientsController::sanitizeFormData() and
     * ClientAjaxHandlers::sanitizeClientFormData() into ONE method
     *
     * @param array $data Raw form data
     * @return array ['client' => array, 'site' => array]
     */
    public function sanitizeFormData(array $data): array
    {
        $client = [];

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
        $site = [
            'site_id' => isset($data['head_site_id']) ? (int) $data['head_site_id'] : 0,
            'site_name' => isset($data['site_name']) ? sanitize_text_field($data['site_name']) : '',
            'place_id' => $placeId,
        ];

        // Handle sub-client relationship for site
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

        return [
            'client' => $client,
            'site' => $site,
        ];
    }

    /**
     * Get client by ID
     *
     * @param int $id Client ID
     * @return array|null
     */
    public function getClient(int $id): ?array
    {
        return $this->model->getById($id);
    }

    /**
     * Get all clients with optional filters
     *
     * @param array $params Query parameters
     * @return array
     */
    public function getClients(array $params = []): array
    {
        return $this->model->getAll($params);
    }

    /**
     * Get client count with optional filters
     *
     * @param array $params Query parameters
     * @return int
     */
    public function getClientCount(array $params = []): int
    {
        return $this->model->count($params);
    }

    /**
     * Delete client
     *
     * @param int $id Client ID
     * @return bool
     */
    public function deleteClient(int $id): bool
    {
        return $this->model->delete($id);
    }

    /**
     * Search clients
     *
     * @param string $search Search term
     * @param int $limit Result limit
     * @return array
     */
    public function searchClients(string $search, int $limit = 10): array
    {
        return $this->model->getAll([
            'search' => $search,
            'limit' => $limit,
        ]);
    }

    /**
     * Get client statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->model->getStatistics();
    }

    /**
     * Get main clients (clients without a main_client_id)
     *
     * @return array
     */
    public function getMainClients(): array
    {
        return $this->model->getMainClients();
    }

    /**
     * Get client details for modal
     *
     * @param int $clientId Client ID
     * @return array|null
     */
    public function getClientDetails(int $clientId): ?array
    {
        $client = $this->model->getById($clientId);

        if (!$client) {
            return null;
        }

        // Build edit URL
        $editUrl = site_url('/client-management', is_ssl() ? 'https' : 'http');
        $editUrl = add_query_arg(['mode' => 'update', 'id' => $clientId], $editUrl);

        // site_name already hydrated by getById() -> hydrateClients()
        $data = array_merge($client, [
            'site_name' => $client['site_name'] ?? '',
            'edit_url' => $editUrl,
        ]);

        // Get main client name if applicable
        if (!empty($client['main_client_id'])) {
            $mainClient = $this->model->getById($client['main_client_id']);
            $data['main_client_name'] = $mainClient['client_name'] ?? '';
        }

        return $data;
    }

    /**
     * Prepare form data (dropdowns, locations, sites, main clients)
     *
     * @param int $clientId Client ID (0 for new)
     * @return array
     */
    public function prepareFormData(int $clientId = 0): array
    {
        // Get dropdown data
        $config = wecoza_config('clients');
        $seta_options = $config['seta_options'];
        $status_options = $config['client_status_options'];

        // Get sites data
        $sitesData = ['head' => null, 'sub_sites' => []];
        if ($clientId > 0) {
            $sitesData = $this->getSitesModel()->getSitesByClient($clientId);
        }

        // Get location hierarchy
        $hierarchy = $this->getSitesModel()->getLocationHierarchy();

        // Get main clients for sub-client dropdown
        $main_clients_raw = $this->model->getMainClients();

        // Transform to proper format for select options: id => client_name
        $main_clients = ['' => 'Select Main Client'];
        foreach ($main_clients_raw as $mainClient) {
            if (isset($mainClient['id']) && isset($mainClient['client_name'])) {
                $main_clients[$mainClient['id']] = $mainClient['client_name'];
            }
        }

        return [
            'seta_options' => $seta_options,
            'status_options' => $status_options,
            'sites' => $sitesData,
            'location_data' => [
                'hierarchy' => $hierarchy,
                'selected' => [
                    'province' => '',
                    'town' => '',
                    'suburb' => '',
                    'locationId' => null,
                    'postalCode' => '',
                ],
            ],
            'main_clients' => $main_clients,
            'main_clients_raw' => $main_clients_raw,
        ];
    }

    /**
     * Filter and process client data for form rendering
     *
     * @param array $client Raw client data from database
     * @return array Processed client data with safe fields
     */
    public function filterClientDataForForm(array $client): array
    {
        if (!is_array($client)) {
            return [];
        }

        $processed = [];

        // First, extract basic scalar fields
        $scalarFields = [
            'id', 'client_name', 'company_registration_nr', 'seta', 'client_status',
            'financial_year_end', 'bbbee_verification_date', 'main_client_id',
            'client_town_id', // Reference to place_id in locations table
            'created_at', 'updated_at',
            'contact_person', 'contact_person_email', 'contact_person_cellphone',
            'contact_person_tel', 'contact_person_position',
            // Hydrated address display fields (populated by SitesModel::hydrateClients)
            'client_street_address', 'client_suburb', 'client_postal_code',
            'client_province', 'client_town',
        ];

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

        return $processed;
    }

    /**
     * Export clients as CSV data
     *
     * @return array ['headers' => array, 'rows' => array]
     */
    public function exportClientsAsCsv(): array
    {
        $clients = $this->model->getAll();

        $headers = [
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
        ];

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
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
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Get branch clients (sites) for a client
     *
     * @param int $clientId Client ID
     * @return array
     */
    public function getBranchClients(int $clientId): array
    {
        return $this->getSitesModel()->getSitesByClient($clientId);
    }

    /**
     * Get location hierarchy
     *
     * @return array
     */
    public function getLocationHierarchy(): array
    {
        return $this->getSitesModel()->getLocationHierarchy();
    }
}
