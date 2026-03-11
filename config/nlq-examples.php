<?php
/**
 * NLQ Few-Shot Examples
 *
 * Curated natural language → SQL examples that teach the AI:
 *   - Exact column names (class_subject NOT subject)
 *   - Correct JOIN patterns with explicit keys and aliases
 *   - Proper table aliases (consistent across all examples)
 *   - WeCoza-specific conventions (agent_id not id, etc.)
 *   - JSONB operations for array/object columns
 *   - Common mistakes to avoid (failure examples)
 *
 * These are injected into the system prompt as few-shot examples.
 * Add/update examples when new query patterns are needed.
 *
 * STANDARD ALIASES:
 *   agents → a, agent_orders → ao, agent_monthly_invoices → ami
 *   learners → l, learner_lp_tracking → llt, learner_hours_log → lhl
 *   classes → c, class_types → ct, class_type_subjects → cts
 *   class_attendance_sessions → cas, class_material_tracking → cmt
 *   class_status_history → csh, class_events → ce
 *   clients → cl, sites → s, locations → loc, employers → e
 *   qa_visits → qv, client_communications → cc
 *
 * @package WeCoza\NLQ
 * @since 1.0.0
 */

return [

    /* ═══════════════════════════════════════════════════════════
     * FAILURE EXAMPLE — teaches what NOT to do
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show me the subject and code for each class',
        'reformulated'=> 'WRONG: SELECT subject, code FROM classes — columns "subject" and "code" do not exist. CORRECT: Use exact column names class_subject and class_code from the classes table. Use Tables: classes (alias c)',
        'sql'         => "SELECT c.class_id, c.class_code, c.class_subject FROM classes c ORDER BY c.class_code ASC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * AGENTS MODULE
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show me all active agents with their name, email and phone number',
        'reformulated'=> 'List all agents where status is active, showing agent_id, first_name, surname, email_address, tel_number. Use Tables: agents (alias a)',
        'sql'         => "SELECT a.agent_id, a.first_name, a.surname, a.email_address, a.tel_number FROM agents a WHERE a.status = 'active' ORDER BY a.surname ASC LIMIT 100;",
    ],
    [
        'input'       => 'Show all agent orders with their hourly rate, class code, subject, and start date',
        'reformulated'=> 'List all agent_orders joined with agents and classes, showing agent name, rate_type, rate_amount, class_code, class_subject, start_date. Join agent_orders ao → agents a ON ao.agent_id = a.agent_id. Join agent_orders ao → classes c ON ao.class_id = c.class_id. Use Tables: agent_orders (alias ao), agents (alias a), classes (alias c)',
        'sql'         => "SELECT ao.order_id, a.first_name || ' ' || a.surname AS agent_name, ao.rate_type, ao.rate_amount, c.class_code, c.class_subject, ao.start_date FROM agent_orders ao JOIN agents a ON ao.agent_id = a.agent_id JOIN classes c ON ao.class_id = c.class_id ORDER BY ao.rate_amount DESC LIMIT 100;",
    ],
    [
        'input'       => 'Which agents have invoices this month and what are their payable hours?',
        'reformulated'=> 'List agent_monthly_invoices for current month joined with agents and classes, showing agent name, class_code, invoice_month, calculated_payable_hours, status. Join agent_monthly_invoices ami → agents a ON ami.agent_id = a.agent_id. Join agent_monthly_invoices ami → classes c ON ami.class_id = c.class_id. Use Tables: agent_monthly_invoices (alias ami), agents (alias a), classes (alias c)',
        'sql'         => "SELECT a.first_name || ' ' || a.surname AS agent_name, c.class_code, ami.invoice_month, ami.calculated_payable_hours, ami.agent_claimed_hours, ami.discrepancy_hours, ami.status FROM agent_monthly_invoices ami JOIN agents a ON ami.agent_id = a.agent_id JOIN classes c ON ami.class_id = c.class_id WHERE ami.invoice_month >= date_trunc('month', CURRENT_DATE) ORDER BY ami.invoice_month DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * LEARNERS MODULE
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Which learners have attendance below 80% in their current learning programme?',
        'reformulated'=> 'List learners from learner_lp_tracking where hours_present / hours_trained < 0.8 and status is in_progress, joined with learners and class_type_subjects for names and subject_name. Join learner_lp_tracking llt → learners l ON llt.learner_id = l.id. Join learner_lp_tracking llt → class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id. Use Tables: learner_lp_tracking (alias llt), learners (alias l), class_type_subjects (alias cts)',
        'sql'         => "SELECT l.id AS learner_id, l.first_name, l.surname, cts.subject_name, llt.hours_present, llt.hours_trained, ROUND(llt.hours_present / NULLIF(llt.hours_trained, 0) * 100, 1) AS attendance_pct FROM learner_lp_tracking llt JOIN learners l ON llt.learner_id = l.id JOIN class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id WHERE llt.hours_trained > 0 AND llt.status = 'in_progress' AND (llt.hours_present / NULLIF(llt.hours_trained, 0)) < 0.8 ORDER BY attendance_pct ASC LIMIT 100;",
    ],
    [
        'input'       => 'Show learners who completed their LP with their completion date',
        'reformulated'=> 'List learner_lp_tracking where status is completed, joined with learners and class_type_subjects, showing learner name, subject_name, completion_date. Join learner_lp_tracking llt → learners l ON llt.learner_id = l.id. Join learner_lp_tracking llt → class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id. Use Tables: learner_lp_tracking (alias llt), learners (alias l), class_type_subjects (alias cts)',
        'sql'         => "SELECT l.first_name, l.surname, cts.subject_name, llt.completion_date, llt.hours_present, llt.hours_trained FROM learner_lp_tracking llt JOIN learners l ON llt.learner_id = l.id JOIN class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id WHERE llt.status = 'completed' ORDER BY llt.completion_date DESC LIMIT 100;",
    ],
    [
        'input'       => 'List learners with their employer name',
        'reformulated'=> 'List learners joined with employers on employer_id, showing learner name and employer_name. Join learners l → employers e ON l.employer_id = e.employer_id. Use Tables: learners (alias l), employers (alias e)',
        'sql'         => "SELECT l.id, l.first_name, l.surname, e.employer_name FROM learners l LEFT JOIN employers e ON l.employer_id = e.employer_id ORDER BY l.surname ASC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * CLASSES MODULE
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show all classes starting this month with their class code and subject',
        'reformulated'=> 'List classes where original_start_date is in the current month, showing class_id, class_code, class_subject, original_start_date. Use Tables: classes (alias c)',
        'sql'         => "SELECT c.class_id, c.class_code, c.class_subject, c.class_type, c.original_start_date, c.class_duration FROM classes c WHERE c.original_start_date >= date_trunc('month', CURRENT_DATE) AND c.original_start_date < date_trunc('month', CURRENT_DATE) + INTERVAL '1 month' ORDER BY c.original_start_date ASC LIMIT 100;",
    ],
    [
        'input'       => 'Show the history of class status changes with the class code',
        'reformulated'=> 'List class_status_history joined with classes, showing class_code, old_status, new_status, reason, changed_at. Join class_status_history csh → classes c ON csh.class_id = c.class_id. Use Tables: class_status_history (alias csh), classes (alias c)',
        'sql'         => "SELECT c.class_code, csh.old_status, csh.new_status, csh.reason, csh.notes, csh.changed_at FROM class_status_history csh JOIN classes c ON csh.class_id = c.class_id ORDER BY csh.changed_at DESC LIMIT 100;",
    ],
    [
        'input'       => 'Which classes have outstanding material delivery?',
        'reformulated'=> 'List class_material_tracking where delivery_status is not delivered, joined with classes for class_code and class_subject. Join class_material_tracking cmt → classes c ON cmt.class_id = c.class_id. Use Tables: class_material_tracking (alias cmt), classes (alias c)',
        'sql'         => "SELECT cmt.id, c.class_code, c.class_subject, cmt.notification_type, cmt.delivery_status, cmt.notification_sent_at, c.original_start_date FROM class_material_tracking cmt JOIN classes c ON cmt.class_id = c.class_id WHERE cmt.delivery_status <> 'delivered' ORDER BY cmt.notification_type DESC, c.original_start_date ASC LIMIT 100;",
    ],
    [
        'input'       => 'Show me classes with their agent name and client name',
        'reformulated'=> 'List classes joined with agents on class_agent = agent_id and clients on client_id, showing class_code, class_subject, agent name, client_name. Join classes c → agents a ON c.class_agent = a.agent_id. Join classes c → clients cl ON c.client_id = cl.client_id. Use Tables: classes (alias c), agents (alias a), clients (alias cl)',
        'sql'         => "SELECT c.class_code, c.class_subject, a.first_name || ' ' || a.surname AS agent_name, cl.client_name, c.original_start_date FROM classes c LEFT JOIN agents a ON c.class_agent = a.agent_id LEFT JOIN clients cl ON c.client_id = cl.client_id ORDER BY c.original_start_date DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * CLIENTS MODULE
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show all clients with their contact person and email',
        'reformulated'=> 'List clients showing client_name, contact_person, contact_person_email, client_status. Use Tables: clients (alias cl)',
        'sql'         => "SELECT cl.client_id, cl.client_name, cl.contact_person, cl.contact_person_email, cl.contact_person_cellphone, cl.client_status FROM clients cl WHERE cl.deleted_at IS NULL ORDER BY cl.client_name ASC LIMIT 100;",
    ],
    [
        'input'       => 'Which sites belong to each client with the site address?',
        'reformulated'=> 'List sites joined with clients and locations via place_id = location_id, showing client_name, site_name, street_address, town, province. Join sites s → clients cl ON s.client_id = cl.client_id. Join sites s → locations loc ON s.place_id = loc.location_id. Use Tables: sites (alias s), clients (alias cl), locations (alias loc)',
        'sql'         => "SELECT cl.client_name, s.site_name, loc.street_address, loc.town, loc.province FROM sites s JOIN clients cl ON s.client_id = cl.client_id LEFT JOIN locations loc ON s.place_id = loc.location_id ORDER BY cl.client_name, s.site_name LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * JSONB EXAMPLES — teaches correct JSONB operations
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show attendance sessions with class code and how many learners attended',
        'reformulated'=> 'List class_attendance_sessions joined with classes, showing class_code, session_date, status, scheduled_hours, and count of entries in learner_data JSONB array using jsonb_array_length(). Join class_attendance_sessions cas → classes c ON cas.class_id = c.class_id. Use Tables: class_attendance_sessions (alias cas), classes (alias c)',
        'sql'         => "SELECT c.class_code, cas.session_date, cas.status, cas.scheduled_hours, jsonb_array_length(cas.learner_data) AS learners_recorded FROM class_attendance_sessions cas JOIN classes c ON cas.class_id = c.class_id WHERE cas.learner_data IS NOT NULL ORDER BY cas.session_date DESC LIMIT 100;",
    ],
    [
        'input'       => 'How many learners are enrolled in each class?',
        'reformulated'=> 'List classes showing class_code, class_subject, and count of entries in learner_ids JSONB array using jsonb_array_length(). Use Tables: classes (alias c)',
        'sql'         => "SELECT c.class_id, c.class_code, c.class_subject, jsonb_array_length(c.learner_ids) AS enrolled_count FROM classes c WHERE c.learner_ids IS NOT NULL ORDER BY enrolled_count DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * CROSS-MODULE — demonstrates multi-table joins
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show classes with agent name, client name, and site location',
        'reformulated'=> 'List classes joined with agents, clients, sites, and locations for a full overview. Join classes c → agents a ON c.class_agent = a.agent_id. Join classes c → clients cl ON c.client_id = cl.client_id. Join classes c → sites s ON c.site_id = s.site_id. Join sites s → locations loc ON s.place_id = loc.location_id. Use Tables: classes (alias c), agents (alias a), clients (alias cl), sites (alias s), locations (alias loc)',
        'sql'         => "SELECT c.class_code, c.class_subject, a.first_name || ' ' || a.surname AS agent_name, cl.client_name, s.site_name, loc.town, loc.province FROM classes c LEFT JOIN agents a ON c.class_agent = a.agent_id LEFT JOIN clients cl ON c.client_id = cl.client_id LEFT JOIN sites s ON c.site_id = s.site_id LEFT JOIN locations loc ON s.place_id = loc.location_id ORDER BY c.original_start_date DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * GROUP BY / AGGREGATION — teaches correct grouping
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'How many classes does each agent have?',
        'reformulated'=> 'Count classes grouped by agent, joined with agents for agent name. Every non-aggregated column must be in GROUP BY. Join classes c → agents a ON c.class_agent = a.agent_id. Use Tables: classes (alias c), agents (alias a)',
        'sql'         => "SELECT a.agent_id, a.first_name, a.surname, COUNT(c.class_id) AS class_count FROM classes c JOIN agents a ON c.class_agent = a.agent_id GROUP BY a.agent_id, a.first_name, a.surname ORDER BY class_count DESC LIMIT 100;",
    ],
    [
        'input'       => 'How many learners are in each class type?',
        'reformulated'=> 'Count learner_lp_tracking records grouped by class_type_subjects subject_name, joined with class_type_subjects for the subject name. All non-aggregated columns in GROUP BY. Join learner_lp_tracking llt → class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id. Use Tables: learner_lp_tracking (alias llt), class_type_subjects (alias cts)',
        'sql'         => "SELECT cts.subject_name, COUNT(DISTINCT llt.learner_id) AS learner_count FROM learner_lp_tracking llt JOIN class_type_subjects cts ON llt.class_type_subject_id = cts.class_type_subject_id GROUP BY cts.subject_name ORDER BY learner_count DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * JSONB SEARCH — teaches querying inside JSONB arrays
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Which classes is learner 5 enrolled in?',
        'reformulated'=> 'Search inside the learner_ids JSONB array column in classes using the @> containment operator to find classes containing learner ID 5. Use Tables: classes (alias c)',
        'sql'         => "SELECT c.class_id, c.class_code, c.class_subject, c.original_start_date FROM classes c WHERE c.learner_ids @> '5'::jsonb ORDER BY c.original_start_date DESC LIMIT 100;",
    ],

    /* ═══════════════════════════════════════════════════════════
     * DATE INTERVALS — teaches relative date filtering
     * ═══════════════════════════════════════════════════════════ */

    [
        'input'       => 'Show classes that started last week',
        'reformulated'=> 'List classes where original_start_date is between 7 days ago and today using CURRENT_DATE and INTERVAL. Use Tables: classes (alias c)',
        'sql'         => "SELECT c.class_id, c.class_code, c.class_subject, c.original_start_date FROM classes c WHERE c.original_start_date >= CURRENT_DATE - INTERVAL '7 days' AND c.original_start_date < CURRENT_DATE ORDER BY c.original_start_date DESC LIMIT 100;",
    ],
    [
        'input'       => 'Show attendance sessions from this week',
        'reformulated'=> 'List class_attendance_sessions where session_date is in the current week using date_trunc for week boundary. Join class_attendance_sessions cas → classes c ON cas.class_id = c.class_id. Use Tables: class_attendance_sessions (alias cas), classes (alias c)',
        'sql'         => "SELECT c.class_code, cas.session_date, cas.status, cas.scheduled_hours FROM class_attendance_sessions cas JOIN classes c ON cas.class_id = c.class_id WHERE cas.session_date >= date_trunc('week', CURRENT_DATE) ORDER BY cas.session_date DESC LIMIT 100;",
    ],
];
