<?php
/**
 * WeCoza Core - Abstract Base Repository
 *
 * Provides common data access patterns for database operations.
 * Implements repository pattern for clean separation of concerns.
 *
 * Child classes should:
 * - Set static $table and $primaryKey
 * - Override methods as needed for custom behavior
 *
 * @package WeCoza\Core\Abstract
 * @since 1.0.0
 */

namespace WeCoza\Core\Abstract;

use WeCoza\Core\Database\PostgresConnection;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseRepository
{
    /**
     * Database connection instance
     */
    protected PostgresConnection $db;

    /**
     * Table name (override in child class)
     */
    protected static string $table = '';

    /**
     * Primary key column name
     */
    protected static string $primaryKey = 'id';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = PostgresConnection::getInstance();
    }

    /**
     * Get database connection
     *
     * @return PostgresConnection
     */
    protected function db(): PostgresConnection
    {
        return $this->db;
    }

    /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Find a record by ID
     *
     * @param int $id Record ID
     * @return array|null Record data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s = :id LIMIT 1",
            static::$table,
            static::$primaryKey
        );

        try {
            $stmt = $this->db->query($sql, ['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository findById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find all records with pagination
     *
     * @param int $limit Max records to return
     * @param int $offset Offset for pagination
     * @param string $orderBy Column to order by
     * @param string $order Sort direction (ASC or DESC)
     * @return array Array of records
     */
    public function findAll(int $limit = 50, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
    {
        // Sanitize order direction
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = sprintf(
            "SELECT * FROM %s ORDER BY %s %s LIMIT :limit OFFSET :offset",
            static::$table,
            $orderBy,
            $order
        );

        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository findAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find records by criteria
     *
     * @param array $criteria Field => value pairs
     * @param int $limit Max records
     * @param int $offset Offset
     * @param string $orderBy Order by column
     * @param string $order Sort direction
     * @return array Array of matching records
     */
    public function findBy(array $criteria, int $limit = 50, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
    {
        if (empty($criteria)) {
            return $this->findAll($limit, $offset, $orderBy, $order);
        }

        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = sprintf(
            "SELECT * FROM %s WHERE %s ORDER BY %s %s LIMIT :limit OFFSET :offset",
            static::$table,
            implode(' AND ', $conditions),
            $orderBy,
            $order
        );

        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository findBy error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a single record by criteria
     *
     * @param array $criteria Field => value pairs
     * @return array|null Record or null
     */
    public function findOneBy(array $criteria): ?array
    {
        $results = $this->findBy($criteria, 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Count records
     *
     * @param array $criteria Optional filter criteria
     * @return int Record count
     */
    public function count(array $criteria = []): int
    {
        $sql = sprintf("SELECT COUNT(*) FROM %s", static::$table);
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                if ($value === null) {
                    $conditions[] = "{$field} IS NULL";
                } else {
                    $conditions[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        try {
            $stmt = $this->db->query($sql, $params);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a record exists
     *
     * @param int $id Record ID
     * @return bool
     */
    public function exists(int $id): bool
    {
        $sql = sprintf(
            "SELECT EXISTS(SELECT 1 FROM %s WHERE %s = :id)",
            static::$table,
            static::$primaryKey
        );

        try {
            $stmt = $this->db->query($sql, ['id' => $id]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Insert a new record
     *
     * @param array $data Column => value pairs
     * @return int|null Inserted ID or null on failure
     */
    public function insert(array $data): ?int
    {
        if (empty($data)) {
            return null;
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            static::$primaryKey
        );

        try {
            $stmt = $this->db->query($sql, $data);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository insert error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a record
     *
     * @param int $id Record ID
     * @param array $data Column => value pairs
     * @return bool Success
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :_id",
            static::$table,
            implode(', ', $setParts),
            static::$primaryKey
        );

        $data['_id'] = $id;

        try {
            $stmt = $this->db->query($sql, $data);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a record
     *
     * @param int $id Record ID
     * @return bool Success
     */
    public function delete(int $id): bool
    {
        $sql = sprintf(
            "DELETE FROM %s WHERE %s = :id",
            static::$table,
            static::$primaryKey
        );

        try {
            $stmt = $this->db->query($sql, ['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete records by criteria
     *
     * @param array $criteria Field => value pairs
     * @return int Number of deleted records
     */
    public function deleteBy(array $criteria): int
    {
        if (empty($criteria)) {
            return 0; // Safety: don't delete all
        }

        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            $conditions[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            static::$table,
            implode(' AND ', $conditions)
        );

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("WeCoza Core: Repository deleteBy error: " . $e->getMessage());
            return 0;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Get paginated results
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Records per page
     * @param array $criteria Optional filter criteria
     * @param string $orderBy Order by column
     * @param string $order Sort direction
     * @return array Pagination result with items, total, page, etc.
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $criteria = [],
        string $orderBy = 'created_at',
        string $order = 'DESC'
    ): array {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = $this->count($criteria);

        $items = empty($criteria)
            ? $this->findAll($perPage, $offset, $orderBy, $order)
            : $this->findBy($criteria, $perPage, $offset, $orderBy, $order);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Begin a transaction
     *
     * @return bool
     */
    protected function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    protected function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    protected function rollback(): bool
    {
        return $this->db->rollback();
    }
}
