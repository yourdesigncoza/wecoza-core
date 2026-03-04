# Stack Research

**Domain:** WordPress plugin — agent-restricted attendance portal, exception UX, stopped-class logic
**Researched:** 2026-03-04
**Confidence:** HIGH (all stack elements are native WordPress or already in the codebase)

---

## Context: What Is NEW vs What EXISTS

Three features are in scope. Stack implications vary significantly:

| Feature | New Stack Needed? |
|---------|------------------|
| Agent-restricted attendance page | YES — WP role, `wp_user_id` column, page template, shortcode |
| Exception button UX improvement | NO — pure HTML/CSS/JS change in existing files |
| Stopped-class capture until stop date | MINIMAL — server-side guard exists; JS gating needed in existing file |

---

## Recommended Stack

### Core Technologies (already present — no change)

| Technology | Version | Purpose | Why Confirmed |
|------------|---------|---------|---------------|
| WordPress | 6.0+ | Role/capability system, AJAX routing, page template hooks | Native WP role API is exactly what's needed for `wecoza_agent` role |
| PHP | 8.1+ | Plugin logic, service layer | Already in use; typed properties, match expressions used throughout |
| PostgreSQL | 14+ | `agents` table, `classes` table (class_agent, backup_agent_ids) | Both tables already have the columns needed; only `wp_user_id` is missing |
| PDO / `wecoza_db()` | — | Database access | Existing singleton; no new driver needed |

### New Elements Required

| Element | Type | Purpose | Why This Approach |
|---------|------|---------|------------------|
| `wecoza_agent` WordPress role | WP Core API | Minimal-capability role for agents who log in but see nothing else | Native `add_role()` + `register_activation_hook()` — no library needed; WP's capability system handles authorization cleanly |
| `capture_attendance` capability | WP Core API | Gate attendance shortcode and AJAX handlers | Fine-grained cap separate from `edit_posts`; existing admins get it too via `add_cap()` |
| `wp_user_id` column on `agents` table | PostgreSQL DDL | Link PG agent record to WP user account | One-way lookup: WP session → `get_current_user_id()` → query agents → `agent_id` → filter classes |
| Plugin-registered page template | WP `theme_page_templates` + `template_include` filters | Bare-bones shell (no nav, no sidebar) for agent login page | Plugin-owned template avoids theme dependency; survives theme changes; standard WP pattern for kiosk pages |
| `[wecoza_agent_attendance_management]` shortcode | WP `add_shortcode()` | Render agent's assigned classes with attendance capture UI | Reuses existing attendance-capture.js and AJAX handlers — zero duplication |

### Supporting Libraries (no new libraries needed)

All needed capabilities exist in the current stack:

| Need | Solution | Why Not a Library |
|------|----------|------------------|
| Agent login redirect | `login_redirect` WP filter | 3-line filter; no library justified |
| Hide admin bar | `show_admin_bar` WP filter | 1-line filter; already standard WP pattern |
| Redirect agents away from other pages | `template_redirect` WP action | Native WP hook; no middleware needed |
| Token-based access | NOT NEEDED | WP role + login is simpler and more secure than custom tokens for this use case |
| CSS isolation for minimal template | Inline `wp_enqueue_style()` call in template | Standard WP asset enqueueing; no bundler needed |

---

## Installation

No new packages. All changes are PHP within the existing plugin.

```bash
# No composer/npm additions required
# SQL to run manually (user-executed — no write access via MCP):
# ALTER TABLE agents ADD COLUMN wp_user_id INTEGER;
# CREATE UNIQUE INDEX idx_agents_wp_user_id ON agents(wp_user_id) WHERE wp_user_id IS NOT NULL;
```

---

## Alternatives Considered

| Feature | Recommended | Alternative | Why Not Alternative |
|---------|-------------|-------------|---------------------|
| Agent auth | WP role + login | Token in URL (e.g. `?token=abc123`) | Token URLs are shareable/loggable; WP session is encrypted, revocable, and integrates with existing nonce validation. No `wp_ajax_nopriv_` hacks needed. |
| Agent auth | WP role + login | Separate WordPress install / subsite | Massive overkill; agents need exactly 1 page |
| Agent auth | WP role + login | WP application passwords + REST API | REST requires a different frontend architecture; existing AJAX handlers would need rewriting |
| Page template | Plugin-registered via `theme_page_templates` | Custom page in theme's `page-templates/` | Theme-dependent; breaks on theme change; plugin must own its own templates |
| Class filtering | Query by `wp_user_id → agent_id → class_agent` | Pass class_id via URL param | URL param is exploitable (agent could see other classes); lookup from authenticated user is secure |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `wp_ajax_nopriv_` hooks for attendance | Contradicts established project policy; entire site requires login | Standard `wp_ajax_` hooks; `wecoza_agent` role provides login |
| JWT or custom session tokens | Unnecessary complexity; WP session + nonce covers this completely | WP native login + `capture_attendance` capability check |
| Separate PHP session management | WordPress already manages sessions securely | `get_current_user_id()` + capability check |
| New JavaScript framework | Not needed for exception button UX fix | Change existing `attendance-capture.js` button rendering |
| New CSS file for exception button | Styling change belongs in existing theme stylesheet | Add rule to `ydcoza-styles.css` per project convention |

---

## Integration Points

### How new elements connect to existing code

**Role registration** fires on plugin `register_activation_hook`. On existing installs, use a version-bump check or admin tool to add the role once.

**`capture_attendance` capability gate** replaces the bare `is_user_logged_in()` check in the attendance shortcode and AJAX handlers. Admins already have all caps, so no regressions.

**`wp_user_id` → agent class lookup:**
```php
// In new shortcode handler (pseudo-code)
$wpUserId  = get_current_user_id();
$agentRow  = AgentRepository::findByWpUserId($wpUserId);   // new method
$agentId   = $agentRow['agent_id'];
$classes   = ClassRepository::getClassesByAgent($agentId); // existing-ish query
```
`ClassRepository` already has `getClassesByAgent()`-style logic in `ClassRepository.php:739` (agent_name hydration). A targeted query for `class_agent = $agentId OR backup_agent_ids @> $agentId::jsonb` is all that's needed.

**Minimal page template** registers via two WP filters:
```php
// theme_page_templates — shows template in page editor dropdown
// template_include — serves the template file when the page loads
```
Template lives at `views/templates/agent-minimal.php` and enqueues only the assets needed for attendance capture.

**Stopped-class JS gating:** `stopDate` is already injected into `window.WeCozaSingleClass` in `attendance.php`. The same pattern applies in the new agent shortcode view — pass stop date, let existing `attendance-capture.js` handle the gate. Server-side guard already exists in `require_active_class()` (AttendanceAjaxHandlers.php:75–95).

---

## Stack Patterns by Variant

**If agent needs more pages later (e.g., view own schedule, download payslips):**
- Add new shortcodes to the same minimal template page, or create additional agent pages using the same template
- No architecture change — the template and role scale naturally

**If agent role needs more capabilities later:**
- Add new capability strings (e.g., `view_own_schedule`) via `add_cap()` on the `wecoza_agent` role
- Gate new features on that cap — no role restructuring

**If agents need to be provisioned in bulk:**
- Build an admin utility shortcode or WP-CLI command that: creates WP user, assigns `wecoza_agent` role, writes `wp_user_id` to agents table
- No stack additions needed — pure PHP using existing `AgentRepository`

---

## Version Compatibility

| Component | Compatible With | Notes |
|-----------|-----------------|-------|
| `add_role()` / role registration | WordPress 2.0+ | Stable core API; fire on `register_activation_hook` |
| `theme_page_templates` filter | WordPress 4.7+ | Plugin-registered templates; fully stable |
| `template_include` filter | WordPress 1.5+ | Standard template override hook |
| `login_redirect` filter | WordPress 3.0+ | Returns URL string; agent redirect on login |
| PostgreSQL `JSONB @>` operator | PostgreSQL 9.4+ | Used for `backup_agent_ids` containment check; confirmed in existing schema |

---

## Sources

- WordPress Codex: `add_role()`, `add_cap()` — HIGH confidence (native WP core API, no version risk)
- WordPress Developer Reference: `theme_page_templates`, `template_include` — HIGH confidence (well-documented, used in major plugins)
- Codebase: `src/Classes/Ajax/AttendanceAjaxHandlers.php` lines 56–95 (`require_active_class`) — stopped-class server guard already partially implemented
- Codebase: `views/classes/components/single-class/attendance.php` lines 43–82 — stopped-class JS injection pattern (`window.WeCozaSingleClass.stopDate`) already in place
- Codebase: `schema/wecoza_db_schema_bu_march_04.sql` — `agents` table confirmed, `wp_user_id` column does NOT exist yet (DDL required)
- Codebase: `src/Classes/Repositories/ClassRepository.php` lines 528–529, 739–744 — `class_agent` and `backup_agent_ids` columns confirmed queryable

---

*Stack research for: WeCoza Core v7.0 — Agent attendance portal, exception UX, stopped-class logic*
*Researched: 2026-03-04*
