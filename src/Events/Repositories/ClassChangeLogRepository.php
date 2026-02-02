<?php
declare(strict_types=1);

namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Core\Database\PostgresConnection;
use PDO;
use function sprintf;
use function strtoupper;
use function implode;
use function in_array;

if (!defined('ABSPATH')) {
    exit;
}

final class ClassChangeLogRepository extends BaseRepository
{
    protected static string $table = 'class_change_logs';
    protected static string $primaryKey = 'log_id';

    /**
     * Get columns allowed for ORDER BY clauses
     *
     * @return array List of allowed column names
     */
    protected function getAllowedOrderColumns(): array
    {
        return ['log_id', 'class_id', 'changed_at', 'operation'];
    }

    /**
     * Get columns allowed for WHERE clause filtering
     *
     * @return array List of allowed column names
     */
    protected function getAllowedFilterColumns(): array
    {
        return ['log_id', 'class_id', 'operation'];
    }

    /**
     * Export all logs with callback processing
     *
     * @param callable(array<string, mixed>):void $callback
     */
    public function exportLogs(callable $callback): void
    {
        $sql = "SELECT log_id, operation, changed_at, class_id, (new_row->>'class_code') AS class_code, (new_row->>'class_subject') AS class_subject, diff FROM class_change_logs ORDER BY log_id ASC;";

        $stmt = $this->db->getPdo()->query($sql);
        if ($stmt === false) {
            return;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = [
                'operation' => $row['operation'] ?? null,
                'changed_at' => $row['changed_at'] ?? null,
                'class_id' => $row['class_id'] ?? null,
                'class_code' => $row['class_code'] ?? null,
                'class_subject' => $row['class_subject'] ?? null,
                'diff' => $row['diff'] ?? null,
            ];

            $callback($payload);
        }
    }

    /**
     * Get logs with AI summary
     *
     * @param int $limit Maximum number of records
     * @param int|null $classId Optional class ID filter
     * @param string|null $operation Optional operation filter (INSERT/UPDATE)
     * @return array<int, array<string, mixed>>
     */
    public function getLogsWithAISummary(int $limit, ?int $classId, ?string $operation): array
    {
        $conditions = [];
        $params = [];

        if ($classId !== null) {
            $conditions[] = 'class_id = :class_id';
            $params[':class_id'] = $classId;
        }

        if ($operation !== null && in_array(strtoupper($operation), ['INSERT', 'UPDATE'], true)) {
            $conditions[] = 'operation = :operation';
            $params[':operation'] = strtoupper($operation);
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf(
            "SELECT log_id, operation, changed_at, class_id, (new_row->>'class_code') AS class_code, (new_row->>'class_subject') AS class_subject, ai_summary FROM class_change_logs %s ORDER BY changed_at DESC LIMIT :limit;",
            $whereClause
        );

        $stmt = $this->db->getPdo()->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            return [];
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'log_id' => $row['log_id'] ?? null,
                'operation' => $row['operation'] ?? null,
                'changed_at' => $row['changed_at'] ?? null,
                'class_id' => $row['class_id'] ?? null,
                'class_code' => $row['class_code'] ?? null,
                'class_subject' => $row['class_subject'] ?? null,
                'ai_summary' => $row['ai_summary'] ?? null,
            ];
        }

        return $results;
    }
}
