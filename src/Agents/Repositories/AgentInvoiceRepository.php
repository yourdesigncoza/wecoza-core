<?php
declare(strict_types=1);

/**
 * Agent Invoice Repository
 *
 * Handles all database operations for the agent_monthly_invoices table.
 * Provides idempotent draft creation and session queries for calculation.
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
 * Agent Invoice Repository class
 *
 * @since 9.0.0
 */
class AgentInvoiceRepository extends BaseRepository
{
    protected static string $table = 'agent_monthly_invoices';
    protected static string $primaryKey = 'invoice_id';

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
            'invoice_id',
            'invoice_month',
            'status',
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
            'invoice_id',
            'order_id',
            'class_id',
            'agent_id',
            'invoice_month',
            'status',
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
            'order_id',
            'class_id',
            'agent_id',
            'invoice_month',
            'class_hours_total',
            'all_absent_days',
            'all_absent_hours',
            'calculated_payable_hours',
            'agent_claimed_hours',
            'discrepancy_hours',
            'notes',
            'status',
            'reviewed_by',
            'reviewed_at',
            'created_at',
            'updated_at',
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
            'class_hours_total',
            'all_absent_days',
            'all_absent_hours',
            'calculated_payable_hours',
            'agent_claimed_hours',
            'discrepancy_hours',
            'notes',
            'status',
            'reviewed_by',
            'reviewed_at',
            'updated_at',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Custom Query Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Find or create a draft invoice for the given order+month combination
     *
     * Uses ON CONFLICT DO NOTHING so concurrent calls are safe. Always returns
     * the full invoice row (pre-existing or newly created).
     *
     * @param int    $orderId      Order ID (FK to agent_orders)
     * @param int    $classId      Class ID (denormalized)
     * @param int    $agentId      Agent ID (denormalized)
     * @param string $invoiceMonth Invoice month as 'Y-m-01'
     * @return array               Full invoice row
     * @throws Exception           On database failure
     */
    public function findOrCreateDraft(int $orderId, int $classId, int $agentId, string $invoiceMonth): array
    {
        $insertSql = "INSERT INTO agent_monthly_invoices
                        (order_id, class_id, agent_id, invoice_month,
                         class_hours_total, all_absent_days, all_absent_hours,
                         calculated_payable_hours, status, created_at, updated_at)
                      VALUES
                        (:order_id, :class_id, :agent_id, :invoice_month,
                         0, 0, 0, 0, 'draft', NOW(), NOW())
                      ON CONFLICT (order_id, invoice_month) DO NOTHING";

        $selectSql = "SELECT * FROM agent_monthly_invoices
                      WHERE order_id = :order_id AND invoice_month = :invoice_month
                      LIMIT 1";

        $this->db->query($insertSql, [
            'order_id'      => $orderId,
            'class_id'      => $classId,
            'agent_id'      => $agentId,
            'invoice_month' => $invoiceMonth,
        ]);

        $stmt   = $this->db->query($selectSql, [
            'order_id'      => $orderId,
            'invoice_month' => $invoiceMonth,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Failed to find or create draft invoice for order {$orderId}, month {$invoiceMonth}");
        }

        return $result;
    }

    /**
     * Get attendance sessions for a class within a calendar month
     *
     * Queries class_attendance_sessions for the full calendar month of $invoiceMonth.
     *
     * @param int    $classId      Class ID
     * @param string $invoiceMonth Month as 'Y-m-01' (any day in month works)
     * @return array               Array of session rows ordered by session_date ASC
     */
    public function getSessionsForMonth(int $classId, string $invoiceMonth): array
    {
        // Compute month boundaries
        $monthStart = date('Y-m-01', strtotime($invoiceMonth));
        $monthEnd   = date('Y-m-01', strtotime($monthStart . ' +1 month'));

        $sql = "SELECT session_id, session_date, scheduled_hours, status, learner_data
                FROM class_attendance_sessions
                WHERE class_id = :class_id
                  AND session_date >= :month_start
                  AND session_date < :month_end
                ORDER BY session_date ASC";

        try {
            $stmt = $this->db->query($sql, [
                'class_id'    => $classId,
                'month_start' => $monthStart,
                'month_end'   => $monthEnd,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AgentInvoiceRepository::getSessionsForMonth'));
            return [];
        }
    }

    /**
     * Get invoices for a class+agent pair (reconciliation list view)
     *
     * @param int $classId Class ID
     * @param int $agentId Agent ID
     * @param int $limit   Max rows to return (default 12 months)
     * @return array       Invoice rows ordered by invoice_month DESC
     */
    public function findInvoicesForClassAgent(int $classId, int $agentId, int $limit = 12): array
    {
        return $this->findBy(
            ['class_id' => $classId, 'agent_id' => $agentId],
            $limit,
            0,
            'invoice_month',
            'DESC'
        );
    }
}
