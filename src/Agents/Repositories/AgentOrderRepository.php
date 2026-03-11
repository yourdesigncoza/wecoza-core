<?php
declare(strict_types=1);

/**
 * Agent Order Repository
 *
 * Handles all database operations for the agent_orders table.
 * Supports idempotent upsert via ON CONFLICT DO NOTHING for rate management.
 *
 * @package WeCoza\Agents\Repositories
 * @since 9.0.0
 */

namespace WeCoza\Agents\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Order Repository class
 *
 * @since 9.0.0
 */
class AgentOrderRepository extends BaseRepository
{
    protected static string $table = 'agent_orders';
    protected static string $primaryKey = 'order_id';

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security - SQL Injection Prevention)
    |--------------------------------------------------------------------------
    */

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array
     */
    protected function getAllowedOrderColumns(): array
    {
        return [
            'order_id',
            'class_id',
            'agent_id',
            'start_date',
            'created_at',
        ];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array
     */
    protected function getAllowedFilterColumns(): array
    {
        return [
            'order_id',
            'class_id',
            'agent_id',
            'status',
            'start_date',
        ];
    }

    /**
     * Get columns allowed for INSERT operations
     *
     * @return array
     */
    protected function getAllowedInsertColumns(): array
    {
        return [
            'class_id',
            'agent_id',
            'rate_type',
            'rate_amount',
            'start_date',
            'end_date',
            'notes',
            'created_at',
            'updated_at',
            'created_by',
        ];
    }

    /**
     * Get columns allowed for UPDATE operations
     *
     * @return array
     */
    protected function getAllowedUpdateColumns(): array
    {
        return [
            'rate_amount',
            'rate_type',
            'end_date',
            'notes',
            'updated_at',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Custom Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Find the active order for a given class and agent
     *
     * Returns the most recent order where end_date is null or in the future.
     *
     * @param int $classId  Class ID
     * @param int $agentId  Agent ID
     * @return array|null   Order row or null if not found
     */
    public function findActiveOrderForClass(int $classId, int $agentId): ?array
    {
        $sql = "SELECT * FROM agent_orders
                WHERE class_id = :class_id
                  AND agent_id = :agent_id
                  AND (end_date IS NULL OR end_date >= CURRENT_DATE)
                ORDER BY start_date DESC
                LIMIT 1";

        try {
            $stmt = $this->db->query($sql, [
                'class_id' => $classId,
                'agent_id' => $agentId,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AgentOrderRepository::findActiveOrderForClass'));
            return null;
        }
    }

    /**
     * Idempotent upsert: ensure an order row exists for a class+agent+start_date
     *
     * Uses ON CONFLICT DO NOTHING so concurrent calls are safe. After the INSERT
     * (or no-op), fetches the canonical order_id for the class+agent pair.
     *
     * @param int    $classId    Class ID
     * @param int    $agentId    Agent ID
     * @param string $startDate  Start date (Y-m-d)
     * @param int    $createdBy  WordPress user ID of creator
     * @return int|null          order_id or null on failure
     */
    public function ensureOrderExists(int $classId, int $agentId, string $startDate, int $createdBy): ?int
    {
        $insertSql = "INSERT INTO agent_orders
                        (class_id, agent_id, rate_type, rate_amount, start_date, created_at, updated_at, created_by)
                      VALUES
                        (:class_id, :agent_id, 'hourly', 0.00, :start_date, NOW(), NOW(), :created_by)
                      ON CONFLICT (class_id, agent_id, start_date) DO NOTHING";

        $selectSql = "SELECT order_id FROM agent_orders
                      WHERE class_id = :class_id AND agent_id = :agent_id
                      ORDER BY start_date DESC
                      LIMIT 1";

        try {
            $this->db->query($insertSql, [
                'class_id'   => $classId,
                'agent_id'   => $agentId,
                'start_date' => $startDate,
                'created_by' => $createdBy,
            ]);

            $stmt   = $this->db->query($selectSql, [
                'class_id' => $classId,
                'agent_id' => $agentId,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (int) $result['order_id'] : null;
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AgentOrderRepository::ensureOrderExists'));
            return null;
        }
    }

    /**
     * Get all orders for a class (admin rate card view)
     *
     * @param int $classId Class ID
     * @return array       Array of order rows ordered by start_date DESC
     */
    public function findOrdersForClass(int $classId): array
    {
        return $this->findBy(
            ['class_id' => $classId],
            100,
            0,
            'start_date',
            'DESC'
        );
    }
}
