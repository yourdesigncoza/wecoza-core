<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Support;

final class SchemaContext
{
    private const MODULE_SCHEMAS = [
        'agents' => [
            'module'      => 'Agents',
            'description' => 'Agent management - company representatives managing classes',
            'tables'      => 'agents (id, first_name, last_name, email, cell_number, tel_number, alt_cell_number, id_number, company_id, status, created_at)',
            'related'     => 'agent_meta (key-value metadata), agent_notes (timestamped notes), agent_absences (date ranges)',
        ],
        'learners' => [
            'module'      => 'Learners',
            'description' => 'Learner management - students enrolled in learning programmes',
            'tables'      => 'learners (id, first_name, last_name, id_number, email, cell_number, gender, race, disability_status, highest_qualification, province, created_at)',
            'related'     => 'learner_progressions (lp tracking: hours_trained, hours_present, hours_absent, progress_percentage, status, portfolio_path)',
        ],
        'classes' => [
            'module'      => 'Classes',
            'description' => 'Class management - training sessions linking clients, agents, and learners',
            'tables'      => 'classes (class_id, client_id, site_id, class_type, class_subject, class_code, class_duration, original_start_date, seta_funded, class_agent, project_supervisor_id)',
            'related'     => 'class_schedules (day_of_week, start_time, end_time), class_learners (linking table), qa_visits (quality assurance)',
        ],
        'clients' => [
            'module'      => 'Clients',
            'description' => 'Client/company management - organizations that commission training',
            'tables'      => 'clients (id, company_name, trading_name, registration_number, contact_person, email, phone, address, city, province)',
            'related'     => 'locations (site_id, site_name, address, city, province)',
        ],
    ];

    private const SHORTCODE_MODULE_MAP = [
        'wecoza_capture_agents'         => 'agents',
        'wecoza_display_agents'         => 'agents',
        'wecoza_single_agent'           => 'agents',
        'wecoza_display_learners'       => 'learners',
        'wecoza_learners_form'          => 'learners',
        'wecoza_single_learner_display' => 'learners',
        'wecoza_learners_update_form'   => 'learners',
        'wecoza_capture_class'          => 'classes',
        'wecoza_display_classes'        => 'classes',
        'wecoza_display_single_class'   => 'classes',
        'wecoza_capture_clients'        => 'clients',
        'wecoza_display_clients'        => 'clients',
        'wecoza_update_clients'         => 'clients',
        'wecoza_locations_capture'      => 'clients',
        'wecoza_locations_list'         => 'clients',
        'wecoza_locations_edit'         => 'clients',
    ];

    public static function getModuleFromShortcode(string $shortcode): ?string
    {
        return self::SHORTCODE_MODULE_MAP[$shortcode] ?? null;
    }

    public static function getModuleFromUrl(string $url): ?string
    {
        $urlLower = strtolower($url);
        foreach (['agents', 'learners', 'classes', 'clients'] as $module) {
            if (str_contains($urlLower, $module)) {
                return $module;
            }
        }
        return null;
    }

    public static function getSchemaForModule(?string $module): string
    {
        if ($module === null || !isset(self::MODULE_SCHEMAS[$module])) {
            return 'General WeCoza page - no specific module context available.';
        }

        $schema = self::MODULE_SCHEMAS[$module];

        return sprintf(
            "Module: %s\nDescription: %s\nMain table: %s\nRelated: %s",
            $schema['module'],
            $schema['description'],
            $schema['tables'],
            $schema['related']
        );
    }

    public static function detectModule(?string $shortcode, ?string $url): ?string
    {
        if ($shortcode) {
            $module = self::getModuleFromShortcode($shortcode);
            if ($module) {
                return $module;
            }
        }

        if ($url) {
            return self::getModuleFromUrl($url);
        }

        return null;
    }
}
