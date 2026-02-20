<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Support;

final class SchemaContext
{
    private const MODULE_SCHEMAS = [
        'agents' => [
            'module'      => 'Agents',
            'description' => 'Agent management - company representatives managing classes',
            'tables'      => 'agents (agent_id, first_name, surname, gender[M|F], race[African|Coloured|White|Indian], sa_id_no, passport_number, tel_number, email_address, title[Mr|Mrs|Ms|Miss|Dr|Prof], id_type[sa_id|passport], province, city, phase_registered[Foundation|Intermediate|Senior|FET], subjects_registered, quantum_maths_score[0-100], quantum_science_score[0-100], highest_qualification, sace_number, status[active|inactive|suspended|deleted], location_id)',
            'related'     => 'agent_absences (absence_id, agent_id, class_id, absence_date, reason), agent_meta (meta_id, agent_id, meta_key, meta_value), agent_notes (note_id, agent_id, note, created_by, created_at)',
        ],
        'learners' => [
            'module'      => 'Learners',
            'description' => 'Learner management - students enrolled in learning programmes',
            'tables'      => 'learners (id, first_name, surname, gender, race, sa_id_no, passport_number, tel_number, alternative_tel_number, email_address, address_line_1, city_town_id, province_region_id, postal_code, assessment_status[Assessed|Not Assessed], numeracy_level, employment_status, employer_id, disability_status, highest_qualification, title)',
            'related'     => 'learner_lp_tracking (tracking_id, learner_id, class_type_subject_id, class_id, hours_trained, hours_present, hours_absent, status[in_progress|completed|on_hold]), learner_hours_log (log_id, learner_id, class_type_subject_id, log_date, hours_trained, hours_present, source[schedule|attendance|manual]), learner_qualifications (id, qualification), learner_sponsors (id, learner_id, employer_id), learner_portfolios (portfolio_id, learner_id, file_path), learner_progression_portfolios (file_id, tracking_id, file_name, file_path), learner_placement_level (placement_level_id, level, level_desc), employers (employer_id, employer_name)',
        ],
        'classes' => [
            'module'      => 'Classes',
            'description' => 'Class management - training sessions linking clients, agents, and learners',
            'tables'      => 'classes (class_id, client_id, site_id, class_type, class_subject, class_code, class_duration, class_address_line, original_start_date, seta_funded, seta, exam_class, exam_type, class_agent, project_supervisor_id, learner_ids[jsonb], backup_agent_ids[jsonb], schedule_data[jsonb], stop_restart_dates[jsonb], class_notes_data[jsonb], order_nr)',
            'related'     => 'class_types (class_type_id, class_type_code, class_type_name, subject_selection_mode[own|all_subjects|progression], progression_total_hours), class_type_subjects (class_type_subject_id, class_type_id, subject_code, subject_name, subject_duration), class_material_tracking (id, class_id, notification_type[orange|red], delivery_status[pending|notified|delivered]), qa_visits (id, class_id, visit_date, visit_type, officer_name, latest_document[jsonb])',
        ],
        'clients' => [
            'module'      => 'Clients',
            'description' => 'Client/company management - organizations that commission training',
            'tables'      => 'clients (client_id, client_name, company_registration_number, seta, client_status, financial_year_end, bbbee_verification_date, main_client_id, contact_person, contact_person_email, contact_person_cellphone, contact_person_tel, contact_person_position, deleted_at)',
            'related'     => 'sites (site_id, client_id, site_name, parent_site_id, place_id), locations (location_id, street_address, suburb, town, province, postal_code, longitude, latitude), client_communications (communication_id, client_id, communication_type, subject, content, communication_date, user_id, site_id)',
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
