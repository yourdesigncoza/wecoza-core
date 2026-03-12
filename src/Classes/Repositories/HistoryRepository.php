<?php
declare(strict_types=1);

/**
 * WeCoza Core - History Repository
 *
 * Queries existing database tables to reconstruct entity relationship
 * timelines for classes, learners, agents, and clients.
 *
 * @package WeCoza\Classes\Repositories
 * @since 1.1.0
 */

namespace WeCoza\Classes\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class HistoryRepository extends BaseRepository
{
    protected static string $table = 'classes';
    protected static string $primaryKey = 'class_id';

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security)
    |--------------------------------------------------------------------------
    */

    protected function getAllowedOrderColumns(): array
    {
        return ['class_id', 'created_at', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['class_id', 'client_id', 'class_agent', 'class_status'];
    }

    /*
    |--------------------------------------------------------------------------
    | Class History
    |--------------------------------------------------------------------------
    */

    /**
     * Get full relationship timeline for a class.
     *
     * Returns:
     *  - agent_assignments: primary & backup agent info
     *  - learner_assignments: learner IDs from JSONB
     *  - status_changes: from class_status_history table
     *  - stop_restart_dates: from JSONB column
     *
     * @param int $classId
     * @return array{agent_assignments: array, learner_assignments: array, status_changes: array, stop_restart_dates: array}
     */
    public function getClassHistory(int $classId): array
    {
        $db = $this->db();

        // 1. Get core class data (agent assignments, learner IDs, stop/restart)
        $classData = $db->getRow(
            "SELECT class_id, client_id, class_agent, initial_class_agent,
                    initial_agent_start_date, backup_agent_ids,
                    learner_ids, stop_restart_dates, class_status,
                    original_start_date, created_at, updated_at
             FROM classes
             WHERE class_id = :class_id",
            ['class_id' => $classId]
        );

        if (!$classData) {
            return [
                'agent_assignments' => [],
                'learner_assignments' => [],
                'status_changes' => [],
                'stop_restart_dates' => [],
            ];
        }

        // 2. Build agent assignments timeline
        $agentAssignments = $this->buildAgentAssignments($classData);

        // 3. Parse learner IDs from JSONB
        //    learner_ids is array of objects: [{id, name, level, status}, ...]
        $learnerEntries = $this->decodeJsonb($classData['learner_ids'] ?? '[]');
        $learnerAssignments = [];
        foreach ($learnerEntries as $entry) {
            if (is_array($entry) && isset($entry['id'])) {
                $learnerAssignments[] = [
                    'learner_id' => (int) $entry['id'],
                    'class_id' => $classId,
                    'level' => $entry['level'] ?? null,
                    'status' => $entry['status'] ?? null,
                ];
            } elseif (is_numeric($entry)) {
                // Fallback: flat array of IDs
                $learnerAssignments[] = [
                    'learner_id' => (int) $entry,
                    'class_id' => $classId,
                    'level' => null,
                    'status' => null,
                ];
            }
        }

        // 4. Fetch status changes from class_status_history
        $statusChanges = $db->getAll(
            "SELECT id, class_id, old_status, new_status, reason, notes,
                    changed_by, changed_at
             FROM class_status_history
             WHERE class_id = :class_id
             ORDER BY changed_at ASC",
            ['class_id' => $classId]
        ) ?: [];

        // 5. Parse stop/restart dates from JSONB
        $stopRestartDates = $this->decodeJsonb($classData['stop_restart_dates'] ?? '[]');

        return [
            'agent_assignments' => $agentAssignments,
            'learner_assignments' => $learnerAssignments,
            'status_changes' => $statusChanges,
            'stop_restart_dates' => $stopRestartDates,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Learner History
    |--------------------------------------------------------------------------
    */

    /**
     * Get relationship timeline for a learner.
     *
     * Returns:
     *  - class_enrollments: LP tracking records with class info
     *  - hours_logged: from learner_hours_log
     *
     * @param int $learnerId
     * @return array{class_enrollments: array, hours_logged: array}
     */
    public function getLearnerHistory(int $learnerId): array
    {
        $db = $this->db();

        // 1. Class enrollments via LP tracking
        $enrollments = $db->getAll(
            "SELECT lt.tracking_id, lt.learner_id, lt.class_id,
                    lt.class_type_subject_id, lt.status, lt.start_date,
                    lt.completion_date, lt.hours_trained, lt.hours_present,
                    lt.hours_absent, lt.created_at, lt.updated_at,
                    c.class_type, c.class_subject, c.class_code,
                    c.client_id, c.class_status AS current_class_status
             FROM learner_lp_tracking lt
             LEFT JOIN classes c ON c.class_id = lt.class_id
             WHERE lt.learner_id = :learner_id
             ORDER BY lt.start_date DESC",
            ['learner_id' => $learnerId]
        ) ?: [];

        // 2. Hours logged
        $hoursLogged = $db->getAll(
            "SELECT log_id, learner_id, class_id, class_type_subject_id,
                    tracking_id, log_date, hours_trained, hours_present,
                    source, session_id, created_by, notes, created_at
             FROM learner_hours_log
             WHERE learner_id = :learner_id
             ORDER BY log_date DESC",
            ['learner_id' => $learnerId]
        ) ?: [];

        return [
            'class_enrollments' => $enrollments,
            'hours_logged' => $hoursLogged,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Agent History
    |--------------------------------------------------------------------------
    */

    /**
     * Get relationship timeline for an agent.
     *
     * Returns:
     *  - primary_classes: classes where agent is primary
     *  - backup_classes: classes where agent is in backup_agent_ids JSONB
     *  - notes: from agent_notes table
     *  - absences: from agent_absences table
     *
     * @param int $agentId
     * @return array{primary_classes: array, backup_classes: array, notes: array, absences: array}
     */
    public function getAgentHistory(int $agentId): array
    {
        $db = $this->db();

        // 1. Classes where agent is primary
        $primaryClasses = $db->getAll(
            "SELECT class_id, client_id, class_type, class_subject, class_code,
                    original_start_date, class_status, created_at
             FROM classes
             WHERE class_agent = :agent_id
             ORDER BY original_start_date DESC",
            ['agent_id' => $agentId]
        ) ?: [];

        // 2. Classes where agent is in backup_agent_ids JSONB
        //    backup_agent_ids is array of objects: [{agent_id: N, date: "..."}, ...]
        $backupClasses = $db->getAll(
            "SELECT class_id, client_id, class_type, class_subject, class_code,
                    original_start_date, class_status, created_at, backup_agent_ids
             FROM classes
             WHERE EXISTS (
                 SELECT 1 FROM jsonb_array_elements(backup_agent_ids) elem
                 WHERE (elem->>'agent_id')::int = :agent_id
             )",
            ['agent_id' => $agentId]
        ) ?: [];

        // 3. Agent notes
        $notes = $db->getAll(
            "SELECT * FROM agent_notes
             WHERE agent_id = :agent_id
             ORDER BY created_at DESC",
            ['agent_id' => $agentId]
        ) ?: [];

        // 4. Agent absences
        $absences = $db->getAll(
            "SELECT * FROM agent_absences
             WHERE agent_id = :agent_id
             ORDER BY created_at DESC",
            ['agent_id' => $agentId]
        ) ?: [];

        return [
            'primary_classes' => $primaryClasses,
            'backup_classes' => $backupClasses,
            'notes' => $notes,
            'absences' => $absences,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Client History
    |--------------------------------------------------------------------------
    */

    /**
     * Get relationship timeline for a client.
     *
     * Returns:
     *  - classes: classes belonging to this client
     *  - locations: locations associated via classes.site_id → locations
     *
     * @param int $clientId
     * @return array{classes: array, locations: array}
     */
    public function getClientHistory(int $clientId): array
    {
        $db = $this->db();

        // 1. Classes for this client
        $classes = $db->getAll(
            "SELECT class_id, class_type, class_subject, class_code,
                    class_agent, original_start_date, class_status,
                    site_id, created_at
             FROM classes
             WHERE client_id = :client_id
             ORDER BY original_start_date DESC",
            ['client_id' => $clientId]
        ) ?: [];

        // 2. Distinct sites/locations used by this client's classes
        //    sites → place_id → locations for address data
        $locations = $db->getAll(
            "SELECT DISTINCT s.site_id, s.site_name, s.client_id,
                    l.street_address, l.suburb, l.town, l.province, l.postal_code
             FROM sites s
             INNER JOIN classes c ON c.site_id = s.site_id
             LEFT JOIN locations l ON l.location_id = s.place_id
             WHERE c.client_id = :client_id
             ORDER BY s.site_name ASC",
            ['client_id' => $clientId]
        ) ?: [];

        return [
            'classes' => $classes,
            'locations' => $locations,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Agent-Class History Table Queries
    |--------------------------------------------------------------------------
    */

    /**
     * Get agent-class assignment history from the dedicated history table.
     *
     * @param int $classId
     * @return array
     */
    public function getAgentClassHistory(int $classId): array
    {
        $db = $this->db();

        $history = $db->getAll(
            "SELECT id, class_id, agent_id, assignment_type,
                    assigned_date, removed_date, changed_by, created_at
             FROM agent_class_history
             WHERE class_id = :class_id
             ORDER BY assigned_date ASC, created_at ASC",
            ['class_id' => $classId]
        );

        return $history ?: [];
    }

    /**
     * Get all class assignments for an agent from the history table.
     *
     * @param int $agentId
     * @return array
     */
    public function getAgentClassHistoryByAgent(int $agentId): array
    {
        $db = $this->db();

        $history = $db->getAll(
            "SELECT id, class_id, agent_id, assignment_type,
                    assigned_date, removed_date, changed_by, created_at
             FROM agent_class_history
             WHERE agent_id = :agent_id
             ORDER BY assigned_date DESC, created_at DESC",
            ['agent_id' => $agentId]
        );

        return $history ?: [];
    }

    /*
    |--------------------------------------------------------------------------
    | Class Extensions (QA Visits, Events, Notes)
    |--------------------------------------------------------------------------
    */

    /**
     * Get QA visit records for a class.
     *
     * @param int $classId
     * @return array
     */
    public function getClassQAVisits(int $classId): array
    {
        $db = $this->db();

        $visits = $db->getAll(
            "SELECT id, class_id, visit_date, visit_type, officer_name,
                    latest_document, created_at
             FROM qa_visits
             WHERE class_id = :class_id
             ORDER BY visit_date DESC",
            ['class_id' => $classId]
        );

        return $visits ?: [];
    }

    /**
     * Get event history for a class from events_log table.
     *
     * @param int $classId
     * @return array
     */
    public function getClassEvents(int $classId): array
    {
        $db = $this->db();

        $events = $db->getAll(
            "SELECT id, event_name, occurred_at, actor_id, processed,
                    processed_at, created_at
             FROM wecoza_events.events_log
             WHERE class_id = :class_id
             ORDER BY occurred_at DESC",
            ['class_id' => $classId]
        );

        return $events ?: [];
    }

    /**
     * Get class notes from classes.class_notes_data JSONB.
     *
     * @param int $classId
     * @return array Parsed notes array
     */
    public function getClassNotes(int $classId): array
    {
        $db = $this->db();

        $row = $db->getRow(
            "SELECT class_notes_data FROM classes WHERE class_id = :class_id",
            ['class_id' => $classId]
        );

        if (!$row) {
            return [];
        }

        return $this->decodeJsonb($row['class_notes_data'] ?? '[]');
    }

    /*
    |--------------------------------------------------------------------------
    | Learner Extensions (Portfolios, Progression Dates)
    |--------------------------------------------------------------------------
    */

    /**
     * Get portfolio files for a learner with LP tracking context.
     *
     * @param int $learnerId
     * @return array
     */
    public function getLearnerPortfolios(int $learnerId): array
    {
        $db = $this->db();

        $portfolios = $db->getAll(
            "SELECT p.file_id, p.tracking_id, p.file_name, p.file_path,
                    p.file_type, p.file_size, p.uploaded_by, p.uploaded_at,
                    lt.class_type_subject_id, lt.class_id, lt.status AS lp_status,
                    c.class_type, c.class_subject
             FROM learner_progression_portfolios p
             INNER JOIN learner_lp_tracking lt ON lt.tracking_id = p.tracking_id
             LEFT JOIN classes c ON c.class_id = lt.class_id
             WHERE lt.learner_id = :learner_id
             ORDER BY p.uploaded_at DESC",
            ['learner_id' => $learnerId]
        );

        return $portfolios ?: [];
    }

    /**
     * Get LP progression dates (start/completion) for a learner.
     *
     * Returns one row per LP tracking entry with dates and status,
     * plus class type/subject for context.
     *
     * @param int $learnerId
     * @return array
     */
    public function getLearnerProgressionDates(int $learnerId): array
    {
        $db = $this->db();

        $dates = $db->getAll(
            "SELECT lt.tracking_id, lt.class_id, lt.class_type_subject_id,
                    lt.status, lt.start_date, lt.completion_date,
                    lt.hours_trained, lt.hours_present, lt.hours_absent,
                    c.class_type, c.class_subject, c.class_code, c.client_id
             FROM learner_lp_tracking lt
             LEFT JOIN classes c ON c.class_id = lt.class_id
             WHERE lt.learner_id = :learner_id
             ORDER BY lt.start_date DESC",
            ['learner_id' => $learnerId]
        );

        return $dates ?: [];
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Extensions (QA Visits, Subjects)
    |--------------------------------------------------------------------------
    */

    /**
     * Get QA visit records across all classes this agent facilitates.
     *
     * @param int $agentId
     * @return array
     */
    public function getAgentQAVisits(int $agentId): array
    {
        $db = $this->db();

        $visits = $db->getAll(
            "SELECT qv.id, qv.class_id, qv.visit_date, qv.visit_type,
                    qv.officer_name, qv.latest_document, qv.created_at,
                    c.class_type, c.class_subject, c.class_code
             FROM qa_visits qv
             INNER JOIN classes c ON c.class_id = qv.class_id
             WHERE c.class_agent = :agent_id
             ORDER BY qv.visit_date DESC",
            ['agent_id' => $agentId]
        );

        return $visits ?: [];
    }

    /**
     * Get distinct subjects/levels/modules an agent has facilitated.
     *
     * @param int $agentId
     * @return array
     */
    public function getAgentSubjects(int $agentId): array
    {
        $db = $this->db();

        $subjects = $db->getAll(
            "SELECT DISTINCT c.class_type, c.class_subject,
                    MIN(c.original_start_date) AS first_facilitated,
                    MAX(c.original_start_date) AS last_facilitated,
                    COUNT(*) AS class_count
             FROM classes c
             WHERE c.class_agent = :agent_id
               AND c.class_type IS NOT NULL
             GROUP BY c.class_type, c.class_subject
             ORDER BY last_facilitated DESC",
            ['agent_id' => $agentId]
        );

        return $subjects ?: [];
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Build agent assignments array from class data.
     *
     * @param array $classData
     * @return array
     */
    private function buildAgentAssignments(array $classData): array
    {
        $assignments = [];

        // Initial agent assignment
        if (!empty($classData['initial_class_agent'])) {
            $assignments[] = [
                'agent_id' => (int) $classData['initial_class_agent'],
                'assignment_type' => 'initial',
                'assigned_date' => $classData['initial_agent_start_date'] ?? $classData['original_start_date'],
                'class_id' => (int) $classData['class_id'],
            ];
        }

        // Current primary agent (if different from initial)
        if (!empty($classData['class_agent'])) {
            $currentAgentId = (int) $classData['class_agent'];
            $initialAgentId = !empty($classData['initial_class_agent'])
                ? (int) $classData['initial_class_agent']
                : null;

            if ($currentAgentId !== $initialAgentId) {
                $assignments[] = [
                    'agent_id' => $currentAgentId,
                    'assignment_type' => 'primary',
                    'assigned_date' => null, // exact date unknown from current schema
                    'class_id' => (int) $classData['class_id'],
                ];
            } else {
                // Same agent — mark initial as also primary
                if (!empty($assignments)) {
                    $assignments[0]['assignment_type'] = 'primary';
                }
            }
        }

        // Backup agents from JSONB (array of {agent_id, date} objects)
        $backupEntries = $this->decodeJsonb($classData['backup_agent_ids'] ?? '[]');
        foreach ($backupEntries as $entry) {
            if (is_array($entry) && isset($entry['agent_id'])) {
                $assignments[] = [
                    'agent_id' => (int) $entry['agent_id'],
                    'assignment_type' => 'backup',
                    'assigned_date' => $entry['date'] ?? null,
                    'class_id' => (int) $classData['class_id'],
                ];
            } elseif (is_numeric($entry)) {
                // Fallback: flat array of IDs
                $assignments[] = [
                    'agent_id' => (int) $entry,
                    'assignment_type' => 'backup',
                    'assigned_date' => null,
                    'class_id' => (int) $classData['class_id'],
                ];
            }
        }

        return $assignments;
    }

    /**
     * Safely decode a JSONB column value.
     *
     * @param mixed $value
     * @return array
     */
    private function decodeJsonb(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
