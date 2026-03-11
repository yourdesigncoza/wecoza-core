<?php
/**
 * NLQ Schema Definition
 *
 * Hardcoded schema for the AI SQL generator. This file defines all tables,
 * columns, relationships, enum values, and business context that the AI
 * uses to generate accurate SQL queries.
 *
 * MAINTENANCE: When database schema changes, update this file to match.
 * The AI will ONLY use tables and columns defined here.
 *
 * @package WeCoza\NLQ
 * @since 1.0.0
 * @last_verified 2026-03-11 (against live PostgreSQL public schema)
 */

return [

    /* ═══════════════════════════════════════════════════════════
     * MODULE: AGENTS
     * Agent/facilitator management — company reps managing classes
     * ═══════════════════════════════════════════════════════════ */

    'agents' => [
        'description' => 'Agent (facilitator/trainer) profiles and personal details',
        'primary_key' => 'agent_id',
        'columns' => [
            'agent_id'                  => 'Auto-increment primary key',
            'first_name'                => 'Agent first name',
            'second_name'               => 'Agent second/middle name',
            'initials'                  => 'Agent initials',
            'surname'                   => 'Agent surname',
            'title'                     => 'Title: Mr, Mrs, Ms, Miss, Dr, Prof',
            'gender'                    => 'Gender: M or F',
            'race'                      => 'Race: African, Coloured, White, Indian',
            'id_type'                   => 'ID type: sa_id or passport',
            'sa_id_no'                  => 'South African ID number (13 digits)',
            'passport_number'           => 'Passport number (if not SA ID)',
            'tel_number'                => 'Phone number',
            'email_address'             => 'Email address',
            'residential_address_line'  => 'Street address line 1',
            'address_line_2'            => 'Street address line 2',
            'residential_suburb'        => 'Suburb',
            'residential_postal_code'   => 'Postal code',
            'province'                  => 'Province name',
            'city'                      => 'City/town name',
            'preferred_working_area_1'  => 'Preferred work area location_id (FK → locations)',
            'preferred_working_area_2'  => 'Second preferred work area location_id',
            'preferred_working_area_3'  => 'Third preferred work area location_id',
            'highest_qualification'     => 'Highest educational qualification',
            'phase_registered'          => 'Teaching phase: Foundation, Intermediate, Senior, FET',
            'subjects_registered'       => 'Subjects registered to teach (text, comma-separated)',
            'sace_number'               => 'SA Council for Educators registration number',
            'sace_registration_date'    => 'SACE registration date',
            'sace_expiry_date'          => 'SACE expiry date',
            'quantum_assessment'        => 'Quantum assessment score (numeric)',
            'quantum_maths_score'       => 'Quantum maths score (0-100)',
            'quantum_science_score'     => 'Quantum science score (0-100)',
            'agent_training_date'       => 'Date agent completed training',
            'bank_name'                 => 'Bank name for payments',
            'bank_branch_code'          => 'Bank branch code',
            'bank_account_number'       => 'Bank account number',
            'account_holder'            => 'Bank account holder name',
            'account_type'              => 'Bank account type (savings/cheque)',
            'signed_agreement_date'     => 'Date agent signed agreement',
            'signed_agreement_file'     => 'Path to signed agreement file',
            'criminal_record_date'      => 'Criminal record check date',
            'criminal_record_file'      => 'Path to criminal record check file',
            'agent_notes'               => 'Free-text notes about the agent',
            'status'                    => 'Status: active, inactive, suspended, deleted',
            'location_id'              => 'FK → locations table',
            'wp_user_id'               => 'WordPress user ID (links to WP account)',
            'created_by'               => 'WP user ID who created the record',
            'updated_by'               => 'WP user ID who last updated',
            'created_at'               => 'Record creation timestamp',
            'updated_at'               => 'Record last update timestamp',
        ],
        'enums' => [
            'status'           => ['active', 'inactive', 'suspended', 'deleted'],
            'gender'           => ['M', 'F'],
            'race'             => ['African', 'Coloured', 'White', 'Indian'],
            'id_type'          => ['sa_id', 'passport'],
            'title'            => ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'],
            'phase_registered' => ['Foundation', 'Intermediate', 'Senior', 'FET'],
        ],
    ],

    'agent_orders' => [
        'description' => 'Agent order/rate assignments — links an agent to a class with a payment rate',
        'primary_key' => 'order_id',
        'columns' => [
            'order_id'    => 'Auto-increment primary key',
            'class_id'    => 'FK → classes',
            'agent_id'    => 'FK → agents',
            'rate_type'   => 'Rate type (e.g. hourly, fixed)',
            'rate_amount' => 'Payment rate amount (numeric)',
            'start_date'  => 'Order start date',
            'end_date'    => 'Order end date',
            'notes'       => 'Order notes',
            'created_at'  => 'Record creation timestamp',
            'updated_at'  => 'Record last update timestamp',
            'created_by'  => 'WP user ID who created',
        ],
    ],

    'agent_monthly_invoices' => [
        'description' => 'Monthly invoices for agent payments — tracks hours worked, absences, and payable amounts per class per month',
        'primary_key' => 'invoice_id',
        'columns' => [
            'invoice_id'              => 'Auto-increment primary key',
            'order_id'                => 'FK → agent_orders',
            'class_id'                => 'FK → classes',
            'agent_id'                => 'FK → agents',
            'invoice_month'           => 'Invoice month (date, first of month)',
            'class_hours_total'       => 'Total scheduled class hours for the month',
            'all_absent_days'         => 'Number of absent days in the month',
            'all_absent_hours'        => 'Total absent hours in the month',
            'calculated_payable_hours'=> 'System-calculated payable hours',
            'agent_claimed_hours'     => 'Hours claimed by the agent',
            'agent_notes'             => 'Agent notes on the invoice',
            'discrepancy_hours'       => 'Difference between calculated and claimed hours',
            'status'                  => 'Invoice status (e.g. draft, submitted, approved, paid)',
            'submitted_at'            => 'When agent submitted the invoice',
            'reviewed_at'             => 'When invoice was reviewed',
            'reviewed_by'             => 'WP user ID who reviewed',
            'review_notes'            => 'Reviewer notes',
            'created_at'              => 'Record creation timestamp',
            'updated_at'              => 'Record last update timestamp',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * MODULE: LEARNERS
     * Learner/student management — enrolled in learning programmes
     * ═══════════════════════════════════════════════════════════ */

    'learners' => [
        'description' => 'Learner (student) profiles enrolled in training programmes',
        'primary_key' => 'id',
        'columns' => [
            'id'                       => 'Auto-increment primary key',
            'first_name'               => 'Learner first name',
            'second_name'              => 'Learner second/middle name',
            'initials'                 => 'Learner initials',
            'surname'                  => 'Learner surname',
            'title'                    => 'Title',
            'gender'                   => 'Gender: M or F',
            'race'                     => 'Race: African, Coloured, White, Indian',
            'sa_id_no'                 => 'South African ID number',
            'passport_number'          => 'Passport number',
            'tel_number'               => 'Primary phone number',
            'alternative_tel_number'   => 'Alternative phone number',
            'email_address'            => 'Email address',
            'address_line_1'           => 'Street address line 1',
            'address_line_2'           => 'Street address line 2',
            'city_town_id'             => 'FK → town/city reference',
            'province_region_id'       => 'FK → province reference',
            'postal_code'              => 'Postal code',
            'assessment_status'        => 'Assessment status: Assessed or Not Assessed',
            'placement_assessment_date'=> 'Date of placement assessment',
            'numeracy_level'           => 'Numeracy level (integer)',
            'communication_level'      => 'Communication level (integer)',
            'employment_status'        => 'Employed: true/false (boolean)',
            'employer_id'              => 'FK → employers',
            'disability_status'        => 'Has disability: true/false (boolean)',
            'highest_qualification'    => 'FK → learner_qualifications.id',
            'scanned_portfolio'        => 'Path to scanned portfolio file',
            'created_at'               => 'Record creation timestamp',
            'updated_at'               => 'Record last update timestamp',
        ],
        'enums' => [
            'assessment_status' => ['Assessed', 'Not Assessed'],
        ],
    ],

    'learner_lp_tracking' => [
        'description' => 'Tracks learner progress through Learning Programmes (LP). One active LP per learner at a time.',
        'primary_key' => 'tracking_id',
        'columns' => [
            'tracking_id'           => 'Auto-increment primary key',
            'learner_id'            => 'FK → learners.id',
            'class_type_subject_id' => 'FK → class_type_subjects',
            'class_id'              => 'FK → classes',
            'hours_trained'         => 'Total hours of training delivered',
            'hours_present'         => 'Hours learner was present',
            'hours_absent'          => 'Hours learner was absent',
            'status'                => 'Progress status: in_progress, completed, on_hold',
            'start_date'            => 'When LP started',
            'completion_date'       => 'When LP was completed (null if ongoing)',
            'portfolio_file_path'   => 'Path to portfolio file',
            'portfolio_uploaded_at' => 'When portfolio was uploaded',
            'marked_complete_by'    => 'WP user ID who marked complete',
            'marked_complete_date'  => 'Date marked complete',
            'notes'                 => 'Notes about progress',
            'created_at'            => 'Record creation timestamp',
            'updated_at'            => 'Record last update timestamp',
        ],
        'enums' => [
            'status' => ['in_progress', 'completed', 'on_hold'],
        ],
    ],

    'learner_hours_log' => [
        'description' => 'Detailed log of learner hours — per session, links to attendance',
        'primary_key' => 'log_id',
        'columns' => [
            'log_id'                => 'Auto-increment primary key',
            'learner_id'            => 'FK → learners.id',
            'class_type_subject_id' => 'FK → class_type_subjects',
            'class_id'              => 'FK → classes',
            'tracking_id'           => 'FK → learner_lp_tracking',
            'log_date'              => 'Date of the session',
            'hours_trained'         => 'Hours of training that day',
            'hours_present'         => 'Hours present that day',
            'source'                => 'How recorded: schedule, attendance, manual',
            'session_id'            => 'FK → class_attendance_sessions',
            'created_by'            => 'WP user ID who recorded',
            'notes'                 => 'Session notes',
            'created_at'            => 'Record creation timestamp',
        ],
        'enums' => [
            'source' => ['schedule', 'attendance', 'manual'],
        ],
    ],

    'learner_qualifications' => [
        'description' => 'Lookup table of qualification levels',
        'primary_key' => 'id',
        'columns' => [
            'id'            => 'Auto-increment primary key',
            'qualification' => 'Qualification name/level',
        ],
    ],

    'learner_sponsors' => [
        'description' => 'Links learners to sponsoring employers',
        'primary_key' => 'id',
        'columns' => [
            'id'          => 'Auto-increment primary key',
            'learner_id'  => 'FK → learners.id',
            'employer_id' => 'FK → employers',
            'created_at'  => 'Record creation timestamp',
        ],
    ],

    'learner_portfolios' => [
        'description' => 'Learner portfolio file uploads',
        'primary_key' => 'portfolio_id',
        'columns' => [
            'portfolio_id' => 'Auto-increment primary key',
            'learner_id'   => 'FK → learners.id',
            'file_path'    => 'Path to uploaded portfolio file',
            'upload_date'  => 'Upload timestamp',
        ],
    ],

    'learner_progression_portfolios' => [
        'description' => 'Portfolio files tied to specific LP tracking records',
        'primary_key' => 'file_id',
        'columns' => [
            'file_id'     => 'Auto-increment primary key',
            'tracking_id' => 'FK → learner_lp_tracking',
            'file_name'   => 'Original file name',
            'file_path'   => 'Server file path',
            'file_type'   => 'MIME type',
            'file_size'   => 'File size in bytes',
            'uploaded_by'  => 'WP user ID who uploaded',
            'uploaded_at'  => 'Upload timestamp',
        ],
    ],

    'learner_placement_level' => [
        'description' => 'Lookup table for learner placement levels',
        'primary_key' => 'placement_level_id',
        'columns' => [
            'placement_level_id' => 'Auto-increment primary key',
            'level'              => 'Level code',
            'level_desc'         => 'Level description',
        ],
    ],

    'employers' => [
        'description' => 'Employer/sponsor companies linked to learners',
        'primary_key' => 'employer_id',
        'columns' => [
            'employer_id'   => 'Auto-increment primary key',
            'employer_name' => 'Company/employer name',
            'created_at'    => 'Record creation timestamp',
            'updated_at'    => 'Record last update timestamp',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * MODULE: CLASSES
     * Class/training session management
     * ═══════════════════════════════════════════════════════════ */

    'classes' => [
        'description' => 'Training classes — links clients, agents (facilitators), and learners. Central entity.',
        'primary_key' => 'class_id',
        'columns' => [
            'class_id'                 => 'Auto-increment primary key',
            'client_id'                => 'FK → clients (who commissioned the training)',
            'site_id'                  => 'FK → sites (training venue)',
            'class_type'               => 'Class type code (FK → class_types.class_type_code)',
            'class_subject'            => 'Subject code',
            'class_code'               => 'Unique class code (e.g. KUD021916-1)',
            'class_duration'           => 'Duration in hours',
            'class_address_line'       => 'Physical address of the class',
            'original_start_date'      => 'Original scheduled start date',
            'seta_funded'              => 'SETA funded: true/false',
            'seta'                     => 'SETA name (if funded)',
            'exam_class'               => 'Is exam class: true/false',
            'exam_type'                => 'Exam type (if applicable)',
            'class_agent'              => 'FK → agents.agent_id (primary facilitator)',
            'initial_class_agent'      => 'Original assigned agent_id',
            'initial_agent_start_date' => 'Original agent start date',
            'project_supervisor_id'    => 'FK → agents.agent_id (supervisor)',
            'learner_ids'              => 'JSONB array of learner IDs enrolled in this class',
            'backup_agent_ids'         => 'JSONB array of backup agent IDs',
            'schedule_data'            => 'JSONB schedule configuration',
            'stop_restart_dates'       => 'JSONB array of stop/restart date pairs',
            'class_notes_data'         => 'JSONB array of timestamped notes',
            'exam_learners'            => 'JSONB array of learner IDs for exam',
            'event_dates'              => 'JSONB event dates',
            'order_nr'                 => 'Order number reference',
            'order_nr_metadata'        => 'JSONB order number metadata',
            'class_status'             => 'Class status (e.g. active, completed, cancelled)',
            'created_at'               => 'Record creation timestamp',
            'updated_at'               => 'Record last update timestamp',
        ],
        'notes' => 'learner_ids is JSONB — use jsonb_array_elements_text(learner_ids) to unnest. class_agent references agents.agent_id.',
    ],

    'class_types' => [
        'description' => 'Lookup table of class types (e.g. LP = Learning Programme)',
        'primary_key' => 'class_type_id',
        'columns' => [
            'class_type_id'          => 'Auto-increment primary key',
            'class_type_code'        => 'Short code (e.g. LP, COMM, RLN)',
            'class_type_name'        => 'Full name of the class type',
            'subject_selection_mode'  => 'How subjects are selected: own, all_subjects, progression',
            'progression_total_hours' => 'Total hours for progression-type classes',
            'display_order'           => 'UI display order',
            'is_active'               => 'Active flag',
            'created_at'              => 'Record creation timestamp',
            'updated_at'              => 'Record last update timestamp',
        ],
        'enums' => [
            'subject_selection_mode' => ['own', 'all_subjects', 'progression'],
        ],
    ],

    'class_type_subjects' => [
        'description' => 'Subjects available under each class type',
        'primary_key' => 'class_type_subject_id',
        'columns' => [
            'class_type_subject_id' => 'Auto-increment primary key',
            'class_type_id'         => 'FK → class_types',
            'subject_code'          => 'Subject code',
            'subject_name'          => 'Subject name',
            'subject_duration'      => 'Duration in hours',
            'total_pages'           => 'Total pages in material',
            'display_order'         => 'UI display order',
            'is_active'             => 'Active flag',
            'created_at'            => 'Record creation timestamp',
            'updated_at'            => 'Record last update timestamp',
        ],
    ],

    'class_attendance_sessions' => [
        'description' => 'Daily attendance sessions for a class — tracks who was present per session',
        'primary_key' => 'session_id',
        'columns' => [
            'session_id'      => 'Auto-increment primary key',
            'class_id'        => 'FK → classes',
            'session_date'    => 'Date of the session',
            'status'          => 'Session status (e.g. completed, cancelled)',
            'scheduled_hours' => 'Scheduled hours for this session',
            'notes'           => 'Session notes',
            'learner_data'    => 'JSONB — per-learner attendance data for this session',
            'captured_by'     => 'WP user ID who captured attendance',
            'captured_at'     => 'When attendance was captured',
            'created_at'      => 'Record creation timestamp',
            'updated_at'      => 'Record last update timestamp',
        ],
        'notes' => 'learner_data is JSONB containing attendance details per learner for the session.',
    ],

    'class_material_tracking' => [
        'description' => 'Tracks material delivery status for classes — orange/red notification system',
        'primary_key' => 'id',
        'columns' => [
            'id'                    => 'Auto-increment primary key',
            'class_id'              => 'FK → classes',
            'notification_type'     => 'Notification level: orange (warning) or red (urgent)',
            'notification_sent_at'  => 'When notification was sent',
            'materials_delivered_at' => 'When materials were actually delivered',
            'delivery_status'       => 'Status: pending, notified, delivered',
            'created_at'            => 'Record creation timestamp',
            'updated_at'            => 'Record last update timestamp',
        ],
        'enums' => [
            'notification_type' => ['orange', 'red'],
            'delivery_status'   => ['pending', 'notified', 'delivered'],
        ],
    ],

    'class_status_history' => [
        'description' => 'Audit trail of class status changes',
        'primary_key' => 'id',
        'columns' => [
            'id'         => 'Auto-increment primary key',
            'class_id'   => 'FK → classes',
            'old_status' => 'Previous status value',
            'new_status' => 'New status value',
            'reason'     => 'Reason for status change',
            'notes'      => 'Additional notes',
            'changed_by' => 'WP user ID who made the change',
            'changed_at' => 'When the change occurred',
        ],
    ],

    'class_events' => [
        'description' => 'Event/notification system for classes — tracks events, AI summaries, notification delivery',
        'primary_key' => 'event_id',
        'columns' => [
            'event_id'            => 'Auto-increment primary key',
            'event_type'          => 'Type of event',
            'entity_type'         => 'Entity the event relates to (class, agent, etc.)',
            'entity_id'           => 'ID of the related entity',
            'user_id'             => 'WP user who triggered the event',
            'event_data'          => 'JSONB event payload',
            'ai_summary'          => 'JSONB AI-generated summary of the event',
            'notification_status' => 'Notification delivery status',
            'created_at'          => 'Event creation timestamp',
            'enriched_at'         => 'When AI enrichment was applied',
            'sent_at'             => 'When notification was sent',
            'viewed_at'           => 'When user viewed the notification',
            'acknowledged_at'     => 'When user acknowledged',
            'deleted_at'          => 'Soft-delete timestamp',
            'deleted_by'          => 'WP user who deleted',
        ],
    ],

    'qa_visits' => [
        'description' => 'Quality Assurance visits to classes by QA officers',
        'primary_key' => 'id',
        'columns' => [
            'id'              => 'Auto-increment primary key',
            'class_id'        => 'FK → classes',
            'visit_date'      => 'Date of the QA visit',
            'visit_type'      => 'Type of visit',
            'officer_name'    => 'Name of the QA officer',
            'latest_document' => 'JSONB — latest document/report details',
            'created_at'      => 'Record creation timestamp',
            'updated_at'      => 'Record last update timestamp',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * MODULE: CLIENTS
     * Client/company management
     * ═══════════════════════════════════════════════════════════ */

    'clients' => [
        'description' => 'Client companies that commission training',
        'primary_key' => 'client_id',
        'columns' => [
            'client_id'                  => 'Auto-increment primary key',
            'client_name'                => 'Company/client name',
            'company_registration_number' => 'Company registration number',
            'seta'                       => 'SETA the client is registered with',
            'client_status'              => 'Status (e.g. active, inactive)',
            'financial_year_end'         => 'Financial year-end date',
            'bbbee_verification_date'    => 'BBBEE verification/expiry date',
            'main_client_id'             => 'FK → clients (parent client for sub-clients)',
            'contact_person'             => 'Primary contact person name',
            'contact_person_email'       => 'Contact email',
            'contact_person_cellphone'   => 'Contact cell number',
            'contact_person_tel'         => 'Contact landline',
            'contact_person_position'    => 'Contact job title',
            'deleted_at'                 => 'Soft-delete timestamp',
            'created_at'                 => 'Record creation timestamp',
            'updated_at'                 => 'Record last update timestamp',
        ],
    ],

    'sites' => [
        'description' => 'Training sites/venues belonging to clients. Can be hierarchical (head site → sub-sites).',
        'primary_key' => 'site_id',
        'columns' => [
            'site_id'        => 'Auto-increment primary key',
            'client_id'      => 'FK → clients',
            'site_name'      => 'Site/venue name',
            'parent_site_id' => 'FK → sites (parent site for sub-sites, null for head sites)',
            'place_id'       => 'FK → locations',
            'created_at'     => 'Record creation timestamp',
            'updated_at'     => 'Record last update timestamp',
        ],
    ],

    'locations' => [
        'description' => 'Physical addresses/locations — linked to sites and agents',
        'primary_key' => 'location_id',
        'columns' => [
            'location_id'    => 'Auto-increment primary key',
            'street_address' => 'Full street address (text)',
            'suburb'         => 'Suburb name',
            'town'           => 'Town/city name',
            'province'       => 'Province name',
            'postal_code'    => 'Postal code',
            'longitude'      => 'GPS longitude',
            'latitude'       => 'GPS latitude',
            'created_at'     => 'Record creation timestamp',
            'updated_at'     => 'Record last update timestamp',
        ],
    ],

    'client_communications' => [
        'description' => 'Communication log between WeCoza and clients',
        'primary_key' => 'communication_id',
        'columns' => [
            'communication_id'   => 'Auto-increment primary key',
            'client_id'          => 'FK → clients',
            'communication_type' => 'Type of communication (email, phone, meeting, etc.)',
            'subject'            => 'Communication subject',
            'content'            => 'Communication content/body',
            'communication_date' => 'When the communication occurred',
            'user_id'            => 'WP user who logged it',
            'site_id'            => 'FK → sites (if site-specific)',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * VIEWS (read-only, pre-joined for convenience)
     * ═══════════════════════════════════════════════════════════ */

    'v_client_head_sites' => [
        'description' => 'VIEW: Client head sites with full location details. Use instead of joining sites + locations + clients for head offices.',
        'primary_key' => 'site_id',
        'columns' => [
            'site_id'        => 'Site ID',
            'client_id'      => 'Client ID',
            'client_name'    => 'Client company name',
            'site_name'      => 'Site name',
            'place_id'       => 'Location ID',
            'street_address' => 'Full street address',
            'suburb'         => 'Suburb',
            'town'           => 'Town/city',
            'province'       => 'Province',
            'postal_code'    => 'Postal code',
            'longitude'      => 'GPS longitude',
            'latitude'       => 'GPS latitude',
            'created_at'     => 'Record creation timestamp',
            'updated_at'     => 'Record last update timestamp',
        ],
    ],

    'v_client_sub_sites' => [
        'description' => 'VIEW: Client sub-sites with full location details and parent site name.',
        'primary_key' => 'site_id',
        'columns' => [
            'site_id'          => 'Site ID',
            'parent_site_id'   => 'Parent (head) site ID',
            'client_id'        => 'Client ID',
            'site_name'        => 'Sub-site name',
            'place_id'         => 'Location ID',
            'street_address'   => 'Full street address',
            'suburb'            => 'Suburb',
            'town'              => 'Town/city',
            'province'          => 'Province',
            'postal_code'       => 'Postal code',
            'longitude'         => 'GPS longitude',
            'latitude'          => 'GPS latitude',
            'parent_site_name' => 'Name of the parent (head) site',
            'created_at'       => 'Record creation timestamp',
            'updated_at'       => 'Record last update timestamp',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * SYSTEM TABLES (internal, rarely queried directly)
     * ═══════════════════════════════════════════════════════════ */

    'feedback_submissions' => [
        'description' => 'User feedback submissions with AI conversation history',
        'primary_key' => 'id',
        'columns' => [
            'id'                     => 'Auto-increment primary key',
            'user_id'                => 'WP user ID',
            'user_email'             => 'User email',
            'category'               => 'Feedback category',
            'feedback_text'          => 'Feedback content',
            'ai_conversation'        => 'JSONB AI conversation history',
            'ai_generated_title'     => 'AI-generated title summary',
            'ai_suggested_priority'  => 'AI-suggested priority level',
            'page_url'               => 'URL where feedback was submitted',
            'page_title'             => 'Page title',
            'shortcode'              => 'Active shortcode on the page',
            'is_resolved'            => 'Resolved flag',
            'trello_card_id'         => 'Linked Trello card ID',
            'trello_card_url'        => 'Linked Trello card URL',
            'created_at'             => 'Record creation timestamp',
            'updated_at'             => 'Record last update timestamp',
        ],
    ],

    'feedback_comments' => [
        'description' => 'Comments on feedback submissions',
        'primary_key' => 'id',
        'columns' => [
            'id'           => 'Auto-increment primary key',
            'feedback_id'  => 'FK → feedback_submissions',
            'author_email' => 'Comment author email',
            'comment_text' => 'Comment content',
            'created_at'   => 'Record creation timestamp',
        ],
    ],

    'saved_queries' => [
        'description' => 'NLQ saved queries — stores SQL queries generated by the AI for reuse via shortcodes',
        'primary_key' => 'id',
        'columns' => [
            'id'               => 'Auto-increment primary key',
            'query_name'       => 'Human-readable query name',
            'query_slug'       => 'URL-friendly slug (unique)',
            'description'      => 'What the query shows',
            'natural_language'  => 'Original NL question from user',
            'sql_query'        => 'The SQL query (SELECT only)',
            'category'         => 'Organizational category',
            'is_active'        => 'Active flag',
            'created_by'       => 'WP user ID',
            'updated_by'       => 'WP user ID',
            'created_at'       => 'Record creation timestamp',
            'updated_at'       => 'Record last update timestamp',
            'last_executed'    => 'Last execution timestamp',
            'execution_count'  => 'Total times executed',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
     * RELATIONSHIPS (for AI context on JOINs)
     * ═══════════════════════════════════════════════════════════ */

    '_relationships' => [
        'classes.client_id → clients.client_id',
        'classes.site_id → sites.site_id',
        'classes.class_agent → agents.agent_id',
        'classes.project_supervisor_id → agents.agent_id',
        'classes.initial_class_agent → agents.agent_id',
        'sites.client_id → clients.client_id',
        'sites.parent_site_id → sites.site_id',
        'sites.place_id → locations.location_id',
        'agents.location_id → locations.location_id',
        'agent_orders.agent_id → agents.agent_id',
        'agent_orders.class_id → classes.class_id',
        'agent_monthly_invoices.agent_id → agents.agent_id',
        'agent_monthly_invoices.class_id → classes.class_id',
        'agent_monthly_invoices.order_id → agent_orders.order_id',
        'learners.employer_id → employers.employer_id',
        'learners.highest_qualification → learner_qualifications.id',
        'learner_lp_tracking.learner_id → learners.id',
        'learner_lp_tracking.class_id → classes.class_id',
        'learner_lp_tracking.class_type_subject_id → class_type_subjects.class_type_subject_id',
        'learner_hours_log.learner_id → learners.id',
        'learner_hours_log.class_id → classes.class_id',
        'learner_hours_log.tracking_id → learner_lp_tracking.tracking_id',
        'learner_hours_log.session_id → class_attendance_sessions.session_id',
        'learner_sponsors.learner_id → learners.id',
        'learner_sponsors.employer_id → employers.employer_id',
        'learner_portfolios.learner_id → learners.id',
        'learner_progression_portfolios.tracking_id → learner_lp_tracking.tracking_id',
        'class_type_subjects.class_type_id → class_types.class_type_id',
        'class_attendance_sessions.class_id → classes.class_id',
        'class_material_tracking.class_id → classes.class_id',
        'class_status_history.class_id → classes.class_id',
        'class_events.entity_id → (polymorphic, depends on entity_type)',
        'qa_visits.class_id → classes.class_id',
        'client_communications.client_id → clients.client_id',
        'client_communications.site_id → sites.site_id',
        'feedback_comments.feedback_id → feedback_submissions.id',
    ],
];
