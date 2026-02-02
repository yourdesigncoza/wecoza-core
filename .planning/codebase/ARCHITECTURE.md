# Architecture

**Analysis Date:** 2026-02-02

## Pattern Overview

**Overall:** WordPress MVC Plugin with Module-based Architecture

**Key Characteristics:**
- Modular structure with isolated Learners and Classes modules
- Service-oriented layer for complex business logic
- Repository pattern for data access with column whitelisting security
- Dependency injection via constructor initialization
- PSR-4 autoloading with lazy-loaded database connection

## Layers

**Presentation Layer (Controllers):**
- Purpose: Handle WordPress hooks, shortcodes, and AJAX requests
- Location: `src/Learners/Controllers/`, `src/Classes/Controllers/`
- Contains: Controller classes extending `BaseController`
- Depends on: Models, Repositories, Services, Views
- Used by: WordPress hooks (init, wp_ajax_*, wp_enqueue_scripts)
- Pattern: Each controller instantiates in `plugins_loaded` hook (priority 5)

**Business Logic Layer (Services):**
- Purpose: Complex operations requiring multiple data sources or external logic
- Location: `src/Learners/Services/`, `src/Classes/Services/`
- Contains: `ProgressionService`, `PortfolioUploadService`, `ScheduleService`, `UploadService`, `FormDataProcessor`
- Depends on: Models, Repositories
- Used by: Controllers
- Pattern: Services encapsulate workflows; called by controllers to handle domain logic

**Model Layer:**
- Purpose: Data representation and entity-level operations
- Location: `src/Learners/Models/`, `src/Classes/Models/`
- Contains: Classes extending `BaseModel` with type casting, hydration, property access
- Depends on: Repositories for persistence
- Used by: Controllers, Services
- Pattern: Models are hydrated from database arrays; provide type-safe property access via `__get` magic method

**Data Access Layer (Repositories):**
- Purpose: Database queries with SQL injection prevention via column whitelisting
- Location: `src/Learners/Repositories/`, `src/Classes/Repositories/`
- Contains: Classes extending `BaseRepository` implementing CRUD and pagination
- Depends on: `PostgresConnection` singleton
- Used by: Models, Services
- Pattern: All column names validated against whitelists; prepared statements for all queries

**Framework Layer (Core):**
- Purpose: Shared infrastructure and abstractions
- Location: `core/Abstract/`, `core/Database/`, `core/Helpers/`
- Contains: `BaseController`, `BaseModel`, `BaseRepository`, `PostgresConnection`, helper functions
- Depends on: WordPress, PHP 8.0+
- Used by: All modules
- Pattern: Base classes provide common functionality; global helper functions available everywhere

**View Layer:**
- Purpose: HTML output and user interface
- Location: `views/learners/`, `views/classes/`, `views/components/`
- Contains: PHP template files (.php or .view.php extensions)
- Depends on: Controller data via `wecoza_view()`, `wecoza_component()`
- Used by: Controllers via `render()` and `component()` methods
- Pattern: Templates receive data as extracted variables; components in `views/components/` for reusable partials

## Data Flow

**Shortcode Rendering Flow:**

1. WordPress page loads shortcode e.g. `[wecoza_display_learners]`
2. `LearnerController::registerShortcodes()` has registered callback in `init` hook
3. Controller callback method called (e.g., `renderLearnerList()`)
4. Controller method calls `$repository->findAll()` to fetch data
5. `Repository->findAll()` validates column whitelist, queries `PostgresConnection`
6. Database returns array of records
7. Controller instantiates Model objects with returned data
8. Models are hydrated and passed to template via `$this->render('view', ['data' => $models])`
9. `wecoza_view()` extracts data to local scope and includes PHP template
10. Template outputs HTML directly or via component partials

**AJAX Request Flow:**

1. Frontend JavaScript calls `wp_ajax_wecoza_get_learner` with nonce
2. WordPress routes to registered AJAX handler (Controller method)
3. `BaseController::requireNonce()` validates CSRF token
4. `BaseController::requireCapability()` checks user permissions
5. `BaseController::input()` sanitizes POST data via `wecoza_sanitize_value()`
6. Controller calls service or repository method with cleaned data
7. Service/Repository performs database operation
8. Result passed to `BaseController::sendSuccess()` or `sendError()`
9. Response returned as JSON via `wp_send_json_*()` WordPress functions

**Class Assignment with LP Progression (WEC-168):**

1. Admin assigns learner to class via ClassController AJAX handler
2. Handler calls `ProgressionService::createLPForClassAssignment()`
3. Service checks for active LP collision via `LearnerProgressionModel::getCurrentForLearner()`
4. If collision exists and no override flag: returns warning with collision data
5. If collision exists and override flag: puts current LP on hold via `$current->putOnHold()`
6. Creates new `LearnerProgressionModel` with status 'in_progress'
7. Service calls `$progression->save()` to persist to database
8. Returns success result with new progression tracking ID

**State Management:**

- **Request State:** Passed through method parameters; no global state
- **Application State:** Lazy-loaded singleton `PostgresConnection` maintains database connection
- **User State:** Relies on WordPress authentication and capability system
- **Business State:** Learner progression tracked in `learner_progressions` table with statuses: `in_progress`, `completed`, `on_hold`

## Key Abstractions

**BaseController:**
- Purpose: Common WordPress integration for all controllers
- Examples: `LearnerController`, `ClassController`, `ClassAjaxController`, `QAController`
- Pattern: Provides `registerHooks()` override point; helper methods for nonce/capability checking, input sanitization, JSON responses

**BaseModel:**
- Purpose: Entity representation with type casting and magic property access
- Examples: `LearnerModel`, `ClassModel`, `LearnerProgressionModel`, `QAModel`
- Pattern: Properties defined as protected; `__get()` handles both snake_case and camelCase access; hydration from database arrays

**BaseRepository:**
- Purpose: Common CRUD patterns with SQL injection protection
- Examples: `LearnerRepository`, `ClassRepository`, `LearnerProgressionRepository`
- Pattern: Column whitelisting enforced via `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`; all user input validated before SQL construction

**PostgresConnection:**
- Purpose: Singleton PDO wrapper with lazy loading
- Pattern: Not connected until first query; SSL support for remote databases; transaction methods available to repositories

**ProgressionService:**
- Purpose: Complex LP assignment logic with collision detection
- Pattern: Orchestrates model operations; returns result arrays with success/warning data instead of throwing exceptions for foreseeable collisions

## Entry Points

**WordPress Initialization:**
- Location: `wecoza-core.php` lines 135-227
- Triggers: `plugins_loaded` hook (priority 5)
- Responsibilities: PSR-4 autoloader registration, config loading, module initialization, shortcode/AJAX handler registration

**Module Initialization (Learners):**
- Location: `src/Learners/Controllers/LearnerController.php` constructor
- Triggers: Instantiated in `plugins_loaded` hook
- Responsibilities: Registers shortcodes in `init` hook, registers AJAX handlers

**Module Initialization (Classes):**
- Location: `src/Classes/Controllers/ClassController.php::initialize()`
- Triggers: Called in `plugins_loaded` hook
- Responsibilities: Registers shortcodes, enqueues frontend assets, ensures required WordPress pages exist

**Shortcode Handlers:**
- Location: Various shortcode classes in `src/Learners/Shortcodes/` and controller methods
- Triggers: WordPress shortcode parser
- Examples: `[wecoza_display_learners]`, `[wecoza_capture_class]`, `[wecoza_display_single_class]`

**AJAX Handlers:**
- Location: `src/Learners/Ajax/LearnerAjaxHandlers.php`, controller AJAX methods
- Triggers: WordPress AJAX request with `action=` parameter matching registered handler
- Pattern: Functions registered via `add_action('wp_ajax_*')` and `wp_ajax_nopriv_*` (where applicable)

**Frontend Assets:**
- Location: `assets/js/learners/`, `assets/js/classes/`, `assets/css/`
- Enqueued: In controller `wp_enqueue_scripts` hooks or plugin main file

## Error Handling

**Strategy:** Errors logged to WordPress debug log; exceptions caught at service level; JSON responses indicate success/failure status

**Patterns:**

- **Repository Methods:** Return null/empty array on failure; log error to error_log
- **Service Methods:** Throw exceptions for programming errors (shouldn't happen); return result arrays for expected collisions
- **AJAX Handlers:** Catch exceptions, validate inputs, call `sendError()` with HTTP status code and message
- **Models:** Validation errors stored in model instance; caller checks via return value or exception

**Example Error Response:**
```
{
  "success": false,
  "data": {
    "message": "Invalid security token.",
    "missing_fields": ["title", "surname"]
  },
  "statusCode": 403
}
```

## Cross-Cutting Concerns

**Logging:**
- Via `error_log()` in repositories and database class
- Only when `WP_DEBUG` constant is defined
- Messages prefixed with "WeCoza Core:" for identification
- Location: WordPress debug.log file

**Validation:**
- Input: `BaseController::input()` and `sanitizeArray()` methods using `wecoza_sanitize_value()`
- Database: Repository column whitelisting in `getAllowedInsertColumns()` and `getAllowedUpdateColumns()`
- Required fields: `BaseController::requireFields()` validates presence before operation

**Authentication:**
- Entire plugin requires WordPress login (no pages accessible to unauthenticated users)
- Learner PII access requires `manage_learners` capability (Admin only)
- AJAX handlers use nonce verification via `wp_verify_nonce()` wrapped in `BaseController::requireNonce()`

**Authorization:**
- Capabilities checked via `current_user_can()` in controller methods
- `manage_learners` and `manage_options` are primary capabilities
- Added to admin role on plugin activation; removed on deactivation

---

*Architecture analysis: 2026-02-02*
