<?php
declare(strict_types=1);

/**
 * Agent Invoice Service
 *
 * Business logic for monthly agent invoice calculations and lifecycle management.
 * Calculates payable hours from attendance session data, enforces submission
 * workflow, and persists discrepancy between agent-claimed and calculated hours.
 *
 * @package WeCoza\Agents\Services
 * @since 9.0.0
 */

namespace WeCoza\Agents\Services;

use WeCoza\Agents\Repositories\AgentInvoiceRepository;
use WeCoza\Agents\Repositories\AgentOrderRepository;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Invoice Service
 *
 * @since 9.0.0
 */
class AgentInvoiceService
{
    /**
     * @var AgentInvoiceRepository
     */
    private AgentInvoiceRepository $invoiceRepo;

    /**
     * @var AgentOrderRepository
     */
    private AgentOrderRepository $orderRepo;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->invoiceRepo = new AgentInvoiceRepository();
        $this->orderRepo   = new AgentOrderRepository();
    }

    /**
     * Calculate the monthly hour summary for a class+agent+month
     *
     * Queries all attendance sessions in the given calendar month and computes:
     * - class_hours_total: sum of scheduled_hours for all sessions
     * - all_absent_days: count of "all-absent" sessions
     * - all_absent_hours: sum of scheduled_hours for all-absent sessions
     * - calculated_payable_hours: class_hours_total - all_absent_hours
     *
     * An "all-absent session" is any session where the agent was absent (status=agent_absent)
     * OR every learner recorded 0 hours_present (status=captured with all-zero learner_data).
     *
     * @param int    $classId      Class ID
     * @param int    $agentId      Agent ID (not used for session query but kept for API clarity)
     * @param string $invoiceMonth Month as 'Y-m-d' (any day; normalised to first of month)
     * @return array {
     *     class_hours_total:       float,
     *     all_absent_days:         int,
     *     all_absent_hours:        float,
     *     calculated_payable_hours: float,
     *     sessions_captured:       int,
     *     sessions:                array
     * }
     */
    public function calculateMonthSummary(int $classId, int $agentId, string $invoiceMonth): array
    {
        // Normalise to first day of month
        $invoiceMonth = date('Y-m-01', strtotime($invoiceMonth));

        $sessions = $this->invoiceRepo->getSessionsForMonth($classId, $invoiceMonth);

        $classHoursTotal      = 0.0;
        $allAbsentDays        = 0;
        $allAbsentHours       = 0.0;
        $sessionsCaptured     = 0;

        foreach ($sessions as $session) {
            $scheduledHours    = (float) ($session['scheduled_hours'] ?? 0);
            $classHoursTotal  += $scheduledHours;

            if ($session['status'] === 'captured') {
                $sessionsCaptured++;
            }

            if ($this->isAllAbsentSession($session)) {
                $allAbsentDays++;
                $allAbsentHours += $scheduledHours;
            }
        }

        $calculatedPayableHours = $classHoursTotal - $allAbsentHours;

        return [
            'class_hours_total'        => $classHoursTotal,
            'all_absent_days'          => $allAbsentDays,
            'all_absent_hours'         => $allAbsentHours,
            'calculated_payable_hours' => $calculatedPayableHours,
            'sessions_captured'        => $sessionsCaptured,
            'sessions'                 => $sessions,
        ];
    }

    /**
     * Submit an invoice for a class+agent+month
     *
     * Calculates the month summary, finds or creates a draft invoice, then updates
     * it with calculated values and the agent's claimed hours.
     * Stores discrepancy = claimed_hours - calculated_payable_hours.
     *
     * @param int         $classId      Class ID
     * @param int         $agentId      Agent ID
     * @param string      $invoiceMonth Month as 'Y-m-d'
     * @param float       $claimedHours Hours claimed by the agent
     * @param string|null $notes        Optional notes
     * @return array                    Updated invoice row
     * @throws RuntimeException         When no active order exists for this class+agent
     */
    public function submitInvoice(
        int $classId,
        int $agentId,
        string $invoiceMonth,
        float $claimedHours,
        ?string $notes = null
    ): array {
        // Normalise month
        $invoiceMonth = date('Y-m-01', strtotime($invoiceMonth));

        // Require an active order for rate lookup
        $order = $this->orderRepo->findActiveOrderForClass($classId, $agentId);
        if (!$order) {
            throw new RuntimeException(
                "No active order found for class {$classId} and agent {$agentId}"
            );
        }

        // Calculate summary from attendance data
        $summary     = $this->calculateMonthSummary($classId, $agentId, $invoiceMonth);
        $discrepancy = $claimedHours - $summary['calculated_payable_hours'];

        // Find or create the draft invoice row
        $invoice = $this->invoiceRepo->findOrCreateDraft(
            (int) $order['order_id'],
            $classId,
            $agentId,
            $invoiceMonth
        );

        // Update the invoice row with calculated values
        $updateData = [
            'class_hours_total'        => $summary['class_hours_total'],
            'all_absent_days'          => $summary['all_absent_days'],
            'all_absent_hours'         => $summary['all_absent_hours'],
            'calculated_payable_hours' => $summary['calculated_payable_hours'],
            'agent_claimed_hours'      => $claimedHours,
            'discrepancy_hours'        => $discrepancy,
            'status'                   => 'submitted',
            'updated_at'               => date('Y-m-d H:i:s'),
        ];

        if ($notes !== null) {
            $updateData['notes'] = $notes;
        }

        $this->invoiceRepo->update((int) $invoice['invoice_id'], $updateData);

        // Return the refreshed row
        return $this->invoiceRepo->findById((int) $invoice['invoice_id']) ?? $invoice;
    }

    /**
     * Review (approve or dispute) a submitted invoice
     *
     * @param int    $invoiceId  Invoice ID to review
     * @param string $newStatus  New status: 'approved' or 'disputed'
     * @param int    $reviewedBy WordPress user ID of the reviewer
     * @return bool              True on success, false on invalid status or DB failure
     */
    public function reviewInvoice(int $invoiceId, string $newStatus, int $reviewedBy): bool
    {
        if (!in_array($newStatus, ['approved', 'disputed'], true)) {
            return false;
        }

        return $this->invoiceRepo->update($invoiceId, [
            'status'      => $newStatus,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get invoices for a class+agent pair
     *
     * @param int $classId Class ID
     * @param int $agentId Agent ID
     * @param int $limit   Max rows (default 12 months)
     * @return array       Invoice rows ordered by invoice_month DESC
     */
    public function getInvoicesForClassAgent(int $classId, int $agentId, int $limit = 12): array
    {
        return $this->invoiceRepo->findInvoicesForClassAgent($classId, $agentId, $limit);
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether a session counts as "all-absent"
     *
     * Rules:
     * - status = 'agent_absent'       → always all-absent
     * - status = 'captured'           → all-absent only if every learner has hours_present == 0
     * - status = 'pending'            → not all-absent (data not yet captured)
     * - status = 'client_cancelled'   → not all-absent (client-side cancellation, not agent absence)
     *
     * @param array $session Session row from class_attendance_sessions
     * @return bool
     */
    private function isAllAbsentSession(array $session): bool
    {
        $status = $session['status'] ?? '';

        if ($status === 'agent_absent') {
            return true;
        }

        if ($status === 'captured') {
            $learnerData = $session['learner_data'] ?? null;

            // Decode if stored as JSON string (PostgreSQL JSONB may be returned as string)
            if (is_string($learnerData)) {
                $learnerData = json_decode($learnerData, true);
            }

            // If no learner data present, we cannot confirm all-absent — treat as not absent
            if (empty($learnerData) || !is_array($learnerData)) {
                return false;
            }

            // All-absent only when every learner entry records zero hours_present
            foreach ($learnerData as $entry) {
                $hoursPresent = (float) ($entry['hours_present'] ?? 0);
                if ($hoursPresent > 0) {
                    return false;
                }
            }

            return true;
        }

        // 'pending', 'client_cancelled', or unknown → not all-absent
        return false;
    }
}
