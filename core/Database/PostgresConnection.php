<?php
/**
 * WeCoza Core - PostgreSQL Database Connection
 *
 * Singleton PDO wrapper with lazy loading and SSL support.
 * Merged from WeCozaLearnersDB.php and DatabaseService.php.
 *
 * Features:
 * - Lazy connection (connects on first query, not instantiation)
 * - SSL mode for DigitalOcean Managed Database
 * - Transaction support
 * - Utility methods for schema inspection
 *
 * @package WeCoza\Core\Database
 * @since 1.0.0
 */

namespace WeCoza\Core\Database;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class PostgresConnection
{
    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * PDO connection instance
     */
    private ?PDO $pdo = null;

    /**
     * Connection info for debugging
     */
    private array $connectionInfo = [];

    /**
     * Cache for table column metadata (avoids repeated information_schema queries)
     */
    private array $tableColumnsCache = [];

    /**
     * Private constructor - use getInstance()
     */
    private function __construct()
    {
        // Lazy loading - don't connect here
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to PostgreSQL database (lazy loading)
     *
     * Connection is deferred until actually needed. This prevents
     * database connections during plugin loading when not required.
     *
     * @return void
     */
    private function connect(): void
    {
        // Skip if already connected
        if ($this->pdo !== null) {
            return;
        }

        // Check if WordPress functions are available (will retry on next query)
        if (!function_exists('get_option')) {
            error_log('WeCoza Core: WordPress not ready - deferring database connection');
            return;
        }

        // Load configuration
        $config = function_exists('wecoza_config') ? wecoza_config('app') : [];
        $dbConfig = $config['database'] ?? [];
        $defaults = $dbConfig['defaults'] ?? [];

        // Get credentials from WordPress options with fallbacks
        $host = get_option('wecoza_postgres_host', $defaults['host'] ?? '');
        $port = get_option('wecoza_postgres_port', $defaults['port'] ?? '25060');
        $dbname = get_option('wecoza_postgres_dbname', $defaults['dbname'] ?? '');
        $user = get_option('wecoza_postgres_user', $defaults['user'] ?? '');
        $pass = get_option('wecoza_postgres_password', '');

        // Store connection info for debugging (without password)
        $this->connectionInfo = compact('host', 'port', 'dbname', 'user');

        // Check if password is configured
        if (empty($pass)) {
            error_log('WeCoza Core: PostgreSQL password not configured. Please set wecoza_postgres_password option.');
            return;
        }

        // SSL mode (required for DigitalOcean Managed Database)
        $sslmode = $dbConfig['sslmode'] ?? 'require';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

            $this->pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Uncomment for connection debugging:
                // error_log('WeCoza Core: PostgreSQL connection successful');
            }
        } catch (PDOException $e) {
            error_log('WeCoza Core: PostgreSQL connection error: ' . $e->getMessage());
            $this->pdo = null;
        }
    }

    /**
     * Get PDO instance (triggers lazy connection)
     *
     * @return PDO|null
     */
    public function getPdo(): ?PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Check if connected to database
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->getPdo() !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Execute a query with parameters
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     * @throws Exception If database not connected
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('WeCoza Core: Query error: ' . $e->getMessage());
            error_log('WeCoza Core: SQL: ' . $sql);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WeCoza Core: Params: ' . print_r($params, true));
            }
            throw $e;
        }
    }

    /**
     * Prepare a SQL statement
     *
     * @param string $sql SQL query
     * @return PDOStatement
     * @throws Exception If database not connected
     */
    public function prepare(string $sql): PDOStatement
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->prepare($sql);
    }

    /**
     * Execute a SQL statement directly
     *
     * @param string $sql SQL statement
     * @return int Number of affected rows
     * @throws Exception If database not connected
     */
    public function exec(string $sql): int
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->exec($sql);
    }

    /**
     * Quote a string for use in a query
     *
     * @param string $string String to quote
     * @param int $type PDO parameter type
     * @return string Quoted string
     * @throws Exception If database not connected
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->quote($string, $type);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Convenience Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Execute query and return all rows
     *
     * @param string $sql SQL query
     * @param array $params Bound parameters
     * @return array|false Array of rows or false on failure
     */
    public function getAll(string $sql, array $params = []): array|false
    {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Execute query and return single row
     *
     * @param string $sql SQL query
     * @param array $params Bound parameters
     * @return array|false Single row or false on failure
     */
    public function getRow(string $sql, array $params = []): array|false
    {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Execute query and return single scalar value
     *
     * @param string $sql SQL query
     * @param array $params Bound parameters
     * @return mixed Single value or false on failure
     */
    public function getValue(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Insert a row into a table
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return string|bool Last insert ID or true (no RETURNING), false on failure
     */
    public function insert(string $table, array $data): string|bool
    {
        try {
            $pdo = $this->getPdo();
            if ($pdo === null) {
                return false;
            }

            $fields = array_keys($data);
            $placeholders = array_map(fn($f) => ':' . $f, $fields);

            // Detect primary key column for RETURNING clause
            $returningColumn = null;
            foreach (['id', 'agent_id', 'client_id', 'location_id', 'site_id', 'communication_id'] as $candidate) {
                if ($this->tableHasColumn($table, $candidate)) {
                    $returningColumn = $candidate;
                    break;
                }
            }

            $returningClause = $returningColumn ? ' RETURNING ' . $returningColumn : '';

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)%s',
                $table,
                implode(', ', $fields),
                implode(', ', $placeholders),
                $returningClause
            );

            $stmt = $pdo->prepare($sql);

            foreach ($data as $field => $value) {
                $stmt->bindValue(':' . $field, $value);
            }

            $stmt->execute();

            if ($returningColumn) {
                return $stmt->fetchColumn();
            }

            return true;
        } catch (\PDOException $e) {
            error_log('WeCoza Core: Insert failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update rows in a table
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value to SET
     * @param string $where WHERE clause (e.g. "id = :id")
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int|false
    {
        try {
            $pdo = $this->getPdo();
            if ($pdo === null) {
                return false;
            }

            $setParts = [];
            foreach ($data as $field => $value) {
                $setParts[] = $field . ' = :set_' . $field;
            }

            $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $setParts), $where);

            $stmt = $pdo->prepare($sql);

            foreach ($data as $field => $value) {
                $stmt->bindValue(':set_' . $field, $value);
            }

            foreach ($whereParams as $param => $value) {
                $stmt->bindValue($param, $value);
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log('WeCoza Core: Update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete rows from a table
     *
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    public function delete(string $table, string $where, array $params = []): int|false
    {
        try {
            $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('WeCoza Core: Delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Begin a transaction
     *
     * @return bool
     * @throws Exception If database not connected
     */
    public function beginTransaction(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     * @throws Exception If database not connected
     */
    public function commit(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     * @throws Exception If database not connected
     */
    public function rollback(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->rollBack();
    }

    /**
     * Check if currently in a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo?->inTransaction() ?? false;
    }

    /**
     * Get the last inserted ID
     *
     * @param string|null $sequenceName PostgreSQL sequence name (optional)
     * @return string Last insert ID
     * @throws Exception If database not connected
     */
    public function lastInsertId(?string $sequenceName = null): string
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new Exception('Database connection not available');
        }
        return $pdo->lastInsertId($sequenceName);
    }

    /*
    |--------------------------------------------------------------------------
    | Utility Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Test database connection
     *
     * @return bool True if connection successful
     */
    public function testConnection(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            error_log('WeCoza Core: Connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     *
     * @param string $tableName Table name
     * @param string $schema Schema name (default: public)
     * @return bool
     */
    public function tableExists(string $tableName, string $schema = 'public'): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        try {
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_schema = :schema
                AND table_name = :table
            )";
            $stmt = $this->query($sql, ['schema' => $schema, 'table' => $tableName]);
            $result = $stmt->fetch();
            return $result['exists'] ?? false;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error checking table existence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get columns for a table
     *
     * @param string $tableName Table name
     * @param string $schema Schema name (default: public)
     * @return array Array of column info
     */
    public function getTableColumns(string $tableName, string $schema = 'public'): array
    {
        $cacheKey = "{$schema}.{$tableName}";
        if (isset($this->tableColumnsCache[$cacheKey])) {
            return $this->tableColumnsCache[$cacheKey];
        }

        $pdo = $this->getPdo();
        if ($pdo === null) {
            return [];
        }

        try {
            $sql = "SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_schema = :schema
                    AND table_name = :table
                    ORDER BY ordinal_position";
            $stmt = $this->query($sql, ['schema' => $schema, 'table' => $tableName]);
            $columns = $stmt->fetchAll();
            $this->tableColumnsCache[$cacheKey] = $columns;
            return $columns;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error getting table columns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a table has a specific column
     *
     * @param string $tableName Table name (may include schema prefix like "public.locations")
     * @param string $columnName Column name to check
     * @return bool
     */
    public function tableHasColumn(string $tableName, string $columnName): bool
    {
        $schema = 'public';
        if (str_contains($tableName, '.')) {
            [$schema, $tableName] = explode('.', $tableName, 2);
        }

        $columns = $this->getTableColumns($tableName, $schema);

        foreach ($columns as $col) {
            if ($col['column_name'] === $columnName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get PostgreSQL version
     *
     * @return string|null Version string or null on error
     */
    public function getVersion(): ?string
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->query('SELECT version()');
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            error_log('WeCoza Core: Error getting database version: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get connection info for debugging
     *
     * Only returns info when WP_DEBUG is enabled.
     *
     * @return array Connection info (without password)
     */
    public function getConnectionInfo(): array
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return ['debug' => 'disabled'];
        }

        return array_merge($this->connectionInfo, [
            'connected' => $this->pdo !== null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Singleton Protection
    |--------------------------------------------------------------------------
    */

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     *
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}
