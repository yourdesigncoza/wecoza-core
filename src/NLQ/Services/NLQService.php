<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - Core Service
 *
 * Orchestrates the NLQ workflow:
 *   1. Validate SQL via SQLSandbox
 *   2. Execute on PostgreSQL
 *   3. Save/manage queries via SavedQueryRepository
 *
 * The AI generation layer (Phase 2) will plug in here.
 *
 * @package WeCoza\NLQ\Services
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Services;

use WeCoza\NLQ\Repositories\SavedQueryRepository;
use WeCoza\Core\Database\PostgresConnection;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

final class NLQService
{
    private SavedQueryRepository $repository;
    private PostgresConnection $db;

    public function __construct()
    {
        $this->repository = new SavedQueryRepository();
        $this->db = PostgresConnection::getInstance();
    }

    /* ─── Execute a saved query by ID ─────────────────────── */

    /**
     * Execute a saved query and return results
     *
     * @param int $queryId The saved query ID
     * @return array{success: bool, data?: array, columns?: array, error?: string, query_name?: string}
     */
    public function executeById(int $queryId): array
    {
        // Fetch the saved query
        $query = $this->repository->findById($queryId);
        if (!$query) {
            return ['success' => false, 'error' => 'Query not found.'];
        }

        if (!($query['is_active'] ?? true)) {
            return ['success' => false, 'error' => 'This query has been deactivated.'];
        }

        // Validate SQL before execution (defense in depth)
        $validation = SQLSandbox::validate($query['sql_query']);
        if (!$validation['valid']) {
            wecoza_log("NLQ: Blocked execution of query #{$queryId}: " . implode(', ', $validation['errors']), 'warning');
            return ['success' => false, 'error' => 'This query failed safety validation and cannot be executed.'];
        }

        // Execute
        $result = $this->executeSql($validation['sanitized']);

        // Record execution stats
        if ($result['success']) {
            $this->repository->recordExecution($queryId);
        }

        $result['query_name'] = $query['query_name'] ?? '';
        $result['description'] = $query['description'] ?? '';
        $result['natural_language'] = $query['natural_language'] ?? '';
        $result['category'] = $query['category'] ?? '';
        $result['sql_query'] = $query['sql_query'] ?? '';

        return $result;
    }

    /* ─── Execute raw SQL (with sandbox validation) ───────── */

    /**
     * Validate and execute a SQL query directly (for preview before saving)
     *
     * @param string $sql Raw SQL query
     * @return array{success: bool, data?: array, columns?: array, error?: string, row_count?: int}
     */
    public function executePreview(string $sql): array
    {
        // Validate first
        $validation = SQLSandbox::validate($sql);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => implode(' ', $validation['errors'])];
        }

        return $this->executeSql($validation['sanitized']);
    }

    /* ─── Save a new query ────────────────────────────────── */

    /**
     * Save a new query to the database
     *
     * @param array $data {query_name, sql_query, description?, natural_language?, category?}
     * @param int   $userId WP user ID of the creator
     * @return array{success: bool, id?: int, slug?: string, error?: string}
     */
    public function saveQuery(array $data, int $userId): array
    {
        // Validate required fields
        if (empty($data['query_name']) || empty($data['sql_query'])) {
            return ['success' => false, 'error' => 'Query name and SQL are required.'];
        }

        // Validate the SQL
        $validation = SQLSandbox::validate($data['sql_query']);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'SQL validation failed: ' . implode(' ', $validation['errors'])];
        }

        // Generate slug
        $slug = $this->repository->generateSlug($data['query_name']);

        // Prepare insert data
        $insertData = [
            'query_name'       => sanitize_text_field($data['query_name']),
            'query_slug'       => $slug,
            'description'      => sanitize_textarea_field($data['description'] ?? ''),
            'natural_language'  => sanitize_textarea_field($data['natural_language'] ?? ''),
            'sql_query'        => $validation['sanitized'],
            'category'         => sanitize_text_field($data['category'] ?? ''),
            'is_active'        => 'true',
            'created_by'       => $userId,
        ];

        try {
            $id = $this->repository->insert($insertData);
            if ($id) {
                wecoza_log("NLQ: Query saved - ID: {$id}, Name: {$insertData['query_name']}, By: {$userId}");
                return ['success' => true, 'id' => $id, 'slug' => $slug];
            }
            return ['success' => false, 'error' => 'Failed to save query.'];
        } catch (Exception $e) {
            wecoza_log("NLQ: Save failed: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => 'Database error while saving query.'];
        }
    }

    /* ─── Update an existing query ────────────────────────── */

    /**
     * Update a saved query
     *
     * @param int   $queryId Query ID to update
     * @param array $data    Fields to update
     * @param int   $userId  WP user ID making the update
     * @return array{success: bool, error?: string}
     */
    public function updateQuery(int $queryId, array $data, int $userId): array
    {
        $existing = $this->repository->findById($queryId);
        if (!$existing) {
            return ['success' => false, 'error' => 'Query not found.'];
        }

        // If SQL is being updated, validate it
        if (!empty($data['sql_query'])) {
            $validation = SQLSandbox::validate($data['sql_query']);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => 'SQL validation failed: ' . implode(' ', $validation['errors'])];
            }
            $data['sql_query'] = $validation['sanitized'];
        }

        // Sanitize fields
        $updateData = ['updated_by' => $userId, 'updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['query_name'])) {
            $updateData['query_name'] = sanitize_text_field($data['query_name']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['natural_language'])) {
            $updateData['natural_language'] = sanitize_textarea_field($data['natural_language']);
        }
        if (isset($data['sql_query'])) {
            $updateData['sql_query'] = $data['sql_query'];
        }
        if (isset($data['category'])) {
            $updateData['category'] = sanitize_text_field($data['category']);
        }

        try {
            $success = $this->repository->update($queryId, $updateData);
            return $success
                ? ['success' => true]
                : ['success' => false, 'error' => 'Failed to update query.'];
        } catch (Exception $e) {
            wecoza_log("NLQ: Update failed for #{$queryId}: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => 'Database error while updating query.'];
        }
    }

    /* ─── Get all categories ──────────────────────────────── */

    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    /* ─── Private: Execute SQL on PostgreSQL ──────────────── */

    private function executeSql(string $sql): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = [];
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
            }

            return [
                'success'   => true,
                'data'      => $rows,
                'columns'   => $columns,
                'row_count' => count($rows),
            ];
        } catch (Exception $e) {
            wecoza_log("NLQ: SQL execution error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error'   => 'Query execution failed: ' . $e->getMessage(),
            ];
        }
    }
}
