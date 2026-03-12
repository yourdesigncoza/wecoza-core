<?php
declare(strict_types=1);

/**
 * WeCoza Core - History Service
 *
 * Unified facade that composes HistoryRepository methods into
 * per-entity timeline arrays covering all WEC-189 data points.
 *
 * Each get*Timeline() method returns a structured array with all
 * relationship data for that entity type. Empty sub-arrays are
 * returned for missing entities — never null, never exceptions
 * for "not found."
 *
 * @package WeCoza\Classes\Services
 * @since 1.1.0
 */

namespace WeCoza\Classes\Services;

use WeCoza\Classes\Repositories\HistoryRepository;
use WeCoza\Core\Database\PostgresConnection;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class HistoryService
{
    private HistoryRepository $repo;
    private PostgresConnection $db;

    /**
     * Constructor with dependency injection.
     *
     * @param HistoryRepository|null $repo
     */
    public function __construct(?HistoryRepository $repo = null)
    {
        $this->repo = $repo ?? new HistoryRepository();
        $this->db = PostgresConnection::getInstance();
    }

    /*
    |--------------------------------------------------------------------------
    | Class Timeline
    |--------------------------------------------------------------------------
    */

    /**
     * Get full timeline for a class.
     *
     * Merges: agent assignments, learner enrollments, status changes,
     * stop/restart dates, QA visits, events, and class notes.
     *
     * @param int $classId
     * @return array
     */
    public function getClassTimeline(int $classId): array
    {
        $history = $this->repo->getClassHistory($classId);
        $qaVisits = $this->repo->getClassQAVisits($classId);
        $events = $this->repo->getClassEvents($classId);
        $notes = $this->repo->getClassNotes($classId);

        return [
            'agent_assignments' => $history['agent_assignments'],
            'learner_assignments' => $history['learner_assignments'],
            'status_changes' => $history['status_changes'],
            'stop_restart_dates' => $history['stop_restart_dates'],
            'qa_visits' => $qaVisits,
            'events' => $events,
            'notes' => $notes,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Agent Timeline
    |--------------------------------------------------------------------------
    */

    /**
     * Get full timeline for an agent.
     *
     * Merges: primary/backup classes, notes, absences, QA visits,
     * and distinct subjects facilitated.
     *
     * @param int $agentId
     * @return array
     */
    public function getAgentTimeline(int $agentId): array
    {
        $history = $this->repo->getAgentHistory($agentId);
        $qaVisits = $this->repo->getAgentQAVisits($agentId);
        $subjects = $this->repo->getAgentSubjects($agentId);

        // Derive unique clients from primary classes
        $clients = $this->deriveClientsFromClasses($history['primary_classes']);

        return [
            'primary_classes' => $history['primary_classes'],
            'backup_classes' => $history['backup_classes'],
            'notes' => $history['notes'],
            'absences' => $history['absences'],
            'qa_visits' => $qaVisits,
            'subjects' => $subjects,
            'clients' => $clients,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Learner Timeline
    |--------------------------------------------------------------------------
    */

    /**
     * Get full timeline for a learner.
     *
     * Merges: class enrollments, hours logged, portfolios,
     * progression dates with start/completion, and derived client list.
     *
     * @param int $learnerId
     * @return array
     */
    public function getLearnerTimeline(int $learnerId): array
    {
        $history = $this->repo->getLearnerHistory($learnerId);
        $portfolios = $this->repo->getLearnerPortfolios($learnerId);
        $progressionDates = $this->repo->getLearnerProgressionDates($learnerId);

        // Derive clients from class enrollments
        $clients = $this->deriveClientsFromEnrollments($history['class_enrollments']);

        return [
            'class_enrollments' => $history['class_enrollments'],
            'hours_logged' => $history['hours_logged'],
            'portfolios' => $portfolios,
            'progression_dates' => $progressionDates,
            'clients' => $clients,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Client Timeline
    |--------------------------------------------------------------------------
    */

    /**
     * Get full timeline for a client.
     *
     * Merges: classes, locations, and derives agent list and
     * learner list from associated classes.
     *
     * @param int $clientId
     * @return array
     */
    public function getClientTimeline(int $clientId): array
    {
        $history = $this->repo->getClientHistory($clientId);

        // Derive agents from classes
        $agents = $this->deriveAgentsFromClasses($history['classes']);

        // Derive learner list from classes
        $learners = $this->deriveLearnerListFromClasses($history['classes']);

        return [
            'classes' => $history['classes'],
            'locations' => $history['locations'],
            'agents' => $agents,
            'learners' => $learners,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Derive unique client IDs from a set of classes.
     *
     * @param array $classes
     * @return array Unique client info [{client_id}]
     */
    private function deriveClientsFromClasses(array $classes): array
    {
        $seen = [];
        $clients = [];

        foreach ($classes as $class) {
            $clientId = $class['client_id'] ?? null;
            if ($clientId !== null && !isset($seen[$clientId])) {
                $seen[$clientId] = true;
                $clients[] = ['client_id' => (int) $clientId];
            }
        }

        return $clients;
    }

    /**
     * Derive unique clients from learner class enrollments.
     *
     * @param array $enrollments
     * @return array
     */
    private function deriveClientsFromEnrollments(array $enrollments): array
    {
        $seen = [];
        $clients = [];

        foreach ($enrollments as $enrollment) {
            $clientId = $enrollment['client_id'] ?? null;
            if ($clientId !== null && !isset($seen[$clientId])) {
                $seen[$clientId] = true;
                $clients[] = ['client_id' => (int) $clientId];
            }
        }

        return $clients;
    }

    /**
     * Derive unique agents from a set of client classes.
     *
     * @param array $classes
     * @return array
     */
    private function deriveAgentsFromClasses(array $classes): array
    {
        $seen = [];
        $agents = [];

        foreach ($classes as $class) {
            $agentId = $class['class_agent'] ?? null;
            if ($agentId !== null && !isset($seen[$agentId])) {
                $seen[$agentId] = true;
                $agents[] = [
                    'agent_id' => (int) $agentId,
                    'class_id' => (int) ($class['class_id'] ?? 0),
                ];
            }
        }

        return $agents;
    }

    /**
     * Derive learner list from client classes by querying learner_ids JSONB.
     *
     * @param array $classes Array of class rows (must include class_id)
     * @return array Unique learners [{learner_id, class_id}]
     */
    private function deriveLearnerListFromClasses(array $classes): array
    {
        if (empty($classes)) {
            return [];
        }

        $classIds = array_filter(array_map(
            fn($c) => isset($c['class_id']) ? (int) $c['class_id'] : null,
            $classes
        ));

        if (empty($classIds)) {
            return [];
        }

        // Build parameterized IN clause
        $placeholders = [];
        $params = [];
        foreach ($classIds as $i => $id) {
            $key = "cid_{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }
        $inClause = implode(',', $placeholders);

        $rows = $this->db->getAll(
            "SELECT class_id, learner_ids FROM classes WHERE class_id IN ({$inClause})",
            $params
        ) ?: [];

        $seen = [];
        $learners = [];

        foreach ($rows as $row) {
            $learnerEntries = $this->decodeJsonb($row['learner_ids'] ?? '[]');
            foreach ($learnerEntries as $entry) {
                $learnerId = null;
                if (is_array($entry) && isset($entry['id'])) {
                    $learnerId = (int) $entry['id'];
                } elseif (is_numeric($entry)) {
                    $learnerId = (int) $entry;
                }

                if ($learnerId !== null && !isset($seen[$learnerId])) {
                    $seen[$learnerId] = true;
                    $learners[] = [
                        'learner_id' => $learnerId,
                        'name' => $entry['name'] ?? null,
                        'class_id' => (int) $row['class_id'],
                    ];
                }
            }
        }

        return $learners;
    }

    /**
     * Safely decode a JSONB column value.
     *
     * @param mixed $value
     * @return array
     */
    private function decodeJsonb(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
