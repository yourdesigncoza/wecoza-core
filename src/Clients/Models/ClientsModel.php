<?php
declare(strict_types=1);

namespace WeCoza\Clients\Models;

use WeCoza\Core\Abstract\BaseModel;

class ClientsModel extends BaseModel implements \ArrayAccess {

    protected static string $table = 'clients';

    protected static string $primaryKey = 'id';

    protected static array $fillable = [
        'client_name',
        'company_registration_nr',
        'seta',
        'client_status',
        'financial_year_end',
        'bbbee_verification_date',
        'main_client_id',
        'client_town_id', // Reference to place_id in locations table
        'contact_person',
        'contact_person_email',
        'contact_person_cellphone',
        'contact_person_tel',
        'contact_person_position',
        'created_at',
        'updated_at',
    ];

    protected static array $guarded = ['id', 'created_at', 'updated_at'];

    protected static array $casts = [];

    protected $resolvedPrimaryKey = 'id';

    protected $columnCandidates = [
        'id' => ['client_id', 'id'],
        'client_name' => ['client_name'],
        'company_registration_nr' => ['company_registration_nr', 'company_registration_number'],
        'seta' => ['seta'],
        'client_status' => ['client_status'],
        'financial_year_end' => ['financial_year_end'],
        'bbbee_verification_date' => ['bbbee_verification_date'],
        'main_client_id' => ['main_client_id'],
        'client_town_id' => ['client_town_id'],
        'contact_person' => ['contact_person'],
        'contact_person_email' => ['contact_person_email'],
        'contact_person_cellphone' => ['contact_person_cellphone'],
        'contact_person_tel' => ['contact_person_tel'],
        'contact_person_position' => ['contact_person_position'],
        'created_at' => ['created_at'],
        'updated_at' => ['updated_at'],
    ];

    protected $columnMap = [];

    protected static $columnMapCache = [];

    protected array $attributes = [];

    protected $jsonFields = [];

    protected $dateFields = [
        'financial_year_end',
        'bbbee_verification_date',
    ];

    protected $communicationsModel;

    protected $sitesModel;

    public function __construct() {
        // Call parent constructor but skip hydration (we handle column resolution first)
        parent::__construct([]);

        // Column resolution mechanism (existing logic)
        $cacheKey = static::$table;

        if (isset(self::$columnMapCache[$cacheKey])) {
            $this->columnMap = self::$columnMapCache[$cacheKey];
        } else {
            foreach ($this->columnCandidates as $field => $candidates) {
                $this->columnMap[$field] = $this->resolveColumn($candidates);
            }

            self::$columnMapCache[$cacheKey] = $this->columnMap;
        }

        $this->resolvedPrimaryKey = $this->columnMap['id'] ?: 'id';

        // Related model instantiation
        $this->communicationsModel = new ClientCommunicationsModel();
        $this->sitesModel = new SitesModel();

        // Filter fillable/jsonFields/dateFields based on resolved columns
        static::$fillable = array_values(array_filter(static::$fillable, function ($field) {
            return !empty($this->columnMap[$field] ?? null);
        }));

        $this->jsonFields = array_values(array_filter($this->jsonFields, function ($field) {
            return !empty($this->columnMap[$field] ?? null);
        }));

        $this->dateFields = array_values(array_filter($this->dateFields, function ($field) {
            return !empty($this->columnMap[$field] ?? null);
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | ArrayAccess Implementation (backward compatibility for $client['field'])
    |--------------------------------------------------------------------------
    */

    public function offsetExists($offset): bool {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void {
        if ($offset === null) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void {
        unset($this->attributes[$offset]);
    }

    /*
    |--------------------------------------------------------------------------
    | Column Resolution Mechanism
    |--------------------------------------------------------------------------
    */

    protected function resolveColumn($candidates) {
        foreach ((array) $candidates as $candidate) {
            if ($candidate && wecoza_db()->tableHasColumn(static::$table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function getColumn($field, $fallback = null) {
        if (!empty($this->columnMap[$field])) {
            return $this->columnMap[$field];
        }

        return $fallback;
    }

    /**
     * Override toArray to return attributes array (array-oriented architecture)
     *
     * @return array
     */
    public function toArray(): array {
        return $this->attributes;
    }

    protected function normalizeRow($row) {
        if (!is_array($row)) {
            return [];
        }

        $normalized = $row;

        foreach ($this->columnMap as $field => $column) {
            if ($column && array_key_exists($column, $row)) {
                $normalized[$field] = $row[$column];
            }
        }

        if (!isset($normalized['id']) && isset($row[$this->resolvedPrimaryKey])) {
            $normalized['id'] = $row[$this->resolvedPrimaryKey];
        }

        return $normalized;
    }

    protected function prepareDataForSave(array $data) {
        $prepared = [];

        foreach ($this->fillable as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $column = $this->getColumn($field);
            if (!$column) {
                continue;
            }

            $value = $data[$field];

            if (in_array($field, $this->dateFields, true) && $value === '') {
                $value = null;
            }

            if (in_array($field, $this->jsonFields, true)) {
                if (is_array($value)) {
                    $value = json_encode($value);
                } elseif ($value === '' || $value === null) {
                    $value = '[]';
                }
            }

            $prepared[$column] = $value;
        }

        return $prepared;
    }

    protected function hydrateRows(&$rows) {
        if (empty($rows)) {
            return;
        }

        $single = false;
        if (isset($rows['id'])) {
            $rows = [$rows];
            $single = true;
        }

        foreach ($rows as &$row) {
            $this->decodeJsonFields($row);
        }
        unset($row);

        $this->sitesModel->hydrateClients($rows);
        $this->hydrateRelatedData($rows);

        if ($single) {
            $rows = reset($rows);
        }
    }

    protected function hydrateRelatedData(&$rows) {
        if (empty($rows)) {
            return;
        }

        $single = false;
        if (isset($rows['id'])) {
            $rows = [$rows];
            $single = true;
        }

        $clientIds = [];
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $clientIds[] = (int) $row['id'];
            }
        }

        $clientIds = array_values(array_unique(array_filter($clientIds)));
        if (!$clientIds) {
            if ($single) {
                $rows = reset($rows);
            }
            return;
        }


        $communications = $this->communicationsModel->getLatestCommunicationTypes($clientIds);

        foreach ($rows as &$row) {
            $clientId = (int) ($row['id'] ?? 0);
            if (!$clientId) {
                continue;
            }



            if (isset($communications[$clientId])) {
                $row['last_communication_at'] = $communications[$clientId]['communication_date'];
            }
        }
        unset($row);

        if ($single) {
            $rows = reset($rows);
        }
    }

    public function getAllClients(array $params = []): array {
        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $sql = "SELECT {$alias}.*, {$alias}.{$primaryKey} AS id, mc.client_name AS main_client_name FROM {$this->table} {$alias}
                LEFT JOIN {$this->table} mc ON {$alias}.main_client_id = mc.{$primaryKey}";
        $where = [];
        $bindings = [];

        if (!empty($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $searchClauses = [];

            foreach (['client_name', 'company_registration_nr', 'seta'] as $index => $field) {
                $column = $this->getColumn($field);
                if ($column) {
                    $placeholder = ':search' . $index;
                    $searchClauses[] = "CAST({$alias}.{$column} AS TEXT) ILIKE {$placeholder}";
                    $bindings[$placeholder] = $search;
                }
            }

            if ($searchClauses) {
                $where[] = '(' . implode(' OR ', $searchClauses) . ')';
            }
        }

        if (!empty($params['status'])) {
            $statusColumn = $this->getColumn('client_status');
            if ($statusColumn) {
                $where[] = "{$alias}.{$statusColumn} = :status";
                $bindings[':status'] = $params['status'];
            }
        }

        if (!empty($params['seta'])) {
            $setaColumn = $this->getColumn('seta');
            if ($setaColumn) {
                $where[] = "{$alias}.{$setaColumn} = :seta";
                $bindings[':seta'] = $params['seta'];
            }
        }

        // Exclude soft-deleted records by default (only if deleted_at column exists)
        if ((!isset($params['include_deleted']) || !$params['include_deleted']) && wecoza_db()->tableHasColumn($this->table, 'deleted_at')) {
            $where[] = "{$alias}.deleted_at IS NULL";
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $orderBy = preg_replace('/[^a-zA-Z0-9_]/', '', $params['order_by'] ?? 'client_name') ?: 'client_name';
        $orderDir = !empty($params['order_dir']) && strtoupper($params['order_dir']) === 'DESC' ? 'DESC' : 'ASC';
        $orderColumn = $this->getColumn($orderBy) ?: $this->getColumn('client_name', $primaryKey);
        $sql .= " ORDER BY {$alias}.{$orderColumn} {$orderDir}";

        if (!empty($params['limit'])) {
            $sql .= ' LIMIT :limit';
            $bindings[':limit'] = (int) $params['limit'];

            if (!empty($params['offset'])) {
                $sql .= ' OFFSET :offset';
                $bindings[':offset'] = (int) $params['offset'];
            }
        }

        $rows = wecoza_db()->getAll($sql, $bindings) ?: [];

        if ($rows) {
            foreach ($rows as &$row) {
                $row = $this->normalizeRow($row);
            }
            unset($row);

            $this->hydrateRows($rows);
        }

        return $rows;
    }

    public static function getById(int $id): ?static {
        $instance = new static();
        $alias = 'c';
        $primaryKey = $instance->resolvedPrimaryKey;
        $sql = "SELECT {$alias}.*, {$alias}.{$primaryKey} AS id FROM {$instance::$table} {$alias} WHERE {$alias}.{$primaryKey} = :id";
        $row = wecoza_db()->getRow($sql, [':id' => $id]);

        if (!$row) {
            return null;
        }

        $normalized = $instance->normalizeRow($row);
        $instance->hydrateRows($normalized); // Mutates $normalized by reference

        // Store hydrated result in attributes for ArrayAccess
        $instance->attributes = $normalized;

        return $instance;
    }

    public function getByRegistrationNumber(string $regNr): array|false {
        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $registrationColumn = $this->getColumn('company_registration_nr');
        if (!$registrationColumn) {
            return false;
        }

        $sql = "SELECT {$alias}.*, {$alias}.{$primaryKey} AS id FROM {$this->table} {$alias} WHERE {$alias}.{$registrationColumn} = :reg_nr";
        $row = wecoza_db()->getRow($sql, [':reg_nr' => $regNr]);

        if (!$row) {
            return false;
        }

        $normalized = $this->normalizeRow($row);
        $this->hydrateRows($normalized);

        return $normalized;
    }

    public function create(array $data): int|false {
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $prepared = $this->prepareDataForSave($data);

        if (!$prepared) {
            return false;
        }

        $insertId = wecoza_db()->insert($this->table, $prepared);

        return $insertId ? (int) $insertId : false;
    }

    public function save(): bool {
        return false; // Not implemented - use create() instead
    }

    public function update($id = null, array $data = []): bool {
        if ($id !== null && !empty($data)) {
            return $this->updateById($id, $data);
        }
        return false;
    }

    public function updateById(int $id, array $data): bool {
        $data['updated_at'] = current_time('mysql');
        $prepared = $this->prepareDataForSave($data);

        if (!$prepared) {
            return true;
        }

        $where = $this->resolvedPrimaryKey . ' = :id';
        $params = [':id' => $id];

        $result = wecoza_db()->update($this->table, $prepared, $where, $params);

        return $result !== false;
    }

    public function delete($id = null): bool {
        if ($id !== null) {
            return $this->deleteById($id);
        }
        return false;
    }

    public function deleteById(int $id): bool {
        // Soft delete: set deleted_at timestamp instead of hard delete
        $data = ['deleted_at' => current_time('mysql')];
        $where = $this->resolvedPrimaryKey . ' = :id';
        $result = wecoza_db()->update($this->table, $data, $where, [':id' => $id]);

        return $result !== false;
    }

    public function count(array $params = []): int {
        $alias = 'c';
        $sql = "SELECT COUNT(*) FROM {$this->table} {$alias}";
        $where = [];
        $bindings = [];

        if (!empty($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $searchClauses = [];

            foreach (['client_name', 'company_registration_nr', 'seta'] as $index => $field) {
                $column = $this->getColumn($field);
                if ($column) {
                    $placeholder = ':search' . $index;
                    $searchClauses[] = "CAST({$alias}.{$column} AS TEXT) ILIKE {$placeholder}";
                    $bindings[$placeholder] = $search;
                }
            }

            if ($searchClauses) {
                $where[] = '(' . implode(' OR ', $searchClauses) . ')';
            }
        }

        if (!empty($params['status'])) {
            $statusColumn = $this->getColumn('client_status');
            if ($statusColumn) {
                $where[] = "{$alias}.{$statusColumn} = :status";
                $bindings[':status'] = $params['status'];
            }
        }

        if (!empty($params['seta'])) {
            $setaColumn = $this->getColumn('seta');
            if ($setaColumn) {
                $where[] = "{$alias}.{$setaColumn} = :seta";
                $bindings[':seta'] = $params['seta'];
            }
        }

        // Exclude soft-deleted records by default (only if deleted_at column exists)
        if ((!isset($params['include_deleted']) || !$params['include_deleted']) && wecoza_db()->tableHasColumn($this->table, 'deleted_at')) {
            $where[] = "{$alias}.deleted_at IS NULL";
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $count = wecoza_db()->getValue($sql, $bindings);

        return (int) $count;
    }

    public function getStatistics(): array {
        $alias = 'c';
        $statusColumn = $this->getColumn('client_status');

        $select = [
            'COUNT(*) AS total_clients',
        ];

        if ($statusColumn) {
            $select[] = "SUM(CASE WHEN {$alias}.{$statusColumn} = 'Active Client' THEN 1 ELSE 0 END) AS active_clients";
            $select[] = "SUM(CASE WHEN {$alias}.{$statusColumn} = 'Lead' THEN 1 ELSE 0 END) AS leads";
            $select[] = "SUM(CASE WHEN {$alias}.{$statusColumn} = 'Cold Call' THEN 1 ELSE 0 END) AS cold_calls";
            $select[] = "SUM(CASE WHEN {$alias}.{$statusColumn} = 'Lost Client' THEN 1 ELSE 0 END) AS lost_clients";
        }

        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$this->table} {$alias}";
        // Exclude soft-deleted records (only if deleted_at column exists)
        if (wecoza_db()->tableHasColumn($this->table, 'deleted_at')) {
            $sql .= " WHERE {$alias}.deleted_at IS NULL";
        }
        $row = wecoza_db()->getRow($sql) ?: [];

        return wp_parse_args($row, [
            'total_clients' => 0,
            'active_clients' => 0,
            'leads' => 0,
            'cold_calls' => 0,
            'lost_clients' => 0,
        ]);
    }

    public function getForDropdown(): array {
        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $nameColumn = $this->getColumn('client_name', $primaryKey);
        $registrationColumn = $this->getColumn('company_registration_nr');

        $select = [
            "{$alias}.{$primaryKey} AS id",
            "{$alias}.{$nameColumn} AS client_name",
        ];

        if ($registrationColumn) {
            $select[] = "{$alias}.{$registrationColumn} AS company_registration_nr";
        }

        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$this->table} {$alias} ORDER BY {$alias}.{$nameColumn}";
        $rows = wecoza_db()->getAll($sql) ?: [];

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
        }
        unset($row);

        return $rows;
    }

    public function validate(array $data, ?int $id = null): array {
        $errors = [];
        $config = wecoza_config('clients');
        $rules = $config['validation_rules'] ?? [];

        foreach ($rules as $field => $ruleSet) {
            if (!empty($ruleSet['required']) && empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                continue;
            }

            if (empty($data[$field])) {
                continue;
            }

            $value = $data[$field];

            if (!empty($ruleSet['max_length']) && strlen($value) > (int) $ruleSet['max_length']) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must not exceed ' . (int) $ruleSet['max_length'] . ' characters.';
            }

            if (!empty($ruleSet['email']) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = __('Please provide a valid email address.', 'wecoza-core');
            }

            if (!empty($ruleSet['date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    $errors[$field] = __('Please provide a valid date.', 'wecoza-core');
                }
            }

            if (!empty($ruleSet['in']) && !in_array($value, (array) $ruleSet['in'], true)) {
                $errors[$field] = __('Invalid value selected.', 'wecoza-core');
            }

            if (!empty($ruleSet['unique']) && $field === 'company_registration_nr') {
                $existing = $this->getByRegistrationNumber($value);
                if ($existing && (!$id || (int) $existing['id'] !== (int) $id)) {
                    $errors[$field] = __('This company registration number already exists.', 'wecoza-core');
                }
            }
        }

        // Validate main_client_id specifically
        if (!empty($data['main_client_id'])) {
            $mainClientId = (int) $data['main_client_id'];

            if ($mainClientId <= 0) {
                $errors['main_client_id'] = __('Invalid main client selected.', 'wecoza-core');
            } elseif ($id && $mainClientId === (int) $id) {
                $errors['main_client_id'] = __('A client cannot be its own parent.', 'wecoza-core');
            } else {
                // Check if the selected main client exists and is actually a main client
                $mainClient = self::getById($mainClientId);
                if (!$mainClient) {
                    $errors['main_client_id'] = __('Selected main client does not exist.', 'wecoza-core');
                } elseif (!empty($mainClient['main_client_id'])) {
                    $errors['main_client_id'] = __('Selected client is already a sub-client. Please select a main client.', 'wecoza-core');
                }
            }
        }

        return $errors;
    }

    public function getLocationHierarchy(bool $useCache = true): array {
        return $this->sitesModel->getLocationHierarchy($useCache);
    }

    public function getLocationById(int $locationId): ?array {
        return $this->sitesModel->getLocationById($locationId);
    }

    public function getSitesModel(): SitesModel {
        return $this->sitesModel;
    }



    public function getCommunicationsModel(): ClientCommunicationsModel {
        return $this->communicationsModel;
    }

    protected function decodeJsonFields(&$data) {
        foreach ($this->jsonFields as $field) {
            if (isset($data[$field])) {
                $decoded = json_decode($data[$field], true);
                $data[$field] = is_array($decoded) ? $decoded : [];
            }
        }
    }

    /**
     * Get only main clients (clients without a main_client_id)
     */
    public function getMainClients(): array {
        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $nameColumn = $this->getColumn('client_name', $primaryKey);
        $registrationColumn = $this->getColumn('company_registration_nr');

        $select = [
            "{$alias}.{$primaryKey} AS id",
            "{$alias}.{$nameColumn} AS client_name",
        ];

        if ($registrationColumn) {
            $select[] = "{$alias}.{$registrationColumn} AS company_registration_nr";
        }

        $where = ["{$alias}.main_client_id IS NULL"];
        // Exclude soft-deleted records (only if deleted_at column exists)
        if (wecoza_db()->tableHasColumn($this->table, 'deleted_at')) {
            $where[] = "{$alias}.deleted_at IS NULL";
        }

        $sql = 'SELECT ' . implode(', ', $select) . "
                FROM {$this->table} {$alias}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$alias}.{$nameColumn}";

        $rows = wecoza_db()->getAll($sql) ?: [];

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get sub-clients of a specific main client
     */
    public function getSubClients(int $mainClientId): array {
        $mainClientId = (int) $mainClientId;
        if ($mainClientId <= 0) {
            return [];
        }

        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $nameColumn = $this->getColumn('client_name', $primaryKey);
        $registrationColumn = $this->getColumn('company_registration_nr');

        $select = [
            "{$alias}.{$primaryKey} AS id",
            "{$alias}.{$nameColumn} AS client_name",
        ];

        if ($registrationColumn) {
            $select[] = "{$alias}.{$registrationColumn} AS company_registration_nr";
        }

        $where = ["{$alias}.main_client_id = :main_client_id"];
        // Exclude soft-deleted records (only if deleted_at column exists)
        if (wecoza_db()->tableHasColumn($this->table, 'deleted_at')) {
            $where[] = "{$alias}.deleted_at IS NULL";
        }

        $sql = 'SELECT ' . implode(', ', $select) . "
                FROM {$this->table} {$alias}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$alias}.{$nameColumn}";

        $rows = wecoza_db()->getAll($sql, [':main_client_id' => $mainClientId]) ?: [];

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get all clients with their sub-client relationship information
     */
    public function getAllWithHierarchy(): array {
        $alias = 'c';
        $primaryKey = $this->resolvedPrimaryKey;
        $nameColumn = $this->getColumn('client_name', $primaryKey);
        $registrationColumn = $this->getColumn('company_registration_nr');
        $mainClientColumn = $this->getColumn('main_client_id');

        $select = [
            "{$alias}.{$primaryKey} AS id",
            "{$alias}.{$nameColumn} AS client_name",
        ];

        if ($registrationColumn) {
            $select[] = "{$alias}.{$registrationColumn} AS company_registration_nr";
        }

        if ($mainClientColumn) {
            $select[] = "{$alias}.{$mainClientColumn} AS main_client_id";
        }

        $sql = 'SELECT ' . implode(', ', $select) . "
                FROM {$this->table} {$alias}
                ORDER BY {$alias}.main_client_id NULLS FIRST, {$alias}.{$nameColumn}";

        $rows = wecoza_db()->getAll($sql) ?: [];

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Update client hierarchy (change a client from main to sub-client or vice versa)
     */
    public function updateClientHierarchy(int $clientId, ?int $mainClientId = null): bool {
        $clientId = (int) $clientId;
        if ($clientId <= 0) {
            return false;
        }

        $data = [];
        if ($mainClientId === null) {
            $data['main_client_id'] = null; // Make it a main client
        } else {
            $mainClientId = (int) $mainClientId;
            if ($mainClientId <= 0 || $mainClientId === $clientId) {
                return false;
            }
            $data['main_client_id'] = $mainClientId;
        }

        $where = $this->resolvedPrimaryKey . ' = :id';
        $params = [':id' => $clientId];

        return wecoza_db()->update($this->table, $data, $where, $params);
    }
}
