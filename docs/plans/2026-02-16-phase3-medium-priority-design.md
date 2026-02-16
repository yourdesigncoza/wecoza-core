# Phase 3: Medium Priority (Technical Debt) Implementation Design

**Date**: 2026-02-16
**Scope**: Architectural improvements and code quality enhancements
**Environment**: Staging
**Strategy**: Incremental refactoring with backward compatibility

---

## Scope Overview

**Medium Priority Items** (from code review section 7):
13. Extract business logic from controllers to service classes
14. Unify model architecture - make modules extend BaseModel
15. Normalize address storage - migrate Agents to use locations table
16. Use BaseRepository methods - stop bypassing parent class
17. Add return type hints to all methods
18. Extract magic numbers to constants

**Estimated Impact**: ~30-40 files modified, significant architectural improvements

---

## Task Breakdown

### Category 1: Service Layer Extraction (Tasks 1-3)

**Task 1: Extract Learner business logic to services**
- Move progression logic from LearnerController to ProgressionService
- Move validation orchestration to LearnerService
- Controllers should only: validate input, call services, return responses
- Files: LearnerController.php, create LearnerService.php

**Task 2: Extract Agent business logic to services**
- Move agent creation workflow to AgentService
- Move working areas logic to WorkingAreasService (already exists, expand usage)
- Files: AgentsController.php, AgentService.php (create)

**Task 3: Extract Client business logic to services**
- Move client location linking to ClientService
- Move address normalization to AddressService
- Files: ClientsController.php, ClientService.php (create)

### Category 2: Model Architecture Unification (Tasks 4-5)

**Task 4: Migrate Clients to extend BaseModel**
- ClientsModel should extend BaseModel
- Remove duplicate methods (get, set, toArray)
- Use BaseModel's validation framework
- Files: ClientsModel.php

**Task 5: Migrate Agents to extend BaseModel**
- AgentModel should extend BaseModel
- Remove duplicate methods
- Preserve existing validation logic (already consolidated)
- Files: AgentModel.php

### Category 3: Address Storage Normalization (Task 6)

**Task 6: Migrate Agents addresses to locations table**
- Create migration script to move agent addresses to shared locations table
- Update AgentRepository to use LocationsModel for addresses
- Remove address columns from agents table (create migration)
- Update AgentsController to use location linking
- Files: AgentRepository.php, migration script, database schema

**Note**: This is a database schema change - requires careful migration planning

### Category 4: Repository Method Usage (Tasks 7-8)

**Task 7: Audit BaseRepository method usage**
- Find all direct SQL queries in repositories
- Identify where findBy(), updateBy(), deleteBy() should be used
- Document bypasses that are justified (complex queries)
- Files: All *Repository.php files

**Task 8: Refactor repositories to use BaseRepository methods**
- Replace manual queries with parent methods where appropriate
- Use quoteIdentifier() for all column names
- Preserve complex queries that need custom SQL
- Files: LearnerRepository.php, AgentRepository.php, ClientRepository.php

### Category 5: Return Type Hints (Tasks 9-10)

**Task 9: Add return types to Controllers**
- Add return type hints to all controller methods
- Use union types where needed (string|void for render methods)
- Ensure consistency across all modules
- Files: All *Controller.php files

**Task 10: Add return types to Models and Services**
- Add return type hints to all model methods
- Add return type hints to all service methods
- Cover getters, setters, business logic methods
- Files: All *Model.php and *Service.php files

### Category 6: Constants Extraction (Task 11)

**Task 11: Extract magic numbers to constants**
- Find all magic numbers (pagination limits, timeouts, status codes)
- Extract to class constants or config file
- Common candidates:
  - Pagination: 10, 20, 50
  - Timeouts: 30, 60, 120
  - HTTP status codes: 200, 403, 404, 500
  - Quantum scores: 100, 120 (max scores)
- Files: Various controllers and services

### Category 7: Verification (Task 12)

**Task 12: Architectural verification**
- Verify services properly handle business logic
- Verify models extend BaseModel correctly
- Verify repositories use parent methods
- Verify return types complete
- Verify constants used consistently

---

## Implementation Patterns

### Service Layer Pattern

**Before** (Business logic in controller):
```php
// LearnerController
public function ajaxUpdateLearner(): void
{
    // 50 lines of business logic
    $data = $this->sanitizeInput();
    $learner = $this->repository->find($id);
    if ($learner->hasActiveLP()) {
        // business rule check
    }
    // more business logic
    $this->repository->update($learner);
}
```

**After** (Thin controller, fat service):
```php
// LearnerController
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

// LearnerService
public function updateLearner(int $id, array $data): array
{
    // All business logic here
    $learner = $this->repository->find($id);
    $this->validateBusinessRules($learner, $data);
    $updated = $this->repository->update($learner);
    return $updated->toArray();
}
```

### BaseModel Extension Pattern

**Before** (Clients/Agents models):
```php
class ClientsModel
{
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    // Duplicate methods from BaseModel
}
```

**After**:
```php
class ClientsModel extends BaseModel
{
    protected string $table = 'wecoza_clients';
    protected string $primaryKey = 'id';

    // Inherits get(), set(), toArray(), validate()
    // Only override where needed
}
```

### BaseRepository Method Usage

**Before** (Manual SQL):
```php
public function findActive(): array
{
    $sql = "SELECT * FROM {$this->table} WHERE status = :status";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['status' => 'active']);
    return $stmt->fetchAll();
}
```

**After** (Use parent methods):
```php
public function findActive(): array
{
    return $this->findBy(['status' => 'active']);
}
```

### Return Type Hints

**Add to all methods**:
```php
public function getById(int $id): ?array
{
    return $this->repository->find($id)?->toArray();
}

public function create(array $data): array
{
    $model = new LearnerModel($data);
    return $this->repository->save($model)->toArray();
}

public function delete(int $id): bool
{
    return $this->repository->delete($id);
}
```

### Constants Extraction

**Before**:
```php
$limit = 20; // Pagination limit
$timeout = 30; // API timeout in seconds
$maxScore = 120; // Maximum quantum score
```

**After**:
```php
class LearnerConstants
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const API_TIMEOUT_SECONDS = 30;
    public const MAX_QUANTUM_SCORE = 120;
}

// Usage
$limit = LearnerConstants::DEFAULT_PAGE_SIZE;
```

---

## Migration Considerations

### Address Storage Migration (Task 6)

**This is a database schema change requiring:**

1. **Backward compatibility period**:
   - Dual-write: Write to both old columns AND locations table
   - Dual-read: Read from new table, fallback to old columns
   - Migration script to copy existing data

2. **Migration script** (run once):
```php
// Copy agent addresses to locations table
foreach ($agents as $agent) {
    if (!empty($agent['address'])) {
        $location = new LocationsModel([
            'entity_type' => 'agent',
            'entity_id' => $agent['id'],
            'address' => $agent['address'],
            'city' => $agent['city'],
            'province' => $agent['province'],
            'postal_code' => $agent['postal_code'],
        ]);
        $locationRepo->save($location);
    }
}
```

3. **Deprecation period** (1-2 releases):
   - Mark old columns as deprecated
   - Log usage of old columns
   - Update all UI to use new locations

4. **Final cleanup**:
   - Remove old address columns from agents table
   - Remove dual-read code

**Recommendation**: Defer Task 6 to a separate migration phase if timeline is tight.

---

## Success Criteria

### Service Layer
- ✅ Controllers <100 lines per method
- ✅ Business logic in service classes
- ✅ Controllers only handle: input, service call, response

### Model Architecture
- ✅ Clients and Agents extend BaseModel
- ✅ No duplicate get/set/toArray methods
- ✅ Validation framework consistent

### Repository Usage
- ✅ 80% of queries use BaseRepository methods
- ✅ Manual SQL only for complex joins/aggregations
- ✅ All column names use quoteIdentifier()

### Return Types
- ✅ All public methods have return type hints
- ✅ Union types used appropriately (string|void, array|null)
- ✅ No mixed return types without documentation

### Constants
- ✅ No magic numbers in business logic
- ✅ All constants in class constants or config
- ✅ Consistent naming (SCREAMING_SNAKE_CASE)

### Verification
- ✅ All tests pass
- ✅ No breaking changes
- ✅ Architecture improved without functionality changes

---

## Deliverables

1. **Service classes**: LearnerService, AgentService, ClientService
2. **Refactored models**: ClientsModel, AgentModel extending BaseModel
3. **Repository improvements**: Use BaseRepository methods, quoteIdentifier()
4. **Return types**: Complete coverage on all public methods
5. **Constants**: Extracted to named constants
6. **Migration script** (optional, Task 6): Address storage migration
7. **Verification report**: Architectural improvements verified

---

**Estimated Timeline**: 2-3 days (excluding address migration)

**Recommended Approach**:
- Start with Tasks 1-5, 7-11 (non-breaking refactors)
- Defer Task 6 (address migration) if needed
- Can be done incrementally, module by module

---

**End of Design**
