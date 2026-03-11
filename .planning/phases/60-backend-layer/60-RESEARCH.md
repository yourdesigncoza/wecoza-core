# Phase 60: Backend Layer - Research

**Researched:** 2026-03-11
**Domain:** PHP service layer / AJAX endpoints / PostgreSQL business logic (WeCoza Core plugin)
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ORD-02 | Agent order created automatically when class has order_nr and assigned agent | Hook into `saveClassAjax()` post-save path; `AgentOrderService::ensureOrderExists()` does the upsert via ON CONFLICT DO NOTHING |
| INV-03 | System calculates discrepancy between claimed and calculated payable hours | `AgentInvoiceService::calculateMonthSummary()` computes all fields; discrepancy stored on `handleInvoiceSubmit()` as `agent_claimed_hours - calculated_payable_hours` |
</phase_requirements>

---

## Summary

Phase 60 adds the PHP backend layer on top of the two tables created in Phase 59 (`agent_orders`, `agent_monthly_invoices`). It has two concerns: (1) **auto-create agent orders** when a class save occurs with both `order_nr` and `class_agent` set, and (2) **invoice calculation and submission logic** including the six AJAX endpoints that the UI (Phase 62) will consume.

The auto-creation hook must be wired inside `ClassAjaxController::saveClassAjax()` immediately after `$result = $class->save()` / `$class->update()` succeeds. The pattern already exists for LP auto-creation (`self::createLPsForNewLearners()`) and for event dispatching — follow the same hook-in-place approach.

All six AJAX endpoints should live in a new `AgentOrdersAjaxHandlers` class under `src/Agents/Ajax/`, bootstrapped in `wecoza-core.php` with the same `class_exists` guard pattern used for `AgentsAjaxHandlers`.

**Primary recommendation:** Two new service classes (`AgentOrderService`, `AgentInvoiceService`) + two new repository classes (`AgentOrderRepository`, `AgentInvoiceRepository`) + one new AJAX handler class (`AgentOrdersAjaxHandlers`). All wired into the plugin bootstrap.

---

## Standard Stack

### Core
| Component | Pattern | Purpose |
|-----------|---------|---------|
| `BaseRepository` | Extend, column whitelisting | Raw DB access for agent_orders and agent_monthly_invoices |
| `AjaxSecurity` | `requireNonce()`, `requireCapability()`, `post()`, `sendSuccess()`, `sendError()` | Nonce + capability enforcement on every endpoint |
| `wecoza_db()` | `PostgresConnection::getInstance()` | Direct queries for complex JOIN/aggregate logic |
| `check_ajax_referer()` / `wp_send_json_success()` | Procedural style (attendance pattern) | Alt style — but prefer `AjaxSecurity` class style |

### Nonce Name Convention
- Attendance uses `'wecoza_attendance_nonce'` (string literal checked via `check_ajax_referer`)
- Agents module uses `'agents_nonce_action'` via `AjaxSecurity::requireNonce()`
- **For Phase 60:** use `'wecoza_orders_nonce'` — consistent with attendance pattern, distinct scope

### Capability Convention
| Action | Required Capability |
|--------|-------------------|
| Order save (admin only) | `'edit_others_posts'` (reuse agents pattern) or `'manage_options'` |
| Invoice calculate (agent views own) | `'capture_attendance'` (agents already have this) |
| Invoice submit (agent) | `'capture_attendance'` |
| Invoice review (admin) | `'manage_options'` |
| Invoice list (admin) | `'manage_options'` |

---

## Architecture Patterns

### Where New Files Live

```
src/Agents/
├── Ajax/
│   ├── AgentsAjaxHandlers.php          # existing
│   └── AgentOrdersAjaxHandlers.php     # NEW — six AJAX endpoints
├── Repositories/
│   ├── AgentRepository.php             # existing
│   ├── AgentOrderRepository.php        # NEW — agent_orders table
│   └── AgentInvoiceRepository.php      # NEW — agent_monthly_invoices table
└── Services/
    ├── AgentService.php                # existing
    ├── AgentOrderService.php           # NEW — order auto-create, upsert logic
    └── AgentInvoiceService.php         # NEW — calculateMonthSummary(), submit, review
```

### Pattern 1: Repository — Extend BaseRepository

```php
// src/Agents/Repositories/AgentOrderRepository.php
namespace WeCoza\Agents\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

class AgentOrderRepository extends BaseRepository
{
    protected static string $table = 'agent_orders';
    protected static string $primaryKey = 'order_id';

    protected function getAllowedInsertColumns(): array
    {
        return ['class_id', 'agent_id', 'rate_type', 'rate_amount', 'start_date',
                'end_date', 'notes', 'created_at', 'updated_at', 'created_by'];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return ['rate_amount', 'rate_type', 'end_date', 'notes', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['order_id', 'class_id', 'agent_id', 'status', 'start_date'];
    }

    protected function getAllowedOrderColumns(): array
    {
        return ['order_id', 'class_id', 'agent_id', 'start_date', 'created_at'];
    }

    /**
     * Find most-recent active order for a class+agent combination.
     * "Active" = end_date IS NULL or end_date >= today.
     */
    public function findActiveOrderForClass(int $classId, int $agentId): ?array
    {
        $sql = "SELECT * FROM agent_orders
                WHERE class_id = :class_id AND agent_id = :agent_id
                  AND (end_date IS NULL OR end_date >= CURRENT_DATE)
                ORDER BY start_date DESC
                LIMIT 1";
        // ...
    }

    /**
     * Upsert: insert order if none exists for class_id+agent_id+start_date.
     * ON CONFLICT DO NOTHING — safe to call on every class save.
     */
    public function ensureOrderExists(int $classId, int $agentId, string $startDate, int $createdBy): ?int
    {
        $sql = "INSERT INTO agent_orders (class_id, agent_id, rate_type, rate_amount, start_date, created_at, updated_at, created_by)
                VALUES (:class_id, :agent_id, 'hourly', 0.00, :start_date, NOW(), NOW(), :created_by)
                ON CONFLICT (class_id, agent_id, start_date) DO NOTHING
                RETURNING order_id";
        // Returns null if row already existed (DO NOTHING path), fetch existing order_id separately if needed
    }
}
```

### Pattern 2: AgentInvoiceRepository — month-scoped queries

```php
class AgentInvoiceRepository extends BaseRepository
{
    protected static string $table = 'agent_monthly_invoices';
    protected static string $primaryKey = 'invoice_id';

    /**
     * Find or create draft invoice for a given order+month.
     * Uses ON CONFLICT DO NOTHING + SELECT to guarantee idempotency.
     */
    public function findOrCreateDraft(int $orderId, int $classId, int $agentId, string $invoiceMonth): array
    { ... }

    /**
     * Get all sessions for a class within a calendar month from class_attendance_sessions.
     * Used by AgentInvoiceService::calculateMonthSummary().
     * NOTE: This crosses module boundary — query attendance table directly.
     */
    public function getSessionsForMonth(int $classId, string $invoiceMonth): array
    {
        $sql = "SELECT session_id, session_date, scheduled_hours, status, learner_data
                FROM class_attendance_sessions
                WHERE class_id = :class_id
                  AND session_date >= :month_start
                  AND session_date < :month_end
                ORDER BY session_date ASC";
        // month_start = first day, month_end = first day of next month
    }
}
```

### Pattern 3: AgentOrderService — hook-in-place for auto-create

The auto-create hook must fire inside `ClassAjaxController::saveClassAjax()` after `$result = true`. The existing pattern for LP creation is the direct model:

```php
// In ClassAjaxController::saveClassAjax(), after $result check:
// Existing:
self::dispatchClassEvents($class, $isUpdate, $oldClassData, $oldLearnerIds);
self::createLPsForNewLearners($class, $formData, $isUpdate);

// Add after the above:
self::ensureAgentOrderExists($class);  // NEW static helper method
```

```php
// Add private static method to ClassAjaxController:
private static function ensureAgentOrderExists(\WeCoza\Classes\Models\ClassModel $class): void
{
    $classData = $class->toArray();
    $orderNr   = $classData['order_nr'] ?? null;
    $agentId   = $classData['class_agent'] ?? null;

    // Only create order when BOTH order_nr AND class_agent are set
    if (empty($orderNr) || empty($agentId)) {
        return;
    }

    try {
        $service = new \WeCoza\Agents\Services\AgentOrderService();
        $service->ensureOrderForClass(
            (int) $class->getId(),
            (int) $agentId,
            $classData['original_start_date'] ?? null
        );
    } catch (\Throwable $e) {
        // Non-blocking — log but don't fail the class save
        wecoza_log('AgentOrderService::ensureOrderForClass failed: ' . $e->getMessage(), 'error');
    }
}
```

**Key constraint:** This must be non-blocking. A failure in order creation must NOT cause the class save to fail. Log and continue.

### Pattern 4: AgentInvoiceService::calculateMonthSummary()

```php
/**
 * Calculate monthly invoice summary for a class+agent+month.
 *
 * @param int    $classId      Class ID
 * @param int    $agentId      Agent ID (used to look up active order)
 * @param string $invoiceMonth First day of month (Y-m-01)
 * @return array {
 *   class_hours_total: float,
 *   all_absent_days: int,
 *   all_absent_hours: float,
 *   calculated_payable_hours: float,
 *   sessions: array   // raw session breakdown for UI display
 * }
 */
public function calculateMonthSummary(int $classId, int $agentId, string $invoiceMonth): array
{
    // 1. Get sessions for month from class_attendance_sessions
    $sessions = $this->invoiceRepo->getSessionsForMonth($classId, $invoiceMonth);

    // 2. Sum class_hours_total = sum of scheduled_hours for all sessions
    // 3. Detect all-absent sessions:
    //    A session is "all-absent" when status = 'captured' AND
    //    ALL learner entries in learner_data JSON have hours_present = 0
    //    OR when status = 'agent_absent' (exception type)
    // 4. Sum all_absent_hours from those sessions
    // 5. calculated_payable_hours = class_hours_total - all_absent_hours

    return [
        'class_hours_total' => ...,
        'all_absent_days'   => ...,
        'all_absent_hours'  => ...,
        'calculated_payable_hours' => ...,
        'sessions' => ...,
    ];
}
```

**Critical detail on "all-absent" detection:** The `learner_data` column in `class_attendance_sessions` is stored as a JSON string. It's an array of `{learner_id, hours_present, page_number}` objects (see `AttendanceService::captureAttendance()`). An all-absent session = status is `'captured'` AND every entry's `hours_present == 0`. Sessions with status `'agent_absent'` are also all-absent by definition (agent didn't attend — no learners taught). `'client_cancelled'` sessions should NOT count as all-absent for payable hours purposes — this needs a decision (see Open Questions).

### Pattern 5: AJAX endpoint structure (AgentOrdersAjaxHandlers)

Follow `AgentsAjaxHandlers` class style (not procedural functions like attendance). All six endpoints:

```
wp_ajax_wecoza_order_save         → handleOrderSave()   [POST, manage_options]
wp_ajax_wecoza_order_get          → handleOrderGet()    [GET, edit_others_posts]
wp_ajax_wecoza_invoice_calculate  → handleCalculate()   [GET, capture_attendance]
wp_ajax_wecoza_invoice_submit     → handleSubmit()      [POST, capture_attendance]
wp_ajax_wecoza_invoice_review     → handleReview()      [POST, manage_options]
wp_ajax_wecoza_invoice_list       → handleList()        [GET, manage_options]
```

All use `AjaxSecurity::requireNonce('wecoza_orders_nonce')` as first line.

### Bootstrap Registration

In `wecoza-core.php` plugins_loaded block, alongside existing Agents module init:

```php
// Add after existing AgentsAjaxHandlers registration:
if (class_exists(\WeCoza\Agents\Ajax\AgentOrdersAjaxHandlers::class)) {
    new \WeCoza\Agents\Ajax\AgentOrdersAjaxHandlers();
}
```

### Anti-Patterns to Avoid

- **Don't inject AgentOrderService into ClassAjaxController constructor** — ClassAjaxController uses static methods throughout; keep the hook as a private static method calling the service inline.
- **Don't fail class save on order creation errors** — wrap in try/catch and log only.
- **Don't recalculate all-absent by querying learner_hours_log** — the `learner_data` JSON column on `class_attendance_sessions` is the source of truth for session-level hours; use it directly.
- **Don't use BaseRepository::insert() for the upsert** — BaseRepository has no ON CONFLICT support; write raw SQL in `AgentOrderRepository::ensureOrderExists()`.
- **Don't create invoice rows eagerly on class save** — invoices are created lazily on `wecoza_invoice_calculate` request or explicitly on `wecoza_invoice_submit`. Only orders are auto-created.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Nonce + capability check | Custom verification | `AjaxSecurity::requireNonce()` + `AjaxSecurity::requireCapability()` |
| Safe JSON response | Manual `echo json_encode` | `AjaxSecurity::sendSuccess()` / `AjaxSecurity::sendError()` |
| Input sanitization | Manual filter_var | `AjaxSecurity::post()` with type arg |
| Column injection prevention | String escaping | BaseRepository column whitelisting pattern |
| Upsert "insert if not exists" | PHP-side existence check | PostgreSQL `ON CONFLICT DO NOTHING` |

---

## Common Pitfalls

### Pitfall 1: Multiple active orders for same class+agent

**What goes wrong:** `findActiveOrderForClass()` returns multiple rows when there are overlapping rate periods (end_date not set on old row).

**Why it happens:** Migration seeds rows with `end_date = NULL`. When a second order is created for a rate change, the old one still has `end_date = NULL`.

**How to avoid:** `AgentOrderService::ensureOrderForClass()` should only insert if NO active order exists (`end_date IS NULL OR end_date >= today`). The `ON CONFLICT DO NOTHING` prevents duplicates at the DB level for the exact same `(class_id, agent_id, start_date)` triple.

**Warning signs:** `calculateMonthSummary()` returns inflated hours because it joined on multiple order rows.

### Pitfall 2: learner_data JSON null for exception sessions

**What goes wrong:** `calculateMonthSummary()` treats `agent_absent` or `client_cancelled` sessions as all-absent because `learner_data` is NULL on those rows (they're exceptions, not captures).

**Why it happens:** `markException()` in `AttendanceService` creates sessions with `learner_data = null` and status `'agent_absent'` or `'client_cancelled'`.

**How to avoid:** Branch the all-absent detection logic on session status:
- `status = 'captured'`: parse `learner_data` JSON and check if all `hours_present == 0`
- `status = 'agent_absent'`: count as all-absent (zero hours for agent)
- `status = 'client_cancelled'`: policy decision (see Open Questions) — default: NOT all-absent (client cancelled, not agent's fault)
- `status = 'pending'`: not yet captured — exclude from calculations

### Pitfall 3: invoice_month date format

**What goes wrong:** Passing `'2026-03'` as month string instead of `'2026-03-01'` breaks PostgreSQL DATE comparisons.

**Why it happens:** The column type is `DATE`, stored as first-day-of-month. AJAX input from JS typically sends `'2026-03'`.

**How to avoid:** In AJAX handler, normalize month input: `$invoiceMonth = date('Y-m-01', strtotime($rawMonth . '-01'))`. Validate with regex `'/^\d{4}-\d{2}$/'` before normalizing.

### Pitfall 4: Class has no attendance sessions yet

**What goes wrong:** `calculateMonthSummary()` returns zeros for a newly active class — caller might misinterpret as error.

**Why it happens:** No sessions captured yet in `class_attendance_sessions` for that month.

**How to avoid:** Return the summary with all zeros and include a `sessions_captured: 0` count in the response. The AJAX endpoint returns 200 with zeros, not an error.

### Pitfall 5: AJAX handler not bootstrapped for agents who aren't admins

**What goes wrong:** `wp_ajax_wecoza_invoice_calculate` fires for `wp_agent` users but `wp_ajax_nopriv_` is never needed (site requires login).

**How to avoid:** Only register `add_action('wp_ajax_...')` — never `wp_ajax_nopriv_`. This is consistent with all existing handlers in the codebase.

---

## Code Examples

### AgentOrderRepository::ensureOrderExists (raw upsert)

```php
// Source: Phase 59 schema — UNIQUE(class_id, agent_id, start_date)
public function ensureOrderExists(
    int $classId,
    int $agentId,
    string $startDate,
    int $createdBy
): ?int {
    // Try to insert; skip silently if duplicate
    $insertSql = "INSERT INTO agent_orders
                    (class_id, agent_id, rate_type, rate_amount, start_date, created_at, updated_at, created_by)
                  VALUES (:class_id, :agent_id, 'hourly', 0.00, :start_date, NOW(), NOW(), :created_by)
                  ON CONFLICT (class_id, agent_id, start_date) DO NOTHING";

    $this->db->query($insertSql, [
        'class_id'   => $classId,
        'agent_id'   => $agentId,
        'start_date' => $startDate,
        'created_by' => $createdBy,
    ]);

    // Fetch order_id regardless of insert/skip
    $selectSql = "SELECT order_id FROM agent_orders
                  WHERE class_id = :class_id AND agent_id = :agent_id
                  ORDER BY start_date DESC LIMIT 1";

    $stmt = $this->db->query($selectSql, [
        'class_id' => $classId,
        'agent_id' => $agentId,
    ]);

    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $row ? (int) $row['order_id'] : null;
}
```

### calculateMonthSummary — all-absent detection from learner_data JSON

```php
// Source: AttendanceService::captureAttendance() — learner_data is json_encode(array of {learner_id, hours_present, page_number})
private function isAllAbsentSession(array $session): bool
{
    if ($session['status'] === 'agent_absent') {
        return true;
    }

    if ($session['status'] !== 'captured') {
        return false; // pending, client_cancelled — not counted
    }

    $learnerData = $session['learner_data'];
    if (empty($learnerData)) {
        return false; // captured with no learner data — edge case, not all-absent
    }

    if (is_string($learnerData)) {
        $learnerData = json_decode($learnerData, true) ?: [];
    }

    foreach ($learnerData as $entry) {
        $hoursPresent = (float) ($entry['hours_present'] ?? 0);
        if ($hoursPresent > 0) {
            return false; // At least one learner present
        }
    }

    return true; // All learners have 0 hours
}
```

### AjaxSecurity pattern (from AgentsAjaxHandlers — HIGH confidence)

```php
// Source: src/Agents/Ajax/AgentsAjaxHandlers.php
public function handleOrderGet(): void
{
    AjaxSecurity::requireNonce('wecoza_orders_nonce');

    $orderId = AjaxSecurity::post('order_id', 'int', 0);
    // OR for GET:
    $orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if ($orderId <= 0) {
        AjaxSecurity::sendError('Invalid order ID.', 400);
    }

    try {
        $order = $this->orderService->getOrder($orderId);
        if (!$order) {
            AjaxSecurity::sendError('Order not found.', 404);
        }
        AjaxSecurity::sendSuccess(['order' => $order]);
    } catch (\Throwable $e) {
        wecoza_log('Error fetching order: ' . $e->getMessage(), 'error');
        AjaxSecurity::sendError('An error occurred.', 500);
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Procedural AJAX (AttendanceAjaxHandlers.php) | Class-based AJAX (AgentsAjaxHandlers) | Agents module uses class — follow for Phase 60 |
| Direct DB in controller | Service layer + Repository | All new code uses Service/Repository separation |
| MySQL wpdb | PostgreSQL wecoza_db() / PDO | All queries must use wecoza_db(), not $wpdb |

---

## Open Questions

1. **`client_cancelled` sessions in payable hours calculation**
   - What we know: `client_cancelled` means client cancelled the session (not agent's fault)
   - What's unclear: Should these hours be paid (client cancelled but agent was available) or not paid (no teaching occurred)?
   - Recommendation: Default to NOT counting `client_cancelled` as all-absent (agent should be paid for availability). Confirm with business rules before implementing. If wrong, only one line in `isAllAbsentSession()` changes.

2. **`wecoza_invoice_calculate` — creates draft row or returns pure calculation?**
   - What we know: The success criterion says "returns correct values for any class+agent+month"
   - What's unclear: Does calling calculate also persist a draft invoice row, or is it pure read?
   - Recommendation: Calculate is pure read — no DB write. The draft row is created only on `wecoza_invoice_submit`. This avoids stale draft rows accumulating.

3. **Agent capability for order endpoints**
   - `wp_agent` role currently has `capture_attendance` — do they also need to read their own orders?
   - Recommendation: `handleOrderGet()` and `handleCalculate()` allow `capture_attendance`; `handleOrderSave()` requires `edit_others_posts` (admin only). Agents can view but not edit orders.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected — no test directory, no pytest.ini, no jest.config |
| Config file | None — Wave 0 gap |
| Quick run command | N/A |
| Full suite command | N/A |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ORD-02 | Order auto-created when class has order_nr + class_agent | Integration | Manual verification via DB query | ❌ Wave 0 |
| INV-03 | Discrepancy = claimed - calculated stored on submit | Integration | Manual verification via DB query | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** Manual DB inspection (no automated test infrastructure)
- **Per wave merge:** Verify via read-only MCP SQL queries against agent_orders and agent_monthly_invoices
- **Phase gate:** All six AJAX endpoints return expected JSON via browser network tab before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] No PHP unit test framework in place — test by DB inspection and manual AJAX calls
- [ ] Verification strategy: use MCP read-only queries to confirm DB state after triggering class save and invoice submit actions

---

## Sources

### Primary (HIGH confidence)
- `schema/001_create_agent_orders.sql` — table structure, constraints, UNIQUE key
- `schema/002_create_agent_monthly_invoices.sql` — table structure, status enum, FK relationships
- `src/Agents/Ajax/AgentsAjaxHandlers.php` — AjaxSecurity usage pattern
- `src/Agents/Services/AgentService.php` — service layer pattern
- `core/Abstract/BaseRepository.php` — repository base class, column whitelisting
- `src/Classes/Controllers/ClassAjaxController.php` — hook-in-place pattern for LP auto-creation
- `src/Classes/Ajax/AttendanceAjaxHandlers.php` — procedural AJAX pattern (use class pattern instead)
- `src/Classes/Services/AttendanceService.php` — learner_data JSON structure
- `src/Classes/Repositories/AttendanceRepository.php` — attendance table access pattern
- `wecoza-core.php` — bootstrap registration pattern

### Secondary (MEDIUM confidence)
- `.planning/STATE.md` — accumulated project decisions about rate changes and denormalization
- `.planning/phases/59-database-schema/59-01-PLAN.md` — schema field definitions and constraints

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — codebase itself is the reference; all patterns verified from existing files
- Architecture: HIGH — established patterns (BaseRepository, AjaxSecurity, service layer) exist and are consistent
- Pitfalls: HIGH — identified from actual code paths (learner_data JSON, ON CONFLICT, status enum)
- Open questions: MEDIUM — require one-line policy decisions, won't block implementation

**Research date:** 2026-03-11
**Valid until:** 2026-04-10 (stable codebase, low churn risk)
