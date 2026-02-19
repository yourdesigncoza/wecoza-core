<?php
declare(strict_types=1);

/**
 * WeCoza Core - Class Repository
 *
 * Repository for fetching class-related reference data from database.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Repositories
 * @since 1.0.0
 */

namespace WeCoza\Classes\Repositories;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Classes\Models\ClassModel;
use WeCoza\Classes\Models\QAVisitModel;
use WeCoza\Classes\Controllers\ClassTypesController;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ClassRepository extends BaseRepository
{
    protected static string $table = 'classes';
    protected static string $primaryKey = 'class_id';
    protected static string $modelClass = ClassModel::class;

    private const CACHE_DURATION = 12 * HOUR_IN_SECONDS;

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security)
    |--------------------------------------------------------------------------
    */

    /**
     * Columns allowed for ORDER BY
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'class_id', 'client_id', 'class_type', 'class_subject',
            'original_start_date', 'created_at', 'updated_at', 'class_code'
        ];
    }

    /**
     * Columns allowed for WHERE filtering
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'class_id', 'client_id', 'site_id', 'class_type', 'class_subject',
            'class_code', 'seta_funded', 'seta', 'exam_class', 'class_agent',
            'project_supervisor_id', 'created_at', 'updated_at'
        ];
    }

    /**
     * Columns allowed for INSERT
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'client_id', 'site_id', 'class_address_line', 'class_type',
            'class_subject', 'class_code', 'class_duration', 'original_start_date',
            'seta_funded', 'seta', 'exam_class', 'exam_type', 'class_agent',
            'initial_class_agent', 'initial_agent_start_date', 'project_supervisor_id',
            'learner_ids', 'exam_learners', 'backup_agent_ids', 'agent_replacements',
            'schedule_data', 'stop_restart_dates', 'event_dates', 'class_notes_data',
            'order_nr', 'created_at', 'updated_at'
        ];
    }

    /**
     * Columns allowed for UPDATE
     */
    protected function getAllowedUpdateColumns(): array
    {
        // Same as insert, minus created_at
        $columns = $this->getAllowedInsertColumns();
        return array_values(array_diff($columns, ['created_at']));
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations (Delegated from ClassModel)
    |--------------------------------------------------------------------------
    */

    /**
     * Insert a new class record
     *
     * @param array $data Class data
     * @return int|null The new class ID or null on failure
     */
    public function insertClass(array $data): ?int
    {
        return parent::insert($data);
    }

    /**
     * Update a class record
     *
     * @param int $id Class ID
     * @param array $data Class data
     * @return bool Success status
     */
    public function updateClass(int $id, array $data): bool
    {
        return parent::update($id, $data);
    }

    /**
     * Delete a class record
     *
     * @param int $id Class ID
     * @return bool Success status
     */
    public function deleteClass(int $id): bool
    {
        return parent::delete($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Reference Data Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get all clients ordered by name
     */
    public static function getClients(): array
    {
        // Complex query: static context, reads from clients table (not $table)
        try {
            $db = wecoza_db();
            $sql = "SELECT client_id, client_name FROM public.clients ORDER BY client_name ASC";
            $stmt = $db->query($sql);

            $clients = [];
            while ($row = $stmt->fetch()) {
                $clients[] = [
                    'id' => (int)$row['client_id'],
                    'name' => sanitize_text_field($row['client_name'])
                ];
            }

            return $clients;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching clients: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all sites grouped by client ID
     */
    public static function getSites(): array
    {
        // Complex query: static context, JOIN sites + locations tables
        try {
            $db = wecoza_db();
            $sql = "SELECT s.site_id, s.client_id, s.site_name, l.street_address as address
                    FROM public.sites s
                    LEFT JOIN public.locations l ON s.place_id = l.location_id
                    ORDER BY s.client_id ASC, s.site_name ASC";
            $stmt = $db->query($sql);

            $sites = [];
            while ($row = $stmt->fetch()) {
                $client_id = (int)$row['client_id'];

                if (!isset($sites[$client_id])) {
                    $sites[$client_id] = [];
                }

                $sites[$client_id][] = [
                    'id' => (int)$row['site_id'],
                    'name' => sanitize_text_field($row['site_name']),
                    'address' => sanitize_textarea_field($row['address'])
                ];
            }

            return $sites;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching sites: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all learners with location and progression context (cached)
     *
     * Includes:
     * - Last completed course (subject name, completion date)
     * - Current active LP (class_type_subject_id, subject_name, progress %, class_id)
     */
    public static function getLearners(): array
    {
        // Complex query: static context, dual CTE with 5-table JOIN for progression context
        try {
            $cache_key = 'wecoza_class_learners_with_progression';
            $cached_learners = get_transient($cache_key);
            if ($cached_learners !== false) {
                return $cached_learners;
            }

            $db = wecoza_db();

            // Optimized query with progression context using CTEs
            $sql = "
                WITH last_completed AS (
                    SELECT DISTINCT ON (lpt.learner_id)
                        lpt.learner_id,
                        lpt.class_type_subject_id AS last_subject_id,
                        cts.subject_name AS last_course_name,
                        lpt.completion_date AS last_completion_date
                    FROM learner_lp_tracking lpt
                    LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
                    WHERE lpt.status = 'completed'
                    ORDER BY lpt.learner_id, lpt.completion_date DESC
                ),
                active_lp AS (
                    SELECT
                        lpt.learner_id,
                        lpt.tracking_id AS active_tracking_id,
                        lpt.class_type_subject_id AS active_subject_id,
                        cts.subject_name AS active_course_name,
                        cts.subject_duration AS active_subject_duration,
                        lpt.class_id AS active_class_id,
                        c.class_code AS active_class_code,
                        lpt.hours_present AS active_hours_present,
                        lpt.start_date AS active_start_date,
                        CASE
                            WHEN cts.subject_duration > 0 THEN
                                LEAST(100, ROUND((lpt.hours_present / cts.subject_duration) * 100, 1))
                            ELSE 0
                        END AS active_progress_pct
                    FROM learner_lp_tracking lpt
                    LEFT JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
                    LEFT JOIN classes c ON lpt.class_id = c.class_id
                    WHERE lpt.status = 'in_progress'
                )
                SELECT
                    l.id,
                    l.first_name,
                    l.second_name,
                    l.initials,
                    l.surname,
                    l.sa_id_no,
                    l.passport_number,
                    l.city_town_id,
                    l.province_region_id,
                    l.postal_code,
                    loc.town AS city_town_name,
                    loc.province AS province_region_name,
                    lc.last_course_name,
                    lc.last_completion_date,
                    alp.active_tracking_id,
                    alp.active_subject_id,
                    alp.active_course_name,
                    alp.active_class_id,
                    alp.active_class_code,
                    alp.active_hours_present,
                    alp.active_subject_duration,
                    alp.active_progress_pct,
                    alp.active_start_date,
                    CASE WHEN alp.active_tracking_id IS NOT NULL THEN true ELSE false END AS has_active_lp
                FROM public.learners l
                LEFT JOIN public.locations loc ON l.city_town_id = loc.location_id
                LEFT JOIN last_completed lc ON l.id = lc.learner_id
                LEFT JOIN active_lp alp ON l.id = alp.learner_id
                WHERE l.first_name IS NOT NULL AND l.surname IS NOT NULL
                ORDER BY l.surname ASC, l.first_name ASC
            ";
            $stmt = $db->query($sql);

            $learners = [];
            while ($row = $stmt->fetch()) {
                $nameParts = array_filter([
                    trim($row['first_name'] ?? ''),
                    trim($row['second_name'] ?? ''),
                    trim($row['initials'] ?? ''),
                    trim($row['surname'] ?? '')
                ]);
                $formattedName = implode(' ', $nameParts);

                $idNumber = '';
                $idType = '';
                if (!empty($row['sa_id_no'])) {
                    $idNumber = $row['sa_id_no'];
                    $idType = 'sa_id';
                } elseif (!empty($row['passport_number'])) {
                    $idNumber = $row['passport_number'];
                    $idType = 'passport';
                }

                $learners[] = [
                    'id' => (int)$row['id'],
                    'name' => sanitize_text_field($formattedName),
                    'id_number' => sanitize_text_field($idNumber),
                    'id_type' => sanitize_text_field($idType),
                    'first_name' => sanitize_text_field($row['first_name']),
                    'second_name' => sanitize_text_field($row['second_name'] ?? ''),
                    'initials' => sanitize_text_field($row['initials'] ?? ''),
                    'surname' => sanitize_text_field($row['surname']),
                    'city_town_id' => (int)($row['city_town_id'] ?? 0),
                    'province_region_id' => (int)($row['province_region_id'] ?? 0),
                    'postal_code' => sanitize_text_field($row['postal_code'] ?? ''),
                    'city_town_name' => sanitize_text_field($row['city_town_name'] ?? ''),
                    'province_region_name' => sanitize_text_field($row['province_region_name'] ?? ''),
                    // Progression context
                    'last_course_name' => sanitize_text_field($row['last_course_name'] ?? ''),
                    'last_completion_date' => sanitize_text_field($row['last_completion_date'] ?? ''),
                    'has_active_lp' => (bool)($row['has_active_lp'] ?? false),
                    'active_tracking_id' => $row['active_tracking_id'] ? (int)$row['active_tracking_id'] : null,
                    'active_subject_id' => $row['active_subject_id'] ? (int)$row['active_subject_id'] : null,
                    'active_course_name' => sanitize_text_field($row['active_course_name'] ?? ''),
                    'active_class_id' => $row['active_class_id'] ? (int)$row['active_class_id'] : null,
                    'active_class_code' => sanitize_text_field($row['active_class_code'] ?? ''),
                    'active_hours_present' => $row['active_hours_present'] ? (float)$row['active_hours_present'] : 0,
                    'active_subject_duration' => $row['active_subject_duration'] ? (float)$row['active_subject_duration'] : 0,
                    'active_progress_pct' => $row['active_progress_pct'] ? (float)$row['active_progress_pct'] : 0,
                    'active_start_date' => sanitize_text_field($row['active_start_date'] ?? '')
                ];
            }

            set_transient($cache_key, $learners, self::CACHE_DURATION);

            return $learners;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching learners with progression: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active agents from the database (cached)
     */
    public static function getAgents(): array
    {
        // Complex query: static context, reads from agents table (not $table)
        $cache_key = 'wecoza_class_agents';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $db = wecoza_db();
            $sql = "SELECT agent_id, first_name, surname
                    FROM agents
                    WHERE status = 'active'
                    ORDER BY surname, first_name";
            $stmt = $db->query($sql);

            $agents = [];
            while ($row = $stmt->fetch()) {
                $agents[] = [
                    'id' => (int)$row['agent_id'],
                    'name' => sanitize_text_field($row['first_name'] . ' ' . $row['surname'])
                ];
            }

            set_transient($cache_key, $agents, self::CACHE_DURATION);
            return $agents;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching agents for class form: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active supervisors from the database (cached)
     *
     * Supervisors are drawn from the same agents pool — no dedicated
     * supervisor role/flag exists in the agents table schema.
     */
    public static function getSupervisors(): array
    {
        // Complex query: static context, reads from agents table (not $table)
        $cache_key = 'wecoza_class_supervisors';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $db = wecoza_db();
            $sql = "SELECT agent_id, first_name, surname
                    FROM agents
                    WHERE status = 'active'
                    ORDER BY surname, first_name";
            $stmt = $db->query($sql);

            $supervisors = [];
            while ($row = $stmt->fetch()) {
                $supervisors[] = [
                    'id' => (int)$row['agent_id'],
                    'name' => sanitize_text_field($row['first_name'] . ' ' . $row['surname'])
                ];
            }

            set_transient($cache_key, $supervisors, self::CACHE_DURATION);
            return $supervisors;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching supervisors for class form: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all SETA options
     */
    public static function getSeta(): array
    {
        return [
            ['id' => 'BANKSETA', 'name' => 'Banking Sector Education and Training Authority'],
            ['id' => 'CHIETA', 'name' => 'Chemical Industries Education and Training Authority'],
            ['id' => 'CETA', 'name' => 'Construction Education and Training Authority'],
            ['id' => 'ETDP', 'name' => 'Education, Training and Development Practices SETA'],
            ['id' => 'FASSET', 'name' => 'Finance and Accounting Services SETA'],
            ['id' => 'FOODBEV', 'name' => 'Food and Beverages Manufacturing Industry SETA'],
            ['id' => 'HWSETA', 'name' => 'Health and Welfare SETA'],
            ['id' => 'INSETA', 'name' => 'Insurance Sector Education and Training Authority'],
            ['id' => 'LGSETA', 'name' => 'Local Government Sector Education and Training Authority'],
            ['id' => 'MERSETA', 'name' => 'Manufacturing, Engineering and Related Services SETA']
        ];
    }

    /**
     * Get class types (delegates to ClassTypesController)
     */
    public static function getClassTypes(): array
    {
        return ClassTypesController::getClassTypes();
    }

    /**
     * Get Yes/No options for boolean fields
     */
    public static function getYesNoOptions(): array
    {
        return [
            ['id' => 'Yes', 'name' => 'Yes'],
            ['id' => 'No', 'name' => 'No']
        ];
    }

    /**
     * Get class notes options
     */
    public static function getClassNotesOptions(): array
    {
        return [
            ['id' => 'Agent Absent', 'name' => 'Agent Absent'],
            ['id' => 'Client Cancelled', 'name' => 'Client Cancelled'],
            ['id' => 'Poor attendance', 'name' => 'Poor attendance'],
            ['id' => 'Learners behind schedule', 'name' => 'Learners behind schedule'],
            ['id' => 'Learners unhappy', 'name' => 'Learners unhappy'],
            ['id' => 'Client unhappy', 'name' => 'Client unhappy'],
            ['id' => 'Learners too fast', 'name' => 'Learners too fast'],
            ['id' => 'Class on track', 'name' => 'Class on track'],
            ['id' => 'Bad QA report', 'name' => 'Bad QA report'],
            ['id' => 'Good QA report', 'name' => 'Good QA report'],
            ['id' => 'Venue issues', 'name' => 'Venue issues'],
            ['id' => 'Equipment problems', 'name' => 'Equipment problems'],
            ['id' => 'Material shortage', 'name' => 'Material shortage'],
            ['id' => 'Weather delay', 'name' => 'Weather delay'],
            ['id' => 'Holiday adjustment', 'name' => 'Holiday adjustment']
        ];
    }

    /**
     * Clear learners cache
     */
    public static function clearLearnersCache(): void
    {
        delete_transient('wecoza_class_learners_with_locations');
    }

    /**
     * Get all classes from database with optional filtering
     */
    public static function getAllClasses(array $options = []): array
    {
        // Complex query: static context with dynamic ORDER BY + JOIN to clients
        $db = wecoza_db();

        $limit = isset($options['limit']) ? intval($options['limit']) : AppConstants::DEFAULT_PAGE_SIZE;
        $order_by = isset($options['order_by']) ? $options['order_by'] : 'created_at';
        $order = isset($options['order']) ? strtoupper($options['order']) : 'DESC';

        $allowed_columns = [
            'class_id', 'client_id', 'class_type', 'class_subject',
            'original_start_date', 'created_at', 'updated_at'
        ];

        if (!in_array($order_by, $allowed_columns)) {
            $order_by = 'created_at';
        }

        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        $sql = "
            SELECT
                c.class_id,
                c.client_id,
                cl.client_name,
                c.class_type,
                c.class_subject,
                c.class_code,
                c.class_duration,
                c.original_start_date,
                c.seta_funded,
                c.seta,
                c.exam_class,
                c.exam_type,
                c.class_agent,
                c.initial_class_agent,
                c.project_supervisor_id,
                c.stop_restart_dates,
                c.order_nr,
                c.created_at,
                c.updated_at
            FROM public.classes c
            LEFT JOIN public.clients cl ON c.client_id = cl.client_id
            ORDER BY c." . $order_by . " " . $order . "
            LIMIT " . $limit;

        try {
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('WeCoza Core: Error in getAllClasses: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single class from database by ID
     */
    public static function getSingleClass(int $class_id): ?array
    {
        // Complex query: delegates to ClassModel::getById then enriches with multiple lookups
        try {
            $classModel = ClassModel::getById($class_id);

            if (!$classModel) {
                return null;
            }

            $result = [
                'class_id' => $classModel->getId(),
                'client_id' => $classModel->getClientId(),
                'site_id' => $classModel->getSiteId(),
                'class_address_line' => $classModel->getClassAddressLine(),
                'class_type' => $classModel->getClassType(),
                'class_subject' => $classModel->getClassSubject(),
                'class_code' => $classModel->getClassCode(),
                'class_duration' => $classModel->getClassDuration(),
                'original_start_date' => $classModel->getOriginalStartDate(),
                'seta_funded' => $classModel->getSetaFunded() ? 'Yes' : 'No',
                'seta' => $classModel->getSeta(),
                'exam_class' => $classModel->getExamClass() ? 'Yes' : 'No',
                'exam_type' => $classModel->getExamType(),
                'qa_visits' => self::getQAVisitsForClass($classModel->getId()),
                'class_agent' => $classModel->getClassAgent(),
                'initial_class_agent' => $classModel->getInitialClassAgent(),
                'initial_agent_start_date' => $classModel->getInitialAgentStartDate(),
                'project_supervisor_id' => $classModel->getProjectSupervisorId(),
                'learner_ids' => self::enrichLearnerIds($classModel->getLearnerIds()),
                'exam_learners' => self::enrichLearnerIds($classModel->getExamLearners()),
                'backup_agent_ids' => $classModel->getBackupAgentIds(),
                'agent_replacements' => $classModel->getAgentReplacements(),
                'schedule_data' => $classModel->getScheduleData(),
                'stop_restart_dates' => $classModel->getStopRestartDates(),
                'event_dates' => $classModel->getEventDates(),
                'class_notes_data' => $classModel->getClassNotesData(),
                'created_at' => $classModel->getCreatedAt(),
                'updated_at' => $classModel->getUpdatedAt(),
                'order_nr' => $classModel->getOrderNr(),
            ];

            // Add client name
            if ($classModel->getClientId()) {
                $clients = self::getClients();
                foreach ($clients as $client) {
                    if ($client['id'] == $classModel->getClientId()) {
                        $result['client_name'] = $client['name'];
                        break;
                    }
                }
            }

            // Add agent names lookup
            $agents = self::getAgents();
            $agentLookup = [];
            foreach ($agents as $agent) {
                $agentLookup[$agent['id']] = $agent['name'];
            }

            $currentAgentId = $result['class_agent'] ?? $result['initial_class_agent'] ?? null;
            if (!empty($currentAgentId)) {
                $result['agent_name'] = $agentLookup[$currentAgentId] ?? 'Unknown Agent';
                $result['class_agent'] = $currentAgentId;
            }

            if (!empty($result['initial_class_agent'])) {
                $result['initial_agent_name'] = $agentLookup[$result['initial_class_agent']] ?? 'Unknown Agent';
            }

            // Add supervisor name
            if (!empty($result['project_supervisor_id'])) {
                $supervisors = self::getSupervisors();
                foreach ($supervisors as $supervisor) {
                    if ($supervisor['id'] == $result['project_supervisor_id']) {
                        $result['supervisor_name'] = $supervisor['name'];
                        break;
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error in getSingleClass: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Enrich learner ID arrays with name data for display.
     *
     * Handles two formats:
     * - Full objects [{id, name, status, level}] — returned as-is
     * - Plain integer IDs [2, 4, 6] (legacy) — looked up from learners table
     */
    private static function enrichLearnerIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Already full objects — return as-is
        $first = reset($ids);
        if (is_array($first) && isset($first['id'])) {
            return $ids;
        }

        // Plain IDs — look up names from learners table
        $intIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($intIds)) {
            return [];
        }

        try {
            $db = wecoza_db();
            $placeholders = implode(',', array_fill(0, count($intIds), '?'));
            $sql = "SELECT id, first_name, surname FROM learners WHERE id IN ({$placeholders})";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute(array_values($intIds));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $lookup = [];
            foreach ($rows as $row) {
                $lookup[(int) $row['id']] = trim($row['first_name'] . ' ' . $row['surname']);
            }

            return array_map(fn($id) => [
                'id'     => $id,
                'name'   => $lookup[$id] ?? 'Unknown',
                'status' => 'CIC - Currently in Class',
                'level'  => '',
            ], $intIds);
        } catch (\Exception $e) {
            error_log('WeCoza Core: Error enriching learner IDs: ' . $e->getMessage());
            return array_map(fn($id) => [
                'id' => $id, 'name' => 'Learner ID: ' . $id, 'status' => 'CIC - Currently in Class', 'level' => '',
            ], $intIds);
        }
    }

    /**
     * Get site addresses
     */
    public static function getSiteAddresses(): array
    {
        // Complex query: static context, JOIN sites + locations tables
        try {
            $db = wecoza_db();

            $sql = "SELECT s.site_id, l.street_address as address
                    FROM public.sites s
                    LEFT JOIN public.locations l ON s.place_id = l.location_id
                    WHERE l.street_address IS NOT NULL AND l.street_address != ''";
            $stmt = $db->query($sql);

            $addresses = [];
            while ($row = $stmt->fetch()) {
                $site_id = (int)$row['site_id'];
                $address = sanitize_textarea_field($row['address']);

                if (!empty($address)) {
                    $addresses[$site_id] = $address;
                }
            }

            return $addresses;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error fetching site addresses: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enrich classes array with agent names
     */
    public static function enrichClassesWithAgentNames(array $classes): array
    {
        $agents = self::getAgents();
        $agentLookup = [];

        foreach ($agents as $agent) {
            $agentLookup[$agent['id']] = $agent['name'];
        }

        foreach ($classes as &$class) {
            if (!empty($class['class_agent'])) {
                $class['agent_name'] = $agentLookup[$class['class_agent']] ?? 'Unknown Agent';
            }

            if (!empty($class['initial_class_agent'])) {
                $class['initial_agent_name'] = $agentLookup[$class['initial_class_agent']] ?? 'Unknown Agent';
            }
        }

        return $classes;
    }

    /**
     * Get cached class notes
     */
    public static function getCachedClassNotes(int $class_id, array $options = []): array
    {
        // Complex query: static context, reads JSONB column from classes table
        $cache_key = "wecoza_class_notes_{$class_id}";
        $cached_notes = get_transient($cache_key);

        if ($cached_notes !== false) {
            return $cached_notes;
        }

        try {
            $db = wecoza_db();

            $stmt = $db->query("SELECT class_notes_data FROM public.classes WHERE class_id = ? LIMIT 1", [$class_id]);

            $result = $stmt->fetch();

            $notes = [];
            if ($result && !empty($result['class_notes_data'])) {
                $notes_data = json_decode($result['class_notes_data'], true);
                if (is_array($notes_data)) {
                    $notes = $notes_data;

                    usort($notes, function($a, $b) {
                        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
                    });

                    $limit = isset($options['limit']) ? (int)$options['limit'] : AppConstants::DEFAULT_PAGE_SIZE;
                    $offset = isset($options['offset']) ? (int)$options['offset'] : 0;

                    if ($limit > 0) {
                        $notes = array_slice($notes, $offset, $limit);
                    }
                }
            }

            set_transient($cache_key, $notes, 15 * MINUTE_IN_SECONDS);

            return $notes;
        } catch (Exception $e) {
            error_log("WeCoza Core: Error in getCachedClassNotes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cached class notes
     */
    public static function clearCachedClassNotes(int $class_id): void
    {
        $cache_key = "wecoza_class_notes_{$class_id}";
        delete_transient($cache_key);
    }

    /**
     * Get QA visits for a class
     */
    public static function getQAVisitsForClass(int $classId): array
    {
        try {
            $qaVisits = QAVisitModel::findByClassId($classId);

            $visits = [];
            foreach ($qaVisits as $visit) {
                $visits[] = [
                    'date' => $visit->getVisitDate(),
                    'type' => $visit->getVisitType(),
                    'officer' => $visit->getOfficerName(),
                    'document' => $visit->getLatestDocument(),
                    'hasNewFile' => false,
                    'existingDocument' => $visit->getLatestDocument()
                ];
            }

            return [
                'visits' => $visits
            ];
        } catch (Exception $e) {
            error_log('WeCoza Core: Error loading QA visits: ' . $e->getMessage());
            return [
                'visits' => []
            ];
        }
    }

    /**
     * Get sample class data for testing
     */
    public static function getSampleClassData(int $class_id): array
    {
        return [
            'class_id' => $class_id,
            'class_code' => 'SAMPLE-CLASS-' . $class_id,
            'class_subject' => 'Sample Class Subject',
            'class_type' => 1,
            'client_id' => 1,
            'site_id' => 1,
            'client_name' => 'Sample Client Ltd',
            'class_agent' => null,
            'supervisor_name' => 'Dr. Sarah Johnson',
            'project_supervisor_id' => 1,
            'seta_funded' => 'Yes',
            'seta' => 'CHIETA',
            'exam_class' => 'Yes',
            'exam_type' => 'Open Book Exam',
            'class_duration' => 240,
            'class_address_line' => '123 Sample Street, Sample City, 1234',
            'original_start_date' => wp_date('Y-m-d'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'schedule_data' => [
                'pattern' => 'weekly',
                'startDate' => wp_date('Y-m-d'),
                'endDate' => wp_date('Y-m-d', strtotime('+3 months')),
                'selectedDays' => ['Monday', 'Wednesday', 'Friday'],
                'timeData' => [
                    'mode' => 'per-day',
                    'perDayTimes' => [
                        'Monday' => ['startTime' => '09:00', 'endTime' => '11:00', 'duration' => 2],
                        'Wednesday' => ['startTime' => '14:00', 'endTime' => '16:30', 'duration' => 2.5],
                        'Friday' => ['startTime' => '10:00', 'endTime' => '12:00', 'duration' => 2]
                    ]
                ],
                'version' => '2.0',
                'holidayOverrides' => [],
                'exceptionDates' => []
            ],
            'exception_dates' => null,
            'stop_restart_dates' => [
                ['stop_date' => wp_date('Y-m-d', strtotime('+10 days')), 'restart_date' => wp_date('Y-m-d', strtotime('+15 days'))]
            ],
            'learner_ids' => [
                ['id' => 1, 'name' => 'Alice Johnson', 'status' => 'CIC - Currently in Class'],
                ['id' => 2, 'name' => 'Bob Smith', 'status' => 'CIC - Currently in Class']
            ],
            'exam_learners' => [
                ['id' => 1, 'name' => 'Alice Johnson', 'exam_status' => 'Registered'],
                ['id' => 2, 'name' => 'Bob Smith', 'exam_status' => 'Registered']
            ],
            'qa_reports' => [],
            'class_notes_data' => [],
            'backup_agent_ids' => [],
            'initial_class_agent' => 5,
            'initial_agent_start_date' => wp_date('Y-m-d', strtotime('-30 days'))
        ];
    }
}
