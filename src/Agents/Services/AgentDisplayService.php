<?php
/**
 * Agent Display Service
 *
 * Shared display logic extracted from AgentsController and AgentsAjaxHandlers
 * to eliminate code duplication (DRY principle).
 *
 * @package WeCoza\Agents
 * @since 3.1.0
 */

namespace WeCoza\Agents\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Display Service
 *
 * Provides shared static methods for agent display operations used by both
 * the AgentsController (shortcode rendering) and AgentsAjaxHandlers (AJAX pagination).
 *
 * @since 3.1.0
 */
class AgentDisplayService
{
    /**
     * Get agent statistics (total, active, SACE registered, quantum qualified)
     *
     * Extracted from AgentsController and AgentsAjaxHandlers.
     *
     * @return array Statistics data with label, count, badge, and badge_type for each metric
     */
    public static function getAgentStatistics(): array
    {
        try {
            $db = wecoza_db();

            // Get total agents count
            $total_sql = "SELECT COUNT(*) as count FROM agents WHERE status != 'deleted'";
            $total_result = $db->query($total_sql);
            $total_agents = $total_result ? $total_result->fetch()['count'] : 0;

            // Get active agents count
            $active_sql = "SELECT COUNT(*) as count FROM agents WHERE status = 'active'";
            $active_result = $db->query($active_sql);
            $active_agents = $active_result ? $active_result->fetch()['count'] : 0;

            // Get SACE registered count
            $sace_sql = "SELECT COUNT(*) as count FROM agents WHERE sace_number IS NOT NULL AND sace_number != '' AND status != 'deleted'";
            $sace_result = $db->query($sace_sql);
            $sace_registered = $sace_result ? $sace_result->fetch()['count'] : 0;

            // Get quantum qualified count
            $quantum_sql = "SELECT COUNT(*) as count FROM agents WHERE (quantum_maths_score > 0 OR quantum_science_score > 0) AND status != 'deleted'";
            $quantum_result = $db->query($quantum_sql);
            $quantum_qualified = $quantum_result ? $quantum_result->fetch()['count'] : 0;

            return [
                'total_agents' => [
                    'label' => __('Total Agents', 'wecoza-core'),
                    'count' => $total_agents,
                    'badge' => null,
                    'badge_type' => null
                ],
                'active_agents' => [
                    'label' => __('Active Agents', 'wecoza-core'),
                    'count' => $active_agents,
                    'badge' => null,
                    'badge_type' => null
                ],
                'sace_registered' => [
                    'label' => __('SACE Registered', 'wecoza-core'),
                    'count' => $sace_registered,
                    'badge' => null,
                    'badge_type' => null
                ],
                'quantum_qualified' => [
                    'label' => __('Quantum Qualified', 'wecoza-core'),
                    'count' => $quantum_qualified,
                    'badge' => null,
                    'badge_type' => null
                ]
            ];
        } catch (\Exception $e) {
            wecoza_log('Error fetching agent statistics: ' . $e->getMessage(), 'error');

            return self::getEmptyStatistics();
        }
    }

    /**
     * Map database agent fields to frontend display fields
     *
     * Extracted from AgentsController and AgentsAjaxHandlers.
     *
     * @param array $agent Agent data from database
     * @return array Mapped agent data with frontend-friendly field names
     */
    public static function mapAgentFields(array $agent): array
    {
        return [
            'id' => $agent['agent_id'],
            'first_name' => $agent['first_name'],
            'initials' => $agent['initials'] ?? '',
            'last_name' => $agent['surname'],
            'gender' => $agent['gender'] ?? '',
            'race' => $agent['race'] ?? '',
            'phone' => $agent['tel_number'],
            'email' => $agent['email_address'],
            'city' => $agent['city'] ?? '',
            'status' => $agent['status'] ?? 'active',
            'sa_id_no' => $agent['sa_id_no'] ?? '',
            'sace_number' => $agent['sace_number'] ?? '',
            'quantum_maths_score' => intval($agent['quantum_maths_score'] ?? 0),
            'quantum_science_score' => intval($agent['quantum_science_score'] ?? 0),
        ];
    }

    /**
     * Map frontend sort column name to database column name
     *
     * Extracted from AgentsController and AgentsAjaxHandlers.
     *
     * @param string $column Frontend column name
     * @return string Database column name
     */
    public static function mapSortColumn(string $column): string
    {
        $map = [
            'last_name' => 'surname',
            'phone' => 'tel_number',
            'email' => 'email_address',
        ];

        return $map[$column] ?? $column;
    }

    /**
     * Get display columns configuration for agent list table
     *
     * Extracted from AgentsController and AgentsAjaxHandlers.
     *
     * @param string $columns_setting Comma-separated column keys from shortcode attribute
     * @return array Display columns as key => label pairs
     */
    public static function getDisplayColumns(string $columns_setting): array
    {
        $default_columns = [
            'first_name' => __('First Name', 'wecoza-core'),
            'initials' => __('Initials', 'wecoza-core'),
            'last_name' => __('Surname', 'wecoza-core'),
            'gender' => __('Gender', 'wecoza-core'),
            'race' => __('Race', 'wecoza-core'),
            'phone' => __('Tel Number', 'wecoza-core'),
            'email' => __('Email Address', 'wecoza-core'),
            'city' => __('City/Town', 'wecoza-core'),
        ];

        // If specific columns are requested, filter the default set
        if (!empty($columns_setting)) {
            $requested = array_map('trim', explode(',', $columns_setting));
            $columns = [];

            foreach ($requested as $col) {
                if (isset($default_columns[$col])) {
                    $columns[$col] = $default_columns[$col];
                }
            }

            return !empty($columns) ? $columns : $default_columns;
        }

        return $default_columns;
    }

    /**
     * Get empty statistics array (used as fallback on error)
     *
     * @return array Statistics data with all counts set to zero
     */
    private static function getEmptyStatistics(): array
    {
        return [
            'total_agents' => [
                'label' => __('Total Agents', 'wecoza-core'),
                'count' => 0,
                'badge' => null,
                'badge_type' => null
            ],
            'active_agents' => [
                'label' => __('Active Agents', 'wecoza-core'),
                'count' => 0,
                'badge' => null,
                'badge_type' => null
            ],
            'sace_registered' => [
                'label' => __('SACE Registered', 'wecoza-core'),
                'count' => 0,
                'badge' => null,
                'badge_type' => null
            ],
            'quantum_qualified' => [
                'label' => __('Quantum Qualified', 'wecoza-core'),
                'count' => 0,
                'badge' => null,
                'badge_type' => null
            ]
        ];
    }
}
