<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

final class FeedbackCommentRepository extends BaseRepository
{
    protected static string $table = 'feedback_comments';
    protected static string $primaryKey = 'id';

    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'feedback_id', 'created_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'feedback_id', 'author_email'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return ['feedback_id', 'author_email', 'comment_text'];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return []; // Append-only — no edits
    }

    /**
     * Batch-fetch comments for multiple feedback IDs (avoids N+1).
     *
     * @param int[] $feedbackIds
     * @return array<int, array[]> Keyed by feedback_id
     */
    public function findByFeedbackIds(array $feedbackIds): array
    {
        if (empty($feedbackIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($feedbackIds), '?'));
        $sql = "SELECT * FROM feedback_comments
                WHERE feedback_id IN ({$placeholders})
                ORDER BY created_at ASC";

        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($feedbackIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[(int) $row['feedback_id']][] = $row;
            }
            return $grouped;
        } catch (Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'FeedbackCommentRepository::findByFeedbackIds'));
            return [];
        }
    }
}
