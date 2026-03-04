<?php
declare(strict_types=1);

/**
 * Agent Access Controller
 *
 * Provides the [wecoza_agent_attendance] shortcode, auto-creates the /agent-attendance/
 * WP page, and queries classes assigned to the logged-in agent (primary + backup via JSONB).
 *
 * @package WeCoza\Agents
 * @since 7.0.0
 */

namespace WeCoza\Agents\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Access Controller
 *
 * Standalone controller (not extending BaseController) — mirrors AgentsController pattern.
 *
 * @since 7.0.0
 */
class AgentAccessController
{
    /**
     * Constructor — registers WordPress hooks.
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('init', [$this, 'ensureAttendancePage']);
    }

    /**
     * Register the agent attendance shortcode.
     *
     * @return void
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_agent_attendance', [$this, 'agentAttendanceShortcode']);
    }

    /**
     * Auto-create the /agent-attendance/ WP page if it does not exist.
     *
     * Uses the same transient-guarded pattern as ClassController::ensureRequiredPages().
     * Only runs for admin users to avoid unnecessary DB queries on every frontend request.
     *
     * @return void
     */
    public function ensureAttendancePage(): void
    {
        if (!current_user_can('manage_options') || get_transient('wecoza_agent_attendance_page_checked')) {
            return;
        }

        set_transient('wecoza_agent_attendance_page_checked', true, HOUR_IN_SECONDS);

        if (!get_page_by_path('agent-attendance')) {
            wp_insert_post([
                'post_title'     => 'Agent Attendance',
                'post_content'   => '[wecoza_agent_attendance]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'post_name'      => 'agent-attendance',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);
        }
    }

    /**
     * Shortcode handler for [wecoza_agent_attendance].
     *
     * Resolves WP user -> agent_id -> assigned classes, then renders the view.
     *
     * @return string HTML output
     */
    public function agentAttendanceShortcode(): string
    {
        if (!current_user_can('capture_attendance')) {
            return '<div class="alert alert-danger">Access denied.</div>';
        }

        $agentId = $this->resolveAgentId();

        if ($agentId === null) {
            return '<div class="alert alert-warning">Agent profile not found. Contact administrator.</div>';
        }

        $classes = $this->getClassesForAgent($agentId);

        return wecoza_view('agents/attendance/agent-attendance', [
            'classes' => $classes,
            'agentId' => $agentId,
        ], true);
    }

    /**
     * Resolve the agent_id for the currently logged-in WP user.
     *
     * Resolution chain: WP user ID -> agents.wp_user_id lookup -> agent_id
     *
     * @return int|null Agent ID or null if not found / not logged in
     */
    private function resolveAgentId(): ?int
    {
        $wpUserId = get_current_user_id();

        if (!$wpUserId) {
            return null;
        }

        $repo  = new \WeCoza\Agents\Repositories\AgentRepository();
        $agent = $repo->findByWpUserId($wpUserId);

        return $agent ? (int) $agent['agent_id'] : null;
    }

    /**
     * Get all classes assigned to the given agent (primary + backup).
     *
     * Uses a PostgreSQL JSONB containment query to find backup_agent_ids entries.
     * Agent ID is cast to int to match the stored integer format (Pitfall 4).
     *
     * @param int $agentId Agent ID from agents table
     * @return array Array of class rows, empty array if none found
     */
    private function getClassesForAgent(int $agentId): array
    {
        // Cast to int to match stored JSONB format: {"agent_id": 5} not {"agent_id": "5"}
        $jsonFragment = json_encode([['agent_id' => (int) $agentId]]);

        $sql = "
            SELECT class_id, class_code, class_type, class_subject, class_status,
                   class_agent, backup_agent_ids, schedule_data, stop_restart_dates,
                   learner_ids, original_start_date
            FROM classes
            WHERE (class_agent = :agent_id
               OR backup_agent_ids::jsonb @> :json_frag)
              AND class_status != 'deleted'
            ORDER BY original_start_date DESC
        ";

        $params = [
            ':agent_id' => $agentId,
            ':json_frag' => $jsonFragment,
        ];

        return wecoza_db()->getAll($sql, $params) ?: [];
    }
}
