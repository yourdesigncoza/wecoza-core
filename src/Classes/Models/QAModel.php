<?php
/**
 * WeCoza Core - QA Model
 *
 * QA model for handling QA analytics and visit data.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Models
 * @since 1.0.0
 */

namespace WeCoza\Classes\Models;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class QAModel
{
    private $db;

    public function __construct()
    {
        $this->db = wecoza_db();
    }

    /**
     * Get analytics data for QA dashboard
     */
    public function getAnalyticsData(string $start_date = '', string $end_date = '', string $department = ''): array
    {
        $data = [];

        if (empty($start_date)) {
            $start_date = date('Y-m-01', strtotime('-6 months'));
        }
        if (empty($end_date)) {
            $end_date = date('Y-m-t');
        }

        $data['monthly_rates'] = $this->getMonthlyCompletionRates($start_date, $end_date);
        $data['average_ratings'] = $this->getAverageRatings($start_date, $end_date, $department);
        $data['officer_performance'] = $this->getOfficerPerformance($start_date, $end_date);
        $data['trending_issues'] = $this->getTrendingIssues($start_date, $end_date);
        $data['overall_stats'] = $this->getOverallStats($start_date, $end_date);

        return $data;
    }

    /**
     * Get summary data for QA dashboard widget
     */
    public function getSummaryData(): array
    {
        return [
            'recent_visits' => $this->getRecentVisits(5),
            'key_metrics' => $this->getKeyMetrics(),
            'alerts' => $this->getAlerts(),
        ];
    }

    /**
     * Get visits for a specific class
     */
    public function getVisitsByClass(int $class_id): array
    {
        $query = "
            SELECT
                qr.qa_report_id,
                qr.class_id,
                qr.report_date as visit_date,
                qr.notes,
                qr.created_at,
                c.class_code,
                c.class_subject
            FROM qa_reports qr
            JOIN classes c ON qr.class_id = c.class_id
            WHERE qr.class_id = $1
            ORDER BY qr.report_date DESC
        ";

        return $this->db->query($query, [$class_id])->fetchAll();
    }

    /**
     * Create a new QA visit
     */
    public function createVisit(array $visit_data): ?int
    {
        $query = "
            INSERT INTO qa_reports (class_id, report_date, notes, created_at, updated_at)
            VALUES ($1, $2, $3, NOW(), NOW())
            RETURNING qa_report_id
        ";

        $result = $this->db->query($query, [
            $visit_data['class_id'],
            $visit_data['visit_date'],
            $visit_data['visit_notes'] ?? ''
        ]);

        $row = $result->fetch();
        return $row['qa_report_id'] ?? null;
    }

    private function getMonthlyCompletionRates(string $start_date, string $end_date): array
    {
        $query = "
            SELECT
                DATE_TRUNC('month', report_date) as month,
                COUNT(*) as total_visits,
                COUNT(DISTINCT class_id) as classes_visited
            FROM qa_reports
            WHERE report_date BETWEEN $1 AND $2
            GROUP BY DATE_TRUNC('month', report_date)
            ORDER BY month ASC
        ";

        return $this->db->query($query, [$start_date, $end_date])->fetchAll();
    }

    private function getAverageRatings(string $start_date, string $end_date, string $department = ''): array
    {
        $query = "
            SELECT
                c.class_subject,
                c.class_type,
                COUNT(qr.qa_report_id) as visit_count,
                4.2 as avg_rating
            FROM classes c
            LEFT JOIN qa_reports qr ON c.class_id = qr.class_id
            WHERE qr.report_date BETWEEN $1 AND $2
        ";

        $params = [$start_date, $end_date];

        if (!empty($department)) {
            $query .= " AND c.class_subject = $3";
            $params[] = $department;
        }

        $query .= " GROUP BY c.class_subject, c.class_type ORDER BY avg_rating DESC";

        return $this->db->query($query, $params)->fetchAll();
    }

    private function getOfficerPerformance(string $start_date, string $end_date): array
    {
        try {
            $query = "
                SELECT
                    aqv.agent_id,
                    COUNT(aqv.visit_id) as total_visits,
                    COUNT(DISTINCT aqv.class_id) as unique_classes,
                    4.3 as avg_performance_score
                FROM agent_qa_visits aqv
                WHERE aqv.visit_date BETWEEN $1 AND $2
                GROUP BY aqv.agent_id
                ORDER BY total_visits DESC
            ";

            return $this->db->query($query, [$start_date, $end_date])->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    private function getTrendingIssues(string $start_date, string $end_date): array
    {
        return [
            ['issue' => 'Equipment maintenance', 'count' => 12, 'trend' => 'up'],
            ['issue' => 'Attendance tracking', 'count' => 8, 'trend' => 'down'],
            ['issue' => 'Venue cleanliness', 'count' => 6, 'trend' => 'stable'],
            ['issue' => 'Safety compliance', 'count' => 4, 'trend' => 'down']
        ];
    }

    private function getOverallStats(string $start_date, string $end_date): array
    {
        try {
            $query = "
                SELECT
                    COUNT(DISTINCT qr.class_id) as classes_visited,
                    COUNT(qr.qa_report_id) as total_visits,
                    COUNT(DISTINCT aqv.agent_id) as active_officers,
                    4.1 as overall_rating
                FROM qa_reports qr
                LEFT JOIN agent_qa_visits aqv ON qr.qa_report_id = aqv.qa_report_id
                WHERE qr.report_date BETWEEN $1 AND $2
            ";

            $result = $this->db->query($query, [$start_date, $end_date]);
            return $result->fetch() ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function getRecentVisits(int $limit = 5): array
    {
        $query = "
            SELECT
                qr.qa_report_id,
                qr.class_id,
                qr.report_date as visit_date,
                qr.notes,
                c.class_code,
                c.class_subject
            FROM qa_reports qr
            JOIN classes c ON qr.class_id = c.class_id
            ORDER BY qr.report_date DESC
            LIMIT $1
        ";

        return $this->db->query($query, [$limit])->fetchAll();
    }

    private function getKeyMetrics(): array
    {
        $query = "
            SELECT
                COUNT(DISTINCT qr.class_id) as classes_this_month,
                COUNT(qr.qa_report_id) as visits_this_month,
                4.2 as avg_rating_this_month
            FROM qa_reports qr
            WHERE qr.report_date >= DATE_TRUNC('month', CURRENT_DATE)
        ";

        $result = $this->db->query($query);
        return $result->fetch() ?? [];
    }

    private function getAlerts(): array
    {
        return [
            ['type' => 'warning', 'message' => '3 classes require follow-up visits'],
            ['type' => 'info', 'message' => '2 safety issues need resolution'],
            ['type' => 'success', 'message' => 'All reports up to date']
        ];
    }

    /**
     * Get export data for reports
     */
    public function getExportData(string $start_date, string $end_date): array
    {
        $query = "
            SELECT
                qr.qa_report_id,
                qr.class_id,
                qr.report_date,
                'N/A' as officer,
                'N/A' as rating,
                'N/A' as duration,
                qr.notes
            FROM qa_reports qr
            WHERE qr.report_date BETWEEN $1 AND $2
            ORDER BY qr.report_date DESC
        ";

        return $this->db->query($query, [$start_date, $end_date])->fetchAll();
    }
}
