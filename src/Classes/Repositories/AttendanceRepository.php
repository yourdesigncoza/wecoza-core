<?php
declare(strict_types=1);

/**
 * WeCoza Core - Attendance Repository
 *
 * Data access layer for class attendance sessions.
 * Handles all database operations for the class_attendance_sessions table.
 *
 * @package WeCoza\Classes\Repositories
 * @since 1.0.0
 */

namespace WeCoza\Classes\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class AttendanceRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    /**
     * Table name
     */
    protected static string $table = 'class_attendance_sessions';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'session_id';

    /*
    |--------------------------------------------------------------------------
    | Column Whitelisting (Security)
    |--------------------------------------------------------------------------
    */

    /**
     * Get columns allowed for ORDER BY clauses
     */
    protected function getAllowedOrderColumns(): array
    {
        return ['session_id', 'class_id', 'session_date', 'status', 'captured_at', 'created_at', 'updated_at'];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     */
    protected function getAllowedFilterColumns(): array
    {
        return ['session_id', 'class_id', 'session_date', 'status', 'captured_by'];
    }

    /**
     * Get columns allowed for INSERT operations
     */
    protected function getAllowedInsertColumns(): array
    {
        return ['class_id', 'session_date', 'status', 'scheduled_hours', 'notes', 'captured_by', 'captured_at', 'created_at', 'updated_at'];
    }

    /**
     * Get columns allowed for UPDATE operations
     */
    protected function getAllowedUpdateColumns(): array
    {
        return ['status', 'scheduled_hours', 'notes', 'captured_by', 'captured_at', 'updated_at'];
    }

    /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Find all sessions for a given class, ordered by session date ascending
     *
     * @param int $classId Class ID
     * @return array Array of session rows
     */
    public function findByClass(int $classId): array
    {
        $sql = "SELECT * FROM class_attendance_sessions WHERE class_id = :class_id ORDER BY session_date ASC";

        try {
            $stmt = $this->db->query($sql, ['class_id' => $classId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AttendanceRepository::findByClass'));
            return [];
        }
    }

    /**
     * Find a single session by class ID and session date
     *
     * @param int    $classId     Class ID
     * @param string $sessionDate Session date (YYYY-MM-DD)
     * @return array|null Session row or null if not found
     */
    public function findByClassAndDate(int $classId, string $sessionDate): ?array
    {
        $sql = "SELECT * FROM class_attendance_sessions WHERE class_id = :class_id AND session_date = :session_date LIMIT 1";

        try {
            $stmt   = $this->db->query($sql, ['class_id' => $classId, 'session_date' => $sessionDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AttendanceRepository::findByClassAndDate'));
            return null;
        }
    }

    /**
     * Get per-learner hours for a captured session
     *
     * Joins class_attendance_sessions with learner_hours_log and learners
     * to return the hours breakdown for each learner in a session.
     *
     * @param int $sessionId Session ID
     * @return array Array of per-learner hour rows
     */
    public function getSessionsWithLearnerHours(int $sessionId): array
    {
        $sql = "
            SELECT lhl.learner_id, CONCAT(l.first_name, ' ', l.surname) AS learner_name,
                   lhl.hours_trained, lhl.hours_present, (lhl.hours_trained - lhl.hours_present) AS hours_absent
            FROM learner_hours_log lhl
            LEFT JOIN learners l ON lhl.learner_id = l.id
            WHERE lhl.session_id = :session_id
            ORDER BY l.surname, l.first_name
        ";

        try {
            $stmt = $this->db->query($sql, ['session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'AttendanceRepository::getSessionsWithLearnerHours'));
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new attendance session
     *
     * Delegates to parent::insert() which filters via getAllowedInsertColumns()
     * and returns the new session_id via RETURNING.
     *
     * The UNIQUE constraint on (class_id, session_date) is enforced at DB level —
     * the repository trusts the constraint and lets the DB throw on duplicates.
     *
     * @param array $data Session data
     * @return int|null New session_id or null on failure
     */
    public function createSession(array $data): ?int
    {
        return parent::insert($data);
    }

    /**
     * Update an existing attendance session
     *
     * Delegates to parent::update() which filters via getAllowedUpdateColumns().
     *
     * @param int   $sessionId Session ID to update
     * @param array $data      Updated data
     * @return bool True on success
     */
    public function updateSession(int $sessionId, array $data): bool
    {
        return parent::update($sessionId, $data);
    }

    /**
     * Delete an attendance session by ID
     *
     * Delegates to parent::delete().
     *
     * @param int $sessionId Session ID to delete
     * @return bool True on success
     */
    public function deleteSession(int $sessionId): bool
    {
        return parent::delete($sessionId);
    }
}
