<?php
declare(strict_types=1);

/**
 * WeCoza Core - Excessive Hours Service
 *
 * Business logic for excessive training hours detection and resolution.
 * Wraps the repository with filtering, pagination, and resolution validation.
 *
 * @package WeCoza\Reports\ExcessiveHours
 * @since 1.0.0
 */

namespace WeCoza\Reports\ExcessiveHours;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ExcessiveHoursService
{
    /**
     * Class types where excessive hours should be flagged.
     * Mario confirmed: these programmes have fixed allocated hours.
     *
     * Excluded: WALK, RUN, HEXA (designed to exceed), SOFT (never exceeds)
     */
    public const APPLICABLE_CLASS_TYPES = [
        'AET',     // AET Communication & Numeracy
        'REALLL',  // REALLL
        'GETC',    // GETC AET
        'BA2',     // Business Admin NQF 2
        'BA3',     // Business Admin NQF 3
        'BA4',     // Business Admin NQF 4
        'ASC',     // Adult Matric
    ];

    /**
     * DEMO MODE — set to false before production deployment
     * When true and no real data exists, injects hardcoded sample rows.
     * TODO: Remove DEMO_MODE constant and getDemoData() before go-live
     */
    public const DEMO_MODE = true;

    /**
     * Allowed resolution actions (mirrors repository constant for convenience)
     */
    public const ALLOWED_ACTIONS = ExcessiveHoursRepository::ALLOWED_ACTIONS;

    /**
     * Human-readable labels for resolution actions
     */
    public const ACTION_LABELS = [
        'contacted_facilitator' => 'Contacted Facilitator',
        'qa_visit_arranged'     => 'QA Visit Arranged',
        'other'                 => 'Other',
    ];

    private ExcessiveHoursRepository $repository;

    public function __construct(?ExcessiveHoursRepository $repository = null)
    {
        $this->repository = $repository ?? new ExcessiveHoursRepository();
    }

    /**
     * Get flagged learners with excessive hours.
     *
     * Supports DataTable server-side processing parameters.
     *
     * @param array $params DataTable params: draw, start, length, order, search, filters
     * @return array DataTable-compatible response
     */
    public function getFlaggedLearners(array $params = []): array
    {
        $filters = [];

        // Map DataTable params to repository filters
        if (!empty($params['status']) && in_array($params['status'], ['open', 'resolved', 'all'], true)) {
            $filters['status'] = $params['status'];
        } else {
            $filters['status'] = 'open'; // Default to open
        }

        if (!empty($params['client_id'])) {
            $filters['client_id'] = (int) $params['client_id'];
        }

        if (!empty($params['class_type_code'])) {
            $filters['class_type_code'] = $params['class_type_code'];
        }

        if (!empty($params['search'])) {
            $filters['search'] = $params['search'];
        }

        // Sorting
        $orderBy = $params['order_by'] ?? 'overage_hours';
        $orderDir = $params['order_dir'] ?? 'DESC';

        // Pagination
        $limit = min(100, max(1, (int) ($params['length'] ?? 50)));
        $offset = max(0, (int) ($params['start'] ?? 0));

        $result = $this->repository->findFlagged($filters, $orderBy, $orderDir, $limit, $offset);

        // Enrich rows with display data
        $result['data'] = array_map([$this, 'enrichRow'], $result['data']);

        // DEMO DATA — inject hardcoded sample rows when no real data exists
        // TODO: Remove this block before production deployment
        if (empty($result['data']) && self::DEMO_MODE) {
            $result = self::getDemoData($filters['status'] ?? 'open');
        }

        // Add DataTable draw counter if present
        if (isset($params['draw'])) {
            $result['draw'] = (int) $params['draw'];
        }

        $result['demo_mode'] = self::DEMO_MODE && empty($this->repository->findFlagged([], 'overage_hours', 'DESC', 1, 0)['data']);

        return $result;
    }

    /**
     * Resolve a flagged learner's excessive hours.
     *
     * @param int $trackingId LP tracking record ID
     * @param string $actionTaken One of ALLOWED_ACTIONS
     * @param string|null $notes Resolution notes
     * @return array Resolution record details
     * @throws Exception on validation failure
     */
    public function resolveFlag(int $trackingId, string $actionTaken, ?string $notes = null): array
    {
        if (!in_array($actionTaken, self::ALLOWED_ACTIONS, true)) {
            throw new Exception(
                'Invalid action. Allowed: ' . implode(', ', array_values(self::ACTION_LABELS))
            );
        }

        $resolvedBy = get_current_user_id();
        if (!$resolvedBy) {
            throw new Exception('User must be logged in to resolve flags.');
        }

        $resolutionId = $this->repository->createResolution(
            $trackingId,
            $actionTaken,
            $notes,
            $resolvedBy
        );

        $currentUser = wp_get_current_user();

        wecoza_log("Excessive hours flag resolved: tracking_id={$trackingId}, action={$actionTaken}, by={$currentUser->display_name}", 'info');

        return [
            'resolution_id' => $resolutionId,
            'tracking_id' => $trackingId,
            'action_taken' => $actionTaken,
            'action_label' => self::ACTION_LABELS[$actionTaken] ?? $actionTaken,
            'resolved_by_name' => $currentUser->display_name,
            'resolved_at' => wp_date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get count of open (unresolved) excessive hours flags.
     *
     * Lightweight — for SystemPulse and summary cards.
     *
     * @return int
     */
    public function countOpen(): int
    {
        return $this->repository->countOpen();
    }

    /**
     * Get resolution history for a tracking record.
     *
     * @param int $trackingId
     * @return array
     */
    public function getResolutionHistory(int $trackingId): array
    {
        return $this->repository->getResolutionHistory($trackingId);
    }

    /**
     * Enrich a flagged learner row with display-friendly data.
     *
     * @param array $row Raw DB row
     * @return array Enriched row
     */
    private function enrichRow(array $row): array
    {
        // Add action label
        if (!empty($row['action_taken'])) {
            $row['action_label'] = self::ACTION_LABELS[$row['action_taken']] ?? $row['action_taken'];
        }

        // Add resolver display name
        if (!empty($row['resolved_by'])) {
            $user = get_userdata((int) $row['resolved_by']);
            $row['resolved_by_name'] = $user ? $user->display_name : 'Unknown';
        }

        // Format dates
        if (!empty($row['resolved_at'])) {
            $row['resolved_at_display'] = wp_date('j M Y, H:i', strtotime($row['resolved_at']));
        }

        if (!empty($row['start_date'])) {
            $row['start_date_display'] = wp_date('j M Y', strtotime($row['start_date']));
        }

        // Round numeric fields
        $row['hours_trained'] = round((float) ($row['hours_trained'] ?? 0), 1);
        $row['hours_present'] = round((float) ($row['hours_present'] ?? 0), 1);
        $row['subject_duration'] = (int) ($row['subject_duration'] ?? 0);
        $row['overage_hours'] = round((float) ($row['overage_hours'] ?? 0), 1);
        $row['overage_pct'] = round((float) ($row['overage_pct'] ?? 0), 1);

        return $row;
    }

    /**
     * Generate hardcoded demo data for client preview.
     *
     * TODO: Remove this method before production deployment.
     *
     * @param string $statusFilter open|resolved|all
     * @return array Same shape as findFlagged() result
     */
    private static function getDemoData(string $statusFilter = 'open'): array
    {
        $demoRows = [
            [
                'tracking_id' => 9001, 'learner_id' => 101,
                'hours_trained' => 198.5, 'hours_present' => 142.0, 'hours_absent' => 56.5,
                'start_date' => '2025-08-12', 'start_date_display' => '12 Aug 2025',
                'subject_duration' => 160, 'subject_name' => 'Communication Level 3',
                'subject_code' => 'COMM3',
                'overage_hours' => 38.5, 'overage_pct' => 24.1,
                'learner_name' => 'Thabo Mokoena', 'class_code' => 'IMP021916-5',
                'class_id' => 201, 'class_type_name' => 'AET Communication & Numeracy',
                'class_type_code' => 'AET', 'client_name' => 'Impala Resources', 'client_id' => 1,
                'resolution_id' => null, 'action_taken' => null, 'action_label' => null,
                'resolution_notes' => null, 'resolved_by' => null, 'resolved_at' => null,
                'resolved_at_display' => null, 'resolved_by_name' => null,
                'flag_status' => 'open',
            ],
            [
                'tracking_id' => 9002, 'learner_id' => 102,
                'hours_trained' => 245.0, 'hours_present' => 180.0, 'hours_absent' => 65.0,
                'start_date' => '2025-06-03', 'start_date_display' => '3 Jun 2025',
                'subject_duration' => 160, 'subject_name' => 'Numeracy Level 2',
                'subject_code' => 'NUM2',
                'overage_hours' => 85.0, 'overage_pct' => 53.1,
                'learner_name' => 'Nomsa Dlamini', 'class_code' => 'KUD021917-5',
                'class_id' => 202, 'class_type_name' => 'REALLL',
                'class_type_code' => 'REALLL', 'client_name' => 'Kudu Logistics', 'client_id' => 2,
                'resolution_id' => null, 'action_taken' => null, 'action_label' => null,
                'resolution_notes' => null, 'resolved_by' => null, 'resolved_at' => null,
                'resolved_at_display' => null, 'resolved_by_name' => null,
                'flag_status' => 'open',
            ],
            [
                'tracking_id' => 9003, 'learner_id' => 103,
                'hours_trained' => 172.0, 'hours_present' => 155.0, 'hours_absent' => 17.0,
                'start_date' => '2025-09-22', 'start_date_display' => '22 Sep 2025',
                'subject_duration' => 160, 'subject_name' => 'Communication Level 4',
                'subject_code' => 'COMM4',
                'overage_hours' => 12.0, 'overage_pct' => 7.5,
                'learner_name' => 'Sipho Ndaba', 'class_code' => 'PRO021816-7',
                'class_id' => 203, 'class_type_name' => 'GETC AET',
                'class_type_code' => 'GETC', 'client_name' => 'Protea Holdings', 'client_id' => 3,
                'resolution_id' => null, 'action_taken' => null, 'action_label' => null,
                'resolution_notes' => null, 'resolved_by' => null, 'resolved_at' => null,
                'resolved_at_display' => null, 'resolved_by_name' => null,
                'flag_status' => 'open',
            ],
            [
                'tracking_id' => 9004, 'learner_id' => 104,
                'hours_trained' => 580.0, 'hours_present' => 410.0, 'hours_absent' => 170.0,
                'start_date' => '2025-03-10', 'start_date_display' => '10 Mar 2025',
                'subject_duration' => 520, 'subject_name' => 'Business Admin NQF 3',
                'subject_code' => 'BA3LP1',
                'overage_hours' => 60.0, 'overage_pct' => 11.5,
                'learner_name' => 'Lerato Mahlangu', 'class_code' => 'MAR021916-7',
                'class_id' => 204, 'class_type_name' => 'Business Admin NQF 3',
                'class_type_code' => 'BA3', 'client_name' => 'Marula Consulting', 'client_id' => 4,
                'resolution_id' => null, 'action_taken' => null, 'action_label' => null,
                'resolution_notes' => null, 'resolved_by' => null, 'resolved_at' => null,
                'resolved_at_display' => null, 'resolved_by_name' => null,
                'flag_status' => 'open',
            ],
            [
                'tracking_id' => 9005, 'learner_id' => 105,
                'hours_trained' => 190.0, 'hours_present' => 168.0, 'hours_absent' => 22.0,
                'start_date' => '2025-07-15', 'start_date_display' => '15 Jul 2025',
                'subject_duration' => 160, 'subject_name' => 'Numeracy Level 3',
                'subject_code' => 'NUM3',
                'overage_hours' => 30.0, 'overage_pct' => 18.8,
                'learner_name' => 'Zanele Khumalo', 'class_code' => 'TRI022414-8',
                'class_id' => 205, 'class_type_name' => 'AET Communication & Numeracy',
                'class_type_code' => 'AET', 'client_name' => 'Triple E Training(Pty)Ltd', 'client_id' => 5,
                'resolution_id' => 9901, 'action_taken' => 'contacted_facilitator',
                'action_label' => 'Contacted Facilitator',
                'resolution_notes' => 'Spoke to facilitator — learner has poor attendance, catching up slowly.',
                'resolved_by' => 1, 'resolved_at' => wp_date('Y-m-d H:i:s', strtotime('-5 days')),
                'resolved_at_display' => wp_date('j M Y, H:i', strtotime('-5 days')),
                'resolved_by_name' => 'Laudes',
                'flag_status' => 'resolved',
            ],
            [
                'tracking_id' => 9006, 'learner_id' => 106,
                'hours_trained' => 210.0, 'hours_present' => 130.0, 'hours_absent' => 80.0,
                'start_date' => '2025-05-20', 'start_date_display' => '20 May 2025',
                'subject_duration' => 160, 'subject_name' => 'Communication Level 2',
                'subject_code' => 'COMM2',
                'overage_hours' => 50.0, 'overage_pct' => 31.3,
                'learner_name' => 'Bongani Sithole', 'class_code' => 'BIL021916-7',
                'class_id' => 206, 'class_type_name' => 'AET Communication & Numeracy',
                'class_type_code' => 'AET', 'client_name' => 'Biltong Bros Trading', 'client_id' => 6,
                'resolution_id' => 9902, 'action_taken' => 'qa_visit_arranged',
                'action_label' => 'QA Visit Arranged',
                'resolution_notes' => 'QA visit scheduled for next week to assess learner progress on-site.',
                'resolved_by' => 1, 'resolved_at' => wp_date('Y-m-d H:i:s', strtotime('-12 days')),
                'resolved_at_display' => wp_date('j M Y, H:i', strtotime('-12 days')),
                'resolved_by_name' => 'Laudes',
                'flag_status' => 'resolved',
            ],
        ];

        // Apply status filter
        $filtered = match ($statusFilter) {
            'open' => array_values(array_filter($demoRows, fn($r) => $r['flag_status'] === 'open')),
            'resolved' => array_values(array_filter($demoRows, fn($r) => $r['flag_status'] === 'resolved')),
            default => $demoRows,
        };

        $openCount = count(array_filter($demoRows, fn($r) => $r['flag_status'] === 'open'));
        $resolvedCount = count(array_filter($demoRows, fn($r) => $r['flag_status'] === 'resolved'));

        return [
            'data' => $filtered,
            'total' => count($demoRows),
            'open_count' => $openCount,
            'resolved_count' => $resolvedCount,
        ];
    }
}
