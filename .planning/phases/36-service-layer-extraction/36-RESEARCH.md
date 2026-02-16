# Phase 36: Service Layer Extraction - Research

**Researched:** 2026-02-16
**Domain:** Service layer pattern in WordPress/PHP OOP architecture
**Confidence:** HIGH

## Summary

Phase 36 extracts business logic from three controllers (Learners, Agents, Clients) into dedicated service classes, implementing the thin controller/fat service pattern. Research validates the existing PLAN files against current codebase state and identifies critical validation requirements.

**Key finding:** All three existing plan files are accurate and executable. The codebase is ready for service extraction with minimal pre-work. The primary risk is maintaining AJAX response format compatibility during refactoring.

**Primary recommendation:** Execute all three plans in parallel (Wave 1) as designed. Add explicit response format verification to each task's verification criteria.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Service Layer Pattern:**
- Controllers become thin: validate input -> call service -> return response
- Each controller method < 100 lines
- Business logic extracted to dedicated service classes
- Pattern: try/catch in controller, exceptions from service

**Scope per Service:**
- **LearnerService:** Progression logic, validation orchestration, learner CRUD operations
- **AgentService:** Agent creation workflow, working areas coordination (expand existing WorkingAreasService usage)
- **ClientService:** Client location linking, address normalization

**Backward Compatibility (CRITICAL):**
- All existing AJAX endpoints must return identical responses
- No breaking changes to existing functionality
- Dual-write approach for any data migration

**Execution Strategy:**
- Team-based execution with specialized reviewers:
  1. WordPress Best Practices reviewer — verifies all code follows WP/PHP 8 OOP best practices
  2. Regression/Functionality tester — verifies nothing currently working is broken
- All 3 service extractions can run in parallel (Wave 1)

### Claude's Discretion

- Internal service method signatures and parameter naming
- Whether to create separate exception classes or use standard PHP exceptions
- How to organize service method grouping (by CRUD vs by feature)
- Whether ProgressionService stays separate or merges into LearnerService

### Deferred Ideas (OUT OF SCOPE)

- Address storage migration (Task 6 from audit) — separate phase (Phase 38)
- Model architecture unification — separate phase (Phase 37)
- Repository pattern enforcement — separate phase (Phase 39)
- Return type hints — separate phase (Phase 40)
- Constants extraction — separate phase (Phase 40)
- Test suite creation — out of scope for v4.0
</user_constraints>

## Standard Stack

### Core Dependencies
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.0+ | Language runtime | Required for typed properties, match expressions |
| WordPress | 6.0+ | Application framework | Plugin environment |
| PostgreSQL | 13+ | Database | Existing infrastructure via PDO |

### Supporting Patterns
| Pattern | Purpose | When to Use |
|---------|---------|-------------|
| Service Layer | Business logic encapsulation | All controllers extracting logic |
| Repository Pattern | Data access abstraction | Already used (LearnerRepository, AgentRepository, ClientRepository) |
| Lazy Loading | Resource initialization | Service instantiation in controllers |
| Constructor Injection | Dependency management | Service -> Repository relationships |

**Installation:**
No new dependencies required. All patterns use existing PHP 8.0+ features.

## Architecture Patterns

### Recommended Service Structure
```
src/
├── Learners/
│   ├── Controllers/         # Thin controllers (validate, delegate, respond)
│   ├── Services/            # Business logic
│   │   ├── LearnerService.php      # NEW - CRUD, dropdown data, portfolio/sponsor
│   │   └── ProgressionService.php  # EXISTS - keep separate (complex domain)
│   ├── Repositories/        # Data access
│   ├── Models/              # Data structures
│   └── Ajax/                # AJAX handlers (also delegate to services)
├── Agents/
│   ├── Controllers/
│   ├── Services/
│   │   ├── AgentService.php         # NEW - CRUD, form workflow, file uploads
│   │   ├── AgentDisplayService.php  # EXISTS - keep (presentation helper)
│   │   └── WorkingAreasService.php  # EXISTS - keep (separate domain)
│   ├── Repositories/
│   ├── Models/
│   └── Ajax/
├── Clients/
│   ├── Controllers/
│   ├── Services/
│   │   └── ClientService.php        # NEW - CRUD, site management, unified form submission
│   ├── Models/
│   │   ├── ClientsModel.php
│   │   └── SitesModel.php          # EXISTS - accessed via ClientService
│   └── Ajax/
```

### Pattern 1: Lazy Service Instantiation in Controllers
**What:** Controllers instantiate services on-demand via lazy getter pattern
**When to use:** All controller -> service relationships
**Example:**
```php
// Source: ProgressionService.php (existing verified pattern)
class LearnerController extends BaseController
{
    private ?LearnerService $learnerService = null;

    private function getLearnerService(): LearnerService
    {
        if ($this->learnerService === null) {
            $this->learnerService = new LearnerService();
        }
        return $this->learnerService;
    }

    public function ajaxGetLearner(): void
    {
        // Validate input
        $this->requireNonce('learners_nonce');
        $id = $this->input('id', 'int');

        // Delegate to service
        $learner = $this->getLearnerService()->getLearner($id);

        // Format response
        if ($learner) {
            $this->sendSuccess($learner->toArray());
        } else {
            $this->sendError('Learner not found');
        }
    }
}
```

### Pattern 2: Service Constructor Pattern
**What:** Services instantiate their own repositories internally
**When to use:** All service classes
**Example:**
```php
// Source: ProgressionService.php (lines 24-31, verified)
class LearnerService
{
    private LearnerRepository $repository;

    public function __construct()
    {
        $this->repository = new LearnerRepository();
    }

    public function getLearner(int $id): ?LearnerModel
    {
        return LearnerModel::getById($id);
    }
}
```

### Pattern 3: AJAX Handler Thin Pattern
**What:** AJAX handlers validate security, extract params, delegate to service, format response
**When to use:** All AJAX endpoints
**Example:**
```php
// Source: Audit document pattern (lines 142-153)
public function ajaxUpdateLearner(): void
{
    // Security validation
    if (!current_user_can('manage_learners')) {
        $this->sendError('Insufficient permissions.', 403);
        return;
    }
    $this->requireNonce('learners_nonce');

    // Extract parameters
    $id = $this->input('id', 'int');
    $data = $this->input('learner_data', 'array');

    // Delegate to service
    try {
        $result = $this->getLearnerService()->updateLearner($id, $data);
        $this->sendSuccess($result);
    } catch (Exception $e) {
        $this->sendError($e->getMessage());
    }
}
```

### Pattern 4: Unified Form Submission (DRY)
**What:** Single service method handles both controller and AJAX form submissions
**When to use:** When controller shortcode and AJAX handler duplicate logic
**Example:**
```php
// Source: 36-03-PLAN.md Task 1 (ClientService pattern)
// Single method replaces:
// - ClientsController::handleFormSubmission() (~160 lines)
// - ClientAjaxHandlers::saveClient() (~120 lines)

class ClientService
{
    public function handleClientSubmission(array $rawData, int $clientId = 0): array
    {
        // Sanitize
        $payload = $this->sanitizeFormData($rawData);
        $clientData = $payload['client'];
        $siteData = $payload['site'];

        // Validate
        $errors = $this->model->validate($clientData, $clientId);
        $siteErrors = $this->validateSite($siteData);
        if (!empty($errors) || !empty($siteErrors)) {
            return ['success' => false, 'errors' => array_merge($errors, $siteErrors), 'data' => $payload];
        }

        // Save
        $clientId = $clientId > 0 ? $this->model->update($clientId, $clientData) : $this->model->create($clientData);
        $siteId = $this->saveSite($clientId, $siteData);

        // Return
        return ['success' => true, 'client' => $this->model->getById($clientId), 'message' => 'Saved successfully'];
    }
}
```

### Anti-Patterns to Avoid

- **Direct repository access in controllers:** Controllers should NEVER instantiate repositories. Use services.
- **Business logic in AJAX handlers:** Handlers are security + delegation wrappers. No business rules.
- **Duplicate sanitization:** ONE sanitizeFormData() in service, not separate versions in controller and handler.
- **Fat controllers:** No controller method should exceed 100 lines. If it does, logic belongs in service.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Service instantiation | Singleton pattern, DI container | Lazy getter pattern | Existing WeCoza pattern (ProgressionService), simple and effective |
| Data access | Raw SQL in services | Repository pattern (existing) | Already implemented, column whitelisting built-in |
| Input sanitization | Custom filters | BaseController::input() | Existing method with type coercion |
| AJAX security | Manual nonce checks | BaseController::requireNonce() | Existing helper, standardized error handling |
| View rendering | Manual ob_start/include | BaseController::render() | Existing helper with data isolation |

**Key insight:** WeCoza Core already has service layer examples (ProgressionService, WorkingAreasService, AgentDisplayService). Don't invent new patterns - follow existing proven implementations.

## Common Pitfalls

### Pitfall 1: Breaking AJAX Response Format
**What goes wrong:** Refactoring changes response structure, breaking JavaScript expectations
**Why it happens:** Plans focus on business logic extraction, not response format verification
**How to avoid:** Add explicit response format tests to verification criteria
**Warning signs:**
- AJAX endpoints return different JSON structure after refactoring
- Frontend JavaScript errors about missing properties
- Pagination breaks after service extraction

**Prevention checklist:**
- [ ] Document current AJAX response format BEFORE extraction
- [ ] Compare response formats AFTER extraction (use curl or browser network tab)
- [ ] Test all AJAX-driven UI flows (pagination, search, delete, save)

### Pitfall 2: Over-Extraction (Services Too Fine-Grained)
**What goes wrong:** Create too many single-method services instead of cohesive service classes
**Why it happens:** Misunderstanding "single responsibility" as "single method"
**How to avoid:** Group related business operations in one service (e.g., LearnerService handles CRUD, dropdowns, sponsors, portfolios)
**Warning signs:**
- Service class with only 1-2 methods
- Controllers coordinate between 5+ services for one operation
- Service naming like "LearnerCreationService", "LearnerDeletionService"

**Correct scope (from plans):**
- LearnerService: CRUD + dropdowns + sponsors + portfolios + table HTML generation
- AgentService: CRUD + form workflow + file uploads + pagination
- ClientService: CRUD + site management + form submission + export

### Pitfall 3: Forgetting AJAX Handler Refactoring
**What goes wrong:** Controller refactored to use service, but AJAX handler still duplicates logic
**Why it happens:** Plans focus on controllers, handlers treated as afterthought
**How to avoid:** Verify BOTH controller AND handler delegate to service
**Warning signs:**
- LearnerAjaxHandlers still calls LearnerController methods instead of LearnerService
- Duplicate business logic remains in handler functions
- AJAX handler has 50+ line methods after refactoring

**Verification:** Grep for old controller method calls in AJAX handlers:
```bash
# Should NOT find controller method calls in handlers
grep -r "getLearnerController()" src/Learners/Ajax/
grep -r "getRepository()" src/Agents/Ajax/
grep -r "getModel()" src/Clients/Ajax/
```

### Pitfall 4: Model vs Service Confusion
**What goes wrong:** Putting business logic in models instead of services
**Why it happens:** Active Record pattern makes models attractive for business logic
**How to avoid:** Models are data structures + persistence. Services orchestrate models + business rules.
**Warning signs:**
- Models have methods like `createWithValidation()`, `updateWithNotification()`
- Services become thin proxies to model methods
- Controllers bypass service and call model directly

**Correct division:**
- **Model:** `save()`, `update()`, `delete()`, `validate()`, `toArray()`, `hydrate()`
- **Service:** `createLearner()` (calls model + repository + business rules), `updateLearner()`, `handleFormSubmission()`

### Pitfall 5: Ignoring Existing Services
**What goes wrong:** Create new service that overlaps with existing one
**Why it happens:** Not auditing existing Services/ directories before extraction
**How to avoid:** Check what service classes already exist, expand them instead of duplicating

**Current state (verified via file glob):**
- **Learners:** ProgressionService (keep), PortfolioUploadService (keep)
- **Agents:** WorkingAreasService (keep), AgentDisplayService (keep)
- **Clients:** None (create ClientService)

**Decision:** ProgressionService stays separate (complex LP tracking domain). New LearnerService handles CRUD/forms.

## Code Examples

Verified patterns from existing codebase:

### Service Constructor (Existing Pattern)
```php
// Source: src/Learners/Services/ProgressionService.php (lines 24-31)
namespace WeCoza\Learners\Services;

use WeCoza\Learners\Repositories\LearnerProgressionRepository;

class ProgressionService
{
    private LearnerProgressionRepository $repository;

    public function __construct()
    {
        $this->repository = new LearnerProgressionRepository();
    }
}
```

### Controller -> Service Delegation (Expected Pattern)
```php
// Source: 36-01-PLAN.md Task 2, based on existing BaseController helpers
namespace WeCoza\Learners\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Learners\Services\LearnerService;

class LearnerController extends BaseController
{
    private ?LearnerService $learnerService = null;

    private function getLearnerService(): LearnerService
    {
        if ($this->learnerService === null) {
            $this->learnerService = new LearnerService();
        }
        return $this->learnerService;
    }

    public function ajaxGetLearners(): void
    {
        // Security
        if (!current_user_can('manage_learners')) {
            $this->sendError('Insufficient permissions.', 403);
            return;
        }
        $this->requireNonce('learners_nonce');

        // Extract params
        $limit = $this->query('limit', 'int') ?? 50;
        $offset = $this->query('offset', 'int') ?? 0;
        $withMappings = $this->query('mappings', 'bool') ?? false;

        // Delegate to service
        if ($withMappings) {
            $learners = $this->getLearnerService()->getLearnersWithMappings();
        } else {
            $learners = $this->getLearnerService()->getLearners($limit, $offset);
        }

        // Format response
        $data = array_map(fn($l) => $l->toArray(), $learners);
        $this->sendSuccess([
            'learners' => $data,
            'total' => $this->getLearnerService()->getLearnerCount(),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
```

### BaseController AJAX Helpers (Current Implementation)
```php
// Source: core/Abstract/BaseController.php (lines 142-150+)
abstract class BaseController
{
    protected function requireNonce(string $action, string $field = 'nonce'): void
    {
        if (!check_ajax_referer($action, $field, false)) {
            $this->sendError('Security check failed', 403);
            exit;
        }
    }

    protected function sendSuccess(array $data, string $message = '', int $code = 200): void
    {
        wp_send_json_success(['data' => $data, 'message' => $message], $code);
    }

    protected function sendError(string $message, int $code = 400, array $data = []): void
    {
        wp_send_json_error(['message' => $message, 'data' => $data], $code);
    }

    protected function input(string $key, string $type = 'string', $default = null)
    {
        return wecoza_sanitize_value($_POST[$key] ?? $default, $type);
    }

    protected function query(string $key, string $type = 'string', $default = null)
    {
        return wecoza_sanitize_value($_GET[$key] ?? $default, $type);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Business logic in controllers | Service layer pattern | WP ecosystem 2020+ | Controllers become thin, testable services |
| Fat models (Active Record) | Anemic models + rich services | PHP 8.0+ typed era | Better separation, easier testing |
| Manual repository instantiation | Lazy loading in services | Modern PHP | Deferred DB connection, performance |
| Duplicate AJAX/controller logic | Unified service methods | DRY principle | Single source of truth for business rules |

**Current WeCoza state:** Partial service adoption
- **Has:** ProgressionService, WorkingAreasService, AgentDisplayService (8 services in Events module)
- **Missing:** LearnerService, AgentService, ClientService for CRUD/form workflows
- **Pattern maturity:** Existing services follow correct pattern (constructor injection, typed properties)

**Deprecated/outdated:**
- Direct model method calls from controllers (bypass services) - should be eliminated
- Duplicate form submission logic in controller + AJAX handler - consolidate to service

## Validation Against Existing Plans

### Plan 36-01 (LearnerService) - Status: ACCURATE
**Files referenced:** All exist and match expected state
- LearnerController.php: Contains CRUD methods (lines 67-139), dropdown data (lines 150-180), AJAX handlers (lines 223-367)
- LearnerAjaxHandlers.php: Contains `generate_learner_table_rows()` function (lines 56-98), delegates to controller
- ProgressionService.php: Exists, follows correct constructor pattern

**Gaps identified:** None. Plan accurately describes current state and extraction scope.

**Method line counts (current state):**
- `ajaxGetLearner()`: 26 lines (meets <100 target)
- `ajaxGetLearners()`: 28 lines (meets target)
- `ajaxUpdateLearner()`: 54 lines (meets target)
- `getDropdownData()`: 31 lines (simple logic, good candidate for extraction)

**Recommendation:** Execute as written. No plan modifications needed.

### Plan 36-02 (AgentService) - Status: ACCURATE
**Files referenced:** All exist and match expected state
- AgentsController.php: Contains `renderCaptureForm()` (lines 82-175, 94 lines), `collectFormData()` (lines 430-504, 75 lines), file upload methods (lines 579-641)
- AgentsAjaxHandlers.php: Contains `handlePagination()` (lines 65-150+), `handleDelete()` method
- WorkingAreasService.php: Exists (verified in glob)
- AgentDisplayService.php: Exists (verified in glob)

**Gaps identified:** None. Plan accurately describes duplicate pagination logic and form submission complexity.

**Validation findings:**
- `renderCaptureForm()` is 94 lines (plan says ~95) - ACCURATE
- `collectFormData()` is 75 lines (plan says ~70) - ACCURATE
- File upload handling spans 2 methods (~60 lines total)

**Recommendation:** Execute as written. Form submission extraction will significantly simplify controller.

### Plan 36-03 (ClientService) - Status: ACCURATE - HIGH VALUE
**Files referenced:** All exist and match expected state
- ClientsController.php: Contains `handleFormSubmission()` (lines 503-665, 163 lines), `sanitizeFormData()` (lines 673-735, 63 lines), `filterClientDataForForm()` (lines 743-809, 67 lines)
- ClientAjaxHandlers.php: Exists (verified in glob)
- ClientsModel.php: Exists, provides access to SitesModel (line 55)
- SitesModel.php: Exists (referenced in ClientsController imports)

**Gaps identified:** None. Plan accurately identifies massive code duplication opportunity.

**Validation findings:**
- `handleFormSubmission()`: 163 lines (plan says ~160) - ACCURATE
- `captureClientShortcode()`: ~110 lines (complex dropdown/location assembly)
- `updateClientShortcode()`: ~120 lines (similar complexity)
- Duplicate sanitization methods in controller and handler

**Duplication impact:** ~280 lines of duplicate code across controller and AJAX handler (validated)

**Recommendation:** Execute as written. This is the highest-value extraction - eliminates significant duplication.

### Cross-Plan Dependencies
**Verified:** All three plans are marked `depends_on: []` and `wave: 1` - correct for parallel execution.

**No conflicts found:** Each plan targets a different module with no shared files.

## Open Questions

1. **ProgressionService integration with LearnerService**
   - What we know: ProgressionService handles LP tracking (complex domain), LearnerService will handle CRUD/forms
   - What's unclear: Should LearnerService have convenience methods that delegate to ProgressionService, or should controllers use both services directly?
   - Recommendation: Keep separate. Controllers can use both services when needed (e.g., learner detail page loads learner via LearnerService, current LP via ProgressionService).

2. **Exception handling strategy**
   - What we know: Plans show try/catch in controllers, services throw exceptions
   - What's unclear: Standard PHP exceptions or create custom WeCoza exceptions (e.g., `ValidationException`, `NotFoundException`)?
   - Recommendation: Start with standard exceptions (`Exception`, `InvalidArgumentException`). Add custom exceptions later if error handling becomes complex.

3. **AJAX response format verification**
   - What we know: Plans require backward compatibility
   - What's unclear: No explicit verification steps for comparing before/after response formats
   - Recommendation: Add verification step to each plan: "Curl AJAX endpoint before and after, compare JSON structure."

## Sources

### Primary (HIGH confidence)
- **Codebase files** (read directly):
  - `.planning/phases/36-service-layer-extraction/36-CONTEXT.md`
  - `.planning/phases/36-service-layer-extraction/36-01-PLAN.md` (LearnerService)
  - `.planning/phases/36-service-layer-extraction/36-02-PLAN.md` (AgentService)
  - `.planning/phases/36-service-layer-extraction/36-03-PLAN.md` (ClientService)
  - `src/Learners/Controllers/LearnerController.php` (514 lines)
  - `src/Agents/Controllers/AgentsController.php` (720 lines)
  - `src/Clients/Controllers/ClientsController.php` (810 lines)
  - `src/Learners/Ajax/LearnerAjaxHandlers.php` (verified function `generate_learner_table_rows`)
  - `src/Agents/Ajax/AgentsAjaxHandlers.php` (verified `handlePagination`, `handleDelete`)
  - `src/Learners/Services/ProgressionService.php` (verified constructor pattern)
  - `core/Abstract/BaseController.php` (verified AJAX helpers)
  - `docs/plans/2026-02-16-phase3-medium-priority-design.md` (audit source document)

- **Service layer inventory** (via glob):
  - 18 service classes found across Events, Agents, Classes, Learners modules
  - Verified: ProgressionService, WorkingAreasService, AgentDisplayService all exist

### Secondary (MEDIUM confidence)
- WordPress/PHP service layer patterns inferred from existing ProgressionService implementation
- Standard WordPress AJAX patterns from BaseController implementation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - verified via existing codebase (PHP 8.0+, PostgreSQL, WordPress 6.0+)
- Architecture: HIGH - existing services follow consistent pattern (ProgressionService, WorkingAreasService)
- Pitfalls: HIGH - derived from specific plan analysis and current code complexity (160-line methods)
- Plan validation: HIGH - all files exist, line counts verified, patterns match

**Research date:** 2026-02-16
**Valid until:** 2026-03-16 (30 days - stable architecture domain, no fast-moving dependencies)

**Critical finding:** All three plan files (36-01, 36-02, 36-03) are ACCURATE and EXECUTABLE against current codebase state. No plan modifications required. Primary risk is AJAX response format compatibility - add explicit verification steps.
