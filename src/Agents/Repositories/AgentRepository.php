<?php
/**
 * Agent Repository
 *
 * Handles all database operations for agents table.
 * Extends BaseRepository with column whitelisting for SQL injection prevention.
 *
 * @package WeCoza\Agents
 * @since 3.0.0
 */

namespace WeCoza\Agents\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Repository class
 *
 * @since 3.0.0
 */
class AgentRepository extends BaseRepository
{
    protected static string $table = 'agents';
    protected static string $primaryKey = 'agent_id';

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security - SQL Injection Prevention)
    |--------------------------------------------------------------------------
    */

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'agent_id',
            'first_name',
            'surname',
            'email_address',
            'created_at',
            'updated_at',
            'status',
        ];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'agent_id',
            'email_address',
            'sa_id_no',
            'tel_number',
            'status',
            'first_name',
            'surname',
            'created_at',
        ];
    }

    /**
     * Get columns allowed for INSERT operations
     *
     * @return array
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'title',
            'first_name',
            'second_name',
            'surname',
            'initials',
            'gender',
            'race',
            'id_type',
            'sa_id_no',
            'passport_number',
            'tel_number',
            'email_address',
            'residential_address_line',
            'address_line_2',
            'city',
            'province',
            'residential_postal_code',
            'preferred_working_area_1',
            'preferred_working_area_2',
            'preferred_working_area_3',
            'sace_number',
            'sace_registration_date',
            'sace_expiry_date',
            'phase_registered',
            'subjects_registered',
            'highest_qualification',
            'quantum_maths_score',
            'quantum_science_score',
            'quantum_assessment',
            'criminal_record_date',
            'criminal_record_file',
            'signed_agreement_date',
            'signed_agreement_file',
            'bank_name',
            'account_holder',
            'bank_account_number',
            'bank_branch_code',
            'account_type',
            'agent_training_date',
            'status',
            'residential_suburb',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
        ];
    }

    /**
     * Get columns allowed for UPDATE operations
     *
     * @return array
     */
    protected function getAllowedUpdateColumns(): array
    {
        $insertColumns = $this->getAllowedInsertColumns();
        return array_diff($insertColumns, ['created_at', 'created_by']);
    }

    /*
    |--------------------------------------------------------------------------
    | Core CRUD Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new agent
     *
     * @param array $data Agent data
     * @return int|false Agent ID on success, false on failure
     */
    public function createAgent(array $data)
    {
        // Sanitize and validate data
        $cleanData = $this->sanitizeAgentData($data);

        if (empty($cleanData['first_name']) || empty($cleanData['surname']) || empty($cleanData['email_address'])) {
            return false;
        }

        // Check if email already exists
        if ($this->getAgentByEmail($cleanData['email_address'])) {
            return false;
        }

        // Check if ID number already exists
        if (!empty($cleanData['sa_id_no']) && $this->getAgentByIdNumber($cleanData['sa_id_no'])) {
            return false;
        }

        // Add timestamps
        $cleanData['created_at'] = current_time('mysql');
        $cleanData['updated_at'] = current_time('mysql');
        $cleanData['created_by'] = get_current_user_id();
        $cleanData['updated_by'] = get_current_user_id();

        // Insert agent
        return wecoza_db()->insert('agents', $cleanData);
    }

    /**
     * Get agent by ID
     *
     * @param int $agentId Agent ID
     * @return array|null Agent data or null if not found
     */
    public function getAgent(int $agentId): ?array
    {
        $sql = "SELECT * FROM agents WHERE agent_id = :agent_id AND status != 'deleted' LIMIT 1";
        $params = [':agent_id' => $agentId];

        $result = wecoza_db()->getRow($sql, $params);
        return $result ?: null;
    }

    /**
     * Get agent by email
     *
     * @param string $email Email address
     * @return array|null Agent data or null if not found
     */
    public function getAgentByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM agents WHERE email_address = :email AND status != 'deleted' LIMIT 1";
        $params = [':email' => $email];

        $result = wecoza_db()->getRow($sql, $params);
        return $result ?: null;
    }

    /**
     * Get agent by ID number
     *
     * @param string $idNumber ID number
     * @return array|null Agent data or null if not found
     */
    public function getAgentByIdNumber(string $idNumber): ?array
    {
        $sql = "SELECT * FROM agents WHERE sa_id_no = :id_number AND status != 'deleted' LIMIT 1";
        $params = [':id_number' => $idNumber];

        $result = wecoza_db()->getRow($sql, $params);
        return $result ?: null;
    }

    /**
     * Get all agents
     *
     * @param array $args Query arguments
     * @return array Array of agents
     */
    public function getAgents(array $args = []): array
    {
        $defaults = [
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
            'search' => '',
            'meta_query' => [],
        ];

        $args = wp_parse_args($args, $defaults);

        // Build query
        $sql = "SELECT * FROM agents WHERE 1=1";
        $params = [];

        // Status filter
        if (!empty($args['status'])) {
            if ($args['status'] === 'all') {
                $sql .= " AND status != 'deleted'";
            } else {
                $sql .= " AND status = :status";
                $params[':status'] = $args['status'];
            }
        }

        // Search
        if (!empty($args['search'])) {
            $search = '%' . $args['search'] . '%';
            $sql .= " AND (
                first_name LIKE :search1 OR
                surname LIKE :search2 OR
                email_address LIKE :search3 OR
                tel_number LIKE :search4 OR
                sa_id_no LIKE :search5
            )";
            $params[':search1'] = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
            $params[':search4'] = $search;
            $params[':search5'] = $search;
        }

        // Order - validate against whitelist
        $allowedOrderby = $this->getAllowedOrderColumns();
        $orderby = in_array($args['orderby'], $allowedOrderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderby $order";

        // Limit and offset
        if ($args['limit'] > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int) $args['limit'];
            $params[':offset'] = (int) $args['offset'];
        }

        return wecoza_db()->getAll($sql, $params) ?: [];
    }

    /**
     * Update agent
     *
     * @param int $agentId Agent ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function updateAgent(int $agentId, array $data): bool
    {
        // Remove fields that shouldn't be updated
        unset($data['agent_id']);
        unset($data['created_at']);
        unset($data['created_by']);

        // Sanitize data
        $cleanData = $this->sanitizeAgentData($data);

        if (empty($cleanData)) {
            return false;
        }

        // Add update timestamp
        $cleanData['updated_at'] = current_time('mysql');
        $cleanData['updated_by'] = get_current_user_id();

        // Update agent using string WHERE with colon-prefixed params
        $result = wecoza_db()->update(
            'agents',
            $cleanData,
            'agent_id = :agent_id',
            [':agent_id' => $agentId]
        );

        return $result !== false;
    }

    /**
     * Delete agent (soft delete)
     *
     * @param int $agentId Agent ID
     * @return bool Success status
     */
    public function deleteAgent(int $agentId): bool
    {
        return $this->updateAgent($agentId, ['status' => 'deleted']);
    }

    /**
     * Permanently delete agent
     *
     * @param int $agentId Agent ID
     * @return bool Success status
     */
    public function deleteAgentPermanently(int $agentId): bool
    {
        // Delete related data first
        $this->deleteAgentMeta($agentId);
        $this->deleteAgentNotes($agentId);
        $this->deleteAgentAbsences($agentId);

        // Delete agent using string WHERE with colon-prefixed params
        $result = wecoza_db()->delete(
            'agents',
            'agent_id = :agent_id',
            [':agent_id' => $agentId]
        );

        return $result !== false;
    }

    /**
     * Count agents
     *
     * @param array $args Query arguments
     * @return int Agent count
     */
    public function countAgents(array $args = []): int
    {
        $defaults = [
            'status' => 'active',
            'search' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT COUNT(*) as total FROM agents WHERE 1=1";
        $params = [];

        // Status filter
        if (!empty($args['status'])) {
            if ($args['status'] === 'all') {
                $sql .= " AND status != 'deleted'";
            } else {
                $sql .= " AND status = :status";
                $params[':status'] = $args['status'];
            }
        }

        // Search
        if (!empty($args['search'])) {
            $search = '%' . $args['search'] . '%';
            $sql .= " AND (
                first_name LIKE :search1 OR
                surname LIKE :search2 OR
                email_address LIKE :search3
            )";
            $params[':search1'] = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
        }

        $row = wecoza_db()->getRow($sql, $params);
        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Search agents
     *
     * @param string $search Search term
     * @param array $args Additional query arguments
     * @return array Array of agents
     */
    public function searchAgents(string $search, array $args = []): array
    {
        $args['search'] = $search;
        return $this->getAgents($args);
    }

    /**
     * Get agents by status
     *
     * @param string $status Agent status
     * @param array $args Additional query arguments
     * @return array Array of agents
     */
    public function getAgentsByStatus(string $status, array $args = []): array
    {
        $args['status'] = $status;
        return $this->getAgents($args);
    }

    /**
     * Bulk update agent status
     *
     * @param array $agentIds Array of agent IDs
     * @param string $status New status
     * @return int Number of updated agents
     */
    public function bulkUpdateStatus(array $agentIds, string $status): int
    {
        if (empty($agentIds) || !is_array($agentIds)) {
            return 0;
        }

        $count = 0;
        foreach ($agentIds as $agentId) {
            if ($this->updateAgent($agentId, ['status' => $status])) {
                $count++;
            }
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitization
    |--------------------------------------------------------------------------
    */

    /**
     * Sanitize agent data
     *
     * @param array $data Raw agent data
     * @return array Sanitized data
     */
    protected function sanitizeAgentData(array $data): array
    {
        $fields = [
            // Personal Information (database column names)
            'title' => 'sanitize_text_field',
            'first_name' => 'sanitize_text_field',
            'second_name' => 'sanitize_text_field',
            'surname' => 'sanitize_text_field',
            'initials' => 'sanitize_text_field',
            'gender' => 'sanitize_text_field',
            'race' => 'sanitize_text_field',

            // Identification (database column names)
            'id_type' => 'sanitize_text_field',
            'sa_id_no' => 'sanitize_text_field',
            'passport_number' => 'sanitize_text_field',

            // Contact Information (database column names)
            'tel_number' => 'sanitize_text_field',
            'email_address' => 'sanitize_email',

            // Address Information (database column names)
            'residential_address_line' => 'sanitize_textarea_field',
            'address_line_2' => 'sanitize_text_field',
            'city' => 'sanitize_text_field',
            'province' => 'sanitize_text_field',
            'residential_postal_code' => 'sanitize_text_field',

            // Working Areas (database column names)
            'preferred_working_area_1' => [$this, 'sanitizeWorkingArea'],
            'preferred_working_area_2' => [$this, 'sanitizeWorkingArea'],
            'preferred_working_area_3' => [$this, 'sanitizeWorkingArea'],

            // SACE Registration (database column names)
            'sace_number' => 'sanitize_text_field',
            'sace_registration_date' => 'sanitize_text_field',
            'sace_expiry_date' => 'sanitize_text_field',
            'phase_registered' => 'sanitize_text_field',
            'subjects_registered' => 'sanitize_textarea_field',

            // Qualifications (database column names)
            'highest_qualification' => 'sanitize_text_field',

            // Quantum Tests (database column names)
            'quantum_maths_score' => 'absint',
            'quantum_science_score' => 'absint',
            'quantum_assessment' => 'absint',

            // Criminal Record (database column names)
            'criminal_record_date' => 'sanitize_text_field',
            'criminal_record_file' => 'sanitize_text_field',

            // Agreement (database column names)
            'signed_agreement_date' => 'sanitize_text_field',
            'signed_agreement_file' => 'sanitize_text_field',

            // Banking Details (database column names)
            'bank_name' => 'sanitize_text_field',
            'account_holder' => 'sanitize_text_field',
            'bank_account_number' => 'sanitize_text_field',
            'bank_branch_code' => 'sanitize_text_field',
            'account_type' => 'sanitize_text_field',

            // Training (database column names)
            'agent_training_date' => 'sanitize_text_field',

            // Metadata (database column names)
            'status' => 'sanitize_text_field',
            'created_at' => 'sanitize_text_field',
            'updated_at' => 'sanitize_text_field',
            'created_by' => 'absint',
            'updated_by' => 'absint',

            // Legacy fields
            'residential_suburb' => 'sanitize_text_field',
        ];

        $cleanData = [];

        foreach ($fields as $field => $sanitizeFunction) {
            if (isset($data[$field])) {
                $cleanData[$field] = call_user_func($sanitizeFunction, $data[$field]);
            }
        }

        return $cleanData;
    }

    /**
     * Sanitize working area field
     * Returns NULL for empty values instead of 0 to avoid foreign key violations
     *
     * @param mixed $value The value to sanitize
     * @return int|null Sanitized value or NULL if empty
     */
    private function sanitizeWorkingArea($value)
    {
        if (empty($value) || $value === '' || $value === '0') {
            return null;
        }
        return absint($value);
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Meta Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Add agent meta
     *
     * @param int $agentId Agent ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value
     * @return int|false Meta ID on success, false on failure
     */
    public function addAgentMeta(int $agentId, string $metaKey, $metaValue)
    {
        // Check if meta already exists
        $existing = $this->getAgentMeta($agentId, $metaKey, true);

        if ($existing !== null) {
            // Update existing
            return $this->updateAgentMeta($agentId, $metaKey, $metaValue);
        }

        // Insert new meta
        return wecoza_db()->insert('agent_meta', [
            'agent_id' => $agentId,
            'meta_key' => $metaKey,
            'meta_value' => maybe_serialize($metaValue),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get agent meta
     *
     * @param int $agentId Agent ID
     * @param string $metaKey Meta key (optional)
     * @param bool $single Return single value
     * @return mixed Meta value(s)
     */
    public function getAgentMeta(int $agentId, string $metaKey = '', bool $single = false)
    {
        $sql = "SELECT * FROM agent_meta WHERE agent_id = :agent_id";
        $params = [':agent_id' => $agentId];

        if (!empty($metaKey)) {
            $sql .= " AND meta_key = :meta_key";
            $params[':meta_key'] = $metaKey;
        }

        $sql .= " ORDER BY meta_id ASC";

        $rows = wecoza_db()->getAll($sql, $params) ?: [];

        if (empty($rows)) {
            return $single ? null : [];
        }

        if ($single) {
            return maybe_unserialize($rows[0]['meta_value']);
        }

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['meta_key']][] = maybe_unserialize($row['meta_value']);
        }

        return $meta;
    }

    /**
     * Update agent meta
     *
     * @param int $agentId Agent ID
     * @param string $metaKey Meta key
     * @param mixed $metaValue Meta value
     * @return bool Success status
     */
    public function updateAgentMeta(int $agentId, string $metaKey, $metaValue): bool
    {
        $result = wecoza_db()->update(
            'agent_meta',
            ['meta_value' => maybe_serialize($metaValue)],
            'agent_id = :agent_id AND meta_key = :meta_key',
            [':agent_id' => $agentId, ':meta_key' => $metaKey]
        );

        return $result !== false;
    }

    /**
     * Delete agent meta
     *
     * @param int $agentId Agent ID
     * @param string $metaKey Meta key (optional)
     * @return bool Success status
     */
    public function deleteAgentMeta(int $agentId, string $metaKey = ''): bool
    {
        $where = 'agent_id = :agent_id';
        $params = [':agent_id' => $agentId];

        if (!empty($metaKey)) {
            $where .= ' AND meta_key = :meta_key';
            $params[':meta_key'] = $metaKey;
        }

        $result = wecoza_db()->delete('agent_meta', $where, $params);

        return $result !== false;
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Notes Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Add agent note
     *
     * @param int $agentId Agent ID
     * @param string $note Note content
     * @param string $noteType Note type (unused - kept for API compatibility)
     * @return int|false Note ID on success, false on failure
     */
    public function addAgentNote(int $agentId, string $note, string $noteType = 'general')
    {
        // Actual schema: note_id, agent_id, note, note_date
        return wecoza_db()->insert('agent_notes', [
            'agent_id' => $agentId,
            'note' => $note,
            'note_date' => current_time('mysql')
        ]);
    }

    /**
     * Get agent notes
     *
     * @param int $agentId Agent ID
     * @param array $args Query arguments
     * @return array Array of notes
     */
    public function getAgentNotes(int $agentId, array $args = []): array
    {
        $defaults = [
            'note_type' => '', // Unused - kept for API compatibility
            'orderby' => 'note_date', // Actual column name
            'order' => 'DESC',
            'limit' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        // Actual schema: note_id, agent_id, note, note_date
        $sql = "SELECT * FROM agent_notes WHERE agent_id = :agent_id";
        $params = [':agent_id' => $agentId];

        // note_type column doesn't exist in actual schema, skip filter

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int) $args['limit'];
        }

        return wecoza_db()->getAll($sql, $params) ?: [];
    }

    /**
     * Delete agent notes
     *
     * @param int $agentId Agent ID
     * @return bool Success status
     */
    public function deleteAgentNotes(int $agentId): bool
    {
        $result = wecoza_db()->delete(
            'agent_notes',
            'agent_id = :agent_id',
            [':agent_id' => $agentId]
        );

        return $result !== false;
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Absences Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Add agent absence
     *
     * @param int $agentId Agent ID
     * @param string $absenceDate Absence date
     * @param string $reason Reason for absence
     * @return int|false Absence ID on success, false on failure
     */
    public function addAgentAbsence(int $agentId, string $absenceDate, string $reason = '')
    {
        // Actual schema: absence_id, agent_id, class_id, absence_date, reason, reported_at
        return wecoza_db()->insert('agent_absences', [
            'agent_id' => $agentId,
            'class_id' => null, // Optional field
            'absence_date' => $absenceDate,
            'reason' => $reason,
            'reported_at' => current_time('mysql')
        ]);
    }

    /**
     * Get agent absences
     *
     * @param int $agentId Agent ID
     * @param array $args Query arguments
     * @return array Array of absences
     */
    public function getAgentAbsences(int $agentId, array $args = []): array
    {
        $defaults = [
            'from_date' => '',
            'to_date' => '',
            'orderby' => 'absence_date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM agent_absences WHERE agent_id = :agent_id";
        $params = [':agent_id' => $agentId];

        if (!empty($args['from_date'])) {
            $sql .= " AND absence_date >= :from_date";
            $params[':from_date'] = $args['from_date'];
        }

        if (!empty($args['to_date'])) {
            $sql .= " AND absence_date <= :to_date";
            $params[':to_date'] = $args['to_date'];
        }

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        return wecoza_db()->getAll($sql, $params) ?: [];
    }

    /**
     * Delete agent absences
     *
     * @param int $agentId Agent ID
     * @return bool Success status
     */
    public function deleteAgentAbsences(int $agentId): bool
    {
        $result = wecoza_db()->delete(
            'agent_absences',
            'agent_id = :agent_id',
            [':agent_id' => $agentId]
        );

        return $result !== false;
    }
}
