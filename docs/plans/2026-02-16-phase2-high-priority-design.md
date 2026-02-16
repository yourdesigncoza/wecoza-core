# Phase 2: High Priority Fixes Implementation Design

**Date**: 2026-02-16
**Scope**: High Priority Code Quality & Maintainability Issues
**Environment**: Staging
**Strategy**: Parallel Implementation with Standards Review

---

## Scope Overview

**Remaining High Priority Items** (from code review section 7):
- Item 7: Consolidate validation logic - remove duplicates from controllers
- Item 10: Add strict type declarations to all files (40+ missing)
- Item 11: Standardize error handling - define consistent error contract

**Additional Code Quality Items** (from section 4):
- Validation logic triplication (Agents module)
- Array mapping duplication (5 instances)
- Inconsistent error handling (3 different patterns)

---

## Task Breakdown

### Category 1: DRY Violations (3 tasks)

**Task 1: Consolidate Agents validation logic**
- Files: AgentModel.php, AgentsController.php, AgentRepository.php
- Move all validation to AgentModel::validate()
- Remove duplicate validation from Controller and Repository
- Controller calls model->validate(), Repository just saves

**Task 2: Extract array mapping helper**
- File: LearnerAjaxHandlers.php (5 duplications at lines ~222-246)
- Create `transform_to_dropdown_format()` helper function
- Replace 5 array_map instances with helper
- Add to core/Helpers/functions.php

**Task 3: Audit for other DRY violations**
- Search for similar duplication patterns across all modules
- Document findings for potential cleanup

### Category 2: Type Safety (2 tasks)

**Task 4: Add strict type declarations - Controllers**
- Add `declare(strict_types=1);` to all Controller files
- Verify all method parameters have type hints
- Verify all return types declared
- ~15 controller files

**Task 5: Add strict type declarations - Models & Repositories**
- Add `declare(strict_types=1);` to all Model and Repository files
- Add missing parameter/return type hints
- ~25 files

**Task 6: Add strict type declarations - Services & Helpers**
- Add `declare(strict_types=1);` to remaining files
- Complete type coverage across codebase
- ~10-15 files

### Category 3: Error Handling Standardization (4 tasks)

**Task 7: Define error handling contract**
- Document standard error patterns:
  - Exceptions: For exceptional/unexpected errors
  - Structured arrays: For expected validation/business failures
  - WP_Error: For WordPress-integrated errors
- Create coding standard document

**Task 8: Refactor Services to use consistent error handling**
- ProgressionService: Keep exceptions for business logic violations
- AISummaryService: Standardize to structured arrays
- NotificationEmailer: Standardize to structured arrays
- ~5-8 service files

**Task 9: Refactor Controllers error responses**
- Ensure controllers catch exceptions and return proper JSON
- Standardize sendError() usage
- Consistent error response format

**Task 10: Update error handling documentation**
- Add PHPDoc blocks explaining error contracts
- Document what exceptions each method can throw
- Update README with error handling patterns

### Category 4: Verification (2 tasks)

**Task 11: Type safety verification**
- Run static analysis (if available)
- Verify no type errors introduced
- Check all strict_types files parse correctly

**Task 12: Error handling verification**
- Test error scenarios for each module
- Verify consistent error responses
- Confirm no regressions

---

## Team Structure

### Implementation Squad (Parallel Work)

**1. Code Quality Specialist**
- Tasks 1-3: DRY violations
- Extract helpers, consolidate validation
- ~3 tasks

**2. Type Safety Specialist**
- Tasks 4-6: Strict types + type hints
- Add declarations to 40+ files
- ~3 tasks

**3. Error Handling Specialist**
- Tasks 7-10: Standardize error patterns
- Define contract, refactor services/controllers
- ~4 tasks

### QA Squad

**4. Standards Reviewer**
- Cross-reference against `/wordpress-best-practices`
- Verify PHP 8.0+ type safety practices
- Ensure DRY principle applied

**5. Safety Verifier**
- Final integration testing
- Verify no breaking changes
- Test error scenarios

---

## Implementation Patterns

### DRY - Validation Consolidation

**Before** (Agents module):
```php
// AgentModel.php - validation
// AgentsController.php - same validation duplicated
// AgentRepository.php - same sanitization duplicated
```

**After**:
```php
// AgentModel.php
public function validate(): array
{
    $errors = [];
    if (empty($this->name)) {
        $errors['name'] = 'Name is required';
    }
    return $errors;
}

// AgentsController.php
$agent = new AgentModel($formData);
$errors = $agent->validate();
if (!empty($errors)) {
    return $this->sendError($errors);
}
$this->repository->save($agent);
```

### DRY - Array Mapping Helper

**Before**:
```php
// Repeated 5 times
array_map(function($item) {
    return ['id' => $item['id_field'], 'name' => $item['name_field']];
}, $data);
```

**After**:
```php
// core/Helpers/functions.php
function wecoza_transform_dropdown(array $data, string $idField, string $nameField): array
{
    return array_map(fn($item) => [
        'id' => $item[$idField],
        'name' => $item[$nameField],
    ], $data);
}

// Usage
wecoza_transform_dropdown($data, 'id_field', 'name_field');
```

### Type Safety - Strict Types

**Add to all files**:
```php
<?php
declare(strict_types=1);

namespace WeCoza\Module;
```

**Ensure all methods have types**:
```php
public function save(AgentModel $model): bool
{
    // ...
}

private function validateEmail(string $email): bool
{
    return is_email($email);
}
```

### Error Handling - Consistent Patterns

**Pattern 1: Exceptions for Exceptional Cases**
```php
// Business logic violations, unexpected errors
if ($learner->hasActiveLearningPath()) {
    throw new RuntimeException('Learner already has an active LP');
}
```

**Pattern 2: Structured Arrays for Expected Failures**
```php
// Validation failures, API errors, expected failures
return [
    'success' => false,
    'error_code' => 'validation_failed',
    'error_message' => 'Email is required',
    'errors' => ['email' => 'This field is required'],
];
```

**Pattern 3: WP_Error for WordPress Integration**
```php
// When integrating with WP core functions
if (!$user_id) {
    return new WP_Error('user_not_found', 'User does not exist');
}
```

---

## Success Criteria

### DRY Violations
- ✅ Zero duplicated validation logic in Agents module
- ✅ Array mapping helper created and used
- ✅ No other major DRY violations found

### Type Safety
- ✅ All 40+ files have `declare(strict_types=1)`
- ✅ All public methods have parameter types
- ✅ All public methods have return types
- ✅ No type errors when running

### Error Handling
- ✅ Error handling contract documented
- ✅ All services use consistent patterns
- ✅ All controllers handle errors consistently
- ✅ PHPDoc blocks document exceptions

### Verification
- ✅ All files parse without errors
- ✅ No breaking changes introduced
- ✅ Error scenarios tested
- ✅ WordPress standards compliance

---

## Deliverables

1. **Code changes**: 40+ files with type declarations, consolidated validation, standardized errors
2. **Helper functions**: Dropdown transformation helper
3. **Documentation**: Error handling contract document
4. **Verification report**: Type safety and error handling verification

---

**End of Design**
