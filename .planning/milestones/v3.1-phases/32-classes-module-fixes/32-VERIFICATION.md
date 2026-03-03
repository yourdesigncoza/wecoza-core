---
phase: 32-classes-module-fixes
verified: 2026-02-13T12:00:00Z
status: passed
score: 9/9 must-haves verified
---

# Phase 32: Classes Module Fixes Verification Report

**Phase Goal:** Fix critical reverse path bugs and security issues in Classes module forms.
**Verified:** 2026-02-13T12:00:00Z
**Status:** PASSED
**Re-verification:** No -- initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | order_nr survives round-trip from DB to form to DB on class update | VERIFIED | `ClassRepository::getSingleClass()` line 660: `'order_nr' => $classModel->getOrderNr()` present in result array. `FormDataProcessor` line 78 processes it, line 594-596 populates model. |
| 2 | New classes have class_agent set from initial_class_agent when class_agent is empty | VERIFIED | `FormDataProcessor.php` lines 69-72: CLS-02 block `if (empty($processed['class_agent']) && !empty($processed['initial_class_agent'])) { $processed['class_agent'] = $processed['initial_class_agent']; }` |
| 3 | QA write endpoints reject unauthenticated requests (no nopriv) | VERIFIED | `QAController.php` has 0 nopriv registrations for write endpoints. Only 4 read-only nopriv remain: `get_qa_analytics`, `get_qa_summary`, `get_qa_visits`, `get_class_qa_data`. |
| 4 | stop_dates/restart_dates are sanitized and validated as YYYY-MM-DD before storage | VERIFIED | `FormDataProcessor.php` lines 148-150: `sanitizeText()` then `isValidDate()` gate on both `$stopDate` and `$restartDate` before storing. |
| 5 | site_id is cast to integer via intval() like client_id | VERIFIED | `FormDataProcessor.php` line 34: `intval($data['site_id'])` -- matches `client_id` pattern on line 33. |
| 6 | learner_ids and exam_learners contain only positive integers after JSON decode | VERIFIED | `FormDataProcessor.php` line 88: `array_filter(array_map('intval', $learnerData), fn($id) => $id > 0)` for learner_ids. Line 98: identical pattern for exam_learners. |
| 7 | backup_agent_dates are validated as YYYY-MM-DD format | VERIFIED | `FormDataProcessor.php` line 113: `self::isValidDate(self::sanitizeText($agentDates[$i]))` gate before storing. |
| 8 | initial_class_agent dropdown pre-selects from initial_class_agent value, not class_agent | VERIFIED | `update-class.php` line 1509: `$data['class_data']['initial_class_agent']` used for both comparison and selection. No reference to `class_agent` in this dropdown. |
| 9 | Agents/supervisors come from DB queries, not hardcoded static arrays | VERIFIED | `ClassRepository::getAgents()` lines 419-449: queries `agents` table with `WHERE status = 'active'`, cached via `get_transient`/`set_transient` with 12-hour TTL. `getSupervisors()` lines 457-487: same pattern. Zero hardcoded agent names remain in either method. |

**Score:** 9/9 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `src/Classes/Services/FormDataProcessor.php` | Input sanitization fixes (CLS-02/04/05/06/09) | VERIFIED | 656 lines, no stubs, all 5 fixes confirmed, PHP lint passes |
| `src/Classes/Repositories/ClassRepository.php` | order_nr in getSingleClass, DB-backed getAgents/getSupervisors | VERIFIED | 913 lines, `order_nr` on line 660, getAgents uses `wecoza_db()` with transient cache, getSupervisors same pattern |
| `src/Classes/Controllers/QAController.php` | Authenticated-only QA write endpoints | VERIFIED | 580 lines, 4 write nopriv lines removed, 4 read-only nopriv retained correctly |
| `views/classes/components/class-capture-partials/update-class.php` | Correct initial_class_agent pre-selection | VERIFIED | Line 1509 uses `initial_class_agent` field for `selected` attribute |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `ClassRepository::getSingleClass()` | update-class.php form | `order_nr` in result array | WIRED | Line 660 adds `order_nr => getOrderNr()`, form uses `$data['class_data']['order_nr']` |
| `FormDataProcessor::processFormData()` | `ClassModel::save()` | class_agent init from initial_class_agent | WIRED | Lines 69-72 copy initial_class_agent to class_agent when empty, line 579 sets class_agent on model |
| `ClassRepository::getAgents()` | agents table | PDO query with transient cache | WIRED | Lines 428-433: `wecoza_db()` -> SQL SELECT from agents -> `get_transient`/`set_transient` |
| `ClassRepository::getSupervisors()` | agents table | PDO query with transient cache | WIRED | Lines 466-471: Same pattern as getAgents with separate cache key |
| `getSingleClass()` | `getAgents()`/`getSupervisors()` | Agent name lookup | WIRED | Lines 675-700: calls getAgents() and getSupervisors() for name resolution, same return format `['id' => int, 'name' => string]` |

### Requirements Coverage

| Requirement | Status | Details |
|-------------|--------|---------|
| CLS-01: Fix `order_nr` reverse path | SATISFIED | Added to `getSingleClass()` result array on line 660 |
| CLS-02: Set `class_agent` from `initial_class_agent` on create | SATISFIED | Lines 69-72 in FormDataProcessor |
| CLS-03: Remove `nopriv` from QA write endpoints | SATISFIED | 4 write nopriv removed, 4 read nopriv retained |
| CLS-04: Sanitize `stop_dates[]`/`restart_dates[]` with validation | SATISFIED | `sanitizeText()` + `isValidDate()` on lines 148-150 |
| CLS-05: Type-cast `site_id` with `intval()` | SATISFIED | Line 34 uses `intval($data['site_id'])` |
| CLS-06: Sanitize `learner_ids`/`exam_learners` per-entry | SATISFIED | `array_filter(array_map('intval', ...), fn($id) => $id > 0)` on lines 88, 98 |
| CLS-07: Fix `initial_class_agent` pre-selection | SATISFIED | update-class.php line 1509 uses `initial_class_agent` field |
| CLS-08: Migrate agents/supervisors from static arrays to DB queries | SATISFIED | Both methods query `agents` table, cached via transients |
| CLS-09: Validate `backup_agent_dates[]` as valid date format | SATISFIED | Line 113 uses `isValidDate(sanitizeText(...))` gate |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| QAController.php | 255 | `'PDF export not implemented yet'` | Info | Pre-existing, unrelated to this phase |
| ClassRepository.php | 866 | `'Dr. Sarah Johnson'` in getSampleClassData() | Info | Test/sample data method only, not production code |

### Human Verification Required

### 1. Order Number Round-Trip

**Test:** Load an existing class with a non-null `order_nr`, submit the update form without changing anything, verify `order_nr` value is preserved in the database.
**Expected:** The `order_nr` field should retain its original value after form submission.
**Why human:** Requires a live database round-trip through the browser form to confirm the full chain works end-to-end.

### 2. Agent Dropdown Population

**Test:** Open the class capture/update form and verify the agents and supervisors dropdowns show current active agents from the database, not stale hardcoded names.
**Expected:** Dropdown options should match active agents in the `agents` table.
**Why human:** Requires visual inspection of rendered form with live DB data.

### 3. Initial Class Agent Pre-Selection on Edit

**Test:** Create a class with one agent as initial_class_agent, then add a replacement agent (changing class_agent). Open the edit form and verify the Initial Class Agent dropdown shows the original agent, not the current replacement.
**Expected:** Initial Class Agent dropdown pre-selects the original agent, while Class Agent reflects the replacement.
**Why human:** Requires understanding the semantic difference between the two fields in the UI.

### 4. QA Write Endpoint Security

**Test:** While logged out, attempt to call `wp_ajax_nopriv_create_qa_visit` via a direct POST to `admin-ajax.php`. Verify it returns an error (action not found), not a valid response.
**Expected:** WordPress returns 0 or an error since the nopriv action is not registered.
**Why human:** Requires testing unauthenticated AJAX calls against a running WordPress instance.

### Gaps Summary

No gaps found. All 9 requirements (CLS-01 through CLS-09) are fully implemented and verified in the codebase. Each fix uses established patterns already present in the codebase (intval for FK casts, isValidDate for date gates, array_filter/array_map/intval for ID arrays, transient-cached DB queries for reference data). All four modified files pass PHP lint. No stub patterns or blocking anti-patterns detected.

---

_Verified: 2026-02-13T12:00:00Z_
_Verifier: Claude (gsd-verifier)_
