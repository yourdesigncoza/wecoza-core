<?php
declare(strict_types=1);

/**
 * WeCoza Core - Attendance Service
 *
 * Business logic service for class attendance capture, exception marking,
 * and hours reversal. Orchestrates AttendanceRepository, ScheduleService,
 * ProgressionService, and ClassRepository.
 *
 * @package WeCoza\Classes\Services
 * @since 1.0.0
 */

namespace WeCoza\Classes\Services;

use WeCoza\Classes\Repositories\AttendanceRepository;
use WeCoza\Classes\Repositories\ClassRepository;
use WeCoza\Learners\Services\ProgressionService;
use WeCoza\Learners\Repositories\LearnerProgressionRepository;
use DateTime;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class AttendanceService
{
    private AttendanceRepository $repository;
    private ProgressionService $progressionService;
    private LearnerProgressionRepository $progressionRepo;

    public function __construct()
    {
        $this->repository        = new AttendanceRepository();
        $this->progressionService = new ProgressionService();
        $this->progressionRepo   = new LearnerProgressionRepository();
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Fetch and validate class data by ID.
     *
     * @param int $classId
     * @return array
     * @throws Exception if class not found
     */
    private function getClassData(int $classId): array
    {
        $classData = ClassRepository::getSingleClass($classId);

        if (!$classData) {
            throw new Exception("Class not found: {$classId}");
        }

        return $classData;
    }

    /**
     * Parse schedule_data from a class record.
     * Handles both string (JSON) and array forms.
     *
     * @param array $classData
     * @return array Parsed schedule data
     * @throws Exception if schedule data is empty or invalid
     */
    private function parseScheduleData(array $classData): array
    {
        $scheduleData = $classData['schedule_data'] ?? null;

        if (is_string($scheduleData) && !empty($scheduleData)) {
            $scheduleData = json_decode($scheduleData, true);
        }

        if (empty($scheduleData) || !is_array($scheduleData)) {
            throw new Exception("Class has no valid schedule data");
        }

        return $scheduleData;
    }

    /**
     * Calculate hours between two time strings.
     *
     * @param string $startTime HH:MM format
     * @param string $endTime   HH:MM format
     * @return float Hours (0 if invalid)
     */
    private function calculateHoursFromTimes(string $startTime, string $endTime): float
    {
        $start = strtotime($startTime);
        $end   = strtotime($endTime);

        if ($start === false || $end === false || $end <= $start) {
            return 0.0;
        }

        return ($end - $start) / 3600;
    }

    /**
     * Get the current timestamp using the WordPress timezone.
     * Consistent with the DateTime objects in generateSessionList().
     */
    private function now(): string
    {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    }

    /**
     * Validate a session date and return its scheduled hours in a single pass.
     * Replaces separate validateSessionDate + hours-lookup calls to avoid
     * calling generateSessionList() multiple times per request.
     *
     * @throws Exception if the date is not a scheduled date for this class
     */
    private function getValidatedScheduledHours(int $classId, string $sessionDate): float
    {
        $sessions = $this->generateSessionList($classId);

        foreach ($sessions as $session) {
            if ($session['date'] === $sessionDate) {
                return (float) $session['scheduled_hours'];
            }
        }

        throw new Exception("Invalid session date: not a scheduled date for this class");
    }

    /**
     * Find or create a session record, enforcing the "pending-only" lock rule.
     * Shared by captureAttendance and markException to eliminate duplication.
     *
     * @throws Exception if session already captured/marked, or creation fails
     */
    private function createOrUpdateSession(
        int $classId,
        string $sessionDate,
        string $status,
        int $userId,
        float $scheduledHours,
        ?string $notes = null
    ): int {
        $existingSession = $this->repository->findByClassAndDate($classId, $sessionDate);
        $sessionId       = null;

        if ($existingSession) {
            if ($existingSession['status'] !== 'pending') {
                throw new Exception("Session already captured or marked — cannot re-capture");
            }
            $sessionId = (int) $existingSession['session_id'];
        }

        $sessionData = [
            'class_id'        => $classId,
            'session_date'    => $sessionDate,
            'status'          => $status,
            'scheduled_hours' => $scheduledHours,
            'notes'           => $notes,
            'captured_by'     => $userId,
            'captured_at'     => $this->now(),
            'updated_at'      => $this->now(),
        ];

        if ($sessionId !== null) {
            $this->repository->updateSession($sessionId, $sessionData);
        } else {
            $sessionData['created_at'] = $this->now();
            $newSessionId = $this->repository->createSession($sessionData);

            if (!$newSessionId) {
                throw new Exception("Failed to create session record");
            }

            $sessionId = $newSessionId;
        }

        return $sessionId;
    }

    /*
    |--------------------------------------------------------------------------
    | Public Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a list of scheduled sessions for a class, merged with any
     * already-captured session records from the DB.
     *
     * Schedule entries are generated up to today (attendance use-case).
     *
     * @param int $classId
     * @return array Array of session entries, sorted by date ascending
     * @throws Exception on invalid class or schedule data
     */
    public function generateSessionList(int $classId): array
    {
        $classData    = $this->getClassData($classId);
        $scheduleData = $this->parseScheduleData($classData);

        // Extract schedule parameters
        $pattern      = $scheduleData['pattern'] ?? 'weekly';
        $startDateStr = $scheduleData['startDate'] ?? $scheduleData['start_date'] ?? null;
        $endDateStr   = $scheduleData['endDate'] ?? $scheduleData['end_date'] ?? null;
        $timeData     = $scheduleData['timeData'] ?? $scheduleData['time_data'] ?? [];
        $selectedDays = $scheduleData['selectedDays'] ?? $scheduleData['selected_days'] ?? [];
        $dayOfMonth   = isset($scheduleData['dayOfMonth']) ? (int) $scheduleData['dayOfMonth'] : null;

        if (empty($startDateStr)) {
            throw new Exception("Class schedule has no start date");
        }

        // CRITICAL FORMAT MAPPING: DB stores perDayTimes with start_time/end_time keys,
        // but ScheduleService::getTimesForDay() expects perDay with startTime/endTime AND mode='per-day'.
        if (!empty($timeData['perDayTimes']) && is_array($timeData['perDayTimes'])) {
            foreach ($timeData['perDayTimes'] as $day => $times) {
                $timeData['perDay'][$day] = [
                    'startTime' => $times['start_time'] ?? $times['startTime'] ?? '09:00',
                    'endTime'   => $times['end_time'] ?? $times['endTime'] ?? '17:00',
                ];
            }
            // Set mode flag so getTimesForDay() enters the per-day branch
            $timeData['mode'] = 'per-day';
        }

        // Build DateTime objects
        $tz        = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $startDate = new DateTime($startDateStr, $tz);

        // For attendance purposes: generate sessions up to today if no end date
        if (!empty($endDateStr)) {
            $endDate = new DateTime($endDateStr, $tz);
        } else {
            $endDate = new DateTime('today', $tz);
        }

        // Generate scheduled entries via ScheduleService
        $scheduleEntries = ScheduleService::generateScheduleEntries(
            $pattern,
            $startDate,
            $endDate,
            $timeData,
            $selectedDays,
            $dayOfMonth
        );

        // Load existing session records and index by session_date
        $existingSessions = $this->repository->findByClass($classId);
        $sessionsByDate   = [];
        foreach ($existingSessions as $session) {
            $sessionsByDate[$session['session_date']] = $session;
        }

        // Map each schedule entry to the result format
        $sessions = [];
        foreach ($scheduleEntries as $entry) {
            $date            = $entry['date'];
            $existingSession = $sessionsByDate[$date] ?? null;

            $sessions[] = [
                'date'            => $date,
                'day'             => date('l', strtotime($date)),
                'start_time'      => $entry['start_time'],
                'end_time'        => $entry['end_time'],
                'scheduled_hours' => $this->calculateHoursFromTimes($entry['start_time'], $entry['end_time']),
                'session_id'      => $existingSession['session_id'] ?? null,
                'status'          => $existingSession['status'] ?? 'pending',
                'captured_by'     => $existingSession['captured_by'] ?? null,
                'captured_at'     => $existingSession['captured_at'] ?? null,
                'notes'           => $existingSession['notes'] ?? null,
            ];
        }

        // Already sorted ascending by ScheduleService, but ensure order is correct
        usort($sessions, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $sessions;
    }

    /**
     * Validate that a given date is a scheduled session date for a class.
     *
     * @param int    $classId
     * @param string $sessionDate YYYY-MM-DD
     * @return bool True if the date is in the generated schedule
     */
    public function validateSessionDate(int $classId, string $sessionDate): bool
    {
        try {
            $this->getValidatedScheduledHours($classId, $sessionDate);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Capture attendance for a class session.
     *
     * Creates or updates the session record with status=captured, then logs
     * hours for each learner via ProgressionService::logHours().
     *
     * @param int   $classId      Class ID
     * @param string $sessionDate Session date (YYYY-MM-DD)
     * @param array  $learnerHours Array of ['learner_id' => int, 'hours_present' => float]
     * @param int    $capturedBy   WP user ID of the person capturing
     * @return array ['session_id' => int, 'captured_count' => int, 'errors' => array]
     * @throws Exception on invalid date, re-capture attempt, or session creation failure
     */
    public function captureAttendance(int $classId, string $sessionDate, array $learnerHours, int $capturedBy): array
    {
        // Validate date + get hours in one generateSessionList() call (not two)
        $scheduledHours = $this->getValidatedScheduledHours($classId, $sessionDate);

        // Create or update session using shared helper
        $sessionId = $this->createOrUpdateSession($classId, $sessionDate, 'captured', $capturedBy, $scheduledHours);

        // Log hours for each learner — continue even if individual learners fail
        $successCount = 0;
        $errors       = [];

        foreach ($learnerHours as $learner) {
            try {
                $this->progressionService->logHours(
                    learnerId:    (int) $learner['learner_id'],
                    hoursTrained: $scheduledHours,
                    hoursPresent: (float) $learner['hours_present'],
                    source:       'attendance',
                    notes:        "Attendance capture for {$sessionDate}",
                    sessionId:    $sessionId,
                    createdBy:    $capturedBy
                );
                $successCount++;
            } catch (Exception $e) {
                $errors[] = [
                    'learner_id' => $learner['learner_id'],
                    'error'      => $e->getMessage(),
                ];
                wecoza_log(
                    "AttendanceService::captureAttendance — logHours failed for learner {$learner['learner_id']}: " . $e->getMessage(),
                    'warning'
                );
            }
        }

        return [
            'session_id'      => $sessionId,
            'captured_count'  => $successCount,
            'errors'          => $errors,
        ];
    }

    /**
     * Mark a session as an exception (client_cancelled or agent_absent).
     *
     * Creates or updates a session record with zero hours — no logHours() calls.
     *
     * @param int         $classId       Class ID
     * @param string      $sessionDate   Session date (YYYY-MM-DD)
     * @param string      $exceptionType 'client_cancelled' or 'agent_absent'
     * @param int         $markedBy      WP user ID
     * @param string|null $notes         Optional notes
     * @return array ['session_id' => int, 'status' => string]
     * @throws Exception on invalid exception type, invalid date, or re-capture attempt
     */
    public function markException(int $classId, string $sessionDate, string $exceptionType, int $markedBy, ?string $notes = null): array
    {
        // Validate exception type
        $allowedTypes = ['client_cancelled', 'agent_absent'];
        if (!in_array($exceptionType, $allowedTypes, true)) {
            throw new Exception("Invalid exception type: '{$exceptionType}'. Must be one of: " . implode(', ', $allowedTypes));
        }

        // Validate date + get hours in one generateSessionList() call
        $scheduledHours = $this->getValidatedScheduledHours($classId, $sessionDate);

        // Create or update session — NO learner hours logged (zero hours, key business rule)
        $sessionId = $this->createOrUpdateSession($classId, $sessionDate, $exceptionType, $markedBy, $scheduledHours, $notes);

        return [
            'session_id' => $sessionId,
            'status'     => $exceptionType,
        ];
    }

    /**
     * Admin delete: remove a session and reverse any associated learner hours.
     *
     * For 'captured' sessions: deletes learner_hours_log entries and recalculates
     * each affected learner's LP accumulators. For 'pending', 'client_cancelled',
     * or 'agent_absent' sessions: simply deletes the record.
     *
     * Uses a DB transaction to atomically delete hours log + session record.
     *
     * @param int $sessionId  Session ID to delete
     * @param int $deletedBy  WP user ID performing the delete
     * @return bool True on success
     * @throws Exception if session not found
     */
    public function deleteAndReverseHours(int $sessionId, int $deletedBy): bool
    {
        $session = $this->repository->findById($sessionId);

        if (!$session) {
            throw new Exception("Session not found: {$sessionId}");
        }

        $status = $session['status'];

        // Pending, client_cancelled, agent_absent: no hours to reverse — just delete
        if ($status !== 'captured') {
            $this->repository->deleteSession($sessionId);
            wecoza_log("Session {$sessionId} deleted by user {$deletedBy}", 'info');
            return true;
        }

        // Captured session: delete hours log entries and session atomically, then recalculate
        $pdo = wecoza_db()->getPdo();
        $pdo->beginTransaction();

        $affectedTrackingIds = [];

        try {
            // Step 1: Delete learner_hours_log rows, get affected tracking IDs
            $affectedTrackingIds = $this->progressionRepo->deleteHoursLogBySessionId($sessionId);

            // Step 2: Delete the session record
            $this->repository->deleteSession($sessionId);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Step 3: Recalculate LP accumulators for each affected tracking (outside transaction — read-only recalc)
        foreach ($affectedTrackingIds as $trackingId) {
            try {
                $this->progressionService->recalculateHours((int) $trackingId);
            } catch (Exception $e) {
                wecoza_log(
                    "AttendanceService::deleteAndReverseHours — recalculateHours failed for tracking {$trackingId}: " . $e->getMessage(),
                    'warning'
                );
            }
        }

        wecoza_log("Session {$sessionId} deleted by user {$deletedBy}", 'info');

        return true;
    }
}
