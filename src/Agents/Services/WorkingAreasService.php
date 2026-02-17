<?php
declare(strict_types=1);

namespace WeCoza\Agents\Services;

/**
 * WorkingAreasService
 * 
 * Service class for managing working areas data and validation
 */
class WorkingAreasService {
    
    /**
     * Static cache for working areas data
     * 
     * @var array|null
     */
    private static $working_areas = null;
    
    /**
     * Get all working areas
     * 
     * @return array Array of working areas with ID as key and location as value
     */
    public static function get_working_areas(): array {
        if (self::$working_areas === null) {
            self::$working_areas = self::load_working_areas();
        }
        
        return self::$working_areas;
    }
    
    /**
     * Get a specific working area by ID
     * 
     * @param string $id The working area ID
     * @return string|null The working area location or null if not found
     */
    public static function get_working_area_by_id(string $id): ?string {
        $working_areas = self::get_working_areas();
        return isset($working_areas[$id]) ? $working_areas[$id] : null;
    }
    
    /**
     * Load working areas from the locations table
     */
    private static function load_working_areas(): array {
        try {
            $db = wecoza_db();
            $stmt = $db->query(
                'SELECT location_id, suburb, town, province, postal_code FROM locations ORDER BY town, suburb'
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $areas = [];
            foreach ($rows as $row) {
                $label = implode(', ', array_filter([
                    $row['suburb'],
                    $row['town'],
                    $row['province'],
                    $row['postal_code'],
                ]));
                $areas[(string) $row['location_id']] = $label;
            }

            return $areas;
        } catch (\Throwable $e) {
            wecoza_log('WorkingAreasService: Failed to load locations: ' . $e->getMessage(), 'error');
            return [];
        }
    }
}