<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

final class FeedbackRepository extends BaseRepository
{
    protected static string $table = 'feedback_submissions';
    protected static string $primaryKey = 'id';

    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'user_id', 'category', 'sync_status', 'is_resolved', 'created_at', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'user_id', 'category', 'sync_status', 'is_resolved', 'shortcode', 'created_at'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'user_id', 'user_email', 'category', 'feedback_text',
            'ai_conversation', 'ai_generated_title', 'ai_suggested_priority',
            'page_url', 'page_title', 'shortcode', 'url_params',
            'browser_info', 'viewport', 'screenshot_path',
            'linear_issue_id', 'linear_issue_url', 'sync_status',
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return [
            'feedback_text', 'ai_conversation', 'ai_generated_title',
            'ai_suggested_priority', 'linear_issue_id', 'linear_issue_url',
            'sync_status', 'sync_attempts', 'sync_error',
            'is_resolved', 'resolved_by', 'resolved_at', 'updated_at',
        ];
    }

    public function findAllForDashboard(string $filter = 'all'): array
    {
        $where = match ($filter) {
            'open'     => "WHERE is_resolved = FALSE",
            'resolved' => "WHERE is_resolved = TRUE",
            default    => "",
        };

        $sql = "SELECT * FROM feedback_submissions {$where} ORDER BY created_at DESC";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'FeedbackRepository::findAllForDashboard'));
            return [];
        }
    }

    public function toggleResolved(int $id, string $resolvedBy): bool
    {
        $record = $this->findById($id);
        if (!$record) {
            return false;
        }

        $isResolved = !($record['is_resolved'] ?? false);

        return $this->update($id, [
            'is_resolved' => $isResolved ? 'true' : 'false',
            'resolved_by' => $isResolved ? $resolvedBy : null,
            'resolved_at' => $isResolved ? date('Y-m-d H:i:s') : null,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function findPendingSync(int $maxAttempts = 5, int $limit = 20): array
    {
        $sql = "SELECT * FROM feedback_submissions
                WHERE sync_status IN ('pending', 'failed')
                AND sync_attempts < :max_attempts
                ORDER BY created_at ASC
                LIMIT :limit";

        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':max_attempts', $maxAttempts, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markSynced(int $id, string $linearIssueId, string $linearIssueUrl): bool
    {
        return $this->update($id, [
            'linear_issue_id'  => $linearIssueId,
            'linear_issue_url' => $linearIssueUrl,
            'sync_status'      => 'synced',
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Atomic markFailed - uses SQL increment to avoid read-then-write race condition
     */
    public function markFailed(int $id, string $error): bool
    {
        $sql = "UPDATE feedback_submissions
                SET sync_attempts = sync_attempts + 1,
                    sync_status = CASE
                        WHEN sync_attempts + 1 >= 5 THEN 'permanently_failed'
                        ELSE 'failed'
                    END,
                    sync_error = :error,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";

        try {
            $stmt = $this->db->query($sql, ['id' => $id, 'error' => $error]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'FeedbackRepository::markFailed'));
            return false;
        }
    }
}
