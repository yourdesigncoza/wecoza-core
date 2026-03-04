# Project Research Summary

**Project:** WeCoza Core v7.0 — Agent Attendance Portal (WEC-182)
**Domain:** WordPress plugin — agent-restricted attendance capture, exception UX improvement, stopped-class capture window
**Researched:** 2026-03-04
**Confidence:** HIGH

## Executive Summary

This milestone (v7.0) delivers three targeted improvements to the WeCoza attendance system, all sourced from Mario's direct feedback (WEC-182). The work falls into two independent tracks that can be phased separately: (1) two quick UX fixes — an icon-only exception button that agents don't recognise as actionable, and stopped-class capture buttons that should be active until the stop date but are currently gated by a global `isAttendanceLocked` flag — and (2) a new agent-restricted attendance page that lets field agents capture attendance without access to the broader WeCoza admin interface.

The recommended approach is entirely within the existing stack. No new libraries or frameworks are required. The agent page uses WordPress's native role/capability system (`wecoza_agent` role, `capture_attendance` cap), a plugin-registered minimal page template, and a new shortcode that reuses the existing `AttendanceService`, AJAX handlers, and `attendance-capture.js` without duplication. The only schema change required is `ALTER TABLE agents ADD COLUMN wp_user_id INTEGER` — a nullable column that links PostgreSQL agent records to WordPress user accounts. All other changes are PHP and JS within the existing plugin.

The key risks are security-related: missing server-side capability checks on AJAX handlers (UI-hiding is not a security boundary), accidentally exposing endpoints as `wp_ajax_nopriv_`, and `wecoza_agent` role not being registered on update paths (activation hook alone does not fire on plugin updates). These are all avoidable with the explicit patterns documented in the pitfall research. A secondary risk is JSONB key normalisation in `wecoza_get_effective_stop_date()` — if old and new `schedule_data` formats use different casing, stop-date extraction fails silently and blocks valid stopped-class captures. This must be addressed before any UI changes in the stopped-class track.

---

## Key Findings

### Recommended Stack

The entire implementation lives within the existing stack. WordPress core APIs (role system, capability checks, `login_redirect`, `show_admin_bar`, `theme_page_templates` filters), PHP 8.1+, PostgreSQL via `wecoza_db()`, and the existing `attendance-capture.js` are all that's needed. One new WP role (`wecoza_agent`), one new capability (`capture_attendance`), one new DB column, one plugin-registered page template, and one new shortcode cover the full scope.

Token-based agent auth (WP transients with URL tokens) was considered but rejected. The ARCHITECTURE.md researcher documented it as a viable approach, but STACK.md and PITFALLS.md converge on WP role + login as simpler, more secure, and consistent with the project's established policy that the entire site requires authentication. Using `wp_ajax_nopriv_` for agents would be an explicit violation of project policy and would reduce AJAX security to nonce-only.

**Core technologies:**
- **WordPress role/capability API** — `wecoza_agent` role + `capture_attendance` cap — native, no version risk, integrates with existing nonce validation
- **PHP 8.1+ / existing MVC architecture** — `AgentRepository::findByWpUserId()` (new method), `ClassRepository::getClassesByAgent()` (existing-ish) — zero new abstractions
- **PostgreSQL `agents` table** — `wp_user_id INTEGER` column (DDL, manual execution) — one-way lookup from WP session to agent_id to class list
- **Plugin-registered page template** — `views/templates/agent-minimal.php` via `theme_page_templates` filter — survives theme changes, plugin-owned

### Expected Features

**Must have (table stakes):**
- `wecoza_agent` WordPress role with `read` + `capture_attendance` capabilities — prerequisite for everything else
- `wp_user_id` column on `agents` table — required for agent-to-class lookup
- `[wecoza_agent_attendance_management]` shortcode — lists agent's primary + backup classes, reuses existing attendance UI
- Minimal page template with no WeCoza nav/sidebar — bare-bones shell; agents must not see other modules
- Login redirect to `/agent-attendance/` for `wecoza_agent` role — `login_redirect` WP filter, positive role check
- Admin bar hidden for `wecoza_agent` role — `show_admin_bar` filter
- Exception button — visible labelled button replacing icon-only triangle; text "Exception" or "Mark Exception"
- Stopped-class JS gate — `stopDate` passed to `window.WeCozaSingleClass`; JS disables capture buttons for sessions after stop date; fix `isAttendanceLocked` boolean in `ClassController.php`

**Should have (differentiators):**
- Backup class inclusion — query with `OR backup_agent_ids @> :agent_id_json` so agents covering as backup see those classes
- Graceful "not linked" notice when `wp_user_id` lookup finds no agent record — prevents blank screen on misconfiguration
- Stop date shown in UI — "Capture allowed until [date]" message when class is stopped

**Defer (v7.x / v8+):**
- Agent edit form `wp_user_id` field — admin can link via DB for now; build UI when first support request arrives
- Agent attendance history report — not requested by Mario
- Agent-specific dashboard with stats — out of scope; capture-only is sufficient

### Architecture Approach

The architecture for v7.0 adds a thin new layer (role + shortcode + minimal template) over the existing `AttendanceService` / `AttendanceAjaxHandlers` / `attendance-capture.js` stack. The ARCHITECTURE.md initially explored token-based auth with a new `AgentTokenService` and `AgentAttendanceAjaxHandlers` using `wp_ajax_nopriv_` — this approach was superseded by the WP role recommendation from STACK.md and PITFALLS.md. The correct build is: WP role registration → `wp_user_id` schema + whitelist → shortcode controller + minimal template → reuse existing AJAX handlers via `capture_attendance` capability check.

**Major components:**
1. **`AgentAccessController`** — registers `[wecoza_agent_attendance_management]` shortcode; validates `capture_attendance` cap; looks up agent via `wp_user_id`; renders view
2. **`AgentRepository::findByWpUserId()`** — new method; queries `agents WHERE wp_user_id = ?`; returns agent record
3. **`views/templates/agent-minimal.php`** — plugin-registered page template; strips theme nav/sidebar; enqueues only attendance assets
4. **Role/capability registration** — `wecoza_agent` role on `register_activation_hook` AND `plugins_loaded` guard (handles update paths)
5. **Modified `ClassController::enqueueAndLocalizeSingleClassScript()`** — fix `isAttendanceLocked` to be `false` for stopped classes with valid stop date; pass `stopDate` for per-session JS gate
6. **Modified `AttendanceAjaxHandlers`** — add `current_user_can('capture_attendance')` to `handle_attendance_capture()` and `handle_attendance_mark_exception()`

### Critical Pitfalls

1. **`wecoza_agent` role not registered on update path** — activation hook only fires on fresh install/reactivation; add idempotent guard in `plugins_loaded`: `if (!get_role('wecoza_agent')) { add_role(...) }`

2. **Missing `capture_attendance` capability check on AJAX handlers** — the shortcode hiding the UI is not a security boundary; `handle_attendance_capture()` and `handle_attendance_mark_exception()` must both call `current_user_can('capture_attendance')` server-side; same phase as capability registration, not deferred

3. **`isAttendanceLocked` as global gate blocks valid stopped-class captures** — `ClassController.php` line ~507 sets `isAttendanceLocked = ($classStatus !== 'active')`; stopped classes with a valid stop date must pass `isAttendanceLocked = false` so the per-session `stopDate` gate in `getActionButton()` can do its job; fix before any UI changes

4. **JSONB key normalisation failure in `wecoza_get_effective_stop_date()`** — if old `schedule_data` records use camelCase (`stopRestartDates`) and new records use snake_case (`stop_restart_dates`), the function silently returns null and all stopped-class captures return 403; normalise both key variants as first task in stopped-class phase

5. **Exception button JS binding broken after restyle** — `bindEvents()` in `attendance-capture.js` uses CSS class selectors for the exception trigger; if button markup changes (class or element type), click handler silently stops working; use `data-action="mark-exception"` as selector and update view + JS in the same commit

---

## Implications for Roadmap

Based on research, three independent work tracks with a clear dependency order:

### Phase 1: Quick Fixes — Exception Button UX + Stopped-Class Gate

**Rationale:** Both are independent of the agent page work, low-complexity, and directly address Mario's confirmed confusion. Exception button is CSS/HTML only. Stopped-class fix is ~5 lines in `ClassController.php` + JSONB normalisation in `wecoza_get_effective_stop_date()`. Ship these first to validate fixes before tackling the larger agent page work.

**Delivers:** Exception button is visibly labelled; stopped classes show capture buttons for valid sessions up to stop date.

**Addresses:**
- Exception button: change icon-only `<i>` trigger to labelled `<button>` with text; verify JS `bindEvents()` selector matches new markup; use `data-action="mark-exception"` for style independence
- Stopped-class gate: normalise `wecoza_get_effective_stop_date()` key variants first; fix `isAttendanceLocked` boolean in `ClassController`; verify JS per-session `stopDate` check works correctly

**Avoids:**
- Pitfall 5 (JSONB normalisation) — address as first task in this phase
- Pitfall 9 (exception button binding) — update view + JS selector in same commit

### Phase 2: Foundation — `wecoza_agent` Role + `wp_user_id` Schema

**Rationale:** Both are prerequisites for the agent shortcode. Neither delivers user-visible features alone, but they must be complete and verified before Phase 3. Role registration must include the update-path guard. Schema change is manual (user-executed SQL). Column whitelist in `AgentRepository` must be updated in the same phase.

**Delivers:** `wecoza_agent` WordPress role exists; `capture_attendance` capability is registered (on `wecoza_agent` AND `administrator` roles); `agents.wp_user_id` column in DB with unique partial index; `AgentRepository::findByWpUserId()` method; `capture_attendance` check added to `handle_attendance_capture()` and `handle_attendance_mark_exception()`.

**Addresses:**
- `wecoza_agent` role with `read` + `capture_attendance` only — no content caps
- `capture_attendance` on `administrator` role — admins must not lose capture ability

**Avoids:**
- Pitfall 3 (role not registered on update) — `plugins_loaded` guard, not activation-only
- Pitfall 4 (`wp_user_id` column whitelist gap) — update `getAllowedUpdateColumns()` in same commit as DDL
- Pitfall 2 (missing AJAX capability check) — add server-side cap check in same phase

**SQL for manual execution:**
```sql
ALTER TABLE agents ADD COLUMN wp_user_id INTEGER;
CREATE UNIQUE INDEX idx_agents_wp_user_id ON agents(wp_user_id) WHERE wp_user_id IS NOT NULL;
```

### Phase 3: Agent Attendance Page

**Rationale:** Depends on Phase 2 (role + schema). Builds the shortcode, minimal page template, and login redirect. Reuses existing `AttendanceService` and AJAX handlers — no new service logic needed. Agent page must render the same `window.WeCozaSingleClass` config that `attendance-capture.js` expects — identical DOM contracts are mandatory to avoid silent JS rendering failures.

**Delivers:** Agent logs in → redirected to attendance page → sees only their assigned classes (primary + backup) → captures attendance using existing UI → session written to DB with correct attribution.

**Addresses:**
- `[wecoza_agent_attendance_management]` shortcode (new controller + view)
- `views/templates/agent-minimal.php` (plugin-registered, theme-independent)
- `login_redirect` filter with positive role check (`in_array('wecoza_agent', $user->roles)`)
- `show_admin_bar` filter for `wecoza_agent` role
- Agent class lookup: `AgentRepository::findByWpUserId()` → `class_agent = $agentId OR backup_agent_ids @> $agentId::jsonb`
- Asset pruning on minimal template — no admin theme CSS on agent page

**Avoids:**
- Pitfall 1 (no `wp_ajax_nopriv_` on any attendance endpoint — agents are logged in as WP users)
- Pitfall 7 (login redirect uses positive role check, not negative capability check)
- Pitfall 6 (asset dequeuing on minimal template — verified via devtools)
- Pitfall 8 (JS state drift — `window.WeCozaSingleClass` injected with identical config keys)

### Phase Ordering Rationale

- Phase 1 ships independently — no schema or role dependencies; quickest value delivery
- Phase 2 must complete before Phase 3 because the shortcode requires the role (for capability checks) and the `wp_user_id` column (for class lookup)
- Stopped-class JSONB fix (Phase 1) should be verified against both old-format and new-format fixtures before Phase 3 ships — agent page will also render stopped classes

### Research Flags

Phases with well-documented patterns (skip deeper research):
- **Phase 1 (exception button):** Pure view + JS selector change; pattern is clear from codebase inspection
- **Phase 2 (role + schema):** WordPress role API is high-confidence core API; DDL is minimal
- **Phase 3 (agent page):** All components reuse established WP and plugin patterns

No phase requires external research. All implementation decisions are resolved in existing research.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All elements are native WP or already in codebase; no new dependencies; confirmed by direct codebase inspection |
| Features | HIGH | Requirements from Mario's direct quotes; codebase confirms existing hooks and partial implementations |
| Architecture | HIGH | Direct codebase inspection of all relevant files; token-auth branch of ARCHITECTURE.md superseded by WP-role approach from STACK.md + PITFALLS.md |
| Pitfalls | HIGH | Derived from production codebase analysis; specific line numbers cited; all pitfalls are concrete and preventable |

**Overall confidence:** HIGH

### Gaps to Address

- **ARCHITECTURE.md / STACK.md conflict on auth approach:** ARCHITECTURE.md proposed token-based auth (`AgentTokenService`, `wp_ajax_nopriv_`). STACK.md and PITFALLS.md reject this in favour of WP role + login. Roadmapper should use WP role approach. Token-based components (`AgentTokenService`, `AgentAttendanceAjaxHandlers`, `agent-attendance.js`) are NOT part of the implementation plan.

- **Exception button current rendering:** ARCHITECTURE.md notes that `getActionButton()` in `attendance-capture.js` line 473 already renders button text "Exception" — but a second render path (view-level or calendar click handler) may be icon-only. Requires a visual verification step before coding to confirm what actually needs changing.

- **`captured_by` attribution for agent sessions:** When an agent captures, `captureAttendance()` uses `get_current_user_id()` which will return the agent's WP user ID. This is fine — WP user ID is now stored on the agent record, so attribution is resolvable. No schema change required for `class_sessions`. ARCHITECTURE.md's suggestion of `captured_by_agent_id` column is optional; not required for v7.0.

---

## Sources

### Primary (HIGH confidence)

- Codebase: `src/Classes/Ajax/AttendanceAjaxHandlers.php` — `require_active_class()`, nonce/auth model, stopped-class exception branch
- Codebase: `src/Classes/Controllers/ClassController.php` lines 467–516 — `isAttendanceLocked` assignment, JS config injection
- Codebase: `assets/js/classes/attendance-capture.js` lines 458–489 — `getActionButton()`, `stopDate` per-session gate
- Codebase: `views/classes/components/single-class/attendance.php` — stop-date injection script tag, UI gate logic
- Codebase: `core/Helpers/functions.php` — `wecoza_get_effective_stop_date()`, `wecoza_resolve_class_status()`
- Codebase: `src/Classes/Repositories/ClassRepository.php` lines 528–529, 739–744 — `class_agent`, `backup_agent_ids` columns
- Codebase: `schema/wecoza_db_schema_bu_march_04.sql` — `agents` table confirmed; `wp_user_id` column absent
- WordPress Codex: `add_role()`, `add_cap()`, `login_redirect` filter, `theme_page_templates` filter — stable core API

### Secondary (MEDIUM confidence)

- `.planning/todos/pending/` WEC-182 todo files — Mario's direct quotes confirming exception button confusion and stop date behaviour

### Tertiary (LOW confidence)

- None — all findings are from primary codebase sources or official WordPress API documentation

---

*Research completed: 2026-03-04*
*Ready for roadmap: yes*
