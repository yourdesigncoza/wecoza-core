# Phase 52: Class Activation Logic - Research

**Researched:** 2026-02-24
**Domain:** PHP/WordPress AJAX, PostgreSQL schema migration, class status state machine, Bootstrap 5 modals
**Confidence:** HIGH — all findings verified against actual codebase files and live database

---

## Summary

Phase 52 introduces a proper `class_status` column (`draft`/`active`/`stopped`) to replace the current implicit status derived from `order_nr`. The reference plan (snoopy-wobbling-fountain.md) is Gemini-reviewed and accurate. Codebase exploration confirms the plan's assumptions about file locations and method signatures are correct as of Phase 51 completion.

The core challenge is a **dual-path** status system during the migration window: the new `class_status` column may be NULL until backfilled, so a centralized `wecoza_resolve_class_status()` helper must be used everywhere. The plan addresses this with CC1. All AJAX handler patterns, modal patterns, badge patterns, and toast patterns are established in the codebase and can be replicated directly.

**Key risk identified:** The `class_attendance_sessions` table does NOT exist in the live database yet (Phase 48 schema not executed), meaning the attendance lock guard in Phase 5 will query a column that doesn't exist on `classes` yet (the new `class_status` column). Both DB migrations must be applied before any code runs. The planner must sequence the SQL migration as the absolute first task.

**Primary recommendation:** Follow the reference plan's 8-phase sequence exactly. Verify DB migration (Phase 1 SQL) is applied before any PHP/JS tasks run.

---

## Standard Stack

### Core (no new dependencies needed)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress AJAX | WP 6.0+ | `wp_ajax_*` actions, `wp_send_json_*` | Established pattern throughout codebase |
| Bootstrap 5 | Bundled via theme | Modals (`bootstrap.Modal`), toasts | Used in attendance-capture.js already |
| jQuery | Bundled via WP | DOM manipulation, AJAX calls | All class JS uses jQuery |
| PostgreSQL PDO | PHP 8.0+ | Direct DB transactions via `wecoza_db()->getPdo()` | Used in ClassModel, TaskManager |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Phoenix badge system | Theme CSS | `badge-phoenix-{success|warning|danger}` | All status badges in the codebase use these |
| `wecoza_db()` | Core helper | PostgreSQL connection singleton | Use for all DB operations |

---

## Architecture Patterns

### Established AJAX Handler Pattern

The `AttendanceAjaxHandlers.php` file is the canonical pattern for new AJAX handlers. Key observations verified from source:

```php
// File: src/Classes/Ajax/AttendanceAjaxHandlers.php

namespace WeCoza\Classes\Ajax;

// Shared nonce helper (DRY — never inline check_ajax_referer)
function verify_attendance_nonce(): void {
    if (!check_ajax_referer('wecoza_attendance_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        exit;
    }
}

// Handler functions — not class methods
function handle_attendance_capture(): void { ... }

// Registration via a named function called on 'init'
function register_attendance_ajax_handlers(): void {
    add_action('wp_ajax_wecoza_attendance_capture', __NAMESPACE__ . '\handle_attendance_capture');
    // No wp_ajax_nopriv_ — site requires login
}
add_action('init', __NAMESPACE__ . '\register_attendance_ajax_handlers');
```

**For Phase 52:** `ClassStatusAjaxHandler.php` must follow this pattern — namespace `WeCoza\Classes\Ajax`, procedural functions, single registration function on `init`. Use nonce name `wecoza_class_nonce` (already created in ClassController line 497).

### Verified: `wecoza_class_nonce` Already Exists

In `ClassController::enqueueAndLocalizeSingleClassScript()` (line 497):
```php
'classNonce' => wp_create_nonce('wecoza_class_nonce'),
```
The new AJAX handler uses `check_ajax_referer('wecoza_class_nonce', 'nonce')` — no new nonce needed.

### Established Toast/Error Pattern (JS)

From `attendance-capture.js` (lines 697-728):
```javascript
function showToast(message, type) {
    const bgClass = type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : ...;
    const $toast = $('<div>').addClass('toast align-items-center text-white border-0 show ' + bgClass)
        .css({ position: 'fixed', top: '20px', right: '20px', zIndex: 9999, minWidth: '260px' });
    // ... auto-dismiss after 4000ms
}
```
The new `single-class-display.js` handlers must call `showToast()` on AJAX failure per CC6. Since `attendance-capture.js` already defines `showToast()` in its IIFE scope, the new status handlers in `single-class-display.js` must define their own or extract to a shared utility. **Decision needed from planner:** inline or shared utility. Recommended: define locally in `single-class-display.js` (same pattern, no scope conflict with IIFE in attendance-capture.js).

### Established Modal Pattern (JS)

From `attendance-capture.js` (lines 735-739):
```javascript
function showModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
}
```
Use `bootstrap.Modal.getOrCreateInstance()` pattern in the status action handlers.

### Badge Rendering Pattern (PHP)

Classes listing view (`views/classes/components/classes-display.view.php` lines 270-285) uses:
```php
$isDraft = empty($class['order_nr']);
if ($isDraft):
?>
<span class="badge badge-phoenix fs-10 badge-phoenix-warning">
    <span class="badge-label">Draft</span>
    <i class="bi bi-file-earmark-text ms-1"></i>
</span>
<?php else: ?>
<span class="badge badge-phoenix fs-10 badge-phoenix-success">
    <span class="badge-label">Active</span>
    <i class="bi bi-check-circle ms-1"></i>
</span>
```
This exact pattern is upgraded to three-way using `wecoza_resolve_class_status()` in Phase 7. The `badge-phoenix-danger` class for "Stopped" follows the same structure — no custom CSS needed.

### ClassTaskPresenter Output Format

The presenter's `formatClassRow()` (line 117) returns a `'status'` key with `['class' => ..., 'label' => ...]` shape, used in `views/events/event-tasks/main.php` line 148:
```php
<span class="badge badge-phoenix fs-10 <?= esc_attr($class['status']['class']); ?>" ...>
    <?= esc_html($class['status']['label']); ?>
</span>
```
The new `class_status_badge` key in Phase 4 must return the same shape: `['class' => 'badge-phoenix-success', 'label' => 'Active', 'icon' => 'bi-check-circle']`.

### Repository Column Whitelisting Pattern

`ClassRepository::getAllowedInsertColumns()` (line 67) is an array. `getAllowedUpdateColumns()` (line 83) calls `getAllowedInsertColumns()` and diffs out `created_at`. Adding `class_status` to `getAllowedInsertColumns()` automatically adds it to `getAllowedUpdateColumns()`.

---

## Verified File Locations and Current State

### Files to Modify — Verified

| File | Actual State | Plan Accuracy |
|------|-------------|---------------|
| `src/Classes/Models/ClassModel.php` | No `classStatus` property. `isDraft()` (line 416) uses `empty($order_nr)`. `isActive()` (line 420) uses `!empty($order_nr)`. `getStatus()` returns 'Active'/'Draft' only. `toArray()` (line 543) has no `class_status`. | ACCURATE — plan's changes are all needed |
| `src/Classes/Repositories/ClassRepository.php` | `getAllowedInsertColumns()` (line 67) has no `class_status`. `getAllClasses()` SELECT (line 514) has no `c.class_status`. `getAllowedFilterColumns()` (line 55) has no `class_status`. | ACCURATE |
| `core/Helpers/functions.php` | No `wecoza_resolve_class_status()` function exists. | ACCURATE — must be added |
| `src/Events/Services/TaskManager.php` | `updateClassOrderNumber()` (line 231) SQL: `UPDATE classes SET order_nr = :order_nr, order_nr_metadata = :metadata, updated_at = now()`. No `class_status` in SQL. `fetchClassById()` (line 199) selects `class_id, order_nr, order_nr_metadata, event_dates` — no `class_status`. | ACCURATE — both methods need updating |
| `src/Events/Repositories/ClassTaskRepository.php` | SELECT list (lines 63-88) has `c.order_nr` but NOT `c.class_status`. | ACCURATE |
| `src/Events/Views/Presenters/ClassTaskPresenter.php` | `formatClassRow()` has no `formatClassStatusBadge()`. Returns no `class_status_badge` key. | ACCURATE |
| `views/events/event-tasks/main.php` | Badge slot at line 148 shows task status badge only — no class status badge. | ACCURATE |
| `views/classes/components/single-class/attendance.php` | No activation gate. Jumps straight to attendance UI after `empty($class)` check. | ACCURATE — lock needed |
| `src/Classes/Controllers/ClassController.php` | `enqueueAndLocalizeSingleClassScript()` (line 463) does NOT include `classStatus` or `isAttendanceLocked` or `orderNr` in localized data. | ACCURATE |
| `views/classes/components/single-class-display.view.php` | Action buttons at line 120-131. No status management section. Attendance section at line 155. | ACCURATE |
| `views/classes/components/single-class/summary-cards.php` | 5 cards: Client, Class Type, Class Subject, Class Code, Total Hours. No status card. | ACCURATE |
| `assets/js/classes/single-class-display.js` | No `activateClass()`, `stopClass()`, `reactivateClass()` functions. | ACCURATE |
| `views/classes/components/classes-display.view.php` | Binary badge: `empty($class['order_nr'])` → Draft / Active. Lines 270-285. | ACCURATE |
| `wecoza-core.php` | AJAX handlers loaded at line 671 with `require_once`. Pattern established. | ACCURATE |

### New File to Create

| File | Status |
|------|--------|
| `src/Classes/Ajax/ClassStatusAjaxHandler.php` | Does not exist — new file |

---

## Database State — Critical Findings

### Live DB: `classes` Table (confirmed via pg query)

The `classes` table currently has these relevant columns:
- `order_nr VARCHAR` — nullable, current de-facto status indicator
- `order_nr_metadata JSONB` — nullable, stores `{completed_by, completed_at}`
- NO `class_status` column

**Confirmed: `class_status` does NOT exist in the live database.** The Phase 1 SQL migration must be executed first before any PHP code referencing `class_status` is deployed.

### Live DB: `class_status_history` Table

Does NOT exist. Must be created in Phase 1 SQL.

### Live DB: `class_attendance_sessions` Table

Does NOT exist in the live database despite Phase 48 planning it. This means the current attendance AJAX handlers (`AttendanceAjaxHandlers.php`) will fail at runtime. However, this is a pre-existing gap — Phase 52 does NOT need to fix it, but the planner should note that Phase 51's attendance capture is also blocked until that migration runs.

---

## Cross-Cutting Concerns (from Reference Plan — all verified applicable)

### CC1: `wecoza_resolve_class_status()` — MANDATORY

Every place that reads class status must go through this helper. Without it, any code deployed before the DB backfill completes will fail on classes where `class_status` is NULL.

```php
// Add to core/Helpers/functions.php
function wecoza_resolve_class_status(array $class): string {
    return $class['class_status'] ?? (empty($class['order_nr']) ? 'draft' : 'active');
}
```

### CC2: DB Transactions — MANDATORY for status transitions

Pattern already used in `ClassModel::save()` and `ClassModel::update()`. The new AJAX handler must wrap UPDATE + INSERT atomically using `wecoza_db()->getPdo()->beginTransaction()`.

### CC3: Idempotency Guards — Confirmed pattern

AJAX handler must fetch current `class_status` before transitioning. Reject same-state transitions with 400.

### CC4: Input Sanitization — Confirmed WP functions available

All listed functions (`absint`, `sanitize_text_field`, `sanitize_textarea_field`) are standard WP. Whitelist validation for `stop_reason` and `new_status` uses `in_array()`.

### CC5: Localization — Confirmed `wecoza-core` text domain

Text domain verified from existing code: `__('text', 'wecoza-core')`.

### CC6: Frontend Error Handling — showToast() pattern confirmed

`showToast()` is implemented in `attendance-capture.js` (lines 697-728). The status handlers in `single-class-display.js` need their own equivalent (different IIFE scope).

### CC7: `order_nr_metadata` Compatibility

`TaskManager::normaliseOrderNumber()` is a **private** method (line 254). The new `ClassStatusAjaxHandler.php` CANNOT call it directly. Must inline the same logic or make it static/public.

**Risk:** The reference plan says "use `TaskManager::normaliseOrderNumber()`" but it's private. Options:
1. Change `normaliseOrderNumber()` to `public static` in TaskManager
2. Duplicate the 10-line normalization logic in ClassStatusAjaxHandler (violates DRY)
3. Extract to a shared static helper in ClassRepository or functions.php

**Recommendation:** Change to `public static` in TaskManager (minimal change, DRY preserved).

---

## Common Pitfalls

### Pitfall 1: Deploying PHP Before DB Migration

**What goes wrong:** Any code referencing `class_status` column throws a PDO exception if the column doesn't exist.
**How to avoid:** Phase 1 (SQL migration) MUST be executed manually before any other tasks. Planner must mark Phase 1 as a prerequisite blocker for all subsequent tasks.

### Pitfall 2: Double-Calling `wecoza_resolve_class_status()` Twice for Localization

**What goes wrong:** Reference plan (Phase 5B) shows:
```php
'classStatus' => wecoza_resolve_class_status($class),
'isAttendanceLocked' => wecoza_resolve_class_status($class) !== 'active',
```
This calls the helper twice, slightly inefficient.
**How to avoid:** Compute once: `$status = wecoza_resolve_class_status($class);` then use `$status` for both.

### Pitfall 3: `normaliseOrderNumber()` is Private

**What goes wrong:** Calling `TaskManager::normaliseOrderNumber()` from a new file fails with fatal error.
**How to avoid:** Change to `public static` in Phase 3 when touching TaskManager. Then call from ClassStatusAjaxHandler.

### Pitfall 4: Bootstrap Modal Double-Instance

**What goes wrong:** Calling `new bootstrap.Modal(el)` when a modal instance already exists throws a warning or creates a stacked modal.
**How to avoid:** Use `bootstrap.Modal.getOrCreateInstance(el)` as seen in attendance-capture.js line 738.

### Pitfall 5: `getSingleClass()` vs `getAllClasses()` — Two Code Paths

**What goes wrong:** `getAllClasses()` has its own SELECT query (line 514) and its own local `class_status` needs. `getSingleClass()` (line 553) delegates to `ClassModel::getById()` which does `SELECT *`. After the column is added, `SELECT *` will include `class_status` automatically but the model's `hydrate()` method needs to read it.
**How to avoid:** The plan correctly identifies both `getAllClasses()` (add `c.class_status` to SELECT) and `ClassModel::hydrate()` (read `class_status` from `$data`). Both must be updated.

### Pitfall 6: `ClassModel::getById()` Uses `SELECT *`

`ClassModel::getById()` (line 118): `SELECT * FROM classes WHERE class_id = ?`
After migration, `SELECT *` will include `class_status`. The hydrate() update in Phase 2 is sufficient — no separate SQL change needed for `getById()`. This is correct in the plan.

### Pitfall 7: Status History JOIN with wp_users

The reference plan specifies joining `class_status_history` with `wp_users` to get display names for `changed_by`. The `wp_users` table is in the WordPress MySQL database, NOT in PostgreSQL. Cannot JOIN across databases.
**How to avoid:** Fetch `changed_by` (WP user ID) from PostgreSQL, then call `get_userdata($userId)->display_name` in PHP to resolve names. Similar to how `ClassTaskPresenter::$userNameCache` resolves WP user names (lines 34, and pattern used in `formatClassRow()`).

### Pitfall 8: `isClassCurrentlyStopped()` Name Collision

`ClassController` already has a method `isClassCurrentlyStopped()` (line 511) that checks `stop_restart_dates` JSON for legacy stop logic. This is a different "stopped" concept from the new `class_status = 'stopped'`. After Phase 52, there are two stop mechanisms. The legacy JSON-based stop (for schedule exclusion) and the new explicit `class_status` stop. The plan does not address this ambiguity.
**Risk:** Moderate. The legacy `stop_restart_dates` is used for schedule calculation, not for access control. The new `class_status` column controls attendance locking and manager UI. They serve different purposes and can coexist, but views that check `isClassCurrentlyStopped()` and views that check `wecoza_resolve_class_status()` may give contradictory "stopped" signals.
**Recommendation:** Document in code comments that these are distinct: `stop_restart_dates` = schedule pauses, `class_status = 'stopped'` = class deactivation.

---

## Code Examples

### Pattern: AJAX Handler Registration (from AttendanceAjaxHandlers.php)

```php
namespace WeCoza\Classes\Ajax;

function register_class_status_ajax_handlers(): void {
    add_action('wp_ajax_wecoza_class_status_update',  __NAMESPACE__ . '\handle_class_status_update');
    add_action('wp_ajax_wecoza_class_status_history', __NAMESPACE__ . '\handle_class_status_history');
}
add_action('init', __NAMESPACE__ . '\register_class_status_ajax_handlers');
```

### Pattern: DB Transaction for Status Transition

```php
$pdo = wecoza_db()->getPdo();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "UPDATE classes SET class_status = :new_status, updated_at = NOW() WHERE class_id = :class_id"
    );
    $stmt->execute([':new_status' => $newStatus, ':class_id' => $classId]);

    $stmt2 = $pdo->prepare(
        "INSERT INTO class_status_history (class_id, old_status, new_status, reason, notes, changed_by)
         VALUES (:class_id, :old_status, :new_status, :reason, :notes, :changed_by)"
    );
    $stmt2->execute([...]);
    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Pattern: Three-Way Badge PHP (replaces binary in classes-display.view.php)

```php
$classStatus = wecoza_resolve_class_status($class);
$badgeClass  = match($classStatus) {
    'active'  => 'badge-phoenix-success',
    'stopped' => 'badge-phoenix-danger',
    default   => 'badge-phoenix-warning',   // draft
};
$badgeLabel  = match($classStatus) {
    'active'  => 'Active',
    'stopped' => 'Stopped',
    default   => 'Draft',
};
$badgeIcon   = match($classStatus) {
    'active'  => 'bi-check-circle',
    'stopped' => 'bi-stop-circle',
    default   => 'bi-file-earmark-text',
};
?>
<span class="badge badge-phoenix fs-10 <?= esc_attr($badgeClass); ?>">
    <span class="badge-label"><?= esc_html($badgeLabel); ?></span>
    <i class="<?= esc_attr($badgeIcon); ?> ms-1"></i>
</span>
```

### Pattern: Attendance Lock Gate (attendance.php)

```php
// After empty($class) check:
$classStatus = wecoza_resolve_class_status($class);
if ($classStatus !== 'active') {
    $lockMsg = $classStatus === 'stopped'
        ? __('This class has been stopped. Attendance capture is locked.', 'wecoza-core')
        : __('This class is in draft status. Attendance capture is not available until the class is activated.', 'wecoza-core');
    echo '<div class="alert alert-subtle-warning d-flex align-items-center">';
    echo '<i class="bi bi-lock-fill me-3 fs-4"></i><div>' . esc_html($lockMsg) . '</div></div>';
    return;
}
```

### Pattern: WP User Name Resolution (no cross-DB JOIN)

```php
// In ClassStatusAjaxHandler::handle_class_status_history()
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$row) {
    $user = get_userdata((int) $row['changed_by']);
    $row['changed_by_name'] = $user ? $user->display_name : __('Unknown', 'wecoza-core');
}
```

---

## Open Questions

1. **`normaliseOrderNumber()` visibility**
   - What we know: Method is `private` in TaskManager (line 254)
   - What's unclear: Whether to change it to `public static` or inline the logic in ClassStatusAjaxHandler
   - Recommendation: Change to `public static` to preserve DRY. Low-risk change — only called internally.

2. **`isClassCurrentlyStopped()` legacy ambiguity**
   - What we know: ClassController has existing `isClassCurrentlyStopped()` checking JSON stop_restart_dates (line 511)
   - What's unclear: Whether this method needs a deprecation comment or behavioral change
   - Recommendation: Leave it untouched; add a code comment in Phase 2 distinguishing the two stop concepts.

3. **Status button placement in single-class-display**
   - What we know: Plan says "after action buttons, line 131" — confirmed: action buttons are at line 120-131
   - What's unclear: Whether the status management section should be in the `manage_options` block of buttons or a separate card below
   - Recommendation: Separate collapsible card below action buttons (less cluttered, clearer UX separation from Edit/Delete).

4. **`class_attendance_sessions` table gap**
   - What we know: The table doesn't exist in the live database (Phase 48 SQL not applied)
   - What's unclear: Whether Phase 51 attendance JS will fail at runtime
   - Recommendation: Note for the planner that Phase 51 attendance capture is also DB-blocked. Phase 52 SQL migration (Phase 1) can be executed alongside the Phase 48 attendance SQL as a combined migration run.

---

## Implementation Sequence — Verified Feasibility

The reference plan's 8-phase sequence is verified feasible:

| Phase | What | Blocker? |
|-------|------|----------|
| 1: DB Migration | SQL — user executes manually | MUST run before all others |
| 2: ClassModel + ClassRepository + functions.php | Pure PHP — no DB interaction until migration done | Blocked by Phase 1 |
| 3: TaskManager auto-activate | SQL in `updateClassOrderNumber()` | Blocked by Phase 1 |
| 4: Event Tasks badge | ClassTaskRepository + Presenter + main.php | Blocked by Phases 1+2 |
| 5: Attendance lock | View + JS localization + AJAX guard | Blocked by Phases 1+2 |
| 6: Manager status actions | New AJAX handler + view + JS | Blocked by Phases 1+2+3 |
| 7: Classes listing badge | classes-display.view.php | Blocked by Phase 2 |
| 8: CSS (if needed) | ydcoza-styles.css | No blocker; likely nothing needed |

---

## Sources

### Primary (HIGH confidence)
- Live codebase read — all file paths and line numbers verified against actual files as of 2026-02-24
- Live PostgreSQL database schema — confirmed via `information_schema.columns` query
- Reference plan (`/home/laudes/.claude/plans/snoopy-wobbling-fountain.md`) — Gemini-reviewed, all assertions verified against actual code

### Secondary (MEDIUM confidence)
- WordPress AJAX documentation pattern — confirmed against existing `AttendanceAjaxHandlers.php` implementation

---

## Metadata

**Confidence breakdown:**
- File locations and line numbers: HIGH — all read directly from filesystem
- DB schema state: HIGH — queried live PostgreSQL
- AJAX handler pattern: HIGH — copied from working `AttendanceAjaxHandlers.php`
- Badge/modal/toast patterns: HIGH — copied from working `attendance-capture.js`
- `normaliseOrderNumber` visibility risk: HIGH — confirmed `private` via source read

**Research date:** 2026-02-24
**Valid until:** 2026-03-24 (stable codebase, low churn expected)
