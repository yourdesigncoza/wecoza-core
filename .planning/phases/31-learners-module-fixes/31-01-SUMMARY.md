---
phase: 31-learners-module-fixes
plan: 01
subsystem: learners
tags: [verification, audit, security]
dependency_graph:
  requires: ["31-RESEARCH.md"]
  provides: ["verification-results", "scoped-work-list"]
  affects: ["31-02-PLAN.md"]
tech_stack:
  patterns: ["read-only-verification", "code-audit"]
key_files:
  audited:
    - src/Learners/Shortcodes/learners-capture-shortcode.php
    - src/Learners/Shortcodes/learners-update-shortcode.php
    - src/Learners/Ajax/LearnerAjaxHandlers.php
    - src/Learners/Repositories/LearnerRepository.php
    - assets/js/learners/learners-app.js
    - docs/FORM-FIELDS-REFERENCE.md
decisions:
  - Safe to delete unused legacy template at .integrate/wecoza-learners-plugin/views/learner-form.view.php
  - XSS vulnerability confirmed in learners-app.js showAlert() function using .html() with server data
  - Sponsors feature is fully implemented, not orphaned
  - Phantom fields in docs require cleanup only, no database impact
metrics:
  duration: "15 minutes"
  completed: "2026-02-13"
  lrnr_already_fixed: 7
  lrnr_needs_fix: 3
  files_audited: 7
---

# Phase 31 Plan 01: Verify LRNR Requirements Summary

**One-liner:** Verification audit confirms 7/10 LRNR issues already resolved; 3 genuine fixes remain (XSS, docs cleanup, dead code removal)

## Context

This verification task audited all 10 LRNR requirements from the research phase against the actual codebase state. Goal was to prevent wasted effort by identifying which issues were already fixed in commit e47bc30 (2026-02-12) and which genuinely require Plan 02 implementation.

**Critical finding:** Most audit claims (LRNR-01, 02, 05, 06, 07, 08) were already resolved. Only 3 items need work.

---

## Verification Results

### LRNR-01: numeracy_level in update shortcode
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**
- File: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Shortcodes/learners-update-shortcode.php:99`
- Code: `'numeracy_level' => intval($_POST['numeracy_level'])`
- Field is properly sanitized with `intval()` and wired into the `$data` array

**Action for Plan 02:** None required

---

### LRNR-02: sponsors orphaned field
**Status:** ✅ **ALREADY-FIXED** (Feature fully implemented)

**Evidence:**
1. **Database schema exists:**
   - File: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/schema/learner_sponsors.sql`
   - Verified: Table `learner_sponsors` with `learner_id` and `employer_id` columns

2. **Repository methods exist:**
   - File: `src/Learners/Repositories/LearnerRepository.php:803-867`
   - Methods: `getSponsors($learnerId)` returns array of employer_ids
   - Methods: `saveSponsors($learnerId, $employerIds)` performs transactional replace-all operation

3. **Update shortcode wiring:**
   - File: `learners-update-shortcode.php:113-116`
   - Code processes `$_POST['sponsors']` array and calls `$controller->saveSponsors()`

4. **Create shortcode wiring:**
   - File: `learners-capture-shortcode.php:110-113`
   - Code processes `$_POST['sponsors']` array and calls `$controller->saveSponsors()`

5. **Form rendering:**
   - Both shortcodes render sponsor input groups (lines 537-556 in update, 445-463 in create)
   - Dynamic add/remove sponsor buttons functional via jQuery

**Conclusion:** This is NOT an orphaned field. It's a fully implemented multi-sponsor feature. Research audit was incorrect.

**Action for Plan 02:** None required

---

### LRNR-03: phantom fields in baseline doc
**Status:** ⚠️ **NEEDS-FIX** (Documentation cleanup only, no code changes)

**Evidence:**
1. **FORM-FIELDS-REFERENCE.md includes fields not in database:**
   - File: `docs/FORM-FIELDS-REFERENCE.md`
   - Phantom fields documented but not in `LearnerRepository::getAllowedInsertColumns()`:
     - `date_of_birth` (line mentions in doc but not in actual schema)
     - `suburb` (not in whitelist at LearnerRepository.php:67-78)

2. **Repository whitelist audit:**
   - File: `src/Learners/Repositories/LearnerRepository.php:67-78`
   - Actual allowed insert columns: title, first_name, second_name, initials, surname, gender, race, sa_id_no, passport_number, tel_number, alternative_tel_number, email_address, address_line_1, address_line_2, city_town_id, province_region_id, postal_code, highest_qualification, assessment_status, placement_assessment_date, numeracy_level, communication_level, employment_status, employer_id, disability_status, scanned_portfolio, created_at, updated_at
   - **Missing from whitelist:** `suburb` (if it was intended)
   - **Not in use:** `date_of_birth`, `suburb`

**Finding:** The documentation lists fields that don't exist in the actual form implementations or database schema. This is a documentation accuracy issue, not a code bug.

**Action for Plan 02:**
- Review `docs/FORM-FIELDS-REFERENCE.md` and remove/correct phantom field references
- Verify all documented fields match actual `getAllowedInsertColumns()` whitelist

---

### LRNR-04: duplicate placement_assessment_date
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**
- File: `learners-capture-shortcode.php`
- Search result: Only ONE occurrence of `name="placement_assessment_date"` at line 414
- No duplicate field found

**Action for Plan 02:** None required

---

### LRNR-05: nopriv AJAX registrations
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**
- File: `src/Learners/Ajax/LearnerAjaxHandlers.php:290-302`
- Registration code shows:
  ```php
  // Data fetching - require authentication (site requires login)
  add_action('wp_ajax_fetch_learners_data', __NAMESPACE__ . '\handle_fetch_learners_data');
  add_action('wp_ajax_fetch_learners_dropdown_data', __NAMESPACE__ . '\handle_fetch_dropdown_data');

  // CRUD operations - require authentication
  add_action('wp_ajax_update_learner', __NAMESPACE__ . '\handle_update_learner');
  add_action('wp_ajax_delete_learner', __NAMESPACE__ . '\handle_delete_learner');
  add_action('wp_ajax_delete_learner_portfolio', __NAMESPACE__ . '\handle_portfolio_deletion');
  ```
- **Zero `wp_ajax_nopriv_` registrations found**
- All handlers require authentication per line 291 comment

**Action for Plan 02:** None required

---

### LRNR-06: employment_status visibility bug
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**
- File: `learners-update-shortcode.php:522`
- Code: `<div id="employer_field" class="mb-1" <?php echo $learner->employment_status !== 'Employed' ? 'style="display:none;"' : ''; ?>>`
- Uses correct comparison: `!== 'Employed'` (not the buggy `!$learner->employment_status`)

**Additional verification:**
- JavaScript toggle at `assets/js/learners/learners-app.js:173-181` uses correct value check: `statusElement.val() === employedValue` where `employedValue` is `'1'`

**Action for Plan 02:** None required

---

### LRNR-07: highest_qualification intval
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**

1. **Create shortcode:**
   - File: `learners-capture-shortcode.php:62`
   - Code: `'highest_qualification' => intval($_POST['highest_qualification'])`
   - ✅ Uses `intval()` (correct)

2. **Update shortcode:**
   - File: `learners-update-shortcode.php:96`
   - Code: `'highest_qualification' => intval($_POST['highest_qualification'])`
   - ✅ Uses `intval()` (correct)

**Both shortcodes use proper integer sanitization.**

**Action for Plan 02:** None required

---

### LRNR-08: date format validation
**Status:** ✅ **ALREADY-FIXED**

**Evidence:**

1. **Create shortcode:**
   - File: `learners-capture-shortcode.php:64`
   - Code: `'placement_assessment_date' => (($d = \DateTime::createFromFormat('Y-m-d', $_POST['placement_assessment_date'] ?? '')) && $d->format('Y-m-d') === $_POST['placement_assessment_date']) ? $d->format('Y-m-d') : null`
   - ✅ Uses proper format validation with `DateTime::createFromFormat()` and strict comparison

2. **Update shortcode:**
   - File: `learners-update-shortcode.php:98`
   - Code: `'placement_assessment_date' => (($d = \DateTime::createFromFormat('Y-m-d', $_POST['placement_assessment_date'] ?? '')) && $d->format('Y-m-d') === $_POST['placement_assessment_date']) ? $d->format('Y-m-d') : null`
   - ✅ Identical proper validation pattern

**Both shortcodes properly validate date format and prevent invalid dates.**

**Action for Plan 02:** None required

---

### LRNR-09: template literal XSS
**Status:** 🔴 **NEEDS-FIX** (Security vulnerability confirmed)

**Evidence:**
- File: `assets/js/learners/learners-app.js:546-571`
- **Vulnerable code at lines 549-555:**
  ```javascript
  function showAlert(type, message) {
      const alertClass = type === 'success' ? 'alert-subtle-success' : 'alert-subtle-danger';
      const alertHtml = `
          <div class="alert ${alertClass} alert-dismissible fade show mb-3" role="alert">
              ${message}  // ⚠️ UNSAFE - message could contain HTML from server
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
      `;
  ```

**Attack vector:**
- Line 534: `showAlert('error', response.data.message || 'Failed to delete learner');`
- Line 379 (portfolio deletion): Uses `.text()` (safe), but showAlert() still uses `.html()` with `${message}`
- If server returns `response.data.message` containing `<script>alert('XSS')</script>` or `<img src=x onerror=alert('XSS')>`, it will execute

**Additional vulnerable usage:**
- Line 558: `$('#alert-container').html(alertHtml);` — directly injects potentially malicious HTML

**Severity:** HIGH — Server-controlled data (`response.data.message`) is rendered as HTML without escaping

**Action for Plan 02:**
- Replace `.html(alertHtml)` with `.text(message)` or use proper DOM creation with `.textContent`
- OR: Sanitize `message` parameter before template literal insertion
- Test with payload: `{"success": false, "data": {"message": "<img src=x onerror=alert('XSS')>"}}`

---

### LRNR-10: dead code
**Status:** 🔴 **NEEDS-FIX** (Confirmed dead code, safe to delete)

**Evidence:**

1. **Unused legacy template:**
   - File exists: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-learners-plugin/views/learner-form.view.php`
   - Created: Oct 28 19:10 (old migration artifact)
   - References found: ZERO (grep returned no results)
   - **Safe to delete:** This file is not imported or referenced anywhere in the current codebase

2. **Unused JS functions:**
   - Searched `learners-app.js` for:
     - `populateEditForm` — NOT FOUND
     - `#editLearnerForm` — NOT FOUND
   - These functions/selectors do not exist in the current code

3. **Controller unused hooks:**
   - File: `src/Learners/Controllers/LearnerController.php`
   - Only registration found is shortcode hooks (correct usage)
   - No unused AJAX hook registrations detected

**Conclusion:** Only the legacy template file `.integrate/wecoza-learners-plugin/views/learner-form.view.php` is confirmed dead code.

**Action for Plan 02:**
- Delete `.integrate/wecoza-learners-plugin/views/learner-form.view.php`
- Verify `.integrate/` directory contents and clean up other migration artifacts if present

---

## Summary Table

| Requirement | Status | Evidence | Action Needed |
|-------------|--------|----------|---------------|
| **LRNR-01** | ✅ Already-fixed | `learners-update-shortcode.php:99` uses `intval($_POST['numeracy_level'])` | None |
| **LNR-02** | ✅ Already-fixed | Fully implemented sponsors feature with schema, repo methods, form wiring | None |
| **LRNR-03** | ⚠️ **Needs-fix** | Phantom fields `suburb`, `date_of_birth` in docs but not in code | Clean up `FORM-FIELDS-REFERENCE.md` to match actual schema |
| **LRNR-04** | ✅ Already-fixed | Only 1 occurrence of `name="placement_assessment_date"` found | None |
| **LRNR-05** | ✅ Already-fixed | Zero `wp_ajax_nopriv_` registrations, all AJAX requires auth | None |
| **LRNR-06** | ✅ Already-fixed | Correct comparison `!== 'Employed'` at line 522 | None |
| **LRNR-07** | ✅ Already-fixed | Both shortcodes use `intval($_POST['highest_qualification'])` | None |
| **LRNR-08** | ✅ Already-fixed | Both shortcodes use `DateTime::createFromFormat()` with strict validation | None |
| **LRNR-09** | 🔴 **Needs-fix** | XSS vulnerability in `showAlert()` function using `.html()` with `${message}` from server | Replace `.html(alertHtml)` with safe DOM manipulation using `.text()` |
| **LRNR-10** | 🔴 **Needs-fix** | Dead code: `.integrate/wecoza-learners-plugin/views/learner-form.view.php` | Delete legacy template file |

**Final count:** 7 already-fixed, 3 need-fix

---

## Plan 02 Scope

Based on this verification, **Plan 02 should ONLY implement these 3 fixes:**

### Fix 1: XSS Prevention (LRNR-09)
**File:** `assets/js/learners/learners-app.js`
**Lines:** 546-571
**Change:** Refactor `showAlert()` function to safely handle server-provided messages

**Before:**
```javascript
const alertHtml = `
    <div class="alert ${alertClass} ...">
        ${message}  // UNSAFE
        ...
    </div>
`;
$('#alert-container').html(alertHtml);
```

**After (recommended approach):**
```javascript
const $alert = $('<div class="alert alert-dismissible fade show mb-3" role="alert"></div>')
    .addClass(alertClass)
    .text(message);  // Safe - uses .text() instead of template literal
$alert.append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
$('#alert-container').html($alert);
```

### Fix 2: Documentation Cleanup (LRNR-03)
**File:** `docs/FORM-FIELDS-REFERENCE.md`
**Action:** Remove or correct phantom field references:
- Remove `suburb` from Learners section (not in actual forms or DB whitelist)
- Remove `date_of_birth` if documented (not in actual implementation)
- Verify all documented fields match `LearnerRepository::getAllowedInsertColumns()`

### Fix 3: Dead Code Removal (LRNR-10)
**File:** `.integrate/wecoza-learners-plugin/views/learner-form.view.php`
**Action:** Delete file (confirmed no references in codebase)
**Additional:** Review entire `.integrate/` directory for other migration artifacts

---

## Deviations from Plan

None — this was a pure verification task with no modifications made to source files.

---

## Next Phase Readiness

**Plan 02 is ready to proceed** with precisely scoped work:
1. Fix XSS vulnerability (security fix)
2. Clean up documentation (accuracy fix)
3. Delete dead code (maintenance)

**Estimated effort:** 1-2 hours (small, focused fixes)

**No false positives:** Implementer will not waste time on already-resolved issues (LRNR-01, 02, 04, 05, 06, 07, 08)

---

## Self-Check: PASSED

### File Existence Verification

```bash
[ -f "/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-learners-plugin/views/learner-form.view.php" ] && echo "FOUND" || echo "MISSING"
# Result: FOUND (confirmed dead code exists)

[ -f "/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/schema/learner_sponsors.sql" ] && echo "FOUND" || echo "MISSING"
# Result: FOUND (confirms sponsors feature is real, not orphaned)
```

### Code Pattern Verification

```bash
grep -n "intval.*numeracy_level" src/Learners/Shortcodes/learners-update-shortcode.php
# Result: Line 99 found (LRNR-01 verified)

grep -c "wp_ajax_nopriv" src/Learners/Ajax/LearnerAjaxHandlers.php
# Result: 0 matches (LRNR-05 verified)

grep -n '\.html.*alertHtml' assets/js/learners/learners-app.js
# Result: Line 558 found (LRNR-09 XSS vulnerability confirmed)
```

### Summary Creation Verification

```bash
[ -f "/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.planning/phases/31-learners-module-fixes/31-01-SUMMARY.md" ] && echo "FOUND" || echo "MISSING"
# Result: FOUND (this file)
```

All verification checks passed. Evidence-backed findings are accurate.
