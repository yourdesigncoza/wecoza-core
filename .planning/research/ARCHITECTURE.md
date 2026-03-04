# Architecture Research

**Domain:** WordPress plugin — agent-restricted attendance capture, exception UX, stopped-class capture logic
**Researched:** 2026-03-04
**Confidence:** HIGH (all findings from direct codebase inspection)

---

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                          BROWSER / AGENT                             │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  attendance-capture.js (WeCozaSingleClass config object)     │   │
│  │  - Session list loading, month filter, calendar render       │   │
│  │  - Capture modal, view-detail modal, exception modal         │   │
│  │  - stopDate gate: sessions after stop date get no actions    │   │
│  └────────────────────────────┬─────────────────────────────────┘   │
└───────────────────────────────│────────────────────────────────────┘
                                │ wp-ajax.php (authenticated)
┌───────────────────────────────▼────────────────────────────────────┐
│                        AJAX LAYER                                    │
│  AttendanceAjaxHandlers.php                                          │
│  - verify_attendance_nonce()    (every request)                      │
│  - require_active_class()       (capture + exception only)           │
│    └── allows stopped if session_date <= effective stop date         │
│  - handle_attendance_get_sessions()                                  │
│  - handle_attendance_capture()                                       │
│  - handle_attendance_mark_exception()                                │
│  - handle_attendance_get_detail()                                    │
│  - handle_attendance_admin_delete()                                  │
└───────────────────────────────┬────────────────────────────────────┘
                                │
┌───────────────────────────────▼────────────────────────────────────┐
│                       SERVICE LAYER                                  │
│  AttendanceService.php                                               │
│  - generateSessionList()     merges schedule + DB sessions           │
│  - captureAttendance()       writes session + calls logHours()       │
│  - markException()           writes session, zero hours              │
│  - getSessionDetail()        reads per-learner hours                 │
│  - deleteAndReverseHours()   atomic delete + recalculate             │
└──────────┬────────────────────┬────────────────────────────────────┘
           │                    │
┌──────────▼──────┐  ┌──────────▼──────────────────────────────────┐
│ AttendanceRepo  │  │ ProgressionService (logHours, recalculate)   │
│ ClassRepository │  │ LearnerProgressionRepository                  │
│ (PostgreSQL)    │  │ (PostgreSQL)                                  │
└─────────────────┘  └──────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | File |
|-----------|---------------|------|
| `ClassController` | Registers shortcode, enqueues JS, injects `WeCozaSingleClass` config | `src/Classes/Controllers/ClassController.php` |
| `AttendanceAjaxHandlers` | 5 AJAX actions, nonce guard, `require_active_class()` gate | `src/Classes/Ajax/AttendanceAjaxHandlers.php` |
| `AttendanceService` | All attendance business logic — session generation, capture, exception, delete | `src/Classes/Services/AttendanceService.php` |
| `AttendanceRepository` | DB reads/writes for `class_sessions` and `learner_hours_log` | `src/Classes/Repositories/AttendanceRepository.php` |
| `ClassRepository` | `getSingleClass()`, agent lookup, class data fetching | `src/Classes/Repositories/ClassRepository.php` |
| `ProgressionService` | `logHours()`, `recalculateHours()` — LP progress tracking | `src/Learners/Services/ProgressionService.php` |
| `attendance.php` (view) | Renders attendance card, modals, stop-date JS injection | `views/classes/components/single-class/attendance.php` |
| `attendance-capture.js` | All client-side attendance interactivity, `stopDate` gate | `assets/js/classes/attendance-capture.js` |
| `wecoza_resolve_class_status()` | Shared helper — reads `class_status`, falls back to `order_nr` | `core/Helpers/functions.php` |
| `wecoza_get_effective_stop_date()` | Extracts last stop-without-restart from `schedule_data.stop_restart_dates` | `core/Helpers/functions.php` |

---

## New vs Modified Components for v7.0

### NEW Components

| Component | Type | Purpose |
|-----------|------|---------|
| `AgentAccessController` | PHP Controller | Registers shortcode `[wecoza_agent_attendance]`, validates token, renders attendance page |
| `AgentTokenService` | PHP Service | Issues tokens (WP transient), validates tokens, maps token → agent_id + class_id |
| `AgentAttendanceAjaxHandlers` | PHP | AJAX variant of attendance handlers using token auth instead of nonce + WP session |
| `agent-attendance.view.php` | PHP View | Standalone attendance page template for agents (no WP header/nav required) |
| `agent-attendance.js` | JS | Stripped-down attendance-capture.js for agent page (no admin delete, no isAdmin checks) |

**Decision: Token-based auth, not WP user accounts.** Agents are in the PostgreSQL `agents` table and are not WordPress users. Creating WP accounts would require ongoing account management (passwords, resets, roles). A token-based system (WP transient keyed to `agent_id + class_id`, URL-distributed) is simpler, auditable, and already within the WP API surface. Each token is single-use-per-session or time-limited (e.g., 7-day expiry).

### MODIFIED Components

| Component | What Changes | Why |
|-----------|-------------|-----|
| `attendance.php` (view) | Exception button: change icon-only `<i>` to labelled `<button>` with text | UX improvement — current implementation uses icon only per `getActionButton()` in JS |
| `attendance-capture.js` | `getActionButton()`: exception button already has text "Exception" (line 474) — but the trigger may be icon-only elsewhere | Verify and update if the view-level exception trigger is icon-only |
| `require_active_class()` in `AttendanceAjaxHandlers.php` | Already handles stopped-class exception (session_date <= stop_date). May need to confirm JS-side `stopDate` gate aligns | JS injects `stopDate` from view, server re-validates — both gates must agree |
| `ClassController::enqueueAndLocalizeSingleClassScript()` | No change needed for stopped-class capture — logic already written | stopDate already passed to JS via `WeCozaSingleClass` if class is stopped |

---

## Recommended Project Structure

New files slot into existing module layout:

```
src/
├── Classes/
│   ├── Ajax/
│   │   ├── AttendanceAjaxHandlers.php       (MODIFY — no change needed if stopped logic is correct)
│   │   ├── AgentAttendanceAjaxHandlers.php  (NEW — token-auth variant)
│   │   └── ClassStatusAjaxHandler.php
│   ├── Controllers/
│   │   ├── ClassController.php
│   │   └── AgentAccessController.php        (NEW — shortcode + token validate + render)
│   └── Services/
│       ├── AttendanceService.php            (no change — reused as-is)
│       └── AgentTokenService.php            (NEW — token issue/validate/revoke)
│
views/
├── classes/
│   ├── components/
│   │   └── single-class/
│   │       └── attendance.php               (MODIFY — exception button UX)
│   └── agent-attendance.view.php            (NEW — standalone agent page)
│
assets/
└── js/
    └── classes/
        ├── attendance-capture.js            (verify exception button label)
        └── agent-attendance.js              (NEW — agent-scoped JS, no admin ops)
```

---

## Architectural Patterns

### Pattern 1: Token-Based Agent Auth (WP Transient)

**What:** Generate a signed token stored as a WP transient. URL contains `?token=<hash>`. `AgentAccessController` validates on every page load. AJAX handlers validate on every request (token passed as POST param instead of WP nonce).

**When to use:** When the actor is not a WordPress user but needs time-limited, scoped access to a specific resource.

**Trade-offs:** Simple, uses existing WP infrastructure. No session management complexity. Tokens are revocable (delete transient). Downside: token in URL can be shared — mitigate with agent_id + class_id binding in the transient value.

**Implementation sketch:**

```php
// AgentTokenService::issue(int $agentId, int $classId, int $ttlSeconds = 604800): string
// Stores: ['agent_id' => $agentId, 'class_id' => $classId] under transient key "wecoza_agent_token_{$hash}"
// Returns: $hash (the URL token)

// AgentTokenService::validate(string $token): ?array
// Returns ['agent_id' => int, 'class_id' => int] or null if expired/invalid

// AgentAttendanceAjaxHandlers: replaces verify_attendance_nonce() with:
$data = AgentTokenService::validate($_POST['agent_token'] ?? '');
if (!$data) { wp_send_json_error(['message' => 'Invalid or expired token'], 403); exit; }
$agentId = $data['agent_id'];
$classId = $data['class_id'];
```

### Pattern 2: Reuse AttendanceService, New AJAX Handler Namespace

**What:** `AgentAttendanceAjaxHandlers.php` uses `wp_ajax_nopriv_` actions (agents are not logged in) and validates via token instead of nonce. It calls the same `AttendanceService` methods — no duplication of business logic.

**When to use:** When a new auth context (non-WP-user) needs the same business operations.

**Trade-offs:** Two AJAX action namespaces (agent vs standard) but single service layer. No business logic duplication. Clear separation of auth concerns.

**Example:**

```php
// Standard (logged-in WP users):
add_action('wp_ajax_wecoza_attendance_capture', __NAMESPACE__ . '\handle_attendance_capture');

// Agent page (not logged in, token auth):
add_action('wp_ajax_nopriv_wecoza_agent_attendance_capture', 'handle_agent_attendance_capture');
// handle_agent_attendance_capture() validates token, then calls same AttendanceService::captureAttendance()
```

### Pattern 3: Stopped-Class Capture — Already Implemented Server-Side

**What:** `require_active_class()` in `AttendanceAjaxHandlers.php` already allows capture when `status === 'stopped'` AND `session_date <= stop_date`. The view already gates display via `attendance.php` stop-date check and JS injection of `window.WeCozaSingleClass.stopDate`.

**When to use:** No new pattern needed. The gap (if any) is that `isAttendanceLocked` in `ClassController.php` line 507 is set to `$classStatus !== 'active'` — this passes `true` for stopped classes, which may prevent the JS from rendering capture buttons even for dates within the stop window.

**Required fix:** `isAttendanceLocked` should be `false` for stopped classes that have a valid stop date. The JS should check `stopDate` granularly per-session rather than treating the entire class as locked at the config level.

### Pattern 4: Exception Button UX — View-Level Change Only

**What:** The JS `getActionButton()` function at line 472-476 of `attendance-capture.js` already renders a labelled button:

```js
'<button class="btn btn-sm btn-phoenix-warning btn-exception" ...>'
+ '<i class="bi bi-exclamation-triangle-fill me-1"></i>Exception'
+ '</button>'
```

The exception button already has the label "Exception". If the user experience issue is that the button is icon-only, it may be a CSS issue (`btn-icon`) or a different render path. Verify the exact rendering before making changes.

---

## Data Flow

### Agent Token Auth Flow

```
Manager action (Admin)
    ↓
AgentTokenService::issue(agentId, classId)
    → stores transient: wecoza_agent_token_{hash} = {agent_id, class_id}
    → returns URL: /app/agent-attendance/?token={hash}&class_id={classId}
    ↓
Manager shares URL with agent (email/WhatsApp)
    ↓
Agent visits URL (not logged in)
    ↓
AgentAccessController::renderShortcode()
    → validates token via AgentTokenService::validate(token)
    → loads class data via ClassRepository::getSingleClass(class_id)
    → verifies agent is class_agent or backup_agent_ids for that class
    → renders agent-attendance.view.php with restricted config
    ↓
agent-attendance.js initialises
    → config: { classId, agentToken, ajaxUrl, learnerIds }
    → calls wecoza_agent_attendance_get_sessions (nopriv AJAX)
    ↓
AgentAttendanceAjaxHandlers::handle_agent_get_sessions()
    → validates token
    → calls AttendanceService::generateSessionList()
    → returns sessions JSON
    ↓
Agent submits attendance
    → POST to wecoza_agent_attendance_capture (nopriv AJAX)
    → token validated, classId from token (cannot be spoofed from POST)
    → calls AttendanceService::captureAttendance(..., capturedBy: $agentId as negative or 0)
```

**Note on capturedBy:** Standard flow uses `get_current_user_id()` (WP user ID). Agent flow has no WP user. Options:
- Pass `0` as user ID (simplest, loses agent attribution)
- Store agent_id in a separate `captured_by_agent_id` column (cleaner, requires schema change)
- Use negative agent_id as convention (`-$agentId`) in existing `captured_by` column (hacky)

**Recommended:** Add `captured_by_agent_id` column to `class_sessions` table. Pass `captured_by = 0, captured_by_agent_id = $agentId`. The schema change is a one-liner ALTER TABLE and avoids the convention hack.

### Stopped-Class Capture Fix Flow

```
Class stops → class_status = 'stopped', schedule_data.stop_restart_dates has entry with stop but no restart
    ↓
attendance.php renders:
    → wecoza_get_effective_stop_date() returns stop date
    → if stop date exists: renders full attendance card (not locked)
    → injects <script>window.WeCozaSingleClass.stopDate = 'YYYY-MM-DD'</script>
    ↓
ClassController sets isAttendanceLocked = (classStatus !== 'active')   ← BUG
    → For stopped classes with stop date: should be false
    ↓
attendance-capture.js:
    → if isAttendanceLocked: skips rendering action buttons entirely (or shows locked state)
    → if stopDate set: per-session gate in getActionButton() handles post-stop-date sessions

REQUIRED FIX in ClassController::enqueueAndLocalizeSingleClassScript():
    $stopDate = null;
    if ($classStatus === 'stopped') {
        $scheduleData = $class['schedule_data'] ?? [];
        if (is_string($scheduleData)) { $scheduleData = json_decode($scheduleData, true) ?: []; }
        $stopDate = wecoza_get_effective_stop_date($scheduleData);
    }
    'isAttendanceLocked' => $classStatus === 'draft' || ($classStatus === 'stopped' && $stopDate === null),
    'stopDate' => $stopDate,  // already injected via attendance.php script tag, but also pass here for JS config
```

### Exception Button UX Flow

```
Session table renders → getActionButton(session) called per row
    → status === 'pending': renders Capture + Exception button group
    → Exception button: btn-phoenix-warning with icon + text "Exception"

VERIFY: Is btn-phoenix-warning with icon+text actually rendering icon-only?
    → Check if CSS is hiding button text
    → Check if a different code path renders only the icon (e.g., calendar click handler)
    → May be a Phoenix CSS issue with btn-group + btn-sm collapsing text

If text is hidden by CSS: add explicit visibility rule to ydcoza-styles.css
If a second render path is icon-only: update that path to match the labelled pattern
```

---

## Integration Points

### New vs Existing — Explicit Mapping

| Feature | New or Modify | Integration Point |
|---------|--------------|-------------------|
| Agent token issuance | NEW `AgentTokenService` | Called from new admin UI or existing single-class display page (manager generates link) |
| Agent attendance page | NEW shortcode `[wecoza_agent_attendance]` | WordPress page, registered by `AgentAccessController` |
| Agent AJAX handlers | NEW `wp_ajax_nopriv_` actions | `AgentAttendanceAjaxHandlers` calls existing `AttendanceService` — no service duplication |
| `captured_by_agent_id` column | NEW DB column on `class_sessions` | Schema ALTER — one-liner, no existing query changes needed if nullable |
| Exception button label | MODIFY `attendance-capture.js` | Verify `getActionButton()` line 473-474 — may already be correct |
| Stopped-class `isAttendanceLocked` fix | MODIFY `ClassController.php` line 507 | Change boolean logic to allow stopped classes with valid stop date |
| Agent-class relationship | EXISTING | `classes.class_agent` (int, agent_id), `classes.backup_agent_ids` (JSONB array of agent_ids) |
| Token storage | EXISTING WP transient API | No new infrastructure needed |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|--------------|-------|
| AgentAccessController ↔ AgentTokenService | Direct method call | Same request cycle — validate token, get agent+class IDs |
| AgentAttendanceAjaxHandlers ↔ AttendanceService | Direct instantiation | Identical to existing `AttendanceAjaxHandlers` — same service reused |
| AgentTokenService ↔ WP transients | `get_transient / set_transient / delete_transient` | Uses `wecoza_agent_token_{hash}` key prefix |
| AgentAccessController ↔ ClassRepository | `ClassRepository::getSingleClass()` | Verify agent is linked to class before granting access |
| Agent AJAX ↔ ProgressionService | Via AttendanceService (unchanged) | Hours log attribution needs agent_id from token, not WP user |

---

## Build Order

Dependencies drive this order:

1. **Stopped-class `isAttendanceLocked` fix** (ClassController.php, ~5 lines)
   - No new files. Prerequisite: understanding of stopDate flow (done via research).
   - Unblocks: stopped classes can now render capture UI.

2. **Exception button UX audit + fix** (attendance-capture.js / attendance.php)
   - Diagnose first: is the button text already there or genuinely missing?
   - If CSS issue: one rule in `ydcoza-styles.css`.
   - If JS render path missing text: update `getActionButton()`.
   - No dependencies on other v7.0 items.

3. **AgentTokenService** (new file: `src/Classes/Services/AgentTokenService.php`)
   - Pure PHP, no view or JS dependencies.
   - Prerequisite for: `AgentAccessController`, `AgentAttendanceAjaxHandlers`.

4. **DB schema: `captured_by_agent_id` column** (ALTER TABLE, user runs manually)
   - SQL: `ALTER TABLE class_sessions ADD COLUMN captured_by_agent_id INTEGER DEFAULT NULL;`
   - Prerequisite for: agent AJAX handlers that record attribution.

5. **AgentAttendanceAjaxHandlers** (new file: `src/Classes/Ajax/AgentAttendanceAjaxHandlers.php`)
   - Depends on: `AgentTokenService` (step 3), `AttendanceService` (existing).
   - Uses `wp_ajax_nopriv_` actions. Token replaces nonce. ClassId comes from token, not POST.

6. **AgentAccessController + shortcode** (new file: `src/Classes/Controllers/AgentAccessController.php`)
   - Depends on: `AgentTokenService` (step 3), `ClassRepository` (existing).
   - Registers `[wecoza_agent_attendance]` shortcode.
   - Validates token, verifies agent-class link, renders view.

7. **`agent-attendance.view.php`** (new file: `views/classes/agent-attendance.view.php`)
   - Stripped version of `attendance.php` — no admin delete, no class management UI.
   - Depends on: `AgentAccessController` rendering context (step 6).

8. **`agent-attendance.js`** (new file: `assets/js/classes/agent-attendance.js`)
   - Stripped version of `attendance-capture.js` — uses `agentToken` instead of nonce, no admin ops.
   - Depends on: `AgentAttendanceAjaxHandlers` actions existing (step 5).

9. **Manager "Generate Link" UI** (modify single-class display — add link generator to staff/agent section)
   - Depends on: `AgentTokenService` and `AgentAccessController` (steps 3 + 6).
   - Could be deferred if a manual SQL token generation is acceptable for MVP.

---

## Anti-Patterns

### Anti-Pattern 1: Creating WordPress User Accounts for Agents

**What people do:** Register each agent as a WP subscriber or custom-role user so they can authenticate via WP login.

**Why it's wrong:** Agents are already managed in the PostgreSQL `agents` table. Dual-record maintenance (WP users + PostgreSQL agents) creates sync problems. Password resets, email verification, and role management add significant ongoing ops overhead. The WeCoza CLAUDE.md explicitly states agents are NOT WordPress users.

**Do this instead:** Token-based access via WP transients. Tokens are issued per-class, time-limited, revocable. No WP account needed.

### Anti-Pattern 2: Duplicating AttendanceService Logic in Agent Handlers

**What people do:** Copy-paste `handle_attendance_capture()` into `AgentAttendanceAjaxHandlers`, change auth, ship it.

**Why it's wrong:** Business logic diverges. Bug fixes to one don't propagate to the other. Violates DRY principle documented in CLAUDE.md.

**Do this instead:** Agent AJAX handlers do auth (token validation) then call the same `AttendanceService` methods. Auth concern and business logic remain separate.

### Anti-Pattern 3: Passing Agent ID as WP User ID to `captureAttendance()`

**What people do:** Pass `$agentId` (PostgreSQL agent PK) as the `$capturedBy` parameter which the existing code treats as a WP user ID.

**Why it's wrong:** `$capturedBy` is used in `class_status_history` to resolve `get_userdata()`. Passing a PostgreSQL agent_id as a WP user ID will silently return wrong data or null when the status history resolver runs `get_userdata($captured_by)`.

**Do this instead:** Add `captured_by_agent_id` column. Pass `capturedBy: 0` (anonymous WP user) and separately record the agent attribution. Update display logic to show agent name when `captured_by_agent_id` is set.

### Anti-Pattern 4: Using `isAttendanceLocked` as a Global Class-Level Gate in JS

**What people do:** Set `isAttendanceLocked = true` for all stopped classes and skip rendering action buttons entirely.

**Why it's wrong:** Stopped classes should allow capture for sessions up to the effective stop date. A global lock prevents the per-session `stopDate` gate from doing its job.

**Do this instead:** Set `isAttendanceLocked = true` only for draft classes or stopped classes with no valid stop date. For stopped classes with a stop date, pass `isAttendanceLocked = false` and let the per-session `stopDate` check in `getActionButton()` handle the gate.

---

## Sources

- Direct inspection: `src/Classes/Ajax/AttendanceAjaxHandlers.php` — `require_active_class()`, `verify_attendance_nonce()`, all handler functions
- Direct inspection: `src/Classes/Services/AttendanceService.php` — full business logic surface
- Direct inspection: `src/Classes/Controllers/ClassController.php` lines 467-516 — JS config injection, `isAttendanceLocked` assignment
- Direct inspection: `assets/js/classes/attendance-capture.js` lines 458-489 — `getActionButton()`, `stopDate` gate
- Direct inspection: `views/classes/components/single-class/attendance.php` — UI gate logic, stop-date injection script tag
- Direct inspection: `core/Helpers/functions.php` lines 463-519 — `wecoza_resolve_class_status()`, `wecoza_get_effective_stop_date()`
- Direct inspection: `src/Agents/Models/AgentModel.php` — agent data structure, `agents` table schema
- Direct inspection: `src/Classes/Repositories/ClassRepository.php` — `class_agent`, `backup_agent_ids` fields
- Direct inspection: `.planning/PROJECT.md` — v7.0 milestone scope, existing architecture decisions

---

*Architecture research for: WeCoza Core v7.0 — Agent Attendance Access*
*Researched: 2026-03-04*
