---
phase: 62-agent-invoice-ui
verified: 2026-03-11T00:00:00Z
status: human_needed
score: 5/5 must-haves verified
human_verification:
  - test: "Load a single class view as admin (class with assigned agent)"
    expected: "Agent Rate card visible with rate_type dropdown and rate_amount input, both initially disabled. Monthly Invoice section visible with current month pre-selected in month picker and summary loading."
    why_human: "Cannot execute browser rendering or WP page load programmatically"
  - test: "Load same class view as wp_agent role"
    expected: "Agent Rate card NOT visible. Monthly Invoice section visible with claim form showing, no rate card."
    why_human: "Role-based conditional rendering requires authenticated browser session"
  - test: "Change month picker to a different YYYY-MM"
    expected: "Summary metrics (Class Hours, Absent Days, Absent Hours, Payable Hours) recalculate via AJAX and update"
    why_human: "AJAX interaction requires live browser + backend"
  - test: "Submit a claim as agent (enter claimed hours, optionally notes, click Submit Claim)"
    expected: "Claim form replaced by read-only submitted state with Phoenix badge showing 'Submitted', claimed hours displayed, discrepancy warning if applicable"
    why_human: "State swap after AJAX submit requires live interaction"
  - test: "Save a rate as admin (select rate type, enter amount, click Save Rate)"
    expected: "Green 'Saved' message flashes next to button, fades after 2 seconds"
    why_human: "AJAX response and DOM feedback requires live browser"
---

# Phase 62: Agent Invoice UI Verification Report

**Phase Goal:** Agent rate card (admin) + monthly invoice section inline on single class view
**Verified:** 2026-03-11
**Status:** human_needed (all automated checks passed)
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Admin sees Agent Rate card on single class view with rate_type dropdown and rate_amount input | VERIFIED | `agent-rate-card.php` guards with `current_user_can('manage_options')`, contains `<select id="agent-rate-type">` (hourly/daily options) and `<input id="agent-rate-amount" type="number">` |
| 2 | Agent and admin see Monthly Invoice section with class hours, absent days, absent hours, payable hours | VERIFIED | `agent-invoice.php` contains `#inv-class-hours`, `#inv-absent-days`, `#inv-absent-hours`, `#inv-payable-hours` spans inside `#invoice-summary`; no capability guard restricts visibility |
| 3 | Agent can enter claimed hours with optional notes and submit | VERIFIED | `#inv-claimed-hours` input, `#inv-claim-notes` textarea, and `#btn-submit-invoice` button present in `#invoice-claim-form`; `submitInvoice()` in JS POSTs to `wecoza_invoice_submit` |
| 4 | After submission form shows read-only submitted state | VERIFIED | `showSubmittedState()` in `agent-invoice.js` hides `#invoice-claim-form`, populates `#inv-status-badge`, `#inv-submitted-hours`, `#inv-discrepancy-badge`, `#inv-submitted-notes`, shows `#invoice-submitted` |
| 5 | wp_agent role sees submit form; admin role sees rate card + review view | VERIFIED | `agent-rate-card.php` returns early for non-admin; `loadInvoiceStatus()` in JS branches on `config.isAdmin` — admins call `wecoza_invoice_list` to resolve state, agents always see claim form |

**Score:** 5/5 truths verified

---

### Required Artifacts

| Artifact | Expected | Lines | Status | Details |
|----------|----------|-------|--------|---------|
| `views/classes/components/single-class/agent-rate-card.php` | Admin-only rate card component; contains `manage_options` guard | 82 | VERIFIED | Exists, substantive, includes capability guard at line 23, rate_type select, rate_amount input, save button, status feedback span |
| `views/classes/components/single-class/agent-invoice.php` | Monthly invoice section (min 50 lines) | 172 | VERIFIED | Exists, substantive (172 lines), month picker, summary metric cards, claim form, submitted state, loading spinner, alert container |
| `assets/js/classes/agent-invoice.js` | AJAX module (min 80 lines) | 531 | VERIFIED | Exists, substantive (531 lines), all five AJAX actions wired, full state machine for claim/submit/submitted |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `assets/js/classes/agent-invoice.js` | `wecoza_invoice_calculate` | AJAX POST | VERIFIED | `action: 'wecoza_invoice_calculate'` in `calculateInvoice()` at line 205 |
| `assets/js/classes/agent-invoice.js` | `wecoza_invoice_submit` | AJAX POST | VERIFIED | `action: 'wecoza_invoice_submit'` in `submitInvoice()` at line 338 |
| `assets/js/classes/agent-invoice.js` | `wecoza_order_save` | AJAX POST | VERIFIED | `action: 'wecoza_order_save'` in `saveRate()` at line 142 |
| `views/classes/components/single-class-display.view.php` | `agent-rate-card.php` | `wecoza_view` include | VERIFIED | Line 271: `wecoza_view('classes/components/single-class/agent-rate-card', $component_data)` immediately after attendance section |
| `views/classes/components/single-class-display.view.php` | `agent-invoice.php` | `wecoza_view` include | VERIFIED | Line 274: `wecoza_view('classes/components/single-class/agent-invoice', $component_data)` immediately after rate card |

Additional links verified:
- `ClassController.php` registers `wecoza-agent-invoice-js` (line 233) and conditionally enqueues when `classAgent > 0` (line 483)
- `WeCozaSingleClass` localized object contains `ordersNonce` (line 528) and `classAgent` (line 529)
- `isAdmin` already present in localized object (line 524) — consumed by `config.isAdmin` in JS

---

### Requirements Coverage

| Requirement | Description | Status | Evidence |
|-------------|-------------|--------|----------|
| ORD-01 | Admin can set agent rate (hourly/daily) and amount for a class assignment | SATISFIED | `agent-rate-card.php` rate_type select (hourly/daily) + rate_amount input; `saveRate()` POSTs to `wecoza_order_save` |
| INV-01 | Agent can view auto-calculated monthly summary (class hours, absent days, payable hours) | SATISFIED | `calculateInvoice()` POSTs to `wecoza_invoice_calculate`; `populateSummary()` populates all four metric spans |
| INV-02 | Agent can submit claimed hours for a month with optional notes | SATISFIED | `#inv-claimed-hours`, `#inv-claim-notes`, `#btn-submit-invoice`; `submitInvoice()` POSTs to `wecoza_invoice_submit` with `claimed_hours` and optional `notes` |
| INV-04 | Invoice section appears on single class view (inline with attendance) | SATISFIED | Both components inserted in `single-class-display.view.php` at lines 271 and 274, immediately after the attendance component at line 268 |

No orphaned requirements — all four IDs in the PLAN frontmatter map to Phase 62 in REQUIREMENTS.md and are accounted for.

---

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `agent-rate-card.php` | 67 | `placeholder="0.00"` | Info | HTML input placeholder attribute — not a code stub |
| `agent-invoice.php` | 135 | `placeholder="0.00"` | Info | HTML input placeholder attribute — not a code stub |
| `agent-invoice.php` | 146 | `placeholder="..."` | Info | HTML textarea placeholder attribute — not a code stub |

No blockers. No TODO/FIXME/HACK comments. No empty implementations. No console.log-only handlers.

---

### Commit Verification

Both commits declared in SUMMARY exist in git history:
- `91867ef` — feat(62-01): add agent rate card and monthly invoice PHP components
- `643e03b` — feat(62-01): create agent-invoice.js AJAX module

---

### Human Verification Required

#### 1. Admin Rate Card Visibility

**Test:** Log in as admin, navigate to a single class view where the class has an assigned agent. Inspect the page.
**Expected:** Agent Rate card appears with Rate Type dropdown (Select / Hourly / Daily) and Rate Amount input, both disabled until the page loads order data via AJAX. A "Save Rate" button is present but initially disabled.
**Why human:** Capability checks and AJAX-driven enable/disable state require authenticated browser session.

#### 2. Agent Role Isolation

**Test:** Log in as a user with only the `wp_agent` role, navigate to the same single class view.
**Expected:** Agent Rate card is completely absent. Monthly Invoice section is visible with the claim form (claimed hours input + notes textarea + Submit Claim button).
**Why human:** Role-based rendering requires authenticated session as a non-admin user.

#### 3. Month Picker Recalculate

**Test:** As either role, change the month picker to a different month.
**Expected:** Loading spinner appears, then summary metrics (Class Hours, Absent Days, Absent Hours, Payable Hours) update with data for the new month.
**Why human:** AJAX call and DOM update require live browser interaction.

#### 4. Claim Submission State Swap

**Test:** As agent, enter a value in Claimed Hours, optionally add notes, click Submit Claim.
**Expected:** Claim form disappears and is replaced by a read-only submitted state showing a blue "Submitted" badge, the claimed hours value, and notes. If claimed hours exceed payable hours, an orange "Overclaim: X hrs" badge appears.
**Why human:** AJAX POST and subsequent DOM state swap require live interaction with backend.

#### 5. Admin Rate Save

**Test:** As admin, select a rate type and enter a rate amount, click Save Rate.
**Expected:** Button shows "Saving..." spinner, then green "Saved" text appears next to the button and fades after 2 seconds.
**Why human:** AJAX response and timed DOM feedback requires live browser.

---

### Gaps Summary

No automated gaps found. All five observable truths verified. All three artifacts exist and are substantive. All five key links confirmed in source code. All four requirement IDs (ORD-01, INV-01, INV-02, INV-04) satisfied with clear implementation evidence.

Five items require human verification due to browser-only concerns: capability-gated rendering, role isolation, AJAX-driven state, and timed feedback effects.

---

_Verified: 2026-03-11_
_Verifier: John @ YourDesign.co.za_
