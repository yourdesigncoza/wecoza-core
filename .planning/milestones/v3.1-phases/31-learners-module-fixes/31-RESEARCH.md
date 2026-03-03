# Phase 31 Research: Learners Module Fixes

**Date:** 2026-02-13
**Researcher Role:** gsd-phase-researcher
**Objective:** Gather knowledge needed to PLAN Phase 31 well

---

## Executive Summary

Phase 31 fixes 10 critical bugs identified in the Learners module form field wiring audit. The audit provides exact file paths, line numbers, and recommended fixes for each issue. One decision point (LRNR-02 sponsors) requires user input. Otherwise, implementation is straightforward — the audit is the implementation guide.

**Key Finding:** The sponsors feature is already fully implemented backend (table, repository, controller methods) but the audit incorrectly labeled it as orphaned. The actual issue is that the shortcode POST processing was recently added (lines 109-113 in create, 112-116 in update) but wasn't noticed during the audit.

---

## 1. Source Material

### Primary Reference
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/docs/formfieldanalysis/learners-audit.md`
  - Comprehensive form field wiring audit
  - Line-by-line tracing from HTML → POST → DB
  - Exact file paths and line numbers for all 10 issues
  - Recommended fixes for each requirement

### Requirements Doc
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.planning/REQUIREMENTS.md`
  - LRNR-01 through LRNR-10 requirements
  - v3.1 milestone scope: Form field wiring fixes only
  - Future requirements (LRNR-F01, LRNR-F02) explicitly deferred

### Code Base
- **Shortcodes:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Shortcodes/learners-capture-shortcode.php` (create)
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Shortcodes/learners-update-shortcode.php` (edit)
- **AJAX Handlers:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Ajax/LearnerAjaxHandlers.php`
- **JavaScript:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/assets/js/learners/learners-app.js`
- **Repository:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Repositories/LearnerRepository.php`
- **Controller:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Controllers/LearnerController.php`
- **Legacy View:**
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-learners-plugin/views/learner-form.view.php`

---

## 2. Issue Analysis

### LRNR-01: Missing `numeracy_level` in Update Shortcode

**Status:** CRITICAL DATA LOSS BUG (confirmed in audit)

**Root Cause:**
- Create shortcode captures `numeracy_level` at line 65: `'numeracy_level' => intval($_POST['numeracy_level'])`
- Update shortcode data array (lines 76-106) **OMITS** `numeracy_level` entirely
- Field exists in form HTML (line 484), populated via AJAX (line 622)
- Every learner update silently loses numeracy level changes

**Fix Location:**
- File: `src/Learners/Shortcodes/learners-update-shortcode.php`
- Line: After line 98 (after `placement_assessment_date`)
- Add: `'numeracy_level' => intval($_POST['numeracy_level']),`

**Verification:**
- Update shortcode ALREADY has it at line 99: `'numeracy_level' => intval($_POST['numeracy_level'])`
- **AUDIT IS STALE** — this bug was already fixed in a recent commit
- Line count matches: Both shortcodes now have identical `numeracy_level` handling

**Research Finding:** Bug already fixed. Verify during planning that fix is present.

---

### LRNR-02: Sponsors Feature Status

**Audit Claim:** "Completely orphaned feature — no POST processing, no DB table"

**Actual State (verified via code inspection):**

1. **Database schema exists:**
   - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/schema/learner_sponsors.sql`
   - Table: `learner_sponsors` with `learner_id`, `employer_id` FKs
   - Indexes on both foreign keys
   - UNIQUE constraint prevents duplicate sponsor assignments

2. **Repository methods exist:**
   - `LearnerRepository::getSponsors($learnerId)` (lines 803-819) — fetches sponsor employer_ids
   - `LearnerRepository::saveSponsors($learnerId, $employerIds)` (lines 828-865) — transactional replace-all

3. **Controller methods exist:**
   - `LearnerController::getSponsors($learnerId)` (lines 199-202)
   - `LearnerController::saveSponsors($learnerId, $employerIds)` (lines 207-210)

4. **POST processing exists (recently added):**
   - **Create shortcode** (lines 109-113):
     ```php
     // Save sponsors if any were selected
     if (!empty($_POST['sponsors']) && is_array($_POST['sponsors'])) {
         $sponsor_ids = array_map('intval', $_POST['sponsors']);
         $controller->saveSponsors($learner_id, $sponsor_ids);
     }
     ```
   - **Update shortcode** (lines 112-116):
     ```php
     // Save sponsors (replace all existing with submitted values)
     $sponsor_ids = !empty($_POST['sponsors']) && is_array($_POST['sponsors'])
         ? array_map('intval', $_POST['sponsors'])
         : [];
     $controller->saveSponsors($learner_id, $sponsor_ids);
     ```

5. **UI exists in both forms:**
   - Add/remove sponsor buttons functional
   - Employer dropdown populated via AJAX
   - Pre-population on edit via `getSponsors()` (update shortcode line 62)

**Research Conclusion:**
Sponsors feature is **FULLY IMPLEMENTED** and functional. The audit was conducted before the recent POST processing additions. No action needed for LRNR-02.

**IMPORTANT:** Verify with user whether they want this feature to remain. If yes, no changes needed. If no, remove the UI (15+ lines in each form) and POST processing (5 lines each).

---

### LRNR-03: Phantom Fields in Baseline Doc

**Issue:** `date_of_birth` and `suburb` are listed in baseline doc but don't exist in forms or DB

**date_of_birth:**
- Listed at `docs/FORM-FIELDS-REFERENCE.md` line 314 (audit reference, not exact)
- No form field in either shortcode
- No DB column in `learners` table
- No model property
- Never implemented or was removed

**suburb:**
- Listed at baseline doc line 322 (under Address section)
- No DB column in `learners` table (confirmed via schema grep)
- **BUT** included in `LearnerRepository::getAllowedInsertColumns()` line 75 (dead code)
- **BUT** has model property (dead code)
- Value comes from `locations.suburb` via JOIN on read path (line 113 of repository)
- Display shows suburb correctly via JOIN, but INSERT/UPDATE would fail

**Fix:**
1. Remove `date_of_birth` from baseline doc
2. Remove `suburb` from baseline doc
3. Remove `suburb` from `LearnerRepository::getAllowedInsertColumns()` array (line 75)
4. Consider removing `suburb` model property (optional — doesn't cause harm, just dead code)

**Files to modify:**
- `docs/FORM-FIELDS-REFERENCE.md` (remove two entries)
- `src/Learners/Repositories/LearnerRepository.php` (remove from whitelist array)

---

### LRNR-04: Duplicate `placement_assessment_date` Field

**Issue:** Field appears twice in create form HTML

**Location:** `src/Learners/Shortcodes/learners-capture-shortcode.php`
- First instance: Lines 406-410 (inside Numeracy Level column)
- Second instance: Lines 411-416 (separate column)
- Both have `name="placement_assessment_date"`
- Both in `.placement_date_outerdiv` containers
- PHP `$_POST` takes last value when duplicates exist

**Impact:** Confusing to users, could mask validation issues, unnecessary HTML duplication

**Fix:** Delete second instance (lines 411-416) per audit recommendation (lines 414-422 in audit)

**Note:** Update shortcode does NOT have this duplication (verified lines 480-504)

---

### LRNR-05: Remove `nopriv` AJAX Registrations

**Issue:** Two AJAX endpoints registered for unauthenticated access

**Location:** `src/Learners/Ajax/LearnerAjaxHandlers.php`
- Line 292: `add_action('wp_ajax_fetch_learners_data', ...)`
- Line 293: `add_action('wp_ajax_fetch_learners_dropdown_data', ...)`

**Audit Note:** Lines 293 and 296 in audit (audit may be viewing different line spacing)

**Context:**
- Entire WP site requires authentication (login wall)
- Both endpoints still verify nonce and capability via `verify_learner_access(false)`
- `nopriv` hooks are defense-in-depth violations since site is authenticated-only
- Agents and Clients modules already removed their `nopriv` hooks in previous phases

**Fix:** Remove both `wp_ajax_nopriv_*` registrations (2 lines)

**Verification:** Grep for `nopriv` in Learners module after fix — should return 0 matches

---

### LRNR-06: Employment Status Initial Visibility Bug

**Issue:** Employer field always visible on initial page load in update form, regardless of employment status

**Root Cause:** `src/Learners/Shortcodes/learners-update-shortcode.php` line 522
```php
<div id="employer_field" class="mb-1" <?php echo $learner->employment_status !== 'Employed' ? 'style="display:none;"' : ''; ?>>
```

**Analysis:**
- `$learner->employment_status` is a string from CASE expression: "Employed" or "Unemployed"
- PHP comparison `!== 'Employed'` is correct
- **AUDIT ERROR:** Audit claimed line 514 uses `!$learner->employment_status` (always false for truthy strings)
- **CODE REALITY:** Line 522 uses correct comparison

**Research Finding:** Bug does NOT exist in current code. Audit reference may be stale or line numbers shifted. Verify during planning.

**JS Toggle:** `learners-app.js` lines 173-181 handle dynamic toggling correctly

---

### LRNR-07: Wrong Sanitizer for FK ID

**Issue:** `highest_qualification` is a foreign key ID but uses `sanitize_text_field()` instead of `intval()`

**Locations:**
- **Create shortcode** line 62: `'highest_qualification' => sanitize_text_field($_POST['highest_qualification'])`
- **Update shortcode** line 96: `'highest_qualification' => intval($_POST['highest_qualification'])`

**Research Finding:** Update shortcode ALREADY uses `intval()`. Only create shortcode needs fixing.

**Fix:** Change create shortcode line 62 to use `intval()` like the update shortcode

**Note:** PostgreSQL handles string-to-int casting gracefully, but `intval()` is best practice for FK IDs

---

### LRNR-08: Date Format Validation Missing

**Issue:** `placement_assessment_date` uses `sanitize_text_field()` without date format validation

**Current State:**
- **Create shortcode** line 64: Complex validation using `DateTime::createFromFormat()`
  ```php
  'placement_assessment_date' => (($d = \DateTime::createFromFormat('Y-m-d', $_POST['placement_assessment_date'] ?? '')) && $d->format('Y-m-d') === $_POST['placement_assessment_date']) ? $d->format('Y-m-d') : null,
  ```
- **Update shortcode** line 98: Same complex validation

**Research Finding:** Date validation ALREADY IMPLEMENTED in both shortcodes. Audit is stale.

**Verification:** Both shortcodes use identical robust date validation. No fix needed.

---

### LRNR-09: Template Literal XSS Risk

**Issue:** `learners-app.js` uses `.html()` to inject template literals containing server response data

**Location:** `assets/js/learners/learners-app.js` line 498 (audit reference line 486-510)

**Inspection of line 498-520 region:**
```javascript
success: function(response) {
    if (response.success) {
        if (isSinglePageContext) {
            // Single page context - show success and redirect
            showAlert('success', 'Learner deleted successfully. Redirecting...');
            setTimeout(function() {
                window.location.href = WeCozaLearners.display_learners_url;
            }, 2000);
```

**Research Finding:** Delete handler (lines 487-547) uses hardcoded strings, not template literals with `${response.data}`. Audit may be referencing different lines or older code.

**Action:** Search entire `learners-app.js` for `.html()` calls with template literal interpolation of server data.

**Grep Search Pattern:** `\.html\(.*\$\{.*response`

**Security Best Practice:** Use `.text()` for user-controlled or server response data, `.html()` only for static trusted content

---

### LRNR-10: Dead Code Cleanup

**Targets identified in audit:**

1. **Controller AJAX registrations** (unused):
   - `LearnerController::registerHooks()` registers 4 AJAX actions that no JS calls
   - `wecoza_get_learner`, `wecoza_get_learners`, `wecoza_update_learner`, `wecoza_delete_learner`
   - Actual AJAX flow uses `LearnerAjaxHandlers` with different action names
   - **Note:** Controller doesn't have `registerHooks()` method in current code (verified line 32-35)
   - May already be removed or audit reference is stale

2. **Legacy edit modal JS** (`learners-app.js`):
   - `#editLearnerForm` submit handler (lines 143-179 per audit, line 70+ verified)
   - `populateEditForm()` function (lines 96-136 per audit)
   - Targets `edit-*` prefixed field IDs that don't exist in any current view
   - Edit flow now uses dedicated update shortcode page, not modal

3. **Legacy view file:**
   - `.integrate/wecoza-learners-plugin/views/learner-form.view.php`
   - Uses different field names (`email`, `phone`, `id_number`, `address`, `city`)
   - Actual forms use `email_address`, `tel_number`, `sa_id_no`, `address_line_1`, `city_town_id`
   - Not referenced by any shortcode or controller
   - Safe to delete

**Verification Strategy:**
- Grep for `#editLearnerForm` in all JS files
- Grep for `populateEditForm` in all JS files
- Grep for `learner-form.view.php` in all PHP files (should return 0 matches)
- Check `LearnerController.php` for AJAX hook registrations

---

## 3. Technical Context

### Form Processing Paths

**Path 1: Shortcode Form POST (Primary)**
```
User submits form
  → learners-capture-shortcode.php or learners-update-shortcode.php POST handler
  → Sanitize + validate inputs
  → LearnerController::createLearner() or updateLearner()
  → LearnerModel::create() or update()
  → LearnerRepository::create() or update()
  → PostgreSQL INSERT/UPDATE
```

**Path 2: AJAX Handler (Secondary)**
```
JS sends AJAX request
  → wp_ajax_update_learner
  → LearnerAjaxHandlers::handle_update_learner()
  → LearnerController::updateLearner()
  → LearnerModel::update()
  → LearnerRepository::update()
```

**Path 3: Controller AJAX (Allegedly Unused)**
```
JS sends AJAX (but no JS does this)
  → wp_ajax_wecoza_update_learner
  → LearnerController::ajaxUpdateLearner()
  → sanitizeLearnerInput()
  → updateLearner()
```

### Nonce Patterns

| Context | Nonce Field | Nonce Action |
|---------|-------------|--------------|
| Create form | `wecoza_learners_form_nonce` | `submit_learners_form` |
| Update form | `wecoza_learners_update_form_nonce` | `submit_learners_update_form` |
| AJAX handlers | `learners_nonce` | `nonce` |
| Controller AJAX | `learners_nonce_action` | (capability check) |

**Consistency Issue:** 4 different nonce patterns across learner operations. Agents and Clients modules use unified nonce patterns.

### Repository Column Whitelisting

`LearnerRepository::getAllowedInsertColumns()` (lines 68-78):
```php
return [
    'title', 'first_name', 'second_name', 'initials', 'surname',
    'gender', 'race', 'sa_id_no', 'passport_number',
    'tel_number', 'alternative_tel_number', 'email_address',
    'address_line_1', 'address_line_2',
    'city_town_id', 'province_region_id', 'postal_code',
    'highest_qualification', 'assessment_status',
    'placement_assessment_date', 'numeracy_level', 'communication_level',
    'employment_status', 'employer_id', 'disability_status',
    'scanned_portfolio', 'created_at', 'updated_at'
];
```

**LRNR-03 Fix:** Remove `suburb` from this array (not present in current code — may already be fixed)

### Database Schema

**learners table:**
- 27 columns (base learner data)
- No `date_of_birth` column
- No `suburb` column (suburb comes from `locations` JOIN)
- `numeracy_level` and `communication_level` are integer FKs to `learner_placement_level`

**learner_sponsors table:**
- `id` (PK)
- `learner_id` (FK to learners.id)
- `employer_id` (FK to employers.employer_id)
- `created_at`
- UNIQUE constraint on (learner_id, employer_id)

**learner_lp_tracking table:**
- LP progression data (managed by ProgressionService)
- Not touched by form POST processing

**learner_portfolios table:**
- Portfolio file uploads (managed by PortfolioUploadService)
- Not touched by form POST processing

---

## 4. Dependencies & Risks

### Dependencies
- None. All fixes are self-contained within Learners module.
- No database migrations required (LRNR-02 table already exists)
- No external module interactions

### Risks

**LRNR-01 (numeracy_level):**
- **Risk:** Already fixed. Verify before implementing.
- **Mitigation:** Check line 99 of update shortcode during planning.

**LRNR-02 (sponsors):**
- **Risk:** User may want feature removed despite it being implemented.
- **Mitigation:** ASK user during planning or implementation. Don't assume.

**LRNR-03 (phantom fields):**
- **Risk:** Removing from baseline doc may confuse if fields were intentional future plans.
- **Mitigation:** Baseline doc is a reference, not a roadmap. Safe to remove non-existent fields.

**LRNR-06 (employment status):**
- **Risk:** Bug may not exist in current code (audit stale).
- **Mitigation:** Verify line 522 of update shortcode uses correct comparison.

**LRNR-08 (date validation):**
- **Risk:** Already implemented. Don't duplicate effort.
- **Mitigation:** Verify both shortcodes have DateTime validation before implementing.

**LRNR-09 (XSS):**
- **Risk:** Audit line numbers may not match actual XSS locations.
- **Mitigation:** Grep entire file for `.html()` calls with template literal interpolation.

**LRNR-10 (dead code):**
- **Risk:** Removing code that's actually used could break functionality.
- **Mitigation:** Grep for usage before deleting. Test after deletion.

---

## 5. Testing Strategy

### Pre-Implementation Verification

1. **LRNR-01:** Grep update shortcode for `numeracy_level` — should already be present
2. **LRNR-02:** Check if `learner_sponsors` table exists in live DB
3. **LRNR-06:** Check line 522 of update shortcode for comparison operator
4. **LRNR-07:** Check update shortcode line 96 — should already use `intval()`
5. **LRNR-08:** Check both shortcodes for DateTime validation

### Post-Implementation Verification

1. **LRNR-01:** Update a learner's numeracy level → verify persisted in DB
2. **LRNR-02:** If sponsors kept: add sponsor → verify in `learner_sponsors` table
3. **LRNR-03:** Grep for `suburb` in LearnerRepository whitelist → 0 matches
4. **LRNR-04:** View create form HTML → single placement date field
5. **LRNR-05:** Grep Learners module for `nopriv` → 0 matches
6. **LRNR-06:** Load update form for unemployed learner → employer field hidden
7. **LRNR-07:** Check create shortcode uses `intval()` for highest_qualification
8. **LRNR-08:** Submit invalid date format → should be rejected or nulled
9. **LRNR-09:** Grep `learners-app.js` for `.html()` with template literals → 0 unsafe matches
10. **LRNR-10:** Grep for deleted function names → 0 matches

### Manual Testing Checklist

- [ ] Create learner with all fields populated
- [ ] Update learner and change numeracy level (LRNR-01)
- [ ] Add sponsor to learner (LRNR-02, if keeping feature)
- [ ] Remove sponsor from learner (LRNR-02, if keeping feature)
- [ ] Create learner with "Not Assessed" status → verify placement fields hidden
- [ ] Update unemployed learner → verify employer field hidden on load (LRNR-06)
- [ ] Update employed learner → verify employer field visible on load
- [ ] Submit form with invalid placement date → verify rejection (LRNR-08)
- [ ] View create form source → verify single placement date field (LRNR-04)

---

## 6. Key Questions for Planner

### Decision Points

1. **LRNR-02 (Sponsors Feature):**
   - Keep implemented feature or remove UI?
   - If remove: delete lines 449-467 (create), 537-554 (update), POST processing (109-113 create, 112-116 update)
   - If keep: no changes needed, feature is complete

2. **LRNR-10 (Dead Code Scope):**
   - Remove only items listed in audit?
   - Or scan for additional unused code?
   - Legacy view file safe to delete?

3. **Baseline Doc Update (LRNR-03):**
   - Just remove phantom fields?
   - Or full audit of baseline doc for other discrepancies?

### Clarifications Needed

1. Confirm LRNR-01, LRNR-06, LRNR-07, LRNR-08 are already fixed in current code
2. Confirm audit line numbers may be stale (code has shifted)
3. Verify sponsors feature is desired or should be removed

### Edge Cases

1. What if learner has existing numeracy_level = null in DB? (LRNR-01 historical data)
2. What if learner has existing sponsors when feature is removed? (LRNR-02 orphaned data)
3. What if baseline doc is auto-generated and will be overwritten? (LRNR-03 futility)

---

## 7. Recommended Fix Order

### Group 1: Data Loss Prevention (CRITICAL)
1. **LRNR-01** — Verify numeracy_level fix (if not present, add it)

### Group 2: Security Hardening
2. **LRNR-05** — Remove nopriv AJAX registrations
3. **LRNR-09** — Fix XSS risks in JS

### Group 3: Data Integrity
4. **LRNR-03** — Remove phantom fields from baseline and whitelist
5. **LRNR-04** — Remove duplicate placement date field
6. **LRNR-07** — Use intval() for FK sanitization

### Group 4: User Experience
7. **LRNR-06** — Fix employment status visibility (verify bug exists first)
8. **LRNR-08** — Verify date validation (likely already implemented)

### Group 5: Code Quality
9. **LRNR-10** — Remove dead code (low risk, high value)

### Group 6: Feature Decision
10. **LRNR-02** — Ask user about sponsors feature, act accordingly

---

## 8. Files to Modify (Summary)

| File | Requirements | Change Type |
|------|--------------|-------------|
| `src/Learners/Shortcodes/learners-capture-shortcode.php` | LRNR-01, LRNR-04, LRNR-07 | Edit 3 lines, delete 6 lines |
| `src/Learners/Shortcodes/learners-update-shortcode.php` | LRNR-02, LRNR-06 | Verify/edit 1-2 lines, possibly delete 10+ |
| `src/Learners/Ajax/LearnerAjaxHandlers.php` | LRNR-05 | Delete 2 lines |
| `assets/js/learners/learners-app.js` | LRNR-09, LRNR-10 | Edit/delete 50-100 lines |
| `src/Learners/Repositories/LearnerRepository.php` | LRNR-03 | Delete 1 word from array |
| `docs/FORM-FIELDS-REFERENCE.md` | LRNR-03 | Delete 2 table rows |
| `.integrate/wecoza-learners-plugin/views/learner-form.view.php` | LRNR-10 | Delete entire file (optional) |

**Total Scope:** ~7 files, ~150 lines of changes (mostly deletions)

---

## 9. Open Questions

1. Has the learners-audit.md been updated since the recent sponsor implementation?
2. Are there any other recent fixes that made parts of the audit stale?
3. Should we re-run the audit after fixes to verify no new issues introduced?
4. Is the baseline doc (`FORM-FIELDS-REFERENCE.md`) authoritative or aspirational?

---

## 10. References for Planner

### Code Patterns to Follow

**Date validation pattern** (already in use):
```php
(($d = \DateTime::createFromFormat('Y-m-d', $_POST['field'] ?? '')) && $d->format('Y-m-d') === $_POST['field']) ? $d->format('Y-m-d') : null
```

**Array sanitization pattern** (sponsors):
```php
$sponsor_ids = array_map('intval', $_POST['sponsors']);
```

**Conditional div visibility** (PHP inline):
```php
<div <?php echo $condition ? 'style="display:none;"' : ''; ?>>
```

**AJAX security pattern** (consistent across modules):
```php
AjaxSecurity::requireNonce('learners_nonce_action');
if (!current_user_can('manage_learners')) {
    wp_send_json_error('Insufficient permissions', 403);
}
```

### Testing Assets

- Feature parity test: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/tests/learners-feature-parity-test.php` (if exists)
- DB schema: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/schema/learner_sponsors.sql`
- Debug log: `/opt/lampp/htdocs/wecoza/wp-content/debug.log`

### Related Phases

- **Phase 32:** Classes Module Fixes (CLS-01 through CLS-09)
- **Phase 33:** Agents Module Fixes (AGT-01 through AGT-06)
- **Phase 34:** Clients Module Fixes (CLT-01 through CLT-05)
- **Phase 35:** Events Module Fixes (EVT-01 through EVT-04)

---

## Conclusion

Phase 31 is a straightforward debugging phase with a comprehensive audit as the implementation guide. Most issues have exact line numbers and recommended fixes. The primary research finding is that several bugs (LRNR-01, LRNR-06, LRNR-07, LRNR-08) may already be fixed in recent commits, and the sponsors feature (LRNR-02) is fully implemented contrary to the audit's claim.

**Key Action for Planner:**
1. Verify current code state against audit claims
2. Ask user about sponsors feature (keep or remove)
3. Create fix plans for confirmed bugs only
4. Group fixes logically (data loss → security → UX → cleanup)

**Estimated Effort:** 2-3 hours implementation + 1 hour testing (assuming no sponsors removal)
