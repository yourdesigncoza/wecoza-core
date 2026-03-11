<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - Saved Query Repository
 *
 * Manages CRUD operations for the saved_queries table in PostgreSQL.
 * Extends BaseRepository for column whitelisting and security.
 *
 * @package WeCoza\NLQ\Repositories
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

final class SavedQueryRepository extends BaseRepository
{
    protected static string $table = 'saved_queries';
    protected static string $primaryKey = 'id';

    /* ─── Column Whitelisting ─────────────────────────────── */

    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'query_name', 'category', 'is_active', 'created_by', 'created_at', 'updated_at', 'last_executed', 'execution_count'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'query_name', 'query_slug', 'category', 'is_active', 'created_by', 'created_at'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'query_name', 'query_slug', 'description', 'natural_language',
            'sql_query', 'category', 'is_active', 'created_by',
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return [
            'query_name', 'query_slug', 'description', 'natural_language',
            'sql_query', 'category', 'is_active', 'updated_by', 'updated_at',
        ];
    }

    /* ─── Custom Queries ──────────────────────────────────── */

    /**
     * Find a saved query by its slug
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM saved_queries WHERE query_slug = :slug AND is_active = TRUE LIMIT 1"
            );
            $stmt->execute(['slug' => $slug]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::findBySlug error: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Get all active queries, optionally filtered by category
     */
    public function findAllActive(?string $category = null, string $orderBy = 'query_name', string $direction = 'ASC'): array
    {
        $where = "WHERE is_active = TRUE";
        $params = [];

        if ($category !== null) {
            $where .= " AND category = :category";
            $params['category'] = $category;
        }

        // Validate order column
        if (!in_array($orderBy, $this->getAllowedOrderColumns(), true)) {
            $orderBy = 'query_name';
        }
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM saved_queries {$where} ORDER BY {$orderBy} {$direction}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::findAllActive error: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get all queries for admin management (including inactive)
     */
    public function findAllForAdmin(string $search = '', string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $where = "WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $where .= " AND (query_name ILIKE :search OR description ILIKE :search OR category ILIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!in_array($orderBy, $this->getAllowedOrderColumns(), true)) {
            $orderBy = 'created_at';
        }
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM saved_queries {$where} ORDER BY {$orderBy} {$direction}";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::findAllForAdmin error: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Increment execution count and update last_executed timestamp
     */
    public function recordExecution(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE saved_queries SET execution_count = execution_count + 1, last_executed = NOW() WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);
            return true;
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::recordExecution error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get all distinct categories
     */
    public function getCategories(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT DISTINCT category FROM saved_queries WHERE category IS NOT NULL AND category != '' ORDER BY category"
            );
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category');
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::getCategories error: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Generate a unique slug from a query name
     */
    public function generateSlug(string $name): string
    {
        $slug = sanitize_title($name);
        $original = $slug;
        $counter = 1;

        while ($this->findBySlug($slug) !== null) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Hard-delete: permanently remove a query from the database
     */
    public function hardDelete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM saved_queries WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            wecoza_log("SavedQueryRepository::hardDelete error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Soft-delete: mark a query as inactive (kept for potential future use)
     */
    public function deactivate(int $id, int $userId): bool
    {
        return $this->update($id, [
            'is_active'  => 'false',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reactivate a previously deactivated query
     */
    public function activate(int $id, int $userId): bool
    {
        return $this->update($id, [
            'is_active'  => 'true',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
