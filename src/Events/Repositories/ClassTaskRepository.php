<?php
declare(strict_types=1);

namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Core\Database\PostgresConnection;
use PDO;
use RuntimeException;
use function preg_match;
use function strtolower;

if (!defined('ABSPATH')) {
    exit;
}

final class ClassTaskRepository extends BaseRepository
{
    // quoteIdentifier: all column names in this repository are hardcoded literals (safe)

    protected static string $table = 'classes';
    protected static string $primaryKey = 'class_id';

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array List of allowed column names
     */
    protected function getAllowedOrderColumns(): array
    {
        return ['class_id', 'original_start_date'];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array List of allowed column names
     */
    protected function getAllowedFilterColumns(): array
    {
        return ['class_id'];
    }

    /**
     * Fetch classes with their latest change log
     *
     * @param int $limit Number of records to fetch
     * @param string $sortDirection Sort direction (asc/desc)
     * @param int|null $classIdFilter Optional class ID filter
     * @return array<int, array<string, mixed>>
     */
    public function fetchClasses(int $limit, string $sortDirection, ?int $classIdFilter): array
    {
        // Complex query: 4-table JOIN with dynamic WHERE and ORDER BY
        $orderDirection = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

        $whereClause = '';
        if ($classIdFilter !== null) {
            $whereClause = 'WHERE c.class_id = :class_id';
        }

        $sql = <<<SQL
SELECT
    c.class_id,
    c.client_id,
    c.class_type,
    c.class_subject,
    c.class_code,
    c.original_start_date,
    c.initial_class_agent,
    c.class_agent,
    ia.agent_id AS initial_agent_id,
    ia.first_name AS initial_agent_first,
    ia.surname AS initial_agent_surname,
    ia.initials AS initial_agent_initials,
    pa.agent_id AS primary_agent_id,
    pa.first_name AS primary_agent_first,
    pa.surname AS primary_agent_surname,
    pa.initials AS primary_agent_initials,
    c.exam_class,
    c.exam_type,
    c.seta_funded,
    COALESCE(c.seta, cl.seta) AS seta_name,
    c.stop_restart_dates,
    c.updated_at,
    c.order_nr,
    c.class_status,
    c.event_dates,
    cl.client_name
FROM classes c
LEFT JOIN clients cl ON cl.client_id = c.client_id
LEFT JOIN agents ia ON ia.agent_id = c.initial_class_agent
LEFT JOIN agents pa ON pa.agent_id = c.class_agent
{$whereClause}
ORDER BY c.original_start_date {$orderDirection} NULLS LAST, c.class_id {$orderDirection}
LIMIT :limit;
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare class query.');
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($classIdFilter !== null) {
            $stmt->bindValue(':class_id', $classIdFilter, PDO::PARAM_INT);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute class query.');
        }

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row === false) {
                continue;
            }
            $result[] = $row;
        }

        return $result;
    }
}
