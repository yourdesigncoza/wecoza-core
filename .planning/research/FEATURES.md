# Feature Research

**Domain:** Attendance capture UX — agent-restricted page, exception button visibility, stopped-class capture window
**Researched:** 2026-03-04
**Confidence:** HIGH (all requirements derived directly from Mario's quotes, existing codebase, and established WP patterns)

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features agents and managers assume exist. Missing = the feature feels broken or unusable.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Agent sees only their assigned classes | An agent capturing attendance for classes they don't teach is a data-integrity failure; access control is assumed in any multi-user system | MEDIUM | Requires `wp_user_id` on `agents` table + query filter by agent; primary and backup agent IDs both apply (`class_agent` + `backup_agent_ids` JSONB) |
| Exception button is obviously a button | Agents are non-technical end users; a triangle icon with no label reads as a status indicator, not an action — Mario confirmed this confusion directly | LOW | CSS/HTML change only; existing AJAX and modal stay identical; label "Report Exception" or "Mark Exception" |
| Stopped class allows capture up to stop date | Mario stated: "The stop date will always be on a class day, so that will be the last day for capturing." Users expect the system to honour the real-world stop date, not block retroactively | LOW | Stop date is already in `schedule_data.stop_restart_dates`; `wecoza_get_effective_stop_date()` already exists; JS needs to pass stop date through to disable buttons |
| Agent login redirects to attendance page | Agents must not land in WP admin or the full WeCoza nav; redirect on login is a standard pattern for restricted roles | LOW | WordPress `login_redirect` filter; simple role check |
| Admin bar hidden for agent role | WP admin bar leaks access to site navigation that agents must not see | LOW | `show_admin_bar` filter; role check for `wecoza_agent` |
| Minimal page template (no nav, no sidebar) | An agent landing on a page with full WeCoza navigation defeats the access restriction; bare-bones shell is the expected pattern for single-purpose portals | MEDIUM | Plugin-registered page template via `theme_page_templates` filter; no theme dependency |
| Shortcode gates on `capture_attendance` capability | Agents must not be able to access admin views (class management, client lists, learner PII) just by navigating to a URL; server-side capability gate is the expected pattern | LOW | `current_user_can('capture_attendance')` check at shortcode render time |
| Server-side class-status guard for stopped classes | JS stop-date enforcement is UX only; server must enforce the same rule so a crafted POST cannot capture past the stop date | LOW | Already partially implemented in `require_active_class()` — the stopped-class exception branch exists; needs `wecoza_get_effective_stop_date()` called when `session_date` is provided |

### Differentiators (Competitive Advantage)

Features that go beyond base expectations — valuable but not assumed.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Agent sees backup classes too | An agent assigned as backup (`backup_agent_ids`) expects to capture when covering — listing only primary classes would silently drop sessions | LOW | `backup_agent_ids` is already a JSONB column on `classes`; query needs `OR classes.backup_agent_ids @> :agent_id_json` |
| Reuses existing capture UI components | No duplication of the attendance modal, exception modal, or AJAX JS — agent page renders the same components the admin sees | LOW | Pass `classId` into existing `window.WeCozaSingleClass` config; attendance-capture.js works as-is |
| Graceful degradation if agent has no WP account yet | Admin may create the `wecoza_agent` WP user before linking it to the `agents` row — the shortcode should show a clear "not linked" message rather than an empty screen | LOW | `wp_user_id` lookup fails gracefully; render a notice instead of crashing |
| Stop date shown in UI as information | When a class is stopped, showing the stop date next to the lock status helps agents understand why future sessions are disabled | LOW | Pass `stopDate` from PHP into `window.WeCozaSingleClass`; already done in `attendance.php` |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Full WP user registration flow for agents | Agents need WP accounts; seems natural to expose a self-registration form | Agents are managed by WeCoza admins; self-registration bypasses the agents table linkage, creates orphaned WP users with no `wp_user_id` mapping, and opens the site to account spam | Admin creates WP user + links `wp_user_id` manually via agent edit form — one controlled operation |
| Real-time session locking when multiple agents cover | Two agents theoretically capturing the same class at the same time | The existing `createOrUpdateSession()` already rejects non-pending sessions with "Session already captured" — optimistic locking via status check is sufficient; full pessimistic row locking adds complexity with no practical benefit at this scale | Keep current status-check approach |
| Agent-specific dashboard with charts/stats | Agents might want to see their own progress across all classes | Out of scope for this milestone; adds a reporting surface that agents haven't asked for and Mario hasn't mentioned | Keep the page to capture-only; statistics live in the admin views |
| Biometric or photo attendance verification | Some attendance systems use selfies or fingerprints | Completely out of scope for a text-based web portal; adds infrastructure complexity with no business requirement | Text-based per-learner hours entry as currently implemented |
| Email notifications when agent submits attendance | Notify admin when a session is captured | Not requested by Mario; adds noise to the notification system and creates dependency on email deliverability for a routine operation | Admin reviews captured sessions via the existing session list |

---

## Feature Dependencies

```
[wecoza_agent WP Role]
    └──required-by──> [agent-restricted page access]
                          └──required-by──> [login redirect to attendance page]
                          └──required-by──> [admin bar hidden]

[wp_user_id column on agents table]
    └──required-by──> [agent-class lookup query]
                          └──required-by──> [agent attendance shortcode]

[agent attendance shortcode]
    └──reuses──> [attendance-capture.js]
    └──reuses──> [AttendanceService + AJAX handlers]
    └──reuses──> [exception modal]

[exception button UX fix]
    └──independent of──> [agent page]  (can ship separately)

[stopped-class stop date in JS]
    └──required-by──> [capture buttons disabled after stop date]
    └──independent of──> [agent page]  (can ship separately)

[server-side stopped-class guard]
    └──already-exists-partially──> [require_active_class() in AttendanceAjaxHandlers.php]
    └──required-by──> [capture buttons disabled after stop date]  (defence in depth)
```

### Dependency Notes

- **`wecoza_agent` role requires no schema change** — WordPress role registration via `register_role()` on plugin activation hook, capabilities granted via `add_cap()`.
- **`wp_user_id` on agents requires a schema change** — `ALTER TABLE agents ADD COLUMN wp_user_id INTEGER` with unique partial index. User runs this SQL manually per project policy.
- **Agent shortcode requires role + schema** — both must exist before the shortcode is meaningful. Sequence: schema → role → shortcode.
- **Exception button is independent** — purely a view/CSS change; ships at any point without waiting for agent page work.
- **Stopped-class JS gate is independent** — `stopDate` is already passed from PHP; JS needs to use it to disable buttons. Ships independently of agent page.
- **Server-side stopped-class guard already partially exists** — `require_active_class()` has the stopped-class exception branch. It just needs `wecoza_get_effective_stop_date()` wired in when `session_date` is present in the request.

---

## MVP Definition

This is a subsequent milestone on a shipped system. "MVP" here means the minimum that completes the three WEC-182 deliverables Mario raised.

### Launch With (v7.0)

- [x] `wecoza_agent` WordPress role with `read` + `capture_attendance` capabilities — role registration on activation hook
- [x] `wp_user_id` column on `agents` table — schema change SQL for manual execution
- [x] Minimal page template (`views/templates/agent-minimal.php`) — registered via `theme_page_templates` filter, no theme dependency
- [x] `[wecoza_agent_attendance_management]` shortcode — lists agent's classes (primary + backup), reuses existing attendance UI
- [x] Login redirect for `wecoza_agent` role — `login_redirect` filter, routes to `/agent-attendance/` or configured URL
- [x] Admin bar hidden for `wecoza_agent` role — `show_admin_bar` filter
- [x] Exception button — visible labelled button replacing the icon-only triangle in `attendance.php` and the session table row actions
- [x] Stopped-class JS gate — `stopDate` passed into `window.WeCozaSingleClass`; JS disables capture buttons for sessions after stop date

### Add After Validation (v7.x)

- [ ] Agent edit form: `wp_user_id` link field — admin links WP user to agent record in UI rather than DB console; trigger: first support request about linking
- [ ] `view_own_schedule` capability for future agent features (read-only class detail) — trigger: Mario requests it

### Future Consideration (v8+)

- [ ] Agent self-service profile update — trigger: agent volume growth and admin overhead
- [ ] Agent attendance history report — trigger: explicit Mario request

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Exception button UX fix | HIGH — Mario explicitly confused by current state | LOW — CSS/HTML only | P1 |
| Stopped-class capture until stop date (JS) | HIGH — prevents agents being blocked from valid sessions | LOW — stop date already in PHP, JS needs to use it | P1 |
| Stopped-class server-side guard | HIGH — security depth; guards against crafted POSTs | LOW — `require_active_class()` already has the branch | P1 |
| `wecoza_agent` WP role | HIGH — prerequisite for agent page | LOW — `register_role()` on activation | P1 |
| `wp_user_id` schema column | HIGH — prerequisite for agent-class lookup | LOW — one ALTER TABLE + index | P1 |
| Minimal page template | HIGH — agents need isolated context | MEDIUM — plugin-registered template, test across themes | P1 |
| Agent attendance shortcode | HIGH — core deliverable of the milestone | MEDIUM — new shortcode + view, reuses AJAX | P1 |
| Login redirect for agent role | HIGH — agents must not reach WP admin | LOW — one filter | P1 |
| Admin bar hidden for agent role | MEDIUM — UX polish, but not blocking | LOW — one filter | P1 |
| Agent backup class inclusion | MEDIUM — some classes have backup agents | LOW — extend query with JSONB check | P2 |
| Graceful "not linked" notice | MEDIUM — prevents blank screen on misconfiguration | LOW — null check + notice render | P2 |
| Agent edit form `wp_user_id` field | LOW — admin can set via DB for now | MEDIUM — form field, save/validate | P3 |

---

## Sources

- Mario's direct quotes (WEC-182, via `.planning/todos/pending/` files): confirmed agent confusion over exception triangle; confirmed stop date is always on a class day
- Existing codebase analysis: `AttendanceAjaxHandlers.php` — `require_active_class()` already has stopped-class exception branch using `wecoza_get_effective_stop_date()`
- Existing codebase analysis: `attendance.php` — `$stopDate` already extracted from `schedule_data` and available for JS pass-through
- WordPress standard patterns: `login_redirect` filter, `show_admin_bar` filter, `theme_page_templates` filter, `register_role()` — all HIGH confidence, established WP API
- Project decisions table (`PROJECT.md`): "Any logged-in user can capture — no agent-only restriction, simpler auth model" (v6.0 decision) — v7.0 intentionally revisits this per Mario's WEC-182 feedback

---

*Feature research for: WeCoza Core v7.0 — Agent Attendance Access*
*Researched: 2026-03-04*
