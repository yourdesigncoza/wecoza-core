<?php
declare(strict_types=1);

namespace WeCoza\Clients\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Clients\Models\LocationsModel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for locations table operations
 *
 * Provides CRUD operations and query methods for location/address data with security column whitelisting.
 *
 * @since 2.0.0
 */
final class LocationRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    protected static string $table = 'public.locations';
    protected static string $primaryKey = 'location_id';

    /**
     * Get the Model class name
     *
     * @return string
     */
    protected function getModel(): string
    {
        return LocationsModel::class;
    }

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array<int, string>
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'suburb',
            'town',
            'province',
            'postal_code',
            'created_at',
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
            'province',
            'town',
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
            'street_address',
            'suburb',
            'town',
            'province',
            'postal_code',
            'longitude',
            'latitude',
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
            'street_address',
            'suburb',
            'town',
            'province',
            'postal_code',
            'longitude',
            'latitude',
            'updated_at',
        ];
    }

    /**
     * Find locations by coordinates (proximity search)
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm Search radius in kilometers
     * @param int $limit Maximum results
     * @return array
     */
    public function findByCoordinates(float $latitude, float $longitude, float $radiusKm = 10.0, int $limit = 10): array
    {
        // Complex query: Haversine formula distance calculation
        $sql = "SELECT location_id, street_address, suburb, town, province, postal_code, latitude, longitude,
                       (6371 * acos(cos(radians(:lat)) * cos(radians(latitude)) * cos(radians(longitude) - radians(:lng)) + sin(radians(:lat2)) * sin(radians(latitude)))) AS distance
                FROM {$this->table}
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                HAVING distance < :radius
                ORDER BY distance
                LIMIT :limit";

        return wecoza_db()->getAll($sql, [
            ':lat' => $latitude,
            ':lat2' => $latitude,
            ':lng' => $longitude,
            ':radius' => $radiusKm,
            ':limit' => $limit,
        ]) ?: [];
    }

    /**
     * Check for duplicate locations (suburb + town matching)
     *
     * @param string $suburb
     * @param string $town
     * @param int|null $excludeId Location ID to exclude from check
     * @return array
     */
    public function checkDuplicates(string $suburb, string $town, ?int $excludeId = null): array
    {
        // Complex query: LOWER() case-insensitive matching with optional exclude
        $suburb = trim($suburb);
        $town = trim($town);

        if ($suburb === '' && $town === '') {
            return [];
        }

        $conditions = [];
        $params = [];

        if ($suburb !== '') {
            $conditions[] = 'LOWER(suburb) = LOWER(:suburb)';
            $params[':suburb'] = $suburb;
        }

        if ($town !== '') {
            $conditions[] = 'LOWER(town) = LOWER(:town)';
            $params[':town'] = $town;
        }

        $sql = "SELECT location_id, street_address, suburb, town, province, postal_code
                FROM {$this->table}
                WHERE " . implode(' AND ', $conditions);

        if ($excludeId !== null) {
            $sql .= ' AND location_id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' ORDER BY street_address LIMIT 10';

        return wecoza_db()->getAll($sql, $params) ?: [];
    }
}
