---
phase: 31-learners-module-fixes
plan: 02
subsystem: learners
tags: [security, cleanup, dead-code-removal]
dependency_graph:
  requires: ["31-01-PLAN.md"]
  provides: ["lrnr-09-xss-fix", "lrnr-10-dead-code-removal"]
  affects: []
tech_stack:
  patterns: ["xss-prevention", "dom-construction"]
key_files:
  modified:
    - assets/js/learners/learners-app.js
  deleted:
    - .integrate/wecoza-learners-plugin/views/learner-form.view.php
decisions:
  - LRNR-03 already correct - no phantom fields in docs or repository whitelist
  - Legacy template was untracked by git - deletion required no commit
metrics:
  duration: "2 minutes"
  completed: "2026-02-13"
  fixes_implemented: 2
  already_fixed: 1
---

# Phase 31 Plan 02: Learners Module Fixes Summary

**One-liner:** Fixed XSS vulnerability in showAlert() using DOM construction; deleted legacy template; verified all 10 LRNR requirements resolved

## Context

This execution plan implemented the 3 remaining fixes from the Phase 31 Learners audit (LRNR-03, LRNR-09, LRNR-10). Plan 31-01 verification revealed 7/10 items were already fixed in commit e47bc30 (2026-02-12), leaving only these 3 items requiring work.

**Actual scope:** Only 2 of 3 items needed fixes. LRNR-03 was already correct upon deeper inspection.

---

## Task Completion

### Task 1: Fix XSS vulnerability in showAlert (LRNR-09)

**Status:** ✅ Completed
**Commit:** 8e1d5ec

**Issue:** The `showAlert()` function at `assets/js/learners/learners-app.js:546-571` used template literals with `${message}` where `message` received server data from `response.data.message`. This created an XSS vector - malicious HTML in server responses would be executed.

**Fix:**
- Replaced template literal HTML construction with jQuery DOM creation
- Changed `.html(alertHtml)` to `.html('').append($alert)`
- Used `.text(message)` instead of `${message}` to safely insert user/server data
- Message content now rendered as plain text, preventing HTML injection

**Verification:**
```bash
grep -n "\.text(message)" assets/js/learners/learners-app.js
# Found at line 551 (correct)

grep -n '\.html.*\${' assets/js/learners/learners-app.js
# 0 matches (no unsafe patterns remaining)
```

**Additional audit:** All other `.html()` calls in the file checked:
- Lines 374, 391: Static hardcoded strings (safe)
- Line 381: Uses `.text()` for dynamic content (safe)
- Lines 496, 507, 535, 540: Button text manipulation, no server data (safe)

---

### Task 2: Documentation cleanup (LRNR-03)

**Status:** ✅ Already correct - no action needed

**Finding:** The verification summary claimed phantom fields `date_of_birth` and `suburb` existed in `docs/FORM-FIELDS-REFERENCE.md` Learners section, but this was incorrect.

**Evidence:**
1. **FORM-FIELDS-REFERENCE.md Learners section (lines 285-373):**
   - Does NOT contain `date_of_birth` entry
   - Does NOT contain `suburb` entry in Learners Address table (lines 315-323)
   - All documented fields match actual form implementation

2. **LearnerRepository::getAllowedInsertColumns() (lines 68-78):**
   - Does NOT include `suburb` (correct - suburb comes from locations JOIN on read)
   - Does NOT include `date_of_birth` (field doesn't exist)

3. **Repository usage of suburb:**
   - Line 113: `locations.suburb AS suburb` in SELECT JOIN (read-only, correct)
   - Not in insert/update whitelists (correct)

**Conclusion:** Documentation is already accurate. The verification plan misidentified this as needing fixes.

---

### Task 3: Delete dead legacy template (LRNR-10)

**Status:** ✅ Completed (no commit - file untracked)

**Deleted file:** `.integrate/wecoza-learners-plugin/views/learner-form.view.php`

**Verification:**
```bash
grep -r "learner-form.view.php" /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/ --include="*.php"
# 0 results - no references to this file anywhere

ls .integrate/wecoza-learners-plugin/views/learner-form.view.php
# File not found (deleted)
```

**Note:** File was not tracked by git (untracked migration artifact), so deletion required no commit. The `.integrate/` directory contains other legacy files that are still referenced, so the directory itself was preserved.

---

## Final Verification: All 10 LRNR Requirements

Ran comprehensive grep-based verification across all requirements:

| Requirement | Status | Evidence |
|-------------|--------|----------|
| **LRNR-01** | ✅ Pass | `learners-update-shortcode.php:99` has `intval($_POST['numeracy_level'])` |
| **LRNR-02** | ✅ Pass | Sponsors feature fully wired (5 matches in create, 6 in update) |
| **LRNR-03** | ✅ Pass | Suburb only in SELECT JOIN (line 113), not in insert whitelist; date_of_birth absent |
| **LRNR-04** | ✅ Pass | Exactly 1 occurrence of `name="placement_assessment_date"` |
| **LRNR-05** | ✅ Pass | 0 `nopriv` matches across entire Learners module |
| **LRNR-06** | ✅ Pass | Line 522: `$learner->employment_status !== 'Employed'` (correct comparison) |
| **LRNR-07** | ✅ Pass | Both shortcodes use `intval($_POST['highest_qualification'])` |
| **LRNR-08** | ✅ Pass | Both shortcodes use `DateTime::createFromFormat()` with strict validation |
| **LRNR-09** | ✅ Pass | `showAlert()` now uses `.text(message)` (XSS-safe) |
| **LRNR-10** | ✅ Pass | Legacy template deleted, no dead JS functions |

**All 10 requirements verified passing.** Phase 31 success criteria met.

---

## Files Created/Modified

### Modified
- `assets/js/learners/learners-app.js` - XSS fix in showAlert function (lines 546-571)

### Deleted
- `.integrate/wecoza-learners-plugin/views/learner-form.view.php` - Legacy template (untracked)

---

## Deviations from Plan

### 1. LRNR-03 Already Correct (deviation from verification summary)

**Plan expected:** Remove phantom fields `date_of_birth` and `suburb` from FORM-FIELDS-REFERENCE.md Learners section

**Actual state:** These fields were never present in the Learners section. Documentation already accurate.

**Why deviation occurred:** The verification summary (31-01-SUMMARY.md line 91) misidentified this as needing fixes. Deeper inspection during execution revealed the documentation correctly reflected the repository whitelist and actual forms.

**Impact:** Saved unnecessary documentation churn. No files modified.

---

## Credit Attribution

**7 of 10 LRNR requirements** were already resolved prior to this plan, likely by commit e47bc30 (2026-02-12) titled "fix(learners): phase 31 — fix all form field wiring issues (LRNR-01 to LRNR-10)".

This plan executed the 2 genuine remaining fixes (LRNR-09 XSS, LRNR-10 dead code) that were not covered by that earlier commit.

---

## Next Phase Readiness

**Phase 31 complete.** All form field wiring issues in Learners module resolved.

**Ready for Phase 32:** Classes Module Fixes (CLSS-01 through CLSS-12) per ROADMAP.md

---

## Self-Check: PASSED

### Commit Verification
```bash
git log --oneline --grep="31-02"
# Result: 8e1d5ec fix(31-02): prevent XSS in learners showAlert function
```

### File Existence
```bash
[ -f "assets/js/learners/learners-app.js" ] && echo "FOUND" || echo "MISSING"
# Result: FOUND

[ -f ".integrate/wecoza-learners-plugin/views/learner-form.view.php" ] && echo "FOUND" || echo "MISSING"
# Result: MISSING (correct - file deleted)
```

### Code Pattern Verification
```bash
grep -q "\.text(message)" assets/js/learners/learners-app.js && echo "XSS fix present" || echo "XSS fix missing"
# Result: XSS fix present

grep -q '\.html.*\${' assets/js/learners/learners-app.js && echo "Unsafe patterns found" || echo "No unsafe patterns"
# Result: No unsafe patterns
```

All self-checks passed. Evidence-backed findings are accurate.
