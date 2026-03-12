<?php
declare(strict_types=1);

/**
 * WeCoza Core - Audit Service
 *
 * Writes high-level action-code audit log entries to wecoza_events.audit_log.
 * Reads entries by entity type/ID. Purges entries beyond retention period.
 *
 * Design decisions:
 * - D017: High-level only, entity type + ID, no PII, 3-year retention
 * - D018: Action codes only (e.g. CLASS_STATUS_CHANGED), no field diffs
 * - D019: Admin-only access via shortcode gatekeeping (not enforced here)
 *
 * Write failures are logged but NEVER thrown — audit must not block parent operations.
 *
 * @package WeCoza\Classes\Services
 * @since 1.1.0
 */

namespace WeCoza\Classes\Services;

use WeCoza\Core\Database\PostgresConnection;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class AuditService
{
    private PostgresConnection $db;

    /**
     * Known action codes — enforces the "action codes only" rule (D018).
     */
    public const ACTION_CODES = [
        // Class actions
        'CLASS_CREATED',
        'CLASS_UPDATED',
        'CLASS_STATUS_CHANGED',
        'CLASS_AGENT_ASSIGNED',
        'CLASS_AGENT_REMOVED',
        'CLASS_LEARNER_ADDED',
        'CLASS_LEARNER_REMOVED',
        // Learner actions
        'LEARNER_CREATED',
        'LEARNER_UPDATED',
        'LEARNER_LP_STARTED',
        'LEARNER_LP_COMPLETED',
        'LEARNER_LP_STATUS_CHANGED',
        'LEARNER_DELETED',
        'LEARNER_EXAM_RECORDED',
        'LEARNER_PORTFOLIO_UPLOADED',
        // Agent actions
        'AGENT_CREATED',
        'AGENT_UPDATED',
        'AGENT_ASSIGNED',
        'AGENT_REMOVED',
        // Client actions
        'CLIENT_CREATED',
        'CLIENT_UPDATED',
        'CLIENT_DELETED',
    ];

    /**
     * Default retention period in months (3 years = 36 months).
     */
    public const DEFAULT_RETENTION_MONTHS = 36;

    /**
     * Constructor with dependency injection.
     *
     * @param PostgresConnection|null $db
     */
    public function __construct(?PostgresConnection $db = null)
    {
        $this->db = $db ?? PostgresConnection::getInstance();
    }

    /*
    |--------------------------------------------------------------------------
    | Write
    |--------------------------------------------------------------------------
    */

    /**
     * Write a high-level audit log entry.
     *
     * Never throws — write failures are logged and suppressed so audit
     * logging never blocks the parent operation.
     *
     * @param string   $action     Action code (e.g. CLASS_STATUS_CHANGED)
     * @param string   $entityType Entity type (e.g. 'class', 'learner')
     * @param int      $entityId   Entity primary key
     * @param int|null $userId     WordPress user ID (null = system action)
     * @param array    $extra      Additional safe context (no PII)
     * @return bool True if entry was written, false on failure
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?int $userId = null,
        array $extra = []
    ): bool {
        try {
            // Build context JSONB — entity_type + entity_id + safe extras only
            $context = array_merge([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ], $extra);

            // Build message — human-readable but no PII
            $message = "{$action}: {$entityType} #{$entityId}";

            // Capture request metadata when available
            $ipAddress = null;
            $userAgent = null;
            $requestUri = null;

            if (function_exists('sanitize_text_field')) {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
                    ? sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
                    : null;
                $requestUri = $_SERVER['REQUEST_URI'] ?? null;
            }

            // Resolve user ID from WP if not provided
            if ($userId === null && function_exists('get_current_user_id')) {
                $userId = get_current_user_id() ?: null;
            }

            $sql = "INSERT INTO wecoza_events.audit_log
                    (level, action, message, context, user_id, ip_address, user_agent, request_uri)
                    VALUES (:level, :action, :message, :context::jsonb, :user_id, :ip_address::inet, :user_agent, :request_uri)";

            $this->db->query($sql, [
                'level' => 'info',
                'action' => $action,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_uri' => $requestUri,
            ]);

            return true;
        } catch (Exception $e) {
            // Audit failures must never block the parent operation
            if (function_exists('wecoza_log')) {
                wecoza_log("AuditService::log() failed: {$e->getMessage()}", 'error');
            } else {
                error_log("[WeCoza][error] AuditService::log() failed: {$e->getMessage()}");
            }
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Read
    |--------------------------------------------------------------------------
    */

    /**
     * Get audit log entries for a specific entity, paginated.
     *
     * @param string $entityType Entity type (e.g. 'class', 'learner')
     * @param int    $entityId   Entity primary key
     * @param int    $limit      Max entries to return
     * @param int    $offset     Offset for pagination
     * @return array Array of audit log rows
     */
    public function getEntityLog(
        string $entityType,
        int $entityId,
        int $limit = 50,
        int $offset = 0
    ): array {
        $sql = "SELECT id, level, action, message, context, user_id, created_at
                FROM wecoza_events.audit_log
                WHERE context->>'entity_type' = :entity_type
                  AND (context->>'entity_id')::int = :entity_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $result = $this->db->getAll($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $result ?: [];
    }

    /**
     * Get total count of audit log entries for an entity.
     *
     * @param string $entityType
     * @param int    $entityId
     * @return int
     */
    public function getEntityLogCount(string $entityType, int $entityId): int
    {
        $sql = "SELECT COUNT(*) as cnt
                FROM wecoza_events.audit_log
                WHERE context->>'entity_type' = :entity_type
                  AND (context->>'entity_id')::int = :entity_id";

        $result = $this->db->getRow($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        return $result ? (int) $result['cnt'] : 0;
    }

    /**
     * Get recent audit log entries across all entities, paginated.
     * Used by the [wecoza_audit_log] shortcode.
     *
     * @param int         $limit
     * @param int         $offset
     * @param string|null $filterEntityType Optional filter by entity type
     * @return array
     */
    public function getRecentLog(
        int $limit = 50,
        int $offset = 0,
        ?string $filterEntityType = null
    ): array {
        $params = ['limit' => $limit, 'offset' => $offset];

        // Only show entries logged by our AuditService (known action codes)
        // — excludes legacy noise like user_login from other systems
        $actionPlaceholders = [];
        foreach (self::ACTION_CODES as $i => $code) {
            $key = "ac_{$i}";
            $actionPlaceholders[] = ":{$key}";
            $params[$key] = $code;
        }
        $actionIn = implode(',', $actionPlaceholders);

        $conditions = ["action IN ({$actionIn})"];

        if ($filterEntityType !== null) {
            $conditions[] = "context->>'entity_type' = :entity_type";
            $params['entity_type'] = $filterEntityType;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT id, level, action, message, context, user_id, created_at
                FROM wecoza_events.audit_log
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $result = $this->db->getAll($sql, $params);
        return $result ?: [];
    }

    /*
    |--------------------------------------------------------------------------
    | Purge
    |--------------------------------------------------------------------------
    */

    /**
     * Delete audit log entries older than the given number of months.
     *
     * @param int $months Retention period in months (default: 36 = 3 years)
     * @return int Number of rows deleted
     */
    public function purgeOlderThan(int $months = self::DEFAULT_RETENTION_MONTHS): int
    {
        try {
            $sql = "DELETE FROM wecoza_events.audit_log
                    WHERE created_at < NOW() - INTERVAL ':months months'";

            // PDO can't parameterize INTERVAL directly, use string substitution
            // with validated integer to prevent injection
            $months = max(1, abs($months));
            $sql = "DELETE FROM wecoza_events.audit_log
                    WHERE created_at < NOW() - INTERVAL '{$months} months'";

            $stmt = $this->db->query($sql);
            $deleted = $stmt->rowCount();

            if ($deleted > 0 && function_exists('wecoza_log')) {
                wecoza_log("AuditService::purgeOlderThan({$months}): deleted {$deleted} entries", 'info');
            }

            return $deleted;
        } catch (Exception $e) {
            if (function_exists('wecoza_log')) {
                wecoza_log("AuditService::purgeOlderThan() failed: {$e->getMessage()}", 'error');
            } else {
                error_log("[WeCoza][error] AuditService::purgeOlderThan() failed: {$e->getMessage()}");
            }
            return 0;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Utilities
    |--------------------------------------------------------------------------
    */

    /**
     * Check if an action code is known/valid.
     *
     * @param string $action
     * @return bool
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::ACTION_CODES, true);
    }
}
