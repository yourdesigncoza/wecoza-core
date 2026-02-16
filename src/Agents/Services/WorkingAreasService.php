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
     * Load working areas data
     * 
     * @return array Array of working areas
     */
    private static function load_working_areas(): array {
        return array(
            '1' => 'Sandton, Johannesburg, Gauteng, 2196',
            '2' => 'Durbanville, Cape Town, Western Cape, 7551',
            '3' => 'Durban, Durban, KwaZulu-Natal, 4320',
            '4' => 'Hatfield, Pretoria, Gauteng, 0028',
            '5' => 'Stellenbosch, Stellenbosch, Western Cape, 7600',
            '6' => 'Polokwane, Polokwane, Limpopo, 0699',
            '7' => 'Kimberley, Kimberley, Northern Cape, 8301',
            '8' => 'Nelspruit, Mbombela, Mpumalanga, 1200',
            '9' => 'Bloemfontein, Bloemfontein, Free State, 9300',
            '10' => 'Port Elizabeth, Gqeberha, Eastern Cape, 6001',
            '11' => 'Soweto, Johannesburg, Gauteng, 1804',
            '12' => 'Paarl, Paarl, Western Cape, 7620',
            '13' => 'Pietermaritzburg, Pietermaritzburg, KwaZulu-Natal, 3201',
            '14' => 'East London, East London, Eastern Cape, 5201',
        );
    }
}