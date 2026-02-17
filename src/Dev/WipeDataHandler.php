<?php

declare(strict_types=1);

namespace WeCoza\Dev;

/**
 * Wipe Data Handler
 *
 * AJAX handler to truncate transactional WeCoza tables in PostgreSQL.
 * Only available when WP_DEBUG is enabled. Preserves reference/lookup tables.
 */
class WipeDataHandler
{
    /**
     * Transactional tables that will be truncated.
     * Reference tables (locations, class_types, products, etc.) are intentionally excluded.
     */
    private const TRANSACTIONAL_TABLES = [
        'locations',
        'agents',
        'agent_meta',
        'agent_notes',
        'agent_absences',
        'agent_orders',
        'agent_replacements',
        'clients',
        'client_communications',
        'learners',
        'learner_hours_log',
        'learner_lp_tracking',
        'learner_portfolios',
        'learner_products',
        'learner_progression_portfolios',
        'learner_progressions',
        'learner_sponsors',
        'classes',
        'class_agents',
        'class_events',
        'class_material_tracking',
        'class_notes',
        'class_schedules',
        'class_subjects',
        'sites',
        'attendance_registers',
        'collections',
        'deliveries',
        'employers',
        'exams',
        'exam_results',
        'files',
        'history',
        'latest_document',
        'progress_reports',
        'qa_visits',
    ];

    /**
     * Tables preserved (not truncated):
     * - class_types: Class type definitions
     * - class_type_subjects: Subject mappings per class type
     * - products: Learning programmes/qualifications
     * - learner_qualifications: Qualification lookup values
     * - learner_placement_level: Numeracy/communication level lookup values
     * - user_roles: Role definitions
     * - user_permissions: Permission mappings
     * - users: User accounts
     * - sites_migration_backup: Historical backup
     * - sites_address_audit: Audit trail
     *
     * Note: locations IS truncated — rebuild by seeding location form first.
     */

    public function register(): void
    {
        add_action('wp_ajax_wecoza_dev_wipe_data', [$this, 'handleWipe']);
    }

    public function handleWipe(): void
    {
        // Safety checks
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            wp_send_json_error(['message' => 'WP_DEBUG is not enabled.'], 403);
        }

        if (!check_ajax_referer('wecoza_dev_wipe_data', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        try {
            $db = wecoza_db();
            $truncated = [];
            $errors = [];

            foreach (self::TRANSACTIONAL_TABLES as $table) {
                try {
                    $db->exec("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
                    $truncated[] = $table;
                } catch (\PDOException $e) {
                    // Table might not exist yet — log but don't fail
                    $errors[] = $table . ': ' . $e->getMessage();
                    wecoza_log("Dev wipe: failed to truncate {$table}: " . $e->getMessage(), 'warning');
                }
            }

            // Clear WP transients with wecoza_ prefix
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wecoza_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wecoza_%'");

            wecoza_log('Dev wipe completed: ' . count($truncated) . ' tables truncated.', 'info');

            wp_send_json_success([
                'message'   => count($truncated) . ' tables truncated successfully.',
                'truncated' => $truncated,
                'errors'    => $errors,
            ]);

        } catch (\Exception $e) {
            wecoza_log('Dev wipe failed: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => 'Wipe failed: ' . $e->getMessage()], 500);
        }
    }
}
