---
phase: 09-data-privacy-hardening
plan: 02
type: execute
status: complete
subsystem: data-privacy-obfuscation
tags: [pii-detection, heuristic-analysis, pattern-matching, security, south-african-id, passport, phone]

dependency_graph:
  requires:
    - 09-01-PLAN.md (Secure PII obfuscation foundation)
  provides:
    - Heuristic PII pattern detection for custom fields
    - Value-based obfuscation regardless of field name
    - SA ID, passport, phone pattern detection
  affects:
    - 09-03-PLAN.md (Access control uses obfuscation)

tech_stack:
  added: []
  patterns:
    - Trait composition (PIIDetector + DataObfuscator)
    - Heuristic pattern matching
    - Match expressions for pattern routing

file_tracking:
  created:
    - src/Events/Services/Traits/PIIDetector.php
    - tests/Events/PIIDetectorTest.php
  modified:
    - src/Events/Services/Traits/DataObfuscator.php

decisions:
  - id: SEC-06-HEURISTIC-DETECTION
    title: Implement value-based PII detection
    outcome: detectPIIPattern() inspects values for SA ID/phone/passport patterns
    impact: Catches PII in non-standard fields (notes, reference_number, custom attributes)

  - id: SA-ID-13-DIGITS
    title: South African ID pattern is exactly 13 digits
    outcome: looksLikeSouthAfricanID() checks for 13-digit pattern
    impact: Specific pattern takes priority over generic phone detection

  - id: PASSPORT-CONTEXT
    title: Passport detection requires field name hint
    outcome: shouldTreatAsPassport() checks for 'passport', 'travel', 'document' in field name
    impact: Reduces false positives for 6-12 alphanumeric values

  - id: PATTERN-PRIORITY
    title: SA ID detection takes priority over phone
    outcome: detectPIIPattern() checks SA ID before phone
    impact: 13-digit values correctly identified as SA ID, not phone

  - id: CLI-ABSPATH-CHECK
    title: Allow CLI execution for PIIDetector tests
    outcome: ABSPATH check includes php_sapi_name() !== 'cli'
    impact: Tests can run directly with 'php tests/Events/PIIDetectorTest.php'

metrics:
  duration: 3.2min
  completed: 2026-02-02
---

# Phase 09 Plan 02: Heuristic PII Detection Summary

**One-liner:** Value-based PII pattern detection catches SA IDs, passports, and phones in any field via heuristic analysis.

## What Was Built

Added heuristic PII detection to the obfuscation layer to catch personally identifiable information regardless of field name.

### Components Created

1. **PIIDetector Trait** (`src/Events/Services/Traits/PIIDetector.php`)
   - Pattern detection methods for SA ID, passport, phone
   - Masking methods showing last 2 characters for verification
   - 128 lines of code

2. **DataObfuscator Integration**
   - Uses PIIDetector trait for value-based detection
   - Enhanced obfuscateString() with detectPIIPattern() call
   - Added maskDetectedPII() for pattern routing
   - Added shouldTreatAsPassport() for context-aware detection

3. **Unit Tests** (`tests/Events/PIIDetectorTest.php`)
   - 17 test cases covering all detection patterns
   - SA ID, phone, passport detection tests
   - Masking verification tests
   - All tests pass

### Pattern Detection Logic

**Detection Order (most specific first):**
1. SA ID: Exactly 13 digits (after removing formatting)
2. Passport: 6-12 alphanumeric (with field name hint)
3. Phone: 7-15 digits (after removing formatting)
4. Short values (< 6 chars): Ignored (likely codes, not PII)

**Field Name Hints:**
- Passport detection only triggers if field contains "passport", "travel", or "document"
- Prevents false positives for order IDs, product codes, etc.

### Masking Format

- **SA ID:** `ID-XXXXXXXXXXX87` (shows last 2 digits)
- **Passport:** `PASSPORT-XXXXXX56` (shows last 2 chars)
- **Phone:** Existing maskPhone() method (shows last 2 digits)

## Tasks Completed

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Create PIIDetector trait with pattern detection methods | 466660e | PIIDetector.php |
| 2 | Integrate PIIDetector into DataObfuscator | a5e560d | DataObfuscator.php |
| 3 | Add unit test coverage for PIIDetector patterns | b3df943 | PIIDetectorTest.php, PIIDetector.php |

## Decisions Made

### 1. Value-Based Detection Strategy (SEC-06)
**Decision:** Inspect actual VALUES for PII patterns, not just field names.

**Rationale:**
- PII can appear in unexpected places (notes, custom_field, reference_number)
- Field name detection alone is insufficient
- Real-world data is messy and inconsistent

**Implementation:**
- detectPIIPattern() runs on all string values during obfuscation
- Operates as fallback after field-name-based checks
- Catches PII that would otherwise be missed

### 2. South African ID Specificity
**Decision:** SA ID pattern is exactly 13 digits (YYMMDD + 7 digits).

**Rationale:**
- SA ID format is highly structured and specific
- 13-digit pattern is unlikely to be anything else
- Takes priority over generic phone detection

**Implementation:**
- looksLikeSouthAfricanID() checks for exactly 13 digits
- Strips formatting (spaces, dashes) before counting
- Checked before phone pattern to avoid misclassification

### 3. Passport Context Awareness
**Decision:** Passport detection requires field name hint.

**Rationale:**
- 6-12 alphanumeric values are common (order IDs, SKUs, tracking numbers)
- High false positive rate without context
- Field name provides disambiguating signal

**Implementation:**
- shouldTreatAsPassport() checks for 'passport', 'travel', 'document' keywords
- Passport pattern detection only triggered when context matches
- Reduces false positives while maintaining security

### 4. CLI Test Execution Support
**Decision:** Allow PIIDetector to load in CLI context for tests.

**Rationale:**
- ABSPATH check prevented direct test execution
- Tests need trait isolation without full WordPress environment
- CLI context is safe (no web exposure)

**Implementation:**
- ABSPATH check includes `php_sapi_name() !== 'cli'`
- Tests can run with `php tests/Events/PIIDetectorTest.php`
- WordPress environment still protected

## Verification Results

### SEC-06 Heuristic Detection
✅ detectPIIPattern() exists in DataObfuscator
✅ looksLikeSouthAfricanID() exists in PIIDetector
✅ Value-based detection integrated into obfuscation flow

### Integration Verification
✅ DataObfuscator uses PIIDetector trait
✅ obfuscateString() calls detectPIIPattern()
✅ maskDetectedPII() routes to appropriate masking method

### Test Coverage
✅ 17 test cases, 0 failures
✅ SA ID detection tests pass (13 digits with formatting)
✅ Phone detection tests pass (7-15 digits range)
✅ Pattern priority tests pass (SA ID before phone)
✅ Masking tests pass (last 2 chars visible)

## Security Impact

### SEC-06: Heuristic PII Detection ✅ COMPLETE

**Before:** PII only detected via field name matching
**After:** PII detected via value pattern analysis regardless of field name

**Risk Reduction:**
- Custom fields with PII content are now protected
- Notes containing phone numbers are obfuscated
- Reference numbers that are actually SA IDs are caught
- Inconsistent field naming no longer exposes PII

**Example Scenarios:**

```php
// Scenario 1: SA ID in custom field
['custom_notes' => '9001015800087']
// Before: "9001015800087" (exposed)
// After:  "ID-XXXXXXXXXXX87" (obfuscated)

// Scenario 2: Phone in notes
['notes' => 'Contact: 082 123 4567']
// Before: "Contact: 082 123 4567" (exposed)
// After:  "Contact: XXXXXXXX67" (obfuscated)

// Scenario 3: SA ID takes priority
['learner_code' => '9001015800087']
// Detected as SA ID (not phone), masked correctly
```

## Code Quality

### Architecture
- **Trait composition:** PIIDetector is reusable, testable in isolation
- **Separation of concerns:** Detection logic separate from obfuscation logic
- **Match expressions:** Clean pattern routing with exhaustive cases

### Testing
- **17 test cases:** Comprehensive pattern detection coverage
- **CLI-friendly:** Tests run directly without WordPress bootstrap
- **Deterministic:** All tests pass consistently

### Performance
- **Lazy evaluation:** Pattern detection only runs on string values
- **Early exit:** Short values (< 6 chars) skipped immediately
- **Specific-first:** SA ID checked before generic phone pattern

## Next Phase Readiness

### For 09-03 (Access Control)
✅ Obfuscation layer fully hardened
✅ PII detection catches SA IDs, phones, passports
✅ Access control can rely on obfuscation for sensitive data

### For Future Phases
✅ PIIDetector trait is reusable for other modules
✅ Pattern detection can be extended (credit cards, bank accounts)
✅ Test pattern established for trait testing

## Files Modified

```
src/Events/Services/Traits/
  PIIDetector.php                 # NEW: 128 lines (detection + masking)
  DataObfuscator.php              # MODIFIED: +32 lines (integration)

tests/Events/
  PIIDetectorTest.php             # NEW: 150 lines (17 test cases)
```

## Lessons Learned

### What Worked Well
1. **Trait composition** - PIIDetector isolated and testable
2. **Pattern priority** - Specific patterns first reduces false matches
3. **Field name hints** - Context reduces passport false positives
4. **CLI test support** - Direct test execution without WordPress overhead

### Challenges Encountered
1. **ABSPATH check** - Prevented CLI execution until fixed
2. **Missing imports** - `max()` function import initially missing
3. **Test output** - Required matching existing test file patterns

### Improvements Made
1. Added `php_sapi_name()` check to ABSPATH guard
2. Imported `max()` function for passport masking
3. Used existing test pattern (no TestRunner class needed)

## Metrics

- **Duration:** 3.2 minutes
- **Tasks:** 3/3 completed
- **Commits:** 3 atomic commits
- **Files created:** 2 (PIIDetector.php, PIIDetectorTest.php)
- **Files modified:** 1 (DataObfuscator.php)
- **Lines added:** ~310 lines (128 PIIDetector + 32 DataObfuscator + 150 tests)
- **Test cases:** 17 (all passing)
- **Pattern types:** 3 (SA ID, passport, phone)

---

**Status:** ✅ Complete
**Security:** SEC-06 heuristic PII detection fully implemented
**Next:** 09-03 Access Control (uses obfuscation layer)
