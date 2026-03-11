# Phase 61: All-Absent Confirmation - Research

**Researched:** 2026-03-11
**Domain:** JavaScript / jQuery — UX guard in attendance-capture.js
**Confidence:** HIGH

---

## Summary

Phase 61 is a pure front-end change. No PHP, no AJAX endpoint, no database work. The entire implementation lives inside `assets/js/classes/attendance-capture.js`.

The `submitCapture()` function already collects every learner's `hours_present` value into a `learnerHours` array before firing the AJAX call. The guard simply needs to inspect that array for the all-zero case and, if true, prompt the agent with `window.confirm()` before proceeding. If the agent cancels, the button is re-enabled and the function returns early. If the agent confirms, the existing AJAX path runs unchanged.

The STATE.md decision log records: *"all_absent detection is pure JS (UX guard); calculation enforced server-side in AgentInvoiceService"*. This means the guard is advisory — it never blocks server-side saving; it only asks the agent to confirm intentional all-absent recording.

**Primary recommendation:** Insert a `window.confirm()` check inside `submitCapture()`, immediately after validation passes and before the `$.ajax()` call, using the established native-dialog pattern already present in `attendance-capture.js` (line 1054) and `single-class-display.js`.

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ATT-01 | System detects when all learners have 0 hours present in a capture | `learnerHours` array is already populated at the validation-passed point in `submitCapture()`; check `every(l => l.hours_present === 0)` there |
| ATT-02 | Agent confirms "all learners absent" via prompt before submission | `window.confirm()` is the established pattern for advisory confirmations in this codebase; call it after ATT-01 detection, re-enable button on cancel |
</phase_requirements>

---

## Standard Stack

### Core (this phase only uses what already exists)

| Asset | Location | Purpose |
|-------|----------|---------|
| `attendance-capture.js` | `assets/js/classes/attendance-capture.js` | All logic lives here; no new file needed |
| `attendance.php` | `views/classes/components/single-class/attendance.php` | No changes required |
| jQuery + Bootstrap 5 | Already loaded on page | `window.confirm()` needs neither |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `window.confirm()` | Bootstrap 5 modal | Modal gives richer UI but requires new HTML + view file change + async flow; `window.confirm()` matches existing pattern, is synchronous, and requires zero markup |

**Decision:** Use `window.confirm()`. It is already used for the admin-delete confirm in the same file (line 1054) and for class-delete, reactivate, and other confirmations throughout the JS codebase. A Bootstrap modal would be disproportionate for a simple advisory warning.

---

## Architecture Patterns

### Where the check lives

```
submitCapture()
  ├─ disable button + show spinner          (already exists)
  ├─ collect & validate learnerHours[]      (already exists — lines 788–830)
  ├─ if !isValid → re-enable, return        (already exists)
  ├─ [NEW] detect all-zero hours_present    ← ATT-01 insert point
  ├─ [NEW] if all-zero → window.confirm()   ← ATT-02
  │         cancelled → re-enable, return
  └─ $.ajax(...)                            (already exists — line 832)
```

### Pattern: Native Confirm before AJAX (existing precedent)

**What:** Synchronous `window.confirm()` returns `true`/`false`. On `false`, re-enable submit button and return early. On `true`, fall through to the AJAX call.

**Precedent in codebase:**
```javascript
// attendance-capture.js line 1054 — adminDeleteSession()
if (!window.confirm('Are you sure? This will reverse all hours logged for this session.')) {
    return;
}
```

**Applied to submitCapture:**
```javascript
// After isValid check, before $.ajax()
const allAbsent = learnerHours.length > 0 &&
    learnerHours.every(function(l) { return l.hours_present === 0; });

if (allAbsent) {
    if (!window.confirm(
        'All learners have 0 hours present for this session.\n\n' +
        'Do you want to record this as an all-absent session?'
    )) {
        $btn.prop('disabled', false).html(
            '<i class="bi bi-check-lg me-1"></i>' + btnLabel
        );
        return;
    }
}
```

### Anti-Patterns to Avoid

- **Checking before validation:** The all-zero check must come AFTER `isValid` is confirmed. Checking earlier could trigger the dialog even if some inputs are invalid (e.g., a NaN input would appear as 0).
- **Async dialog / Bootstrap modal:** Would require converting the synchronous submit flow into a callback-driven flow — unnecessary complexity for an advisory guard.
- **Re-disabling the button inside the confirm block:** The button is already disabled at the top of `submitCapture()`. Only the `return` path needs to re-enable it; the AJAX path already handles re-enable on error.
- **Counting `hours_absent` field:** The server derives `hours_absent = hours_trained - hours_present`. Only `hours_present` values from the form inputs matter for this check.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Confirmation dialog | Custom modal, alert library | `window.confirm()` (already used) |
| All-zero detection | Complex aggregation | `Array.prototype.every()` one-liner |

---

## Common Pitfalls

### Pitfall 1: Button state mismatch on cancel
**What goes wrong:** After `window.confirm()` returns `false`, the button remains disabled (it was disabled at the top of `submitCapture()`). Agent cannot retry submission.
**How to avoid:** Explicitly re-enable the button and restore its label before `return` — same pattern used in `isValid` failure at line 824–830.

### Pitfall 2: Triggering on empty learner list
**What goes wrong:** If `learnerHours` is empty (no enrolled learners), `[].every(...)` returns `true` vacuously. This would show the dialog even when the button was already disabled.
**How to avoid:** Guard with `learnerHours.length > 0` before `every()`. (If `learnerIds.length === 0`, the button is already disabled by `buildCaptureRows()` at line 687 — but defensive check is cleaner.)

### Pitfall 3: Edge case — single learner with 0 hours
**What goes wrong:** A class with one learner recording 0 hours is a legitimate all-absent scenario. The check should still trigger.
**How to avoid:** No special handling needed; `every()` works correctly for a single-element array.

### Pitfall 4: Floating point comparison
**What goes wrong:** `hours_present` is `parseFloat()` — values like `0.0` must compare as zero.
**How to avoid:** Use `l.hours_present === 0` — `parseFloat('0')` and `parseFloat('0.0')` both produce the JavaScript number `0`, which strictly equals `0`.

---

## Code Examples

### Insertion point (verified from source)

```javascript
// Source: assets/js/classes/attendance-capture.js — submitCapture(), after line 830

// [ATT-01] Detect all-absent session
const allAbsent = learnerHours.length > 0 &&
    learnerHours.every(function(l) { return l.hours_present === 0; });

// [ATT-02] Prompt agent to confirm before firing AJAX
if (allAbsent) {
    if (!window.confirm(
        'All learners have 0 hours present for this session.\n\n' +
        'Do you want to record this as an all-absent session?'
    )) {
        $btn.prop('disabled', false).html(
            '<i class="bi bi-check-lg me-1"></i>' + btnLabel
        );
        return;
    }
}

$.ajax({ ... }); // existing call — unchanged
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| No guard | All-absent guard via `window.confirm()` | Phase 61 adds this |

**Project decision (STATE.md):** Detection is pure JS UX guard. Server side (AgentInvoiceService) enforces business rules independently. The guard does not change what gets saved — it only asks the agent to confirm.

---

## Open Questions

None. Requirements are fully specified and the implementation surface is well-understood.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Manual browser testing (no automated JS test suite detected) |
| Config file | none |
| Quick run command | Open single class page, trigger capture modal, set all hours to 0, click Submit |
| Full suite command | Same — one scenario covers both ATT-01 and ATT-02 |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Verification |
|--------|----------|-----------|-------------|
| ATT-01 | Dialog appears when all learners have 0 hours present | Manual smoke | Set all hours-present inputs to 0, click Submit — confirm dialog appears |
| ATT-01 | Dialog does NOT appear when at least one learner has hours > 0 | Manual smoke | Set one learner's hours to 1, others to 0, click Submit — no dialog |
| ATT-02 | Cancelling dialog re-enables button; no AJAX fired | Manual smoke | Click Cancel in dialog — button re-enables, no network request (DevTools) |
| ATT-02 | Confirming dialog proceeds to submit normally | Manual smoke | Click OK in dialog — session saved as usual |

### Wave 0 Gaps

None — this phase modifies a single existing JS function. No new test infrastructure required.

---

## Sources

### Primary (HIGH confidence)
- Direct source read of `assets/js/classes/attendance-capture.js` — `submitCapture()` function (lines 778–863), `adminDeleteSession()` confirm pattern (line 1054), `showModal`/`hideModal` utilities (lines 1175–1192)
- Direct source read of `views/classes/components/single-class/attendance.php` — modal HTML structure confirming no changes needed
- `.planning/STATE.md` — explicit project decision: *"all_absent detection is pure JS (UX guard)"*
- `.planning/REQUIREMENTS.md` — ATT-01, ATT-02 definitions

### Secondary (MEDIUM confidence)
- Codebase-wide `window.confirm()` usage survey confirms native dialog is the established project pattern for advisory confirmations

---

## Metadata

**Confidence breakdown:**
- Implementation location: HIGH — single function, confirmed by direct source read
- Pattern choice (`window.confirm`): HIGH — multiple precedents in same file and codebase
- Edge cases (empty array, float comparison): HIGH — standard JS semantics, confirmed

**Research date:** 2026-03-11
**Valid until:** Stable — until `submitCapture()` is refactored
