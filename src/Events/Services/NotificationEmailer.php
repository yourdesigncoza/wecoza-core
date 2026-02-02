<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RuntimeException;
use WeCoza\Core\Database\PostgresConnection;
use WeCoza\Events\Views\Presenters\NotificationEmailPresenter;

use function error_log;
use function is_array;
use function is_string;
use function json_decode;
use function strtoupper;
use function sprintf;
use function wp_mail;
use function wecoza_log;

/**
 * Handles email sending for individual notifications.
 *
 * Designed to run as an Action Scheduler job. Fetches notification data,
 * formats email via presenter, and sends via wp_mail.
 */
final class NotificationEmailer
{
    private PostgresConnection $db;

    public function __construct(
        private readonly NotificationEmailPresenter $presenter
    ) {
        $this->db = PostgresConnection::getInstance();
    }

    public static function boot(): self
    {
        $presenter = new NotificationEmailPresenter();
        return new self($presenter);
    }

    /**
     * Send email for a single notification.
     *
     * @param int $logId The log_id to send email for
     * @param string $recipient Email recipient
     * @param array $emailContext Context from AI enrichment (alias_map, obfuscated)
     * @return bool True if email sent successfully
     */
    public function send(int $logId, string $recipient, array $emailContext = []): bool
    {
        $row = $this->fetchRow($logId);
        if ($row === null) {
            wecoza_log("NotificationEmailer: Row not found for log_id {$logId}", 'warning');
            return false;
        }

        $operation = strtoupper((string) ($row['operation'] ?? ''));
        $newRow = $this->decodeJson($row['new_row'] ?? null);
        $oldRow = $this->decodeJson($row['old_row'] ?? null);
        $diff = $this->decodeJson($row['diff'] ?? null);
        $summaryRecord = $this->decodeJson($row['ai_summary'] ?? null);

        $mailData = $this->presenter->present([
            'operation' => $operation,
            'row' => $row,
            'recipient' => $recipient,
            'new_row' => $newRow,
            'old_row' => $oldRow,
            'diff' => $diff,
            'summary' => $summaryRecord,
            'email_context' => $emailContext,
        ]);

        $subject = $mailData['subject'];
        $body = $mailData['body'];
        $headers = $mailData['headers'];

        $sent = wp_mail($recipient, $subject, $body, $headers);

        if (!$sent) {
            error_log(sprintf('WeCoza notification failed for row %d to %s', $logId, $recipient));
        } else {
            wecoza_log(sprintf('WeCoza notification sent for row %d to %s', $logId, $recipient), 'debug');
        }

        return $sent;
    }

    private function fetchRow(int $logId): ?array
    {
        $sql = <<<SQL
SELECT
    log_id,
    operation,
    changed_at,
    class_id,
    new_row,
    old_row,
    diff,
    ai_summary
FROM class_change_logs
WHERE log_id = :log_id
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare notification query.');
        }

        $stmt->bindValue(':log_id', $logId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute notification query.');
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function decodeJson(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
