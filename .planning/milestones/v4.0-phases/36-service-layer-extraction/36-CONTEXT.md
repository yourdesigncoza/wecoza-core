# Phase 36 Context: Service Layer Extraction

**Source:** WordPress audit (2026-02-16-phase3-medium-priority-design.md)
**Audit phases 1 & 2:** Completed. This is Phase 3 (medium priority technical debt).

## Decisions (LOCKED)

### Service Layer Pattern
- Controllers become thin: validate input -> call service -> return response
- Each controller method < 100 lines
- Business logic extracted to dedicated service classes
- Pattern: try/catch in controller, exceptions from service

### Scope per Service
- **LearnerService**: Progression logic, validation orchestration, learner CRUD operations
- **AgentService**: Agent creation workflow, working areas coordination (expand existing WorkingAreasService usage)
- **ClientService**: Client location linking, address normalization

### Backward Compatibility (CRITICAL)
- All existing AJAX endpoints must return identical responses
- No breaking changes to existing functionality
- Dual-write approach for any data migration

### Execution Strategy
- Team-based execution with specialized reviewers:
  1. **WordPress Best Practices reviewer** — verifies all code follows WP/PHP 8 OOP best practices
  2. **Regression/Functionality tester** — verifies nothing currently working is broken
- All 3 service extractions can run in parallel (Wave 1)

## Claude's Discretion

- Internal service method signatures and parameter naming
- Whether to create separate exception classes or use standard PHP exceptions
- How to organize service method grouping (by CRUD vs by feature)
- Whether ProgressionService stays separate or merges into LearnerService

## Deferred Ideas

- Address storage migration (Task 6 from audit) — separate phase (Phase 38)
- Model architecture unification — separate phase (Phase 37)
- Repository pattern enforcement — separate phase (Phase 39)
- Return type hints — separate phase (Phase 40)
- Constants extraction — separate phase (Phase 40)
- Test suite creation — out of scope for v4.0

## Audit Reference Patterns

### Before (Business logic in controller):
```php
public function ajaxUpdateLearner(): void
{
    // 50 lines of business logic
    $data = $this->sanitizeInput();
    $learner = $this->repository->find($id);
    if ($learner->hasActiveLP()) { /* business rule check */ }
    $this->repository->update($learner);
}
```

### After (Thin controller, fat service):
```php
public function ajaxUpdateLearner(): void
{
    $data = $this->input('learner_data', 'array');
    try {
        $result = $this->learnerService->updateLearner($id, $data);
        $this->sendSuccess($result);
    } catch (Exception $e) {
        $this->sendError($e->getMessage());
    }
}
```
