# Phase 54: Agent Foundation - Research

**Researched:** 2026-03-04
**Domain:** WordPress roles/capabilities + PostgreSQL schema migration + AJAX capability guards
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Extend existing `wp_agent` role (at line ~750 in wecoza-core.php) — do NOT create a new role
- Add `capture_attendance` capability to `wp_agent` role
- Add `capture_attendance` capability to `administrator` role too
- Existing `wp_agent` capabilities (read, edit_posts, upload_files) remain unchanged
- Add `plugins_loaded` guard for capability updates — activation hook alone won't survive plugin updates
- Must work after both fresh activation AND plugin update (no manual reactivation)
- Add `wp_user_id` INTEGER column on `agents` table
- All attendance capture AJAX endpoints must enforce `capture_attendance` capability
- All exception-marking AJAX endpoints must enforce `capture_attendance` capability
- Return permissions error for users without the capability

### Claude's Discretion
- Migration approach for adding `wp_user_id` column (ALTER TABLE vs migration system)
- Specific AJAX handler identification and guard implementation pattern
- Version tracking for capability updates (if needed)

### Deferred Ideas (OUT OF SCOPE)
None — phase scope is well-defined.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AGT-01 | Plugin registers `wp_agent` WordPress role with `capture_attendance` capability on activation and update | `plugins_loaded` hook with `add_cap()` — already have role registration pattern in wecoza-core.php at line 750 |
| AGT-02 | Administrator role also receives `capture_attendance` capability | Existing `$admin->add_cap()` block at line 760–772 is the right place to add it |
| AGT-03 | `agents` table has `wp_user_id` column linking to WordPress user accounts | `ALTER TABLE agents ADD COLUMN IF NOT EXISTS wp_user_id INTEGER` — confirmed column does not exist yet |
| AGT-04 | AJAX attendance handlers check `capture_attendance` capability (not just logged-in) | `AttendanceAjaxHandlers.php` has 5 actions; `verify_attendance_nonce()` is the shared entry point to extend |
</phase_requirements>

---

## Summary

Phase 54 has three distinct implementation areas: WordPress role/capability provisioning, PostgreSQL schema migration, and AJAX authorization hardening. All three are well-understood from codebase inspection and have clear, low-risk implementation paths.

The `wp_agent` role already exists in the activation hook (wecoza-core.php line 750) with capabilities `read`, `edit_posts`, `upload_files`. The `capture_attendance` capability is missing from both `wp_agent` and `administrator`. The correct fix is a `plugins_loaded` guard that calls `add_cap()` idempotently — WordPress's `WP_Role::add_cap()` is safe to call repeatedly (stores in `wp_usermeta`/`wp_options`).

The `agents` table confirmed via live DB query has **no** `wp_user_id` column. The schema addition is a single `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` DDL statement plus an optional unique partial index following the project convention (see `idx_agents_email_unique` and `idx_agents_sa_id_unique` which both use `WHERE status <> 'deleted'`).

The `AttendanceAjaxHandlers.php` file has 5 AJAX actions registered. Currently only nonce-checked (`verify_attendance_nonce()`). A shared `verify_attendance_capability()` helper function should be added (matching the existing `verify_attendance_nonce()` pattern) and called inside the two write-action handlers: `handle_attendance_capture()` and `handle_attendance_mark_exception()`. The read-only handlers (`get_sessions`, `get_detail`) and admin-delete handler have different permission requirements and should be evaluated separately.

**Primary recommendation:** Use `plugins_loaded` hook with a version-stamped option to drive idempotent `add_cap()` calls; write one DDL file for the user to execute manually; add a `verify_attendance_capability()` helper function in `AttendanceAjaxHandlers.php` mirroring the existing nonce helper.

---

## Standard Stack

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| `WP_Role::add_cap()` | WP 6.0+ | Add capability to a role | Built-in WP API, idempotent, persisted in `wp_options` as `wp_{role}_capabilities` |
| `get_role()` | WP 6.0+ | Retrieve role object | Standard WP API used throughout codebase |
| `current_user_can()` | WP 6.0+ | Check user capability | Standard WP API used in `ClassStatusAjaxHandler.php` |
| `AjaxSecurity::requireCapability()` | Project | Enforce capability or send 403 JSON | Already exists in `core/Helpers/AjaxSecurity.php` — DRY |

### Supporting
| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| `plugins_loaded` hook | WP 6.0+ | Run code on every page load after plugins initialize | Capability updates that must survive plugin updates |
| `add_action` with activation hook | WP 6.0+ | Run code once on activation | Only for first-run setup, does NOT re-run on plugin update |
| PostgreSQL `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` | PG 9.6+ | Non-destructive column addition | Safe to run multiple times |

---

## Architecture Patterns

### Recommended: `plugins_loaded` Capability Guard

The project already uses `plugins_loaded` at priority 5 for plugin init (wecoza-core.php line 167). Capability provisioning should hook here at a slightly later priority (e.g., 6) so core is loaded but before dependent code runs.

**Pattern — idempotent capability registration:**

```php
// In wecoza-core.php, add after the plugins_loaded hook at priority 5
add_action('plugins_loaded', function () {
    // Add capture_attendance to wp_agent role
    $agent_role = get_role('wp_agent');
    if ($agent_role && !$agent_role->has_cap('capture_attendance')) {
        $agent_role->add_cap('capture_attendance');
    }

    // Add capture_attendance to administrator role
    $admin_role = get_role('administrator');
    if ($admin_role && !$admin_role->has_cap('capture_attendance')) {
        $admin_role->add_cap('capture_attendance');
    }
}, 6);
```

**Why `!$role->has_cap()`:** Avoids redundant DB writes on every page load. `add_cap()` IS idempotent (won't corrupt), but `has_cap()` guard prevents unnecessary writes.

**Alternative — always call `add_cap()` without guard:** Simpler code, still safe. `add_cap()` writes to `wp_options` (serialized array) only if value changes. Acceptable if performance is not a concern. This is how the existing admin caps in the activation hook work (lines 761–771) — no guard.

**Recommendation:** Follow the pattern of the existing admin cap block — call `add_cap()` directly without `has_cap()` guard. Simpler, consistent with existing codebase style. The write cost is negligible.

### AJAX Capability Guard Pattern

Existing pattern in `ClassStatusAjaxHandler.php` (lines 76, 231):
```php
if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
    wp_die();
}
```

Project also has `AjaxSecurity::requireCapability()` which is the DRY version:
```php
// core/Helpers/AjaxSecurity.php line 97
public static function requireCapability(string $capability): void
{
    if (!self::checkCapability($capability)) {
        self::sendError('Insufficient permissions.', 403);
        exit;
    }
}
```

The attendance handlers use a namespaced function `verify_attendance_nonce()` (not the class). Mirror this pattern:

```php
// Add to AttendanceAjaxHandlers.php alongside verify_attendance_nonce()
function verify_attendance_capability(): void
{
    if (!current_user_can('capture_attendance')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        exit;
    }
}
```

Call it inside `handle_attendance_capture()` and `handle_attendance_mark_exception()` immediately after `verify_attendance_nonce()`.

### AJAX Handlers — Scope of Guards

| Handler | Action | Write? | Needs `capture_attendance` guard? |
|---------|--------|--------|----------------------------------|
| `handle_attendance_capture` | `wecoza_attendance_capture` | YES | YES |
| `handle_attendance_mark_exception` | `wecoza_attendance_mark_exception` | YES | YES |
| `handle_attendance_get_sessions` | `wecoza_attendance_get_sessions` | NO (read) | NO — agents need to read sessions |
| `handle_attendance_get_detail` | `wecoza_attendance_get_detail` | NO (read) | NO — agents need to read detail |
| `handle_attendance_admin_delete` | `wecoza_attendance_admin_delete` | YES (admin-only) | Debatable — currently admin-only; could use `manage_options` or `capture_attendance`. Phase 54 scope: add `capture_attendance` to the two capture/exception handlers only. `admin_delete` is out of scope. |

### Schema Migration Pattern

Project convention: user executes DDL manually (from MEMORY.md: "SQL that alters the database — user runs manually"). Provide SQL statement, do NOT execute programmatically.

**DDL to provide:**
```sql
-- Add wp_user_id column to agents table
ALTER TABLE agents ADD COLUMN IF NOT EXISTS wp_user_id INTEGER;

-- Optional: unique partial index (one WP user = one agent, excluding deleted)
-- Follow existing pattern from idx_agents_email_unique
CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_agents_wp_user_id_unique
    ON agents (wp_user_id)
    WHERE wp_user_id IS NOT NULL AND status <> 'deleted';
```

`CONCURRENTLY` is used for production safety (no table lock). `IF NOT EXISTS` makes it re-runnable.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Capability check + 403 response | Custom auth check | `verify_attendance_capability()` function or `AjaxSecurity::requireCapability()` | DRY — matches existing nonce pattern in same file |
| Role/cap persistence | Custom DB writes | `WP_Role::add_cap()` | WP stores in `wp_options` serialized — hand-rolling would duplicate and conflict |
| Schema migration runner | Custom migration table | Manual DDL + `ADD COLUMN IF NOT EXISTS` | Project uses manual DDL by convention; no migration framework in codebase |

---

## Common Pitfalls

### Pitfall 1: Activation Hook Only (AGT-01 failure)
**What goes wrong:** Role registration in `register_activation_hook` only runs on first activation. If the plugin is deactivated and reactivated it runs again, but a plugin **update** (via WP admin updater) does NOT trigger the activation hook. Capability will be missing after updates.
**Why it happens:** WP activation hook fires once on explicit activation, not on update.
**How to avoid:** Add `plugins_loaded` hook for capability registration. Keep the activation hook only for first-time role creation (which uses `if (!get_role(...))` guard anyway).
**Warning signs:** QA reports "works after fresh install but broke after we updated the plugin."

### Pitfall 2: Calling `add_cap()` Before WP Roles Are Loaded
**What goes wrong:** `get_role()` returns null if called too early (before WP has loaded the user roles from `wp_options`).
**Why it happens:** Calling role API before `plugins_loaded` or `init`.
**How to avoid:** All cap additions are inside `plugins_loaded` or later hooks. Already the case in the existing activation hook (which runs after WP is loaded).

### Pitfall 3: Breaking Existing Admin Workflows (AGT-02)
**What goes wrong:** Admins lose access to attendance capture after the capability guard is added because `administrator` role doesn't have `capture_attendance`.
**Why it happens:** Adding capability guards without granting the capability to admins.
**How to avoid:** Grant `capture_attendance` to `administrator` role in the same code block as `wp_agent`. Verify: log in as admin, capture attendance — must succeed.

### Pitfall 4: `wp_user_id` Column — Uniqueness Semantics
**What goes wrong:** Two agent records map to the same WordPress user ID, causing lookup ambiguity.
**Why it happens:** No uniqueness constraint on `wp_user_id`.
**How to avoid:** Use a unique partial index (`WHERE wp_user_id IS NOT NULL AND status <> 'deleted'`). Pattern mirrors `idx_agents_email_unique` already in the database.

### Pitfall 5: Guarding Read-Only Attendance AJAX
**What goes wrong:** Adding `capture_attendance` guard to `get_sessions` or `get_detail` breaks the agent's ability to VIEW session state (which they need before capturing).
**Why it happens:** Over-applying the guard to all attendance endpoints.
**How to avoid:** Guard only write endpoints: `capture` and `mark_exception`. Read endpoints remain nonce-only.

---

## Code Examples

### Capability Registration in `plugins_loaded`

```php
// wecoza-core.php — inside or alongside the plugins_loaded block
add_action('plugins_loaded', function () {
    // Extend wp_agent role with capture_attendance capability
    $agentRole = get_role('wp_agent');
    if ($agentRole) {
        $agentRole->add_cap('capture_attendance');
    }

    // Grant capture_attendance to administrators so existing workflows continue
    $adminRole = get_role('administrator');
    if ($adminRole) {
        $adminRole->add_cap('capture_attendance');
    }
}, 6);
```

### AJAX Capability Guard in `AttendanceAjaxHandlers.php`

```php
// Add alongside verify_attendance_nonce() — same namespace, same pattern
function verify_attendance_capability(): void
{
    if (!current_user_can('capture_attendance')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        exit;
    }
}

// Usage in handle_attendance_capture():
function handle_attendance_capture(): void
{
    try {
        verify_attendance_nonce();
        verify_attendance_capability(); // <-- add after nonce check

        // ... rest of handler unchanged
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// Same in handle_attendance_mark_exception():
function handle_attendance_mark_exception(): void
{
    try {
        verify_attendance_nonce();
        verify_attendance_capability(); // <-- add after nonce check

        // ... rest of handler unchanged
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
```

### DDL for `wp_user_id` Column

```sql
-- Run manually in psql or pg admin
ALTER TABLE agents ADD COLUMN IF NOT EXISTS wp_user_id INTEGER;

CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_agents_wp_user_id_unique
    ON agents (wp_user_id)
    WHERE wp_user_id IS NOT NULL AND status <> 'deleted';
```

---

## Key Findings from Codebase Inspection

### Existing Role Registration (wecoza-core.php lines 750–756)
```php
// Current — activation hook only
if (!get_role("wp_agent")) {
    add_role("wp_agent", __("Agent", "wecoza-core"), [
        "read" => true,
        "edit_posts" => true,
        "upload_files" => true,
    ]);
}
```
`capture_attendance` is absent. The role object IS present in the DB (role was previously created), so `add_role()` will skip. Only `add_cap()` is needed.

### Existing Admin Cap Block (wecoza-core.php lines 760–772)
```php
$admin = get_role("administrator");
if ($admin) {
    $admin->add_cap("manage_learners");
    $admin->add_cap("view_material_tracking");
    // ... etc.
}
```
This is activation-hook-only too. For `capture_attendance` on admin, we should add it to the `plugins_loaded` guard block rather than just the activation hook, to survive updates.

### Confirmed: `wp_user_id` Column Does Not Exist
Live DB query of `agents` table returned 45 columns — `wp_user_id` is absent. DDL is required.

### `AttendanceAjaxHandlers.php` — Current Security Model
- All 5 handlers: nonce check only via `verify_attendance_nonce()`
- No capability check on any handler
- File uses procedural PHP functions (not a class), registered with `add_action('init', ...)`
- The handler functions share a namespace: `WeCoza\Classes\Ajax`

### Existing Partial Index Pattern (matches project convention)
```sql
-- Existing: idx_agents_email_unique
WHERE ((status)::text <> 'deleted'::text)

-- Existing: idx_agents_sa_id_unique
WHERE ((sa_id_no IS NOT NULL) AND ((sa_id_no)::text <> ''::text) AND ((status)::text <> 'deleted'::text))
```
New `wp_user_id` index should use `WHERE wp_user_id IS NOT NULL AND status <> 'deleted'`.

---

## Open Questions

1. **Should `handle_attendance_admin_delete` also require `capture_attendance`?**
   - What we know: Currently admin-delete is nonce-only. It reverses hours — a destructive write.
   - What's unclear: Phase 54 context mentions "capture and exception-marking" endpoints. Admin-delete is neither.
   - Recommendation: Out of scope for Phase 54. Address in Phase 55 or leave as nonce-only (site requires login, admin-delete is destructive admin function — could warrant `manage_options` check instead).

2. **Should `plugins_loaded` cap registration be version-stamped?**
   - What we know: `add_cap()` is idempotent. Calling without version stamp is safe.
   - What's unclear: CONTEXT.md lists version tracking as Claude's discretion.
   - Recommendation: Skip version stamp. It adds complexity for no practical benefit — `add_cap()` is cheap and idempotent. Match existing admin cap style (no version stamp).

---

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `wecoza-core.php` lines 750–772 — activation hook role/cap registration
- Direct codebase inspection: `src/Classes/Ajax/AttendanceAjaxHandlers.php` — all 5 AJAX handlers, current security model
- Direct codebase inspection: `core/Helpers/AjaxSecurity.php` — `requireCapability()` method
- Live PostgreSQL query: `agents` table columns — `wp_user_id` confirmed absent
- Live PostgreSQL query: `pg_indexes` for `agents` — existing partial index patterns confirmed

### Secondary (MEDIUM confidence)
- WordPress documentation (training knowledge): `WP_Role::add_cap()` idempotent behavior, `plugins_loaded` vs activation hook lifecycle

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all APIs verified in live codebase
- Architecture: HIGH — existing patterns directly confirmed; `add_cap()` pattern matches live code
- Pitfalls: HIGH — pitfalls derived from direct code inspection, not speculation
- Schema: HIGH — confirmed via live DB query

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable WordPress APIs, stable schema)
