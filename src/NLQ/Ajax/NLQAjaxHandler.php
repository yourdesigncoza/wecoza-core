<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - AJAX Handlers
 *
 * Handles all AJAX requests for the NLQ module.
 * All endpoints require authentication and nonce verification.
 *
 * @package WeCoza\NLQ\Ajax
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\NLQ\Services\NLQService;
use WeCoza\NLQ\Services\SQLGeneratorService;
use WeCoza\NLQ\Repositories\SavedQueryRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class NLQAjaxHandler
{
    private const NONCE_ACTION = 'wecoza_nlq_nonce';
    private const MANAGE_CAPABILITY = 'manage_options'; // Phase 2: create a custom capability

    /**
     * Register all AJAX actions
     */
    public static function register(): void
    {
        // Query CRUD
        add_action('wp_ajax_wecoza_nlq_save_query', [new self(), 'saveQuery']);
        add_action('wp_ajax_wecoza_nlq_update_query', [new self(), 'updateQuery']);
        add_action('wp_ajax_wecoza_nlq_delete_query', [new self(), 'deleteQuery']);
        add_action('wp_ajax_wecoza_nlq_get_query', [new self(), 'getQuery']);
        add_action('wp_ajax_wecoza_nlq_list_queries', [new self(), 'listQueries']);

        // Execution
        add_action('wp_ajax_wecoza_nlq_preview_sql', [new self(), 'previewSql']);
        add_action('wp_ajax_wecoza_nlq_execute_query', [new self(), 'executeQuery']);

        // AI Generation
        add_action('wp_ajax_wecoza_nlq_generate_sql', [new self(), 'generateSql']);
        add_action('wp_ajax_wecoza_nlq_refine_sql', [new self(), 'refineSql']);

        // Metadata
        add_action('wp_ajax_wecoza_nlq_get_categories', [new self(), 'getCategories']);
    }

    /* ─── Save a new query ────────────────────────────────── */

    public function saveQuery(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $service = new NLQService();
        $result = $service->saveQuery([
            'query_name'       => sanitize_text_field($_POST['query_name'] ?? ''),
            'description'      => sanitize_textarea_field($_POST['description'] ?? ''),
            'natural_language'  => sanitize_textarea_field($_POST['natural_language'] ?? ''),
            'sql_query'        => wp_unslash($_POST['sql_query'] ?? ''),
            'category'         => sanitize_text_field($_POST['category'] ?? ''),
        ], get_current_user_id());

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Query saved successfully.',
                'id'      => $result['id'],
                'slug'    => $result['slug'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Update an existing query ────────────────────────── */

    public function updateQuery(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $queryId = intval($_POST['query_id'] ?? 0);
        if (!$queryId) {
            wp_send_json_error(['message' => 'Invalid query ID.']);
        }

        $service = new NLQService();
        $data = [];

        if (isset($_POST['query_name'])) $data['query_name'] = $_POST['query_name'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['natural_language'])) $data['natural_language'] = $_POST['natural_language'];
        if (isset($_POST['sql_query'])) $data['sql_query'] = wp_unslash($_POST['sql_query']);
        if (isset($_POST['category'])) $data['category'] = $_POST['category'];

        $result = $service->updateQuery($queryId, $data, get_current_user_id());

        if ($result['success']) {
            wp_send_json_success(['message' => 'Query updated successfully.']);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Permanently delete a query ──────────────────────── */

    public function deleteQuery(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $queryId = intval($_POST['query_id'] ?? 0);
        if (!$queryId) {
            wp_send_json_error(['message' => 'Invalid query ID.']);
        }

        $repository = new SavedQueryRepository();
        $success = $repository->hardDelete($queryId);

        if ($success) {
            wp_send_json_success(['message' => 'Query deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete query.']);
        }
    }

    /* ─── Get a single query ──────────────────────────────── */

    public function getQuery(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $queryId = intval($_POST['query_id'] ?? 0);
        if (!$queryId) {
            wp_send_json_error(['message' => 'Invalid query ID.']);
        }

        $repository = new SavedQueryRepository();
        $query = $repository->findById($queryId);

        if ($query) {
            wp_send_json_success($query);
        } else {
            wp_send_json_error(['message' => 'Query not found.']);
        }
    }

    /* ─── List all queries ────────────────────────────────── */

    public function listQueries(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $search = sanitize_text_field($_POST['search'] ?? '');
        $repository = new SavedQueryRepository();
        $queries = $repository->findAllForAdmin($search);

        wp_send_json_success(['queries' => $queries]);
    }

    /* ─── Preview SQL execution (before saving) ──────────── */

    public function previewSql(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $sql = wp_unslash($_POST['sql_query'] ?? '');
        if (empty($sql)) {
            wp_send_json_error(['message' => 'No SQL query provided.']);
        }

        $service = new NLQService();
        $result = $service->executePreview($sql);

        if ($result['success']) {
            wp_send_json_success([
                'columns'   => $result['columns'],
                'data'      => $result['data'],
                'row_count' => $result['row_count'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Execute a saved query by ID ─────────────────────── */

    public function executeQuery(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $queryId = intval($_POST['query_id'] ?? 0);
        if (!$queryId) {
            wp_send_json_error(['message' => 'Invalid query ID.']);
        }

        $service = new NLQService();
        $result = $service->executeById($queryId);

        if ($result['success']) {
            wp_send_json_success([
                'columns'    => $result['columns'],
                'data'       => $result['data'],
                'row_count'  => $result['row_count'],
                'query_name' => $result['query_name'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Generate SQL from natural language ──────────────── */

    public function generateSql(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $question = sanitize_textarea_field($_POST['question'] ?? '');
        if (empty($question)) {
            wp_send_json_error(['message' => 'Please enter a question.']);
        }

        $module = sanitize_text_field($_POST['module'] ?? '') ?: null;

        $generator = new SQLGeneratorService();
        $result = $generator->generate($question, $module);

        if ($result['success']) {
            wp_send_json_success([
                'sql'         => $result['sql'],
                'explanation' => $result['explanation'],
                'module'      => $result['module'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Refine an existing SQL query ────────────────────── */

    public function refineSql(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);
        $this->requireCapability();

        $currentSql = wp_unslash($_POST['current_sql'] ?? '');
        $refinement = sanitize_textarea_field($_POST['refinement'] ?? '');
        $originalPrompt = sanitize_textarea_field($_POST['original_prompt'] ?? '');

        if (empty($currentSql) || empty($refinement)) {
            wp_send_json_error(['message' => 'Current SQL and refinement instructions are required.']);
        }

        $generator = new SQLGeneratorService();
        $result = $generator->refine($currentSql, $refinement, $originalPrompt);

        if ($result['success']) {
            wp_send_json_success([
                'sql'         => $result['sql'],
                'explanation' => $result['explanation'],
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /* ─── Get all categories ──────────────────────────────── */

    public function getCategories(): void
    {
        AjaxSecurity::requireNonce(self::NONCE_ACTION);

        $service = new NLQService();
        wp_send_json_success(['categories' => $service->getCategories()]);
    }

    /* ─── Helpers ─────────────────────────────────────────── */

    private function requireCapability(): void
    {
        if (!current_user_can(self::MANAGE_CAPABILITY)) {
            wp_send_json_error(['message' => 'You do not have permission to perform this action.'], 403);
        }
    }
}
