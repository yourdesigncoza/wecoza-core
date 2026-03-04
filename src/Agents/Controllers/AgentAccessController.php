<?php
declare(strict_types=1);

/**
 * Agent Access Controller
 *
 * Provides the [wecoza_agent_attendance] shortcode, auto-creates the /app/agent-attendance/
 * WP page, queries classes assigned to the logged-in agent (primary + backup via JSONB),
 * and enforces the agent page cage: login redirect, admin block, and template redirect.
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

        // Redirect cage: login → attendance, admin block, page cage
        add_filter('login_redirect', [$this, 'redirectAgentOnLogin'], 9, 3);
        add_action('admin_init', [$this, 'blockAgentAdminAccess']);
        add_action('template_redirect', [$this, 'enforceAgentPageCage']);
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
     * Auto-create the /app/agent-attendance/ CPT post if it does not exist.
     *
     * Uses the same transient-guarded pattern as ClassController::ensureRequiredPages().
     * Creates as an 'app' custom post type (matching all other WeCoza app pages).
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

        $existing = get_posts([
            'post_type'   => 'app',
            'name'        => 'agent-attendance',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($existing)) {
            wp_insert_post([
                'post_title'     => 'Agent Attendance',
                'post_content'   => '[wecoza_agent_attendance]',
                'post_status'    => 'publish',
                'post_type'      => 'app',
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

    // -------------------------------------------------------------------------
    // Redirect cage hooks
    // -------------------------------------------------------------------------

    /**
     * Redirect wp_agent users to the attendance page immediately after login.
     *
     * Runs at priority 9 — fires BEFORE the theme's priority-10
     * ydcoza_force_login_redirect_to_home filter, so agents land on the
     * attendance page rather than being pushed to home_url() by the theme.
     *
     * @param string          $redirectTo URL WordPress would redirect to.
     * @param string          $request    Requested redirect URL.
     * @param \WP_User|mixed  $user       The logged-in user (or WP_Error on failure).
     * @return string Redirect destination URL.
     */
    public function redirectAgentOnLogin(string $redirectTo, string $request, $user): string
    {
        if (!($user instanceof \WP_User)) {
            return $redirectTo;
        }

        if (in_array('wp_agent', $user->roles, true)) {
            return home_url('/app/agent-attendance/');
        }

        return $redirectTo;
    }

    /**
     * Block wp_agent users from accessing the WP admin area.
     *
     * CRITICAL: AJAX requests to admin-ajax.php must never be blocked —
     * attendance capture relies on WordPress AJAX (wp_doing_ajax() guard).
     *
     * @return void
     */
    public function blockAgentAdminAccess(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        $user = wp_get_current_user();

        if (in_array('wp_agent', $user->roles, true)) {
            wp_redirect(home_url('/app/agent-attendance/'));
            exit;
        }
    }

    /**
     * Enforce the agent page cage: wp_agent users may only visit the
     * attendance landing page and the single-class view.
     *
     * Allowlist (all 'app' CPT posts):
     *   - /app/agent-attendance/       (agent-attendance slug)
     *   - /app/display-single-class/   (display-single-class slug)
     *
     * If the attendance post doesn't exist, skip redirect to prevent infinite loop.
     *
     * @return void
     */
    public function enforceAgentPageCage(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();

        if (!in_array('wp_agent', $user->roles, true)) {
            return;
        }

        // Allow WordPress login/logout URLs
        if (is_login()) {
            return;
        }

        // On an 'app' CPT single post — check slug allowlist
        if (is_singular('app')) {
            $allowedSlugs = ['agent-attendance', 'display-single-class'];
            $currentSlug  = get_queried_object()->post_name ?? '';

            if (in_array($currentSlug, $allowedSlugs, true)) {
                return;
            }
        }

        // Safety: verify attendance post exists before redirecting
        $attendancePost = get_posts([
            'post_type'   => 'app',
            'name'        => 'agent-attendance',
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (empty($attendancePost)) {
            wecoza_log('AgentAccessController: attendance app post not found — skipping template_redirect cage', 'warning');
            return;
        }

        // Current page not in allowlist — redirect to attendance page
        wp_redirect(home_url('/app/agent-attendance/'));
        exit;
    }
}
