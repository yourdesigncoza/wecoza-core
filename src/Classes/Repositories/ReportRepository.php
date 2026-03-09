<?php
declare(strict_types=1);

/**
 * WeCoza Core - Report Repository
 *
 * Data access layer for class report extraction.
 * Provides queries for class header info and per-learner report rows
 * with demographics, hours (monthly + total), and progression percentages.
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

class ReportRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    /**
     * Table name
     */
    protected static string $table = 'classes';

    /**
     * Primary key column
     */
    protected static string $primaryKey = 'class_id';

    /**
     * Get class header information for report.
     *
     * Returns a single row with client name, site name, class type, subject,
     * agent name, schedule data, and class code.
     *
     * @param int $classId Class ID
     * @return array|null Header data or null if not found
     */
    public function getClassHeader(int $classId): ?array
    {
        $sql = "
            SELECT
                cl.client_name,
                s.site_name,
                ct.class_type_name,
                cts.subject_name,
                CONCAT(a.first_name, ' ', a.surname) AS class_agent_name,
                c.schedule_data,
                c.class_code
            FROM classes c
            LEFT JOIN clients cl ON c.client_id = cl.client_id
            LEFT JOIN sites s ON c.site_id = s.site_id
            LEFT JOIN class_types ct ON c.class_type = ct.class_type_code
            LEFT JOIN class_type_subjects cts ON c.class_subject = cts.subject_code
            LEFT JOIN agents a ON c.class_agent = a.agent_id
            WHERE c.class_id = :class_id
        ";

        try {
            $stmt = $this->db->query($sql, ['class_id' => $classId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Core: ReportRepository getClassHeader error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get per-learner report data for a class and month.
     *
     * Returns one row per learner enrolled in the class with demographics,
     * total hours, monthly hours (via CTE), and page progression (via CTE).
     *
     * Uses CTEs instead of correlated subqueries for performance.
     *
     * @param int $classId Class ID
     * @param int $year Report year
     * @param int $month Report month (1-12)
     * @return array Array of learner report rows
     */
    public function getClassLearnerReport(int $classId, int $year, int $month): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $sql = "
            WITH attendance_flat AS (
                SELECT cas.class_id,
                       cas.session_date,
                       cas.scheduled_hours,
                       (elem->>'learner_id')::int AS learner_id,
                       COALESCE((elem->>'hours_present')::numeric, 0) AS hours_present,
                       CASE WHEN elem->>'page_number' ~ '^[0-9]+$'
                            THEN (elem->>'page_number')::int ELSE NULL END AS page_number
                FROM class_attendance_sessions cas,
                     jsonb_array_elements(cas.learner_data) AS elem
                WHERE cas.class_id = :class_id_af
            ),
            monthly_hours AS (
                SELECT learner_id,
                       COALESCE(SUM(scheduled_hours), 0) AS month_hours_trained,
                       COALESCE(SUM(hours_present), 0) AS month_hours_present
                FROM attendance_flat
                WHERE session_date BETWEEN :month_start AND :month_end
                GROUP BY learner_id
            ),
            total_hours AS (
                SELECT learner_id,
                       COALESCE(SUM(scheduled_hours), 0) AS total_hours_trained,
                       COALESCE(SUM(hours_present), 0) AS total_hours_present
                FROM attendance_flat
                GROUP BY learner_id
            ),
            page_numbers AS (
                SELECT learner_id,
                       MAX(page_number) AS last_page_number
                FROM attendance_flat
                WHERE page_number IS NOT NULL
                GROUP BY learner_id
            )
            SELECT
                l.surname,
                l.first_name,
                COALESCE(l.race, '') AS race,
                COALESCE(l.gender, '') AS gender,
                cts.subject_name,
                lpt.start_date,
                COALESCE(th.total_hours_trained, 0) AS hours_trained,
                COALESCE(th.total_hours_present, 0) AS hours_present,
                cts.subject_duration,
                cts.total_pages,
                COALESCE(pn.last_page_number, 0) AS last_page_number,
                COALESCE(mh.month_hours_trained, 0) AS month_hours_trained,
                COALESCE(mh.month_hours_present, 0) AS month_hours_present
            FROM learner_lp_tracking lpt
            JOIN learners l ON lpt.learner_id = l.id
            JOIN class_type_subjects cts ON lpt.class_type_subject_id = cts.class_type_subject_id
            LEFT JOIN monthly_hours mh ON mh.learner_id = lpt.learner_id
            LEFT JOIN total_hours th ON th.learner_id = lpt.learner_id
            LEFT JOIN page_numbers pn ON pn.learner_id = lpt.learner_id
            WHERE lpt.class_id = :class_id
            ORDER BY l.surname, l.first_name
        ";

        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':class_id_af', $classId, PDO::PARAM_INT);
            $stmt->bindValue(':month_start', $monthStart, PDO::PARAM_STR);
            $stmt->bindValue(':month_end', $monthEnd, PDO::PARAM_STR);
            $stmt->bindValue(':class_id', $classId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("WeCoza Core: ReportRepository getClassLearnerReport error: " . $e->getMessage());
            return [];
        }
    }
}
