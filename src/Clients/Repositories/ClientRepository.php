<?php
declare(strict_types=1);

namespace WeCoza\Clients\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Clients\Models\ClientsModel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for clients table operations
 *
 * Provides CRUD operations and query methods for client data with security column whitelisting.
 *
 * @since 2.0.0
 */
final class ClientRepository extends BaseRepository
{
    protected static string $table = 'clients';
    protected static string $primaryKey = 'client_id';

    /**
     * Get the Model class name
     *
     * @return string
     */
    protected function getModel(): string
    {
        return ClientsModel::class;
    }

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array<int, string>
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'client_name',
            'company_registration_nr',
            'client_status',
            'seta',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array<int, string>
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'client_status',
            'seta',
            'main_client_id',
            'deleted_at',
        ];
    }

    /**
     * Get columns allowed for INSERT operations
     *
     * @return array<int, string>
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'client_name',
            'company_registration_nr',
            'seta',
            'client_status',
            'financial_year_end',
            'bbbee_verification_date',
            'main_client_id',
            'contact_person',
            'contact_person_email',
            'contact_person_cellphone',
            'contact_person_tel',
            'contact_person_position',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get columns allowed for UPDATE operations
     *
     * @return array<int, string>
     */
    protected function getAllowedUpdateColumns(): array
    {
        return [
            'client_name',
            'company_registration_nr',
            'seta',
            'client_status',
            'financial_year_end',
            'bbbee_verification_date',
            'main_client_id',
            'contact_person',
            'contact_person_email',
            'contact_person_cellphone',
            'contact_person_tel',
            'contact_person_position',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Get main clients (clients without a parent main_client_id)
     *
     * @return array
     */
    public function getMainClients(): array
    {
        return $this->findBy(['main_client_id' => null], 1000, 0, 'client_name', 'ASC');
    }

    /**
     * Get branch/sub-clients of a specific main client
     *
     * @param int $mainClientId
     * @return array
     */
    public function getBranchClients(int $mainClientId): array
    {
        if ($mainClientId <= 0) {
            return [];
        }

        return $this->findBy(['main_client_id' => $mainClientId], 1000, 0, 'client_name', 'ASC');
    }

    /**
     * Search clients by name or registration number (ILIKE)
     *
     * Complex query: ILIKE search not supported by BaseRepository findBy
     *
     * @param string $term Search term
     * @param int $limit Maximum results
     * @return array
     */
    public function searchClients(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $search = '%' . $term . '%';
        $sql = "SELECT client_id, client_name, company_registration_nr, client_status
                FROM {$this->table}
                WHERE client_name ILIKE :search
                   OR company_registration_nr ILIKE :search2
                ORDER BY client_name
                LIMIT :limit";

        return wecoza_db()->getAll($sql, [
            ':search' => $search,
            ':search2' => $search,
            ':limit' => $limit,
        ]) ?: [];
    }
}
