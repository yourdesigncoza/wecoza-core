<?php
declare(strict_types=1);

namespace WeCoza\Agents\CLI;

use PDO;
use WP_CLI;
use WP_CLI_Command;
use WeCoza\Agents\Repositories\AgentRepository;
use WeCoza\Agents\Services\AgentWpUserService;

use function count;
use function is_email;
use function sprintf;
use function trim;
use function wecoza_db;

final class SyncAgentUsersCommand extends WP_CLI_Command
{
    public static function register(): void
    {
        WP_CLI::add_command('wecoza sync-agent-users', new self());
    }

    /**
     * Create WP users for agents that don't have one yet.
     *
     * Finds all active agents without a linked wp_user_id and creates
     * (or links existing) WordPress user accounts with the wp_agent role.
     *
     * ## EXAMPLES
     *
     *     wp wecoza sync-agent-users
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $repository = new AgentRepository();
        $wpUserService = new AgentWpUserService($repository);

        $db = wecoza_db();
        $stmt = $db->prepare(
            "SELECT agent_id, first_name, surname, email_address
             FROM agents
             WHERE status = 'active'
               AND (wp_user_id IS NULL OR wp_user_id = 0)
             ORDER BY agent_id"
        );
        $stmt->execute();
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($agents)) {
            WP_CLI::success('No agents need WP user accounts.');
            return;
        }

        WP_CLI::log(sprintf('Found %d agents without WP users.', count($agents)));

        $created = 0;
        $linked  = 0;
        $skipped = 0;

        foreach ($agents as $agent) {
            $agentId = (int) $agent['agent_id'];
            $email   = trim($agent['email_address'] ?? '');

            if (empty($email) || !is_email($email)) {
                WP_CLI::warning("Agent {$agentId}: skipped — invalid/empty email");
                $skipped++;
                continue;
            }

            $alreadyExists = (bool) get_user_by('email', $email);
            $wpUserId = $wpUserService->syncWpUser($agentId, $agent, null, false);

            if ($wpUserId) {
                if ($alreadyExists) {
                    WP_CLI::log("Agent {$agentId}: linked to existing WP user {$wpUserId}");
                    $linked++;
                } else {
                    WP_CLI::log("Agent {$agentId}: created WP user {$wpUserId}");
                    $created++;
                }
            } else {
                WP_CLI::warning("Agent {$agentId}: failed to create/link WP user");
                $skipped++;
            }
        }

        WP_CLI::success(sprintf(
            'Done. Created: %d, Linked: %d, Skipped: %d',
            $created, $linked, $skipped
        ));
    }
}
