<?php
declare(strict_types=1);

/**
 * Lookup Table AJAX Handler
 *
 * Single AJAX endpoint for all lookup table CRUD operations.
 * Dispatches to LookupTableRepository based on sub_action and table_key.
 *
 * @package WeCoza\LookupTables\Ajax
 * @since 4.1.0
 */

namespace WeCoza\LookupTables\Ajax;

use WeCoza\Classes\Controllers\ClassTypesController;
use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\LookupTables\Controllers\LookupTableController;
use WeCoza\LookupTables\Repositories\LookupTableRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LookupTableAjaxHandler
{
    /**
     * Constructor — registers AJAX hook immediately
     */
    public function __construct()
    {
        $this->registerHandlers();
    }

    /**
     * Register WordPress AJAX action handlers
     *
     * @return void
     */
    private function registerHandlers(): void
    {
        // Authenticated users only (entire WP requires login)
        add_action('wp_ajax_wecoza_lookup_table', [$this, 'handleRequest']);
        // No nopriv handler — entire WP environment requires login
    }

    /**
     * Main request dispatcher — reads sub_action and table_key from POST
     *
     * @return void
     */
    public function handleRequest(): void
    {
        $tableKey  = AjaxSecurity::post('table_key', 'string', '');
        $subAction = AjaxSecurity::post('sub_action', 'string', '');

        // Resolve table config from the controller's TABLES constant
        $config = LookupTableController::getTableConfig($tableKey);
        if ($config === null) {
            AjaxSecurity::sendError('Invalid table.', 400);
            return;
        }

        switch ($subAction) {
            case 'list':
                $this->handleList($config);
                break;

            case 'create':
                $this->handleCreate($config);
                break;

            case 'update':
                $this->handleUpdate($config);
                break;

            case 'delete':
                $this->handleDelete($config);
                break;

            default:
                AjaxSecurity::sendError('Invalid action.', 400);
                break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sub-action Handlers
    |--------------------------------------------------------------------------
    */

    /**
     * Handle list sub-action (read-only — nonce only)
     *
     * @param array $config Table config
     * @return void
     */
    private function handleList(array $config): void
    {
        AjaxSecurity::requireNonce('lookup_table_nonce');

        try {
            $repo  = new LookupTableRepository($config);
            $items = $repo->findAll();
            AjaxSecurity::sendSuccess(['items' => $items]);
        } catch (\Throwable $e) {
            wecoza_log('LookupTableAjaxHandler::handleList error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('Failed to load items.', 500);
        }
    }

    /**
     * Handle create sub-action (nonce + capability required)
     *
     * @param array $config Table config
     * @return void
     */
    private function handleCreate(array $config): void
    {
        AjaxSecurity::requireAuth('lookup_table_nonce', $config['capability']);

        // Sanitize only whitelisted columns
        $data = $this->sanitizeColumns($config);

        try {
            $repo  = new LookupTableRepository($config);
            $newId = $repo->insert($data);

            if ($newId === null) {
                AjaxSecurity::sendError('Failed to create item.', 500);
                return;
            }

            $this->maybeClearClassTypesCache($config);
            AjaxSecurity::sendSuccess(['id' => $newId], 'Item created successfully.');
        } catch (\Throwable $e) {
            wecoza_log('LookupTableAjaxHandler::handleCreate error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('Failed to create item.', 500);
        }
    }

    /**
     * Handle update sub-action (nonce + capability required)
     *
     * @param array $config Table config
     * @return void
     */
    private function handleUpdate(array $config): void
    {
        AjaxSecurity::requireAuth('lookup_table_nonce', $config['capability']);

        $id = AjaxSecurity::requireValidId($_POST['id'] ?? 0, 'id');

        // Sanitize only whitelisted columns
        $data = $this->sanitizeColumns($config);

        try {
            $repo    = new LookupTableRepository($config);
            $success = $repo->update($id, $data);

            if (!$success) {
                AjaxSecurity::sendError('Failed to update item or item not found.', 500);
                return;
            }

            $this->maybeClearClassTypesCache($config);
            AjaxSecurity::sendSuccess(['success' => true], 'Item updated successfully.');
        } catch (\Throwable $e) {
            wecoza_log('LookupTableAjaxHandler::handleUpdate error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('Failed to update item.', 500);
        }
    }

    /**
     * Handle delete sub-action (nonce + capability required)
     *
     * @param array $config Table config
     * @return void
     */
    private function handleDelete(array $config): void
    {
        AjaxSecurity::requireAuth('lookup_table_nonce', $config['capability']);

        $id = AjaxSecurity::requireValidId($_POST['id'] ?? 0, 'id');

        try {
            $repo    = new LookupTableRepository($config);
            $success = $repo->delete($id);

            if (!$success) {
                AjaxSecurity::sendError('Failed to delete item or item not found.', 500);
                return;
            }

            $this->maybeClearClassTypesCache($config);
            AjaxSecurity::sendSuccess(['success' => true], 'Item deleted successfully.');
        } catch (\Throwable $e) {
            wecoza_log('LookupTableAjaxHandler::handleDelete error: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('Failed to delete item.', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Clear class type transient caches when the class_types table is modified.
     *
     * The class_types table is cached by ClassTypesController::getClassTypes() with
     * a 2-hour TTL. Without this invalidation, changes made via the lookup table
     * manager would not appear in the Class Type dropdown for up to 2 hours.
     *
     * @param array $config Table config (checked for table === 'class_types')
     * @return void
     */
    private function maybeClearClassTypesCache(array $config): void
    {
        if (($config['table'] ?? '') === 'class_types') {
            ClassTypesController::clearCache();
        }
    }

    /**
     * Sanitize POST values for whitelisted column names, type-aware.
     *
     * Tables without a `column_types` key default to 'text' for all columns,
     * preserving backward compatibility.
     *
     * @param array $config Full table config (columns, column_types, etc.)
     * @return array Sanitized column => value pairs
     */
    private function sanitizeColumns(array $config): array
    {
        $data  = [];
        $types = $config['column_types'] ?? [];

        foreach ($config['columns'] as $column) {
            if (!isset($_POST[$column])) {
                continue;
            }

            $type = $types[$column] ?? 'text';
            $data[$column] = match ($type) {
                'number'  => wecoza_sanitize_value($_POST[$column], 'int'),
                'select'  => is_numeric($_POST[$column])
                    ? wecoza_sanitize_value($_POST[$column], 'int')
                    : wecoza_sanitize_value($_POST[$column], 'string'),
                'boolean' => in_array($_POST[$column], ['1', 'true', 'on'], true) ? 'true' : 'false',
                default   => wecoza_sanitize_value($_POST[$column], 'string'),
            };
        }

        return $data;
    }
}
