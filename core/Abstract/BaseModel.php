<?php
/**
 * WeCoza Core - Abstract Base Model
 *
 * Provides common model functionality including hydration, type casting,
 * array conversion, and database access patterns.
 *
 * Child classes should:
 * - Define protected properties matching database columns
 * - Set static $table, $primaryKey, $casts, $fillable as needed
 * - Implement abstract methods: getById(), save(), update(), delete()
 *
 * @package WeCoza\Core\Abstract
 * @since 1.0.0
 */

namespace WeCoza\Core\Abstract;

use WeCoza\Core\Database\PostgresConnection;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseModel
{
    /**
     * Database table name (override in child class)
     */
    protected static string $table = '';

    /**
     * Primary key column name
     */
    protected static string $primaryKey = 'id';

    /**
     * Property type casts
     *
     * Example: ['id' => 'int', 'isActive' => 'bool', 'metadata' => 'json']
     * Supported: int, integer, float, double, bool, boolean, string, array, json
     */
    protected static array $casts = [];

    /**
     * Mass-assignable properties (if empty, all are allowed)
     */
    protected static array $fillable = [];

    /**
     * Properties that cannot be mass-assigned
     */
    protected static array $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Constructor
     *
     * @param array|object $data Optional data to hydrate the model (accepts array or stdClass from PDO)
     */
    public function __construct(array|object $data = [])
    {
        if (!empty($data)) {
            // Convert stdClass to array if needed
            $dataArray = is_object($data) ? (array) $data : $data;
            $this->hydrate($dataArray);
        }
    }

    /**
     * Magic getter for property access
     *
     * Allows access via both snake_case and camelCase.
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get(string $name)
    {
        // Try exact match first
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        // Try camelCase conversion
        $camelCase = $this->snakeToCamel($name);
        if (property_exists($this, $camelCase)) {
            return $this->$camelCase;
        }

        // Try snake_case conversion
        $snakeCase = $this->camelToSnake($name);
        if (property_exists($this, $snakeCase)) {
            return $this->$snakeCase;
        }

        return null;
    }

    /**
     * Magic isset for property checking
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    /**
     * Hydrate model from data array
     *
     * Maps database columns (snake_case) to model properties.
     * Applies type casting as defined in $casts.
     *
     * @param array $data Data array (typically from database)
     * @return static Fluent return
     */
    public function hydrate(array $data): static
    {
        foreach ($data as $key => $value) {
            // Determine property name (try both formats)
            $property = property_exists($this, $key) ? $key : $this->snakeToCamel($key);

            if (!property_exists($this, $property)) {
                continue;
            }

            // Check if guarded (unless fillable is explicitly set)
            if (!empty(static::$fillable) && !in_array($property, static::$fillable)) {
                continue;
            }
            if (in_array($property, static::$guarded) && !in_array($property, static::$fillable)) {
                // Allow guarded properties during hydration (from DB), just not mass assignment
                // Actually, we want to allow id etc from DB, so we hydrate all
            }

            // Cast and set value
            $this->$property = $this->castValue($property, $value);
        }

        return $this;
    }

    /**
     * Convert model to array (camelCase keys)
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        $properties = get_object_vars($this);

        foreach ($properties as $property => $value) {
            // Skip null values optionally
            $result[$property] = $value;
        }

        return $result;
    }

    /**
     * Convert model to database array (snake_case keys)
     *
     * @param bool $includeNull Whether to include null values
     * @return array
     */
    public function toDbArray(bool $includeNull = false): array
    {
        $result = [];
        $properties = get_object_vars($this);

        foreach ($properties as $property => $value) {
            if (!$includeNull && $value === null) {
                continue;
            }

            $dbKey = $this->camelToSnake($property);

            // Convert arrays/objects to JSON for JSONB columns
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $result[$dbKey] = $value;
        }

        return $result;
    }

    /**
     * Fill model with data (respects fillable/guarded)
     *
     * @param array $data Data to fill
     * @return static Fluent return
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $property = property_exists($this, $key) ? $key : $this->snakeToCamel($key);

            if (!property_exists($this, $property)) {
                continue;
            }

            // Check fillable/guarded
            if (!empty(static::$fillable) && !in_array($property, static::$fillable)) {
                continue;
            }
            if (in_array($property, static::$guarded)) {
                continue;
            }

            $this->$property = $this->castValue($property, $value);
        }

        return $this;
    }

    /**
     * Cast a value to its defined type
     *
     * Uses centralized wecoza_sanitize_value() helper.
     *
     * @param string $property Property name
     * @param mixed $value Value to cast
     * @return mixed Cast value
     */
    protected function castValue(string $property, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cast = static::$casts[$property] ?? null;

        if (!$cast) {
            return $value;
        }

        // Use 'raw' type for date/datetime to preserve as-is
        if (in_array($cast, ['date', 'datetime'], true)) {
            return $value;
        }

        return wecoza_sanitize_value($value, $cast);
    }

    /**
     * Convert snake_case to camelCase
     *
     * @param string $value Input string
     * @return string camelCase string
     */
    protected function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }

    /**
     * Convert camelCase to snake_case
     *
     * @param string $value Input string
     * @return string snake_case string
     */
    protected function camelToSnake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /**
     * Get database connection
     *
     * @return PostgresConnection
     */
    protected static function db(): PostgresConnection
    {
        return PostgresConnection::getInstance();
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public static function getTable(): string
    {
        return static::$table;
    }

    /**
     * Get the primary key column name
     *
     * @return string
     */
    public static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Check if model has a primary key value set
     *
     * @return bool
     */
    public function exists(): bool
    {
        $pk = static::$primaryKey;
        $pkCamel = $this->snakeToCamel($pk);

        $value = property_exists($this, $pkCamel) ? $this->$pkCamel : ($this->$pk ?? null);

        return $value !== null && $value > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Abstract Methods - Must be implemented by child classes
    |--------------------------------------------------------------------------
    */

    /**
     * Find a record by ID
     *
     * @param int $id Record ID
     * @return static|null
     */
    abstract public static function getById(int $id): ?static;

    /**
     * Get all records with pagination
     *
     * @param int $limit Max records to return
     * @param int $offset Offset for pagination
     * @return array Array of model instances
     */
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        // Default implementation - override in child for custom behavior
        return [];
    }

    /**
     * Save a new record
     *
     * @return bool Success
     */
    abstract public function save(): bool;

    /**
     * Update an existing record
     *
     * @return bool Success
     */
    abstract public function update(): bool;

    /**
     * Delete the record
     *
     * @return bool Success
     */
    abstract public function delete(): bool;
}
