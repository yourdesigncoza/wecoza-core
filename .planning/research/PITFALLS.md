# Pitfalls Research

**Domain:** WordPress plugin — agent-restricted access, attendance UX modification, class status gate logic
**Researched:** 2026-03-04
**Confidence:** HIGH — analysis based directly on the production codebase, existing AJAX architecture, and planned feature todos

---

## Critical Pitfalls

### Pitfall 1: wp_ajax_nopriv_ Gap on Agent AJAX Endpoints

**What goes wrong:**
All 5 existing attendance AJAX handlers are registered exclusively with `wp_ajax_` (WordPress-logged-in users only). The current project policy states "site requires login — no nopriv actions." When agents become WordPress users via the `wecoza_agent` role, this is fine. But if ANY agent AJAX endpoint is accidentally registered with `wp_ajax_nopriv_` during development (to "test quickly"), it exposes the full attendance write API to unauthenticated internet traffic. The nonce system then becomes the only barrier — and nonces for WP nopriv actions are predictable.

**Why it happens:**
Developer registers `wp_ajax_nopriv_wecoza_attendance_capture` during testing to bypass login, forgets to remove it, ships it. The attendance capture endpoint would then accept writes from anyone who can guess or steal a nonce.

**How to avoid:**
Never register any attendance or agent AJAX endpoint with `wp_ajax_nopriv_`. Agents must authenticate as WP users via the `wecoza_agent` role before any AJAX is available. Enforce at code review: search for `wp_ajax_nopriv_` in any attendance or agent file before merge.

**Warning signs:**
- Any `add_action('wp_ajax_nopriv_wecoza_attendance`, ... )` in diff
- AJAX calls succeeding when logged out in browser testing
- `AttendanceAjaxHandlers.php` or a new `AgentAttendanceAjax.php` containing `nopriv`

**Phase to address:**
Phase implementing the `wecoza_agent` role and login redirect. Confirm that the redirect fires before agents could ever reach AJAX endpoints without a session.

---

### Pitfall 2: Capability Check Missing on Attendance Capture — Any WP User Can Write

**What goes wrong:**
The existing `handle_attendance_capture()` only calls `verify_attendance_nonce()`. It does NOT check `current_user_can('capture_attendance')`. This was acceptable when "any logged-in user can capture" was the decision (v6.0). In v7.0, the intent is to restrict capture to agents and admins. If the capability check is added to the new agent shortcode's front-end but NOT added to the server-side AJAX handler, any logged-in WP user (subscriber, editor, etc.) can still POST directly to `wp_ajax_wecoza_attendance_capture` and write sessions.

**Why it happens:**
Capability checks are added to the UI (shortcode visibility gate) but the matching server-side AJAX guard is overlooked. The shortcode hides the button — the developer assumes "it's hidden, so it's protected."

**How to avoid:**
Add `current_user_can('capture_attendance')` inside `handle_attendance_capture()` and `handle_attendance_mark_exception()`. UI hiding is not a security boundary — the AJAX handler is the boundary. The v7.0 phase must update both AJAX handlers when the capability is introduced.

**Warning signs:**
- New `capture_attendance` capability is registered but attendance AJAX handlers still have no capability check
- Shortcode correctly gate-checks capability but handler file is unchanged from v6.0

**Phase to address:**
The `wecoza_agent` role and `capture_attendance` capability registration phase. Capability check must be added to handlers in the same phase — not deferred.

---

### Pitfall 3: `wecoza_agent` Role and `capture_attendance` Capability Not Registered on Plugin Update (Only on Activation)

**What goes wrong:**
WordPress roles and capabilities registered inside `register_activation_hook()` run once at plugin activation. If the site already has the plugin active (v6.0 is active) and the developer deploys v7.0 without deactivating/reactivating, the `wecoza_agent` role never gets added. Agents cannot log in. The shortcode shows nothing. The failure is silent — no PHP error.

**Why it happens:**
Developers correctly place role registration in the activation hook for new installs. Existing installations that do an update (FTP file upload, or plugin update) never trigger activation. The role simply doesn't exist.

**How to avoid:**
Register the role and capability during both `register_activation_hook` AND an `admin_init` / `plugins_loaded` guard check: `if (!get_role('wecoza_agent')) { add_role(...) }`. The activation hook handles fresh installs; the guard handles updates on existing sites. The guard is idempotent — safe to run on every request.

**Warning signs:**
- After deployment: logging in as a new user with `wecoza_agent` role fails with "you do not have permission"
- `get_role('wecoza_agent')` returns null on an existing site after deployment

**Phase to address:**
Role registration phase. Must include update-path guard, not just activation hook.

---

### Pitfall 4: `wp_user_id` Column on Agents Table Breaks Existing Agent Queries

**What goes wrong:**
`ALTER TABLE agents ADD COLUMN wp_user_id INTEGER` is a safe non-breaking PostgreSQL change — nullable column with no default. However, the existing `AgentRepository` and `AgentModel` have `getAllowedInsertColumns()` and `getAllowedUpdateColumns()` whitelists. If `wp_user_id` is added to the DB schema but NOT added to the column whitelists, agent save/update operations silently drop the value. If it IS added to the whitelists but the agent admin forms don't include it as a field, mass-assignment paths could expose the column to unexpected writes.

**Why it happens:**
Schema migration and PHP code are updated independently. The column whitelist in `AgentModel`/`AgentRepository` is forgotten. `wp_user_id` is set via a dedicated admin interface but the whitelist blocks it.

**How to avoid:**
After `ALTER TABLE`, explicitly add `wp_user_id` to `getAllowedUpdateColumns()` in `AgentRepository`. Add a dedicated "Link WP Account" UI path in the agent admin rather than exposing it through the general agent edit form, to avoid accidental overwrite via form submission.

**Warning signs:**
- Saving an agent with `wp_user_id` set results in null in the DB despite the save succeeding
- No exception thrown — column whitelist silently drops unrecognized columns

**Phase to address:**
Schema migration phase. Column whitelist update must happen in the same commit as the DDL.

---

### Pitfall 5: Stopped-Class Session Generation Cuts Off at `today` — Backdating Blocked for Stopped Classes

**What goes wrong:**
`AttendanceService::generateSessionList()` builds sessions "up to today" when no end date is present: `$endDate = new DateTime('today', $tz)`. For a stopped class where the stop date is in the past, `generateSessionList()` correctly includes all sessions up to today. BUT if the last stop date is in the past AND the class had an `endDate` in `schedule_data`, the end date cap may be earlier than today, which is correct. The hidden trap: the AJAX `require_active_class()` guard already accepts stopped-class writes if `session_date <= stop_date` — but only passes the `session_date` from POST. If the JS disables capture buttons for dates after stop date, but the stop date extraction logic (`wecoza_get_effective_stop_date`) fails silently (returns null on malformed JSONB), the gate falls through to the 403 rejection, blocking valid captures.

**Why it happens:**
`wecoza_get_effective_stop_date()` reads from `stop_restart_dates` inside `schedule_data` JSONB. If the JSONB key name differs between old and new class records (e.g., `stopRestartDates` vs `stop_restart_dates` camelCase/snake_case mismatch — the same pattern that required normalization in `generateSessionList`), the function returns null and all stopped-class captures are rejected as if after stop date.

**How to avoid:**
`wecoza_get_effective_stop_date()` must normalize both camelCase and snake_case key variants, mirroring the pattern already used in `generateSessionList` for `perDayTimes`/`perDay` etc. Add defensive null handling — log when stop date is unresolvable. Test with both old-format and new-format schedule_data fixtures.

**Warning signs:**
- Stopped class: capture button available in UI (JS sees stop date correctly) but AJAX returns 403
- `wecoza_get_effective_stop_date()` returning null for valid stopped classes in debug log

**Phase to address:**
Stopped-class capture phase. Add key normalization to `wecoza_get_effective_stop_date()` as first task before any UI changes.

---

### Pitfall 6: Minimal Agent Page Template — CSS/JS Assets Loading Global Styles It Shouldn't

**What goes wrong:**
The planned `views/templates/agent-minimal.php` is a bare-bones page shell. If it calls `wp_head()` without suppressing the full theme stylesheet queue, agents will load the entire WeCoza admin theme CSS/JS — including navigation, sidebar, and dashboard components. Beyond unnecessary payload, the theme CSS may expose navigation elements that the template intends to hide, or clash with the minimal layout.

**Why it happens:**
`wp_head()` outputs all enqueued styles/scripts. Plugin-registered templates still run inside WordPress's full request cycle. Dequeuing specific handles is tedious. Developers output `wp_head()` without specifying what to suppress, assuming the minimal template HTML will "hide" unwanted elements visually.

**How to avoid:**
In the agent minimal template, hook into `wp_enqueue_scripts` with priority 999 and dequeue all theme stylesheets except `ydcoza-styles` and any attendance-specific scripts. Only enqueue: `wecoza-attendance-capture` JS and its dependencies. Use `remove_action('wp_head', 'print_emoji_detection_script')` and similar to strip clutter. Test with browser devtools to verify no admin CSS loads.

**Warning signs:**
- Agent page shows navigation bar or footer even though template HTML omits them
- Page source contains `<link>` tags for theme-admin.css or dashboard styles
- Attendance JS throws errors because admin-only `window.WeCozaAdmin` config is expected but absent

**Phase to address:**
Agent minimal template phase. Asset pruning must be part of the template implementation task, not a follow-up cleanup.

---

### Pitfall 7: Agent Login Redirect Conflicts with WP Admin Area Access for Admins

**What goes wrong:**
Redirecting `wecoza_agent` role users to `/agent-attendance/` on login is correct. The redirect hook is typically `login_redirect` or `wp_login`. If the redirect is implemented as "always redirect to attendance page on login" without role-checking, it will redirect admins too. If it's role-checked but the check is `!current_user_can('manage_options')`, any future role added to the system (e.g., `wecoza_manager`) that also lacks `manage_options` will be incorrectly redirected to the agent page.

**Why it happens:**
Redirect logic uses a negative capability check ("if not admin, go to attendance") rather than a positive role check ("if wecoza_agent, go to attendance").

**How to avoid:**
The redirect condition must be a positive role check: `if (in_array('wecoza_agent', (array) $user->roles))`. Do not infer agent status from missing capabilities. This is also future-proof for new roles.

**Warning signs:**
- Admin users redirected to agent attendance page after login
- New `wecoza_manager` role users (future) incorrectly land on agent page

**Phase to address:**
Login redirect implementation phase. Use explicit `in_array('wecoza_agent', $user->roles)` check.

---

### Pitfall 8: Attendance JS `calendarMonth` vs `currentMonth` State Drift After v7.0 Changes

**What goes wrong:**
`attendance-capture.js` now maintains two parallel month state variables: `currentMonth` (month filter select) and `calendarMonth` (calendar grid). These are synced via the month select change handler and the calendar prev/next buttons. The v7.0 agent shortcode will embed the same attendance JS and UI. If the agent shortcode's page uses a different container ID (e.g., `#agent-attendance-sessions-tbody` instead of `#attendance-sessions-tbody`), the JS will silently fail to find its elements and the calendar/table will not render — with no error because jQuery selectors return empty objects on miss.

**Why it happens:**
Attendance JS was built for a specific DOM structure on the single class display page. When reused on the agent shortcode page, the IDs and config object differ, causing silent rendering failures.

**How to avoid:**
The agent shortcode view must use identical element IDs and inject the same `window.WeCozaSingleClass` config object that `attendance-capture.js` expects. Do not create parallel JS for the agent view — reuse existing JS with identical DOM contracts. Verify this explicitly: load the agent page, open devtools console, confirm no "Cannot read properties of undefined" errors.

**Warning signs:**
- Agent attendance page loads but session table is empty with no error message
- No JS console errors (jQuery silently skips missing elements)
- `config.classId` is undefined (WeCozaSingleClass not injected by agent shortcode PHP)

**Phase to address:**
Agent attendance shortcode implementation phase. Checklist item: verify `window.WeCozaSingleClass` is injected with correct `classId`, `ajaxUrl`, `attendanceNonce`, `learnerIds`, `isAdmin` keys.

---

### Pitfall 9: Exception Button Restyle Breaking Existing JS Event Bindings

**What goes wrong:**
The current exception trigger is an icon element with a specific selector. `attendance-capture.js` `bindEvents()` attaches click handlers using specific CSS class selectors (e.g., `.btn-exception` or the current icon class). Restyling the exception trigger to a labelled button changes the element type and potentially the CSS class. If the existing click handler selector is not updated to match the new button's class/ID, the exception modal never opens. This is an easy miss because the visual appears correct (button is visible) but the click does nothing.

**Why it happens:**
PHP/view template is updated with new button markup. The JS `bindEvents()` selector is not updated to match. No error is thrown — event never fires.

**How to avoid:**
When changing the exception button markup, update the JS selector in the same commit. Use `data-action="mark-exception"` data attribute rather than a CSS class as the event selector — data attributes are style-independent and clearly communicate intent. This decouples the visual styling from the JS binding.

**Warning signs:**
- Restyled exception button renders but clicking it does nothing
- No JS console error (event simply not bound)
- Old icon selector still present in `bindEvents()` after button restyle

**Phase to address:**
Exception button UX phase. Atomic change: view template + JS selector in same commit.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Separate agent AJAX endpoint (duplicate of existing) | Faster to build agent-only endpoint | Two copies of attendance logic to maintain; diverge over time | Never — reuse existing handlers with capability check |
| Store agent-WP-user link in WP usermeta only | No schema migration needed | Cross-DB lookup impossible (usermeta is MySQL, agents is PostgreSQL); can't JOIN agent classes | Never — `wp_user_id` on PostgreSQL agents table is required |
| Use page password protection for agent page | Zero code | Any WP user with the password gets in; not role-scoped; passwords shared | Never — role-based access is required |
| Skip admin bar suppression for `wecoza_agent` | Saves 5 minutes | Agent sees full WP admin bar with Settings, Edit links — confusing and potential back-door | Never for v7.0 — `show_admin_bar(false)` for agent role is mandatory |
| Hardcode agent class list for testing | Quick to verify attendance renders | Hardcoded data shipped accidentally; real agent sees all classes or wrong class | Never — always use `wp_user_id → agent_id → classes` lookup |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| WordPress roles + PostgreSQL agents | Storing role state only in PostgreSQL; forgetting WP side | `wecoza_agent` WP role controls login/sessions; `wp_user_id` on agents table cross-references them — both required |
| Nonce generation for agent shortcode | Generating nonce in PHP for `wecoza_attendance_nonce` action at wrong scope | Nonce must be generated by `wp_create_nonce('wecoza_attendance_nonce')` when the agent user is logged in — nonces are user-session-tied |
| Plugin page template registration | Placing template PHP file in theme directory | Register via `theme_page_templates` filter from plugin; file lives in `views/templates/` — not theme-dependent |
| Stopped-class stop date (JSONB) | Reading `stop_restart_dates` as top-level JSONB key | Key lives inside `schedule_data` JSONB column; must parse `schedule_data` first, then read `stop_restart_dates` within it |
| Cross-DB user lookup (MySQL wp_users + PostgreSQL agents) | Trying to JOIN the two databases | Resolve WP user ID via `get_current_user_id()`, then query PostgreSQL `agents WHERE wp_user_id = $wpUserId` — no cross-DB JOIN possible |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| `generateSessionList()` called twice per AJAX request | Slow capture response; double schedule generation | Existing `getValidatedScheduledHours()` already consolidates — do not add second call in agent code path | Noticeable at 3+ years of weekly schedules (200+ sessions) |
| Agent class lookup queries N+1 on agent dashboard | Dashboard slow if agent has many classes | Single query: `SELECT * FROM classes WHERE class_agent = $agentId OR backup_agent_ids @> $agentIdJson` with one JSONB `@>` operator | Noticeable at 10+ classes per agent |
| Public holidays queried per session-list call without caching | Holiday API called 2-4x per page load | `PublicHolidaysController::getHolidaysByYear()` already called per year in generateSessionList — confirm caching exists or add per-request cache | Noticeable with multi-year classes spanning 3+ years |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Agent role has `edit_posts` or `publish_pages` capability | Agent can create/edit WP content, affecting site | `wecoza_agent` role: ONLY `read` + `capture_attendance`. No content capabilities. |
| Linking WP user to agent via untrusted POST parameter | Attacker POSTs arbitrary `wp_user_id` to link admin WP account to their agent | `wp_user_id` link must only be settable by `manage_options` admin via dedicated admin form — never from agent-facing endpoint |
| Attendance nonce generated for guest then reused after login | Nonces are user-session-bound; guest nonce invalid for logged-in user | Always regenerate nonce after login; agent page must output fresh nonce in PHP after session is established |
| `capture_attendance` capability on `wecoza_agent` role but not granted to admins | Admins lose ability to capture attendance via admin panel | When registering `capture_attendance`, explicitly add it to `administrator` role via `get_role('administrator')->add_cap('capture_attendance')` |
| Agent class filter bypassed by sending arbitrary `class_id` | Agent captures for a class they are not assigned to | Server-side: after capability check, verify `class_agent = wecoza_agent_id` or `backup_agent_ids @> [agent_id]` before allowing capture |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Icon-only exception trigger | Agent doesn't recognize it as a button; exceptions never filed | Labelled button: "Mark Exception" with a warning icon — text makes intent unambiguous |
| Agent sees ALL classes, not just their assigned ones | Overwhelming; confusion about which class to capture | Filter class list to `class_agent = agent_id` OR `backup_agent_ids` contains agent; show class name + date prominently |
| No feedback after successful attendance capture on agent page | Agent re-submits; duplicate capture attempt (blocked by "already captured" guard, but confusing) | Show success message with session date and learner count after capture; disable submit button immediately on click |
| Stopped class shows "Attendance locked" without explanation | Agent confused why they can't capture a class they just stopped | When class is stopped, show "Capture allowed until [stop date]" message with remaining capturable dates |
| Agent page has no logout button | Agent leaves browser open; next person captures on wrong account | Minimal template must include clearly visible logout link — per Mario's quote "they must not be able to go to any other page" |

---

## "Looks Done But Isn't" Checklist

- [ ] **`wecoza_agent` role registration:** Often missing update-path guard — verify `get_role('wecoza_agent')` is non-null on an existing v6.0 site after deployment without deactivation
- [ ] **`capture_attendance` capability on admins:** Often added to `wecoza_agent` but forgotten on `administrator` — verify admin can still capture via `current_user_can('capture_attendance')`
- [ ] **`wp_user_id` column whitelist:** Column added to DB schema but not `getAllowedUpdateColumns()` — verify saving an agent with `wp_user_id` set persists the value
- [ ] **AJAX capability guard:** `capture_attendance` check added to shortcode visibility but not to `handle_attendance_capture()` handler — verify POST to AJAX endpoint without shortcode fails with 403
- [ ] **Agent class filter:** Shortcode renders without server-side class ownership check — verify agent cannot capture for classes they are not `class_agent` or `backup_agent_ids` on
- [ ] **Exception button JS binding:** Button restyled in PHP but selector not updated in `bindEvents()` — verify clicking new button opens exception modal
- [ ] **Stopped-class stop date extraction:** `wecoza_get_effective_stop_date()` handles both camelCase and snake_case keys — verify with old-format and new-format schedule_data
- [ ] **Login redirect role check:** Redirect uses positive `in_array('wecoza_agent', $roles)` not negative capability check — verify admins land on WP admin, not agent page
- [ ] **Agent minimal template asset pruning:** Theme nav/footer CSS not loaded on agent page — verify with devtools Network tab: no admin theme CSS files present
- [ ] **Nonce injection on agent shortcode page:** `window.WeCozaSingleClass.attendanceNonce` present and valid on agent page — verify AJAX attendance call succeeds without 403

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| `wecoza_agent` role never registered (update without reactivation) | LOW | Add `plugins_loaded` guard check; run once on next page load; no data loss |
| `wp_user_id` column whitelist missing — saves silently dropping value | LOW | Add to whitelist; existing NULL rows; re-link agents via admin UI |
| `wp_ajax_nopriv_` accidentally added to attendance endpoint | HIGH | Immediate: remove handler registration; audit server logs for unauthorized writes; invalidate any captured sessions from unauthenticated requests |
| Agent capability missing on administrator — admins locked out of capture | LOW | `get_role('administrator')->add_cap('capture_attendance')` — one-time fix; no data loss |
| Stopped-class stop date extraction returning null — all stopped-class captures blocked | MEDIUM | Fix `wecoza_get_effective_stop_date()` normalization; no data corrupted; agents couldn't write, so no reversal needed |
| Agent sees wrong classes (no ownership filter) — captured wrong class | HIGH | Audit `class_attendance_sessions` for sessions captured by agent on non-assigned classes; delete and reverse via admin delete; add ownership check to handler |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| `wp_ajax_nopriv_` exposure | `wecoza_agent` role + AJAX guard phase | Search codebase for `nopriv` in attendance files; confirm absent |
| Missing `capture_attendance` capability check on AJAX handler | Same phase as capability registration | POST to AJAX as subscriber user; confirm 403 |
| Role not registered on update path | Role registration phase | Deploy to existing v6.0 clone without deactivating; verify role exists |
| `wp_user_id` column whitelist gap | Schema migration phase | Save agent with `wp_user_id`; verify DB value persists |
| Stopped-class JSONB key normalization | Stopped-class capture phase, first task | Test with old and new schedule_data formats; verify stop date resolves |
| Minimal template asset overloading | Agent template phase | Devtools network audit — no admin theme CSS on agent page |
| Login redirect role-check accuracy | Login redirect phase | Login as admin; confirm admin dashboard loads, not agent page |
| Attendance JS state drift on agent page | Agent shortcode phase | Verify `window.WeCozaSingleClass` injected; open devtools; no undefined errors |
| Exception button JS binding after restyle | Exception button UX phase | Click restyled button; confirm exception modal opens |
| Agent class ownership filter missing | Agent shortcode phase | Verify agent cannot capture class not assigned to them via direct AJAX POST |

---

## Sources

- Codebase: `src/Classes/Ajax/AttendanceAjaxHandlers.php` — existing nonce/auth model
- Codebase: `src/Classes/Ajax/ClassStatusAjaxHandler.php` — stopped-class logic, `require_active_class()`
- Codebase: `src/Classes/Services/AttendanceService.php` — `generateSessionList()`, stop date handling
- Codebase: `assets/js/classes/attendance-capture.js` — JS state management, event binding patterns
- Codebase: `core/Helpers/AjaxSecurity.php` — capability check patterns
- Planning: `.planning/todos/pending/2026-03-04-wec-182-agent-restricted-attendance-capture-page.md`
- Planning: `.planning/todos/pending/2026-03-04-wec-182-stopped-class-capture-until-stop-date.md`
- Planning: `.planning/todos/pending/2026-03-04-wec-182-agent-absent-client-cancelled-ux.md`
- Project context: `.planning/PROJECT.md` — v6.0 decisions, known issues, architecture constraints

---
*Pitfalls research for: WeCoza Core v7.0 — agent-restricted attendance, exception UX, stopped-class capture*
*Researched: 2026-03-04*
