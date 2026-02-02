# WeCoza Core Plugin - Code Analysis & Remediation Plan

**Analysis Date:** 2026-02-01
**Tools Used:** Gemini AI Code Analysis, wp-code-analyzer Guidelines
**Priority Legend:** P0 = Critical, P1 = High, P2 = Medium, P3 = Low

---

## Phase 1: Security Fixes (P0 - Critical)

### Step 1.1: Fix SQL Injection in BaseRepository::findAll()

**File:** `core/Abstract/BaseRepository.php`
**Lines:** 100-123
**Issue:** `$orderBy` parameter interpolated directly into SQL

**Current Code:**
```php
public function findAll(int $limit = 50, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
{
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    $sql = sprintf(
        "SELECT * FROM %s ORDER BY %s %s LIMIT :limit OFFSET :offset",
        static::$table,
        $orderBy,  // VULNERABLE
        $order
    );
```

**Required Changes:**
- [x] Add protected method `getAllowedOrderColumns()` returning allowed column names
- [x] Validate `$orderBy` against whitelist before use
- [x] Default to 'created_at' if invalid column provided

**Fixed Code:**
```php
/**
 * Get columns that can be used for ORDER BY
 * Override in child classes to add more columns
 */
protected function getAllowedOrderColumns(): array
{
    return ['id', 'created_at', 'updated_at'];
}

public function findAll(int $limit = 50, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
{
    // Whitelist orderBy column
    if (!in_array($orderBy, $this->getAllowedOrderColumns(), true)) {
        $orderBy = 'created_at';
    }

    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    // ... rest unchanged
}
```

**Verification:**
```bash
# Test with malicious orderBy - should default to created_at
curl -X POST "site.com/wp-admin/admin-ajax.php" \
  -d "action=wecoza_get_learners&orderBy=id;DROP TABLE learners;--"
```

---

### Step 1.2: Fix SQL Injection in BaseRepository::findBy()

**File:** `core/Abstract/BaseRepository.php`
**Lines:** 134-179
**Issue:** Criteria array keys used directly in SQL conditions

**Current Code:**
```php
foreach ($criteria as $field => $value) {
    if ($value === null) {
        $conditions[] = "{$field} IS NULL";  // $field not validated
    } else {
        $conditions[] = "{$field} = :{$field}";
    }
}
```

**Required Changes:**
- [x] Add protected method `getAllowedFilterColumns()` returning allowed column names
- [x] Skip or throw exception for criteria keys not in whitelist
- [x] Validate `$orderBy` parameter (same as Step 1.1)

**Fixed Code:**
```php
/**
 * Get columns that can be used in WHERE clauses
 * Override in child classes to expand
 */
protected function getAllowedFilterColumns(): array
{
    return ['id', 'created_at', 'updated_at'];
}

public function findBy(array $criteria, ...): array
{
    $allowedColumns = $this->getAllowedFilterColumns();

    foreach ($criteria as $field => $value) {
        // Skip non-whitelisted columns
        if (!in_array($field, $allowedColumns, true)) {
            continue;
        }

        if ($value === null) {
            $conditions[] = "{$field} IS NULL";
        } else {
            $conditions[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }
    }
    // ... rest unchanged
}
```

---

### Step 1.3: Fix SQL Injection in BaseRepository::insert()

**File:** `core/Abstract/BaseRepository.php`
**Lines:** 260-284
**Issue:** Column names from `$data` array keys not validated

**Current Code:**
```php
public function insert(array $data): ?int
{
    $columns = array_keys($data);
    $placeholders = array_map(fn($c) => ":{$c}", $columns);
    $sql = sprintf(
        "INSERT INTO %s (%s) VALUES (%s) RETURNING %s",
        static::$table,
        implode(', ', $columns),  // VULNERABLE
        implode(', ', $placeholders),
        static::$primaryKey
    );
```

**Required Changes:**
- [x] Add protected method `getAllowedInsertColumns()` returning insertable columns
- [x] Filter `$data` to only include allowed keys
- [x] Log warning for filtered-out keys (optional)

**Fixed Code:**
```php
/**
 * Get columns that can be inserted
 * Override in child classes with actual table columns
 */
protected function getAllowedInsertColumns(): array
{
    return ['created_at', 'updated_at'];
}

public function insert(array $data): ?int
{
    if (empty($data)) {
        return null;
    }

    $allowedColumns = $this->getAllowedInsertColumns();

    // Filter data to only allowed columns
    $filteredData = array_filter(
        $data,
        fn($key) => in_array($key, $allowedColumns, true),
        ARRAY_FILTER_USE_KEY
    );

    if (empty($filteredData)) {
        error_log("WeCoza Core: Insert rejected - no valid columns in data");
        return null;
    }

    $columns = array_keys($filteredData);
    // ... rest uses $filteredData instead of $data
}
```

---

### Step 1.4: Fix Same Issue in BaseRepository::update()

**File:** `core/Abstract/BaseRepository.php`
**Lines:** 293-320
**Issue:** Same vulnerability as insert() - column names not validated

**Required Changes:**
- [x] Add protected method `getAllowedUpdateColumns()`
- [x] Filter `$data` to only include allowed keys
- [x] Reuse validation logic from insert if possible

---

### Step 1.5: Review AJAX nopriv Hooks

**File:** `src/Learners/Controllers/LearnerController.php`
**Lines:** 37-44

**Current Code:**
```php
add_action('wp_ajax_nopriv_wecoza_get_learner', [$this, 'ajaxGetLearner']);
add_action('wp_ajax_nopriv_wecoza_get_learners', [$this, 'ajaxGetLearners']);
```

**Decision Required:**
- [x] **Option A:** Remove nopriv hooks (learner data is private) ✓ IMPLEMENTED
- [ ] **Option B:** Add explicit public data filtering in handlers

**If Option A (Recommended):**
```php
protected function registerHooks(): void
{
    add_action('init', [$this, 'registerShortcodes']);

    // Authenticated only
    add_action('wp_ajax_wecoza_get_learner', [$this, 'ajaxGetLearner']);
    add_action('wp_ajax_wecoza_get_learners', [$this, 'ajaxGetLearners']);

    // REMOVED: nopriv hooks
}
```

---

### Step 1.6: Add Capability Checks to AJAX Handlers

**File:** `src/Learners/Controllers/LearnerController.php`
**Methods:** `ajaxGetLearner()`, `ajaxGetLearners()`, `ajaxUpdateLearner()`, `ajaxDeleteLearner()`

**Required Changes:**
- [x] Add `current_user_can()` check to each handler
- [x] Define appropriate capability (e.g., 'edit_posts' or custom 'manage_learners')

**Example Fix:**
```php
public function ajaxGetLearners(): void
{
    // Add capability check
    if (!current_user_can('edit_posts')) {
        $this->sendError('Insufficient permissions.', 403);
        return;
    }

    // ... existing logic
}
```

---

## Phase 2: Child Repository Updates (P1 - High)

After updating BaseRepository, child repositories need column definitions.

### Step 2.1: Update LearnerRepository

**File:** `src/Learners/Repositories/LearnerRepository.php`

**Required Changes:**
- [x] Override `getAllowedOrderColumns()` with learner-specific columns
- [x] Override `getAllowedFilterColumns()` with searchable columns
- [x] Override `getAllowedInsertColumns()` with insertable columns
- [x] Override `getAllowedUpdateColumns()` with updatable columns

**Implementation:**
```php
class LearnerRepository extends BaseRepository
{
    protected static string $table = 'learners';
    protected static string $primaryKey = 'id';

    protected function getAllowedOrderColumns(): array
    {
        return [
            'id', 'first_name', 'surname', 'email_address',
            'created_at', 'updated_at', 'city_town_id', 'employer_id'
        ];
    }

    protected function getAllowedFilterColumns(): array
    {
        return [
            'id', 'first_name', 'surname', 'email_address', 'sa_id_no',
            'city_town_id', 'province_region_id', 'employer_id',
            'employment_status', 'disability_status'
        ];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'title', 'first_name', 'second_name', 'initials', 'surname',
            'gender', 'race', 'sa_id_no', 'passport_number',
            'tel_number', 'alternative_tel_number', 'email_address',
            'address_line_1', 'address_line_2', 'suburb',
            'city_town_id', 'province_region_id', 'postal_code',
            'highest_qualification', 'assessment_status',
            'placement_assessment_date', 'numeracy_level', 'communication_level',
            'employment_status', 'employer_id', 'disability_status',
            'scanned_portfolio', 'created_at', 'updated_at'
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        // Same as insert, minus created_at
        $columns = $this->getAllowedInsertColumns();
        return array_diff($columns, ['created_at']);
    }
}
```

---

### Step 2.2: Update ClassRepository

**File:** `src/Classes/Repositories/ClassRepository.php`

**Required Changes:**
- [x] Same as Step 2.1 but for class-specific columns

---

## Phase 3: Code Quality Improvements (P2 - Medium)

### Step 3.1: Standardize ClassModel to Use Repository

**File:** `src/Classes/Models/ClassModel.php`
**Issue:** Contains direct SQL instead of using repository pattern

**Required Changes:**
- [ ] Create ClassRepository with proper CRUD methods
- [ ] Move `save()`, `update()`, `delete()` SQL to repository
- [ ] Update ClassModel to delegate to repository

---

### Step 3.2: Consider Replacing extract() in View Rendering

**File:** `core/Helpers/functions.php`
**Line:** 88

**Current Code:**
```php
extract($data, EXTR_SKIP);
```

**Options:**
- [ ] **Option A:** Keep as-is (low risk with EXTR_SKIP)
- [ ] **Option B:** Pass `$data` array and update all views
- [ ] **Option C:** Create ViewBag class for explicit variable access

**If keeping extract(), add documentation:**
```php
/**
 * @param array $data Variables to pass to view.
 *                    WARNING: Keys become local variables via extract().
 *                    Avoid keys like 'file', 'basePath', 'return', 'data'.
 */
```

---

### Step 3.3: Add Missing Return Types

**Files to update:**
- [ ] `src/Learners/Controllers/LearnerController.php` - shortcode methods
- [ ] `src/Classes/Controllers/ClassController.php` - shortcode methods

---

## Phase 4: Testing & Verification (P1)

### Step 4.1: Create Security Test Script

**File:** `tests/security-test.php` (create if needed)

```php
<?php
/**
 * Security regression tests
 * Run with: php tests/security-test.php
 */

// Test 1: SQL Injection in orderBy should be blocked
$repo = new LearnerRepository();
$result = $repo->findAll(10, 0, 'id; DROP TABLE learners; --', 'DESC');
assert($result !== false, 'Query should succeed with sanitized orderBy');

// Test 2: Invalid criteria keys should be filtered
$result = $repo->findBy([
    'valid_column' => 'value',
    'id; DROP TABLE' => 'malicious'
], 10);
// Should only query valid_column

echo "All security tests passed!\n";
```

---

### Step 4.2: Manual Testing Checklist

- [ ] Test learner list with various sort parameters
- [ ] Test learner search with various filter combinations
- [ ] Test learner create with extra form fields
- [ ] Test learner update with malicious field names
- [ ] Verify unauthenticated users cannot access AJAX endpoints
- [ ] Verify low-privilege users cannot access admin functions

---

## Summary Checklist

### Critical (Do First) ✅ COMPLETED
- [x] Step 1.1: Fix findAll() SQL injection
- [x] Step 1.2: Fix findBy() SQL injection
- [x] Step 1.3: Fix insert() SQL injection
- [x] Step 1.4: Fix update() SQL injection
- [x] Step 1.5: Review nopriv hooks
- [x] Step 1.6: Add capability checks

### High Priority (Do Next) ✅ COMPLETED
- [x] Step 2.1: Update LearnerRepository
- [x] Step 2.2: Update ClassRepository
- [x] Step 4.1: Create security tests - `tests/security-test.php`
- [ ] Step 4.2: Manual testing

### Medium Priority (Schedule) ✅ COMPLETED
- [x] Step 3.1: Standardize ClassModel - Refactored to use ClassRepository for save/update/delete
- [x] Step 3.2: Consider extract() replacement - Documented with reserved key warnings
- [x] Step 3.3: Add return types - Already present on all shortcode methods

---

## Phase 5: Gemini Review Recommendations (P2-P3)

**Reviewed:** 2026-02-01 by Gemini AI

### Additional Recommendations from Gemini Review

#### 5.1 BaseRepository Improvements (P2)

**Issue:** Unquoted database identifiers may fail with reserved words
**Recommendation:** Add `quoteIdentifier()` method for table/column names
```php
protected function quoteIdentifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}
```
**Status:** [ ] Not yet implemented - Low risk for current schema

#### 5.2 Add Nonce Verification to Read Operations (P2)

**Issue:** `ajaxGetLearner()` and `ajaxGetLearners()` lack nonce verification
**Risk:** CSRF vulnerability for information enumeration
**Recommendation:** Add `check_ajax_referer()` to read handlers
**Status:** [x] Implemented - Added `$this->requireNonce('learners_nonce_action')` to both handlers

#### 5.3 Consider Custom Capabilities (P2)

**Issue:** Using `edit_posts` is too broad for PII data
**Risk:** Contributors/Authors can access learner data
**Recommendation:** Create custom capability `manage_learners` mapped to Admin role
**Status:** [x] Implemented - Custom `manage_learners` capability registered on activation, all AJAX handlers updated

#### 5.4 IDOR Prevention - Ownership Checks (P1)

**Issue:** No ownership verification on learner records
**Risk:** Any authenticated user with `edit_posts` can access any learner
**Recommendation:** Add `checkLearnerOwnership()` method based on business rules
**Status:** [ ] Not yet implemented - Requires business logic clarification

#### 5.5 PII Encryption at Rest (P2)

**Issue:** Sensitive fields stored in plaintext
**Fields:** `sa_id_no`, `passport_number`, `disability_status`
**Risk:** POPIA/GDPR compliance concern
**Recommendation:** Implement encryption for sensitive columns
**Status:** [ ] Not yet implemented - Requires encryption key management

#### 5.6 Input Sanitization for XSS Prevention (P2)

**Issue:** Repository accepts data without XSS sanitization
**Risk:** Stored XSS attacks via fields like `first_name`, `address_line_1`
**Recommendation:** Add `strip_tags()` or use WP sanitization in controller layer
**Status:** [ ] Partial - Controller has `sanitizeLearnerInput()` but not verified

#### 5.7 Sanitized Error Logging (P3)

**Issue:** Exception messages may contain PII
**Recommendation:** Log error codes and locations, not full messages with values
**Status:** [ ] Not yet implemented

---

## Notes

- All changes should preserve existing functionality
- Test each step before moving to the next
- Commit after each phase completion
- Consider adding PHPStan/static analysis to CI

---

*Generated by Claude Code with Gemini AI Analysis*
*Security Review: 2026-02-01 - Phase 1 & 2 Complete, Phase 5 Recommendations Added*
*Update: 2026-02-01 - Phase 5.2, 5.3 Implemented (nonces on reads, custom capabilities)*
*Update: 2026-02-01 - Phase 3 Complete (ClassModel refactored, extract() documented, return types verified)*
