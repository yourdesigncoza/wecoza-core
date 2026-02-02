<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use RuntimeException;
use WeCoza\Core\Database\PostgresConnection;
use WeCoza\Events\Support\OpenAIConfig;

use function as_enqueue_async_action;
use function get_option;
use function get_transient;
use function is_string;
use function microtime;
use function set_transient;
use function delete_transient;
use function max;
use function sprintf;
use function strtoupper;
use function update_option;
use function gc_collect_cycles;
use function wecoza_log;

final class NotificationProcessor
{
    private const OPTION_LAST_ID = 'wecoza_last_notified_log_id';
    private const LOCK_KEY = 'wecoza_ai_summary_lock';
    private const LOCK_TTL = 120;  // 2 minutes for 50+ item batches
    private const MAX_RUNTIME_SECONDS = 90;  // Room for 50 items
    private const MIN_REMAINING_SECONDS = 5;
    private const BATCH_LIMIT = 50;
    private const MEMORY_CLEANUP_INTERVAL = 50;  // Every 50 records

    private PostgresConnection $db;

    public function __construct(
        private readonly NotificationSettings $settings,
        private readonly OpenAIConfig $openAIConfig
    ) {
        $this->db = PostgresConnection::getInstance();
    }

    public static function boot(): self
    {
        $openAIConfig = new OpenAIConfig();
        return new self(new NotificationSettings(), $openAIConfig);
    }

    public function process(): void
    {
        if (!$this->acquireLock()) {
            return;
        }

        $start = microtime(true);
        $lastProcessed = (int) get_option(self::OPTION_LAST_ID, 0);
        $latestId = $lastProcessed;

        try {
            $rows = $this->fetchRows($lastProcessed, self::BATCH_LIMIT);
            $iteration = 0;

            foreach ($rows as $row) {
                $iteration++;
                if ($this->shouldStop($start)) {
                    break;
                }

                $latestId = max($latestId, (int) $row['log_id']);
                $logId = (int) $row['log_id'];
                $operation = strtoupper((string) ($row['operation'] ?? ''));

                // Check if this operation has a recipient configured
                $recipient = $this->settings->getRecipientForOperation($operation);
                if ($recipient === null) {
                    continue;
                }

                // Check AI eligibility to decide which job to schedule
                $eligibility = $this->openAIConfig->assessEligibility($logId);
                $needsAI = $eligibility['eligible'] !== false;

                if ($needsAI) {
                    // Schedule AI enrichment first (will chain to email on success)
                    as_enqueue_async_action(
                        'wecoza_enrich_notification',
                        ['log_id' => $logId],
                        'wecoza-notifications'
                    );
                } else {
                    // Skip AI, schedule email directly
                    as_enqueue_async_action(
                        'wecoza_send_notification_email',
                        ['log_id' => $logId, 'recipient' => $recipient, 'email_context' => []],
                        'wecoza-notifications'
                    );
                }

                // Periodic memory cleanup
                if ($this->shouldCleanupMemory($iteration)) {
                    gc_collect_cycles();

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        wecoza_log(sprintf(
                            'NotificationProcessor: Memory cleanup at iteration %d, usage: %s MB',
                            $iteration,
                            round(memory_get_usage(true) / 1048576, 2)
                        ), 'debug');
                    }
                }
            }

            // Final memory cleanup after batch
            gc_collect_cycles();

            if ($latestId !== $lastProcessed) {
                update_option(self::OPTION_LAST_ID, $latestId, false);
            }
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(int $afterId, int $limit): array
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
WHERE log_id > :after_id
ORDER BY log_id ASC
LIMIT :limit;
SQL;

        $stmt = $this->db->getPdo()->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare notification query.');
        }

        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute notification query.');
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    private function shouldStop(float $start): bool
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed >= self::MAX_RUNTIME_SECONDS) {
            return true;
        }

        return (self::MAX_RUNTIME_SECONDS - $elapsed) < self::MIN_REMAINING_SECONDS;
    }

    private function acquireLock(): bool
    {
        $existing = get_transient(self::LOCK_KEY);
        if ($existing !== false) {
            return false;
        }

        return set_transient(self::LOCK_KEY, '1', self::LOCK_TTL);
    }

    private function refreshLock(): void
    {
        // Extend lock during long processing
        set_transient(self::LOCK_KEY, '1', self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Check if memory cleanup should run based on iteration count.
     */
    private function shouldCleanupMemory(int $iteration): bool
    {
        return $iteration > 0 && ($iteration % self::MEMORY_CLEANUP_INTERVAL === 0);
    }
}
