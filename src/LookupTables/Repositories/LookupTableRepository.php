<?php
declare(strict_types=1);

/**
 * Lookup Table Repository
 *
 * Config-driven generic CRUD repository for simple reference/lookup tables.
 * Does not extend BaseRepository because BaseRepository uses static $table,
 * whereas this repository needs runtime table config injected per instance.
 *
 * @package WeCoza\LookupTables\Repositories
 * @since 4.1.0
 */

namespace WeCoza\LookupTables\Repositories;

use WeCoza\Core\Database\PostgresConnection;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class LookupTableRepository
{
    /**
     * Database connection instance
     */
    private PostgresConnection $db;

    /**
     * Table configuration
     * Expected keys: table (string), pk (string), columns (string[])
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Table config: ['table' => string, 'pk' => string, 'columns' => string[]]
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db     = PostgresConnection::getInstance();
    }

    /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Return all rows ordered by primary key ASC
     *
     * @return array
     */
    public function findAll(): array
    {
        $table = $this->quoteIdentifier($this->config['table']);
        $pk    = $this->quoteIdentifier($this->config['pk']);

        $sql = "SELECT * FROM {$table} ORDER BY {$pk} ASC";

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            wecoza_log('LookupTableRepository::findAll error: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Find a single row by primary key
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $table = $this->quoteIdentifier($this->config['table']);
        $pk    = $this->quoteIdentifier($this->config['pk']);

        $sql = "SELECT * FROM {$table} WHERE {$pk} = :id LIMIT 1";

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            wecoza_log('LookupTableRepository::findById error: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Insert a new row (only whitelisted columns from config are written)
     *
     * @param array $data Column => value pairs
     * @return int|null Inserted primary key value, or null on failure
     */
    public function insert(array $data): ?int
    {
        // Whitelist columns against config
        $filtered = $this->filterColumns($data);

        if (empty($filtered)) {
            wecoza_log('LookupTableRepository::insert rejected — no valid columns', 'error');
            return null;
        }

        $table = $this->quoteIdentifier($this->config['table']);
        $pk    = $this->quoteIdentifier($this->config['pk']);

        $quotedCols   = array_map([$this, 'quoteIdentifier'], array_keys($filtered));
        $placeholders = array_map(fn($c) => ":{$c}", array_keys($filtered));

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
            $table,
            implode(', ', $quotedCols),
            implode(', ', $placeholders),
            $pk
        );

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            foreach ($filtered as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            wecoza_log('LookupTableRepository::insert error: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Update a row by primary key (only whitelisted columns from config are written)
     *
     * @param int   $id   Primary key value
     * @param array $data Column => value pairs
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        // Whitelist columns against config
        $filtered = $this->filterColumns($data);

        if (empty($filtered)) {
            wecoza_log('LookupTableRepository::update rejected — no valid columns', 'error');
            return false;
        }

        $table = $this->quoteIdentifier($this->config['table']);
        $pk    = $this->quoteIdentifier($this->config['pk']);

        $setParts = [];
        foreach (array_keys($filtered) as $col) {
            $setParts[] = $this->quoteIdentifier($col) . " = :{$col}";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :_pk_id",
            $table,
            implode(', ', $setParts),
            $pk
        );

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            foreach ($filtered as $col => $val) {
                $stmt->bindValue(":{$col}", $val);
            }
            $stmt->bindValue(':_pk_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            wecoza_log('LookupTableRepository::update error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Delete a row by primary key
     *
     * @param int $id Primary key value
     * @return bool
     */
    public function delete(int $id): bool
    {
        $table = $this->quoteIdentifier($this->config['table']);
        $pk    = $this->quoteIdentifier($this->config['pk']);

        $sql = "DELETE FROM {$table} WHERE {$pk} = :id";

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            wecoza_log('LookupTableRepository::delete error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Filter data array to only whitelisted columns from config
     *
     * @param array $data
     * @return array
     */
    private function filterColumns(array $data): array
    {
        $allowed = $this->config['columns'] ?? [];
        return array_filter(
            $data,
            fn($key) => in_array($key, $allowed, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Quote a PostgreSQL identifier to prevent SQL injection via column/table names
     *
     * @param string $identifier
     * @return string
     */
    private function quoteIdentifier(string $identifier): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
        return '"' . $clean . '"';
    }
}
