# Codebase Structure

**Analysis Date:** 2026-02-02

## Directory Layout

```
wecoza-core/
├── wecoza-core.php           # Plugin entry point: constants, autoloader, module initialization
├── composer.json             # PHP dependencies (currently minimal)
├── core/                      # Framework abstractions and infrastructure
│   ├── Abstract/              # Base classes for extension
│   │   ├── BaseController.php # Common controller functionality
│   │   ├── BaseModel.php      # Common model functionality
│   │   └── BaseRepository.php # Common repository functionality
│   ├── Database/
│   │   └── PostgresConnection.php # Singleton PDO wrapper, lazy-loaded
│   └── Helpers/
│       ├── functions.php      # Global wecoza_* helper functions
│       └── AjaxSecurity.php   # CSRF/nonce/capability checking utilities
├── src/                       # Module implementations
│   ├── Learners/              # Learner management module
│   │   ├── Controllers/
│   │   │   └── LearnerController.php
│   │   ├── Models/
│   │   │   ├── LearnerModel.php
│   │   │   └── LearnerProgressionModel.php
│   │   ├── Repositories/
│   │   │   ├── LearnerRepository.php
│   │   │   └── LearnerProgressionRepository.php
│   │   ├── Services/
│   │   │   ├── ProgressionService.php # LP assignment and tracking
│   │   │   └── PortfolioUploadService.php
│   │   ├── Ajax/
│   │   │   └── LearnerAjaxHandlers.php # AJAX endpoint functions
│   │   └── Shortcodes/
│   │       ├── learners-display-shortcode.php
│   │       ├── learners-capture-shortcode.php
│   │       ├── learner-single-display-shortcode.php
│   │       └── learners-update-shortcode.php
│   └── Classes/               # Class management module
│       ├── Controllers/
│       │   ├── ClassController.php
│       │   ├── ClassAjaxController.php
│       │   ├── QAController.php
│       │   ├── PublicHolidaysController.php
│       │   └── ClassTypesController.php
│       ├── Models/
│       │   ├── ClassModel.php
│       │   ├── QAModel.php
│       │   └── QAVisitModel.php
│       ├── Repositories/
│       │   └── ClassRepository.php
│       └── Services/
│           ├── ScheduleService.php
│           ├── UploadService.php
│           └── FormDataProcessor.php
├── views/                     # HTML templates (PHP)
│   ├── components/            # Reusable partials (shared)
│   ├── learners/              # Learner-specific templates
│   │   └── components/        # Learner-specific partials
│   └── classes/               # Class-specific templates
│       └── components/        # Class-specific partials
│           └── class-capture-partials/ # Form field partial templates
│           └── single-class/  # Single class view partials
├── assets/                    # Frontend assets
│   ├── js/
│   │   ├── learners/          # Learner JavaScript (4+ files)
│   │   │   └── learners-app.js # Global learner functionality
│   │   └── classes/           # Class JavaScript (10+ files)
│   │       └── utils/         # Utility functions
│   └── css/
│       └── learners-style.css # Learner styles
├── config/                    # Configuration files
│   └── app.php                # Application config (DB, cache, paths)
├── schema/                    # Database schema backups
├── tests/                     # Test files
├── docs/                      # Documentation
│   ├── analyzer/              # Analysis outputs
│   └── todo/                  # TODO tracking
└── .planning/                 # Planning documents (codebase analysis)
    └── codebase/              # This file location
```

## Directory Purposes

**wecoza-core.php:**
- Purpose: Plugin bootstrap and initialization
- Contains: Plugin header, PSR-4 autoloader, module instantiation, asset enqueueing, activation/deactivation hooks
- Key exports: Constants (WECOZA_CORE_VERSION, WECOZA_CORE_PATH, etc.)

**core/Abstract/:**
- Purpose: Base classes for extending throughout the plugin
- Contains: `BaseController`, `BaseModel`, `BaseRepository`
- Usage: All module classes extend these; provides common methods and patterns

**core/Database/:**
- Purpose: Database connection management
- Contains: `PostgresConnection` singleton
- Key feature: Lazy-loaded connection that defers until first query

**core/Helpers/:**
- Purpose: Global utility functions and security helpers
- Contains: `functions.php` (view rendering, config, paths, asset URLs), `AjaxSecurity.php` (nonce/capability checking)
- Exported to global scope: `wecoza_*` functions available everywhere

**src/Learners/:**
- Purpose: Learner module - complete CRUD and progression tracking
- Organization: Controllers → Models/Services → Repositories → Views
- Key feature: `ProgressionService` handles complex LP assignment with collision detection

**src/Classes/:**
- Purpose: Class module - schedule, QA tracking, assignments
- Organization: Multiple controllers for different concerns (class CRUD, AJAX, QA, holidays)
- Key feature: `FormDataProcessor`, `ScheduleService` handle complex data transformations

**views/:**
- Purpose: HTML template output
- Structure: Global `components/` for shared partials; module-specific subdirectories for page templates
- Rendering: Via `wecoza_view()` and `wecoza_component()` helper functions
- Extension: Both `.php` and `.view.php` extensions supported

**assets/:**
- Purpose: Frontend CSS/JavaScript
- Enqueued via: Controller `wp_enqueue_scripts` hooks
- Nonce/URL injection: `wp_localize_script()` passes AJAX URLs and nonces to JavaScript

**config/:**
- Purpose: Centralized configuration
- Loaded via: `wecoza_config('app')` helper; cached after first load
- Contains: Database credentials, cache settings, path configurations

## Key File Locations

**Entry Points:**
- `wecoza-core.php`: Main plugin file, WordPress entry point
- `src/Learners/Controllers/LearnerController.php`: Learner module instantiation
- `src/Classes/Controllers/ClassController.php`: Classes module instantiation

**Configuration:**
- `config/app.php`: Database host, port, SSL mode, cache expiration
- `wecoza-core.php` lines 93-122: Asset enqueueing and localization

**Core Logic:**
- `src/Learners/Services/ProgressionService.php`: LP assignment, collision detection, progression creation
- `src/Classes/Services/ScheduleService.php`: Class schedule calculations
- `src/Classes/Services/FormDataProcessor.php`: Complex form data transformation

**Database Access:**
- `src/Learners/Repositories/LearnerRepository.php`: Learner CRUD with complex queries
- `src/Classes/Repositories/ClassRepository.php`: Class data with caching
- `core/Database/PostgresConnection.php`: Connection management and query execution

**Testing:**
- `tests/security-test.php`: Security validation tests

## Naming Conventions

**Files:**

- **PHP Classes:** PascalCase (e.g., `LearnerModel.php`, `ClassController.php`)
- **View Files:** kebab-case with `.view.php` or `.php` extension (e.g., `learner-form.view.php`, `single-class-display.view.php`)
- **AJAX/Shortcode Files:** kebab-case (e.g., `learners-capture-shortcode.php`, `learner-single-display-shortcode.php`)

**Directories:**

- **Namespaces:** PascalCase matching directory structure (e.g., `WeCoza\Learners\Models`)
- **Module Directories:** PascalCase (e.g., `Learners/`, `Classes/`)
- **Subdirectories:** PascalCase or kebab-case per type (e.g., `Controllers/`, `class-capture-partials/`)

**Functions:**

- **Global Helpers:** snake_case with `wecoza_` prefix (e.g., `wecoza_view()`, `wecoza_sanitize_value()`, `wecoza_config()`)
- **AJAX Handlers:** snake_case with `handle_` prefix (e.g., `handle_fetch_learners_data()`)
- **Class Methods:** camelCase (e.g., `getLearner()`, `findById()`, `registerHooks()`)

**Variables:**

- **Properties:** camelCase (e.g., `$learnerId`, `$progressionService`, `$repository`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `WECOZA_CORE_VERSION`, `CACHE_DURATION`)

**Database:**

- **Table Names:** snake_case (e.g., `learners`, `classes`, `learner_progressions`)
- **Columns:** snake_case (e.g., `learner_id`, `class_id`, `created_at`)
- **Model Properties:** camelCase (e.g., `$learnerId`, `$firstName`, `$progressPercentage`)

## Where to Add New Code

**New Learner Feature:**
- **Implementation:** `src/Learners/Services/[Feature]Service.php` (if complex), `src/Learners/Controllers/LearnerController.php` (if simple)
- **Data Access:** `src/Learners/Repositories/LearnerRepository.php` (add query methods)
- **UI:** `views/learners/[feature-name].php` or `views/learners/components/[component].php`
- **AJAX:** Add handler to `src/Learners/Ajax/LearnerAjaxHandlers.php`
- **Tests:** `tests/[FeatureName]Test.php` or add to `tests/security-test.php`

**New Class Feature:**
- **Implementation:** `src/Classes/Services/[Feature]Service.php`, or add to `ClassController.php`
- **Data Access:** `src/Classes/Repositories/ClassRepository.php` (add query methods)
- **UI:** `views/classes/[feature-name].view.php` or `views/classes/components/[component]`
- **Controller:** Add new class to `src/Classes/Controllers/[Feature]Controller.php` or use existing
- **Tests:** `tests/[FeatureName]Test.php`

**New Model/Entity:**
- **Model:** `src/[Module]/Models/[Entity]Model.php` extending `BaseModel`
- **Repository:** `src/[Module]/Repositories/[Entity]Repository.php` extending `BaseRepository`
- **Whitelisting:** Define `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`

**Shared Utilities:**
- **Helper Functions:** Add to `core/Helpers/functions.php` with `wecoza_` prefix
- **Base Classes:** Add to `core/Abstract/` if shared across modules
- **Components:** Add to `views/components/` if used by multiple modules

**Frontend Assets:**
- **Learner JS:** `assets/js/learners/[feature-name].js`
- **Class JS:** `assets/js/classes/[feature-name].js`
- **Utilities:** `assets/js/classes/utils/[utility-name].js`
- **Styles:** `assets/css/[module]-style.css`

## Special Directories

**views/components/:**
- Purpose: Shared HTML partials used by multiple modules
- Generated: No (manually created)
- Committed: Yes
- Loaded via: `wecoza_component('component-name')` helper

**schema/:**
- Purpose: Database schema backups and migration documentation
- Generated: Database exports
- Committed: Yes (for version control)

**tests/:**
- Purpose: Unit and security tests
- Generated: No (manually written)
- Committed: Yes
- Executed: Via test runner (PHPUnit assumed)

**docs/analyzer/:**
- Purpose: Code analysis outputs and reports
- Generated: Yes (via analysis tools)
- Committed: Selectively (analysis results, not logs)

**daily-updates/:**
- Purpose: Development work logs and progress reports
- Generated: Manual daily entries
- Committed: Yes

**.planning/:**
- Purpose: Architecture and planning documents
- Generated: Yes (via GSD analyzer)
- Committed: Yes
- Contents: `ARCHITECTURE.md`, `STRUCTURE.md`, `CONVENTIONS.md`, `TESTING.md`, `STACK.md`, `INTEGRATIONS.md`, `CONCERNS.md`

## File Organization Patterns

**Controller Pattern:**
```php
// src/Learners/Controllers/LearnerController.php
class LearnerController extends BaseController {
    protected function registerHooks(): void { /* WordPress hooks */ }
    public function ajaxMethod() { /* AJAX handler */ }
    public function renderView() { /* Shortcode handler */ }
}
```

**Model Pattern:**
```php
// src/Learners/Models/LearnerModel.php
class LearnerModel extends BaseModel {
    protected static string $table = 'learners';
    protected static array $casts = ['id' => 'int']; // Type casting
    protected ?int $id = null; // Properties matching DB columns
    public static function getById(int $id): ?self { /* Load from DB */ }
}
```

**Repository Pattern:**
```php
// src/Learners/Repositories/LearnerRepository.php
class LearnerRepository extends BaseRepository {
    protected static string $table = 'learners';
    protected function getAllowedFilterColumns(): array { /* Column whitelist */ }
    public function findByEmail(string $email): ?array { /* Custom query */ }
}
```

**Service Pattern:**
```php
// src/Learners/Services/ProgressionService.php
class ProgressionService {
    private LearnerProgressionRepository $repository;
    public function startLearnerProgression(...): LearnerProgressionModel { /* Business logic */ }
}
```

**View Pattern:**
```php
// views/learners/learner-form.php - receives extracted $data variables
<?php if (!empty($learner)): ?>
    <form action="<?php echo esc_url($form_action); ?>">
        <input value="<?php echo esc_attr($learner->firstName); ?>" />
    </form>
<?php endif; ?>
```

---

*Structure analysis: 2026-02-02*
