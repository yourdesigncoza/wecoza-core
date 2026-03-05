# Phase 55: Agent Attendance Page - Research

**Researched:** 2026-03-04
**Domain:** WordPress role-based page restriction, PostgreSQL JSONB class-agent lookup, attendance UI reuse
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AGT-05 | Agent sees only their assigned classes (primary + backup) on dedicated attendance page | JSONB query confirmed — `class_agent = :id OR backup_agent_ids::jsonb @> '[{"agent_id": N}]'::jsonb` |
| AGT-06 | Agent-dedicated attendance shortcode renders minimal page with existing attendance capture UI | Existing `single-class-display.view.php` + `attendance.php` component reusable as-is |
| AGT-07 | Agent is redirected away from WP admin and other WeCoza pages — can only access attendance page | `login_redirect` filter + `template_redirect` hook pattern confirmed |
| AGT-08 | Agent login shows attendance page directly (no WP dashboard) | Existing `login_redirect` filter in theme `helper.php` line 401 — needs wp_agent branching |
</phase_requirements>

---

## Summary

Phase 55 delivers the agent-facing attendance page: a locked-down view where `wp_agent` users land immediately after login, see only their assigned classes, can capture attendance, and cannot navigate anywhere else in WeCoza.

The foundation from Phase 54 is fully in place: `wp_agent` role exists with `capture_attendance` capability, `agents.wp_user_id` column links WP users to agent records, and attendance AJAX is capability-gated. Phase 55 builds on that by adding (1) a shortcode that queries classes by agent, renders the attendance UI, and (2) WordPress hooks that enforce the redirect cage.

The two plans already scoped — `55-01: AgentAccessController + shortcode + class lookup` and `55-02: Minimal page template + login redirect + admin redirect guard` — map cleanly to the implementation. No schema changes are required.

**Primary recommendation:** Add the `AgentAccessController` in `src/Agents/Controllers/`, register a `[wecoza_agent_attendance]` shortcode, create a WP page at `/agent-attendance/`, then wire three WordPress hooks for login redirect, admin redirect, and page access enforcement.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Roles/Caps | WP 6.0+ | `current_user_can()`, `get_role()` | Native WP, already used throughout plugin |
| PostgreSQL JSONB | Postgres (existing) | `@>` containment for backup_agent_ids | Already in schema; no migration needed |
| PHP PDO | 8.0+ | Parameterized queries via `wecoza_db()` | Plugin standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wecoza_view()` helper | Plugin internal | Render attendance view template | For any new PHP view |
| `wecoza_component()` helper | Plugin internal | Include existing component partials | Reuse attendance.php component directly |
| Phoenix Bootstrap classes | Theme | UI — cards, badges, alerts | Per CLAUDE.md — no custom CSS |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `template_redirect` hook | `wp` hook | `template_redirect` fires after WP query is set — more reliable for page-based guards |
| Creating new attendance view | Reusing existing `attendance.php` component | Reuse wins — DRY, tested, no duplication |
| WP custom page template file | Shortcode on a regular WP page | Shortcode approach is simpler and consistent with existing plugin patterns |

**Installation:** No new packages. Pure WordPress + existing plugin infrastructure.

---

## Architecture Patterns

### Recommended Project Structure

```
src/Agents/
├── Controllers/
│   ├── AgentsController.php         # existing
│   └── AgentAccessController.php    # NEW — shortcode + redirect logic
views/agents/
├── attendance/
│   └── agent-attendance.view.php    # NEW — agent-facing class list + attendance UI
```

No new repositories or models needed.

### Pattern 1: Class Lookup by Agent (Primary + Backup)

**What:** Single parameterized SQL query using PostgreSQL JSONB containment to find all classes where the agent is either `class_agent` (primary) or appears in `backup_agent_ids` (JSON array of `{date, agent_id}` objects).

**Confirmed working query (tested against live DB):**
```php
// Source: tested 2026-03-04 against live wecoza DB
$agentId = 5; // integer agent_id from agents table
$jsonFragment = json_encode([['agent_id' => $agentId]]);

$sql = "
    SELECT class_id, class_code, class_type, class_subject, class_status,
           class_agent, backup_agent_ids, schedule_data, stop_restart_dates,
           learner_ids
    FROM classes
    WHERE class_agent = :agent_id
       OR backup_agent_ids::jsonb @> :json_frag
    ORDER BY original_start_date DESC
";
$params = [
    ':agent_id'   => $agentId,
    ':json_frag'  => $jsonFragment,
];
$classes = wecoza_db()->getAll($sql, $params);
```

**Agent ID resolution chain:**
```
wp_get_current_user()->ID                   // WP user ID
→ AgentRepository::findByWpUserId($wpUserId) // agents.wp_user_id lookup
→ $agent['agent_id']                         // integer agent_id for class query
```

`findByWpUserId()` already exists in `AgentRepository` (line 269). No new repository method needed.

### Pattern 2: Login Redirect for wp_agent Users

**What:** Intercept the existing `login_redirect` filter in the theme (helper.php line 401) — but we should NOT modify theme files. Instead, add a higher-priority filter in `wecoza-core.php` or `AgentAccessController` that fires before the theme filter.

**Why NOT modify theme:** The `login_redirect` filter already exists in the theme at priority 10. Adding a plugin-level filter at priority 5 or 9 ensures agent redirect takes precedence without touching theme code.

```php
// Source: WordPress Codex — login_redirect filter
add_filter('login_redirect', function (string $redirectTo, string $request, $user): string {
    if (!($user instanceof \WP_User)) {
        return $redirectTo;
    }
    if (in_array('wp_agent', $user->roles, true)) {
        return home_url('/agent-attendance/');
    }
    return $redirectTo;
}, 9, 3); // Priority 9 — fires before theme's priority-10 filter
```

### Pattern 3: Admin Area Redirect Guard

**What:** Prevent `wp_agent` users from accessing `/wp-admin/`. Use `admin_init` hook to redirect non-privileged admin users away.

```php
// Source: WordPress Codex — admin_init hook
add_action('admin_init', function (): void {
    $user = wp_get_current_user();
    if (in_array('wp_agent', $user->roles, true)) {
        wp_redirect(home_url('/agent-attendance/'));
        exit;
    }
});
```

**Note:** AJAX requests go to `/wp-admin/admin-ajax.php`. The guard MUST allow AJAX through:
```php
add_action('admin_init', function (): void {
    if (wp_doing_ajax()) {
        return; // NEVER block AJAX — attendance capture uses admin-ajax.php
    }
    $user = wp_get_current_user();
    if (in_array('wp_agent', $user->roles, true)) {
        wp_redirect(home_url('/agent-attendance/'));
        exit;
    }
});
```

### Pattern 4: WeCoza Page Access Guard (template_redirect)

**What:** Prevent `wp_agent` users from browsing other WeCoza pages. Use `template_redirect` hook: if the user is a `wp_agent` AND the current page is NOT the attendance page, redirect back.

```php
// Source: WordPress Codex — template_redirect hook
add_action('template_redirect', function (): void {
    if (!is_user_logged_in()) {
        return;
    }
    $user = wp_get_current_user();
    if (!in_array('wp_agent', $user->roles, true)) {
        return; // Not an agent — no restriction
    }

    // Identify the attendance page by slug or option
    $attendancePage = get_page_by_path('agent-attendance');
    if ($attendancePage && is_page($attendancePage->ID)) {
        return; // Already on the attendance page — allow
    }

    // Redirect to attendance page
    wp_redirect(home_url('/agent-attendance/'));
    exit;
});
```

**Slug approach:** Using `get_page_by_path('agent-attendance')` is robust — it works even if the page ID changes. Store the attendance page slug as a plugin constant or option.

### Pattern 5: Shortcode — Agent Attendance

**What:** `[wecoza_agent_attendance]` shortcode rendered on a dedicated WP page (`/agent-attendance/`). The shortcode:
1. Checks user is logged in with `capture_attendance` capability
2. Resolves WP user → agent record → agent_id
3. Queries assigned classes
4. Renders a list of classes, each with the attendance capture section

**View strategy:** Each class row links to the class detail (attendance) view. OR — if the goal is truly self-contained — the shortcode renders a simplified accordion/list with attendance embedded per class. Given AGT-06 says "existing attendance capture UI," the safest approach is:
- Show a list of classes assigned to the agent
- Each class links to the existing `/app/display-single-class/?class_id=X` page
- The `template_redirect` guard (Pattern 4) must allow `app/display-single-class` for wp_agent users ONLY when accessed from the attendance page OR unconditionally while viewing their own class

**Revised guard logic:** Allow wp_agent access to:
1. `/agent-attendance/` — the landing page
2. `/app/display-single-class/` — single class view (needed for attendance capture)
3. AJAX endpoints (`admin-ajax.php`) — already allowed by Pattern 3

The two-page allowlist keeps the guard simple without reimplementing the attendance UI.

### Pattern 6: Enqueue Attendance Assets for wp_agent

The existing `ClassController::enqueueAssets()` uses `shouldEnqueueAssets()` to conditionally load JS. For the attendance page, the same scripts must load. Either:
- Use `AgentAccessController::enqueueAssets()` that explicitly enqueues the attendance JS
- OR call `ClassController`'s enqueue via hook

**Recommended:** AgentAccessController adds its own `wp_enqueue_scripts` hook that enqueues only the attendance-specific scripts (`attendance-capture.js`, its deps) when on the agent attendance page or single class page.

### Anti-Patterns to Avoid

- **Modifying theme `login_redirect` function:** Theme code belongs to theme. Use a higher-priority plugin filter instead.
- **Querying `backup_agent_ids` in PHP (loop + json_decode):** Never load all classes and filter in PHP. Use the DB-level JSONB `@>` query.
- **Blocking `wp-admin/admin-ajax.php` in `admin_init`:** Would break all AJAX including attendance capture. Always check `wp_doing_ajax()` first.
- **Recreating attendance UI:** Reuse the existing `attendance.php` component and the single-class display. DRY principle.
- **Hard-coding page ID:** Use `get_page_by_path('agent-attendance')` or store slug as constant — IDs can change between environments.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Agent → class lookup | PHP loop over all classes | PostgreSQL JSONB `@>` query | Scales; single DB round-trip |
| AJAX capability check | Custom session/cookie auth | `current_user_can('capture_attendance')` | Already enforced in Phase 54 handlers |
| Attendance capture UI | New form | Existing `attendance.php` component + JS | Tested, exception handling already wired |
| Login restriction | Session management | WP `login_redirect` filter | Built-in WP hook |
| Admin block | `.htaccess` rules | `admin_init` hook | PHP-level, context-aware (allows AJAX) |

---

## Common Pitfalls

### Pitfall 1: Blocking AJAX in admin_init
**What goes wrong:** `admin_init` fires for all `/wp-admin/` requests including `admin-ajax.php`. Redirecting unconditionally breaks attendance capture.
**Why it happens:** Attendance capture JS posts to `wp-admin/admin-ajax.php`.
**How to avoid:** Always guard with `if (wp_doing_ajax()) { return; }` before redirecting.
**Warning signs:** Attendance modal submits but nothing happens; JS console shows 302 redirect.

### Pitfall 2: login_redirect Priority Conflict
**What goes wrong:** Theme's `ydcoza_force_login_redirect_to_home` at priority 10 redirects all users to `home_url()`. If plugin filter runs at priority 10 too, execution order is undefined.
**How to avoid:** Use priority 9 for the plugin filter — fires first, returns early for `wp_agent`, theme filter is never reached.
**Warning signs:** Agent logs in and lands on home page instead of attendance page.

### Pitfall 3: WP User with No Agent Record
**What goes wrong:** A `wp_agent` WP user exists but `agents.wp_user_id` doesn't point to any agent (stale data, failed sync).
**How to avoid:** In `AgentAccessController`, if `findByWpUserId()` returns null, show a clear error message ("Your agent profile could not be found. Please contact an administrator.") — do NOT crash.
**Warning signs:** Blank page or PHP fatal for agent with orphaned WP account.

### Pitfall 4: backup_agent_ids JSON Format Mismatch
**What goes wrong:** The JSONB containment query `@> '[{"agent_id": N}]'` uses integer `N`. If stored data has string `"agent_id"` values (e.g. `{"agent_id": "5"}` vs `{"agent_id": 5}`), the containment check fails silently.
**Verified format (from live DB 2026-03-04):** `[{"date": "2026-03-31", "agent_id": 5}]` — agent_id is an integer.
**How to avoid:** Cast agent_id to int when building json_encode: `json_encode([['agent_id' => (int)$agentId]])`.
**Warning signs:** Backup agents don't see their classes.

### Pitfall 5: template_redirect Infinite Loop
**What goes wrong:** If the attendance page slug lookup fails (page doesn't exist yet), every page triggers the redirect, including the redirect target itself — infinite loop.
**How to avoid:** Check `$attendancePage` is non-null before comparing. If null, do NOT redirect — log an error instead.

### Pitfall 6: Page Not Created Automatically
**What goes wrong:** The attendance page at `/agent-attendance/` doesn't exist until an admin creates it. If agents log in before the page exists, the redirect leads to a 404.
**How to avoid:** `AgentAccessController::initialize()` should call an `ensureAttendancePage()` method (similar to `ClassController::ensureRequiredPages()`) that creates the page if missing on `init`, guarded by a transient (same pattern already in ClassController).

---

## Code Examples

### Class Lookup for Agent

```php
// Source: tested against live DB 2026-03-04
// In AgentAccessController or a new AgentClassService

private function getClassesForAgent(int $agentId): array
{
    $jsonFragment = json_encode([['agent_id' => $agentId]]);
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
    return wecoza_db()->getAll($sql, [
        ':agent_id'   => $agentId,
        ':json_frag'  => $jsonFragment,
    ]) ?: [];
}
```

### WP User → Agent ID Resolution

```php
// Source: AgentRepository::findByWpUserId() already exists
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
```

### Shortcode Registration Pattern (from ClassController)

```php
// Mirrors ClassController::registerShortcodes() pattern
public function registerShortcodes(): void
{
    add_shortcode('wecoza_agent_attendance', [$this, 'agentAttendanceShortcode']);
}

public function agentAttendanceShortcode(): string
{
    if (!current_user_can('capture_attendance')) {
        return '<div class="alert alert-danger">Access denied.</div>';
    }

    $agentId = $this->resolveAgentId();
    if (!$agentId) {
        return '<div class="alert alert-warning">Agent profile not found. Contact administrator.</div>';
    }

    $classes = $this->getClassesForAgent($agentId);

    return wecoza_view('agents/attendance/agent-attendance', [
        'classes'  => $classes,
        'agentId'  => $agentId,
    ], true);
}
```

### ensureAttendancePage (mirrors ClassController pattern)

```php
// Same transient-guarded pattern as ClassController::ensureRequiredPages()
public function ensureAttendancePage(): void
{
    if (get_transient('wecoza_agent_attendance_page_checked')) {
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
```

### Allowed Pages for wp_agent (template_redirect guard)

```php
// Source: WordPress Codex — template_redirect hook
add_action('template_redirect', function (): void {
    if (!is_user_logged_in()) {
        return;
    }
    $user = wp_get_current_user();
    if (!in_array('wp_agent', $user->roles, true)) {
        return;
    }

    // Allowlist: attendance page + single class view (for capture)
    $allowedSlugs = ['agent-attendance'];
    $singleClassPage = get_page_by_path('app/display-single-class');

    if (is_page($allowedSlugs)) {
        return;
    }
    if ($singleClassPage && is_page($singleClassPage->ID)) {
        return;
    }

    wp_redirect(home_url('/agent-attendance/'));
    exit;
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `login_redirect` sends all users to home | Role-aware `login_redirect` with priority 9 | Phase 55 | Agents land on attendance page, not home |
| No admin guard | `admin_init` redirect for wp_agent (AJAX-safe) | Phase 55 | Agents cannot access WP dashboard |
| All pages accessible | `template_redirect` allowlist for wp_agent | Phase 55 | Navigation cage enforced |

---

## Open Questions

1. **Should backup agent class access be date-gated?**
   - What we know: `backup_agent_ids` has a `date` field (e.g. `{"date": "2026-03-31", "agent_id": 5}`)
   - What's unclear: Should the backup agent only see a class if today >= that date? Or always?
   - Recommendation: For MVP, show all classes where agent appears as backup regardless of date. Date-gating is a complexity multiplier — defer unless Mario specifies.

2. **Single-class page allowed for ALL agent's classes?**
   - What we know: `template_redirect` guard must allow `/app/display-single-class/`
   - What's unclear: Should we verify the requested `class_id` belongs to the agent, or just allow the page broadly?
   - Recommendation: Allow the page broadly for `wp_agent` users. The attendance capture itself is scoped to the class data already loaded. Adding class ownership check is defensive but may over-engineer.

3. **Attendance page page template**
   - What we know: All WeCoza pages use the Dashboard-Template or default WP template
   - What's unclear: Should agent attendance page use `dashboard-template.php` (includes full nav) or a minimal template?
   - Recommendation: Use the standard WP page (no custom template) but suppress nav via CSS or the `template_redirect` page guard already stops navigation. If the existing theme shows a nav sidebar, the guard handles it — agents can't use those links without being redirected back.

---

## Sources

### Primary (HIGH confidence)
- Live PostgreSQL database — JSONB `@>` query tested and confirmed working 2026-03-04
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Agents/Repositories/AgentRepository.php` — `findByWpUserId()` confirmed at line 269
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Classes/Models/ClassModel.php` — `backup_agent_ids` is parsed JSON array
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/functions/helper.php` line 401 — existing `login_redirect` filter at priority 10
- WordPress Codex: `admin_init`, `template_redirect`, `login_redirect` hooks

### Secondary (MEDIUM confidence)
- Pattern extrapolated from `ClassController::ensureRequiredPages()` — same transient-guard approach confirmed in code

### Tertiary (LOW confidence)
- Backup agent date-gating behavior — no existing logic found; assumption is show all

---

## Metadata

**Confidence breakdown:**
- Class lookup (JSONB query): HIGH — tested against live DB
- Login redirect hook: HIGH — existing theme code confirmed, priority strategy well understood
- admin_init AJAX exemption: HIGH — `wp_doing_ajax()` is standard WP pattern
- template_redirect allowlist: HIGH — standard WP hook, logic is simple
- Page auto-creation pattern: HIGH — mirrors existing ClassController code exactly
- Backup agent date-gating: LOW — no business rule confirmed; open question raised

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable domain — WP hooks + existing schema)
