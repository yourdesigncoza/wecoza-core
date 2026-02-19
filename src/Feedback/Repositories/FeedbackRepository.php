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
        return ['id', 'user_id', 'category', 'is_resolved', 'created_at', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'user_id', 'category', 'is_resolved', 'shortcode', 'created_at'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'user_id', 'user_email', 'category', 'feedback_text',
            'ai_conversation', 'ai_generated_title', 'ai_suggested_priority',
            'page_url', 'page_title', 'shortcode', 'url_params',
            'browser_info', 'viewport', 'screenshot_path',
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return [
            'feedback_text', 'ai_conversation', 'ai_generated_title',
            'ai_suggested_priority',
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

}
