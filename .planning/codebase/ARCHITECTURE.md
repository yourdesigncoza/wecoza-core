# Architecture

**Analysis Date:** 2026-03-03

## Pattern Overview

**Overall:** Modular MVC with Repository Pattern, Service Layer, and Event-Driven Notifications

**Key Characteristics:**
- **Module-based organization** - Each domain (Learners, Classes, Events, Agents, Clients, Feedback, LookupTables) is a self-contained module with complete stack (Controllers, Models, Repositories, Services, Views, Ajax)
- **PSR-4 Autoloading** - Namespace-based class loading with zero manual requires for application classes
- **Base abstractions** - `BaseController`, `BaseModel`, `BaseRepository` provide common patterns and reduce duplication
- **Database agnostic** - PostgreSQL accessed via singleton `PostgresConnection`, not WordPress `$wpdb`
- **Security-first** - Column whitelisting, nonce verification, capability checks built into abstraction layers
- **WordPress integration** - Hooks, shortcodes, AJAX actions wired through controller registration during `plugins_loaded` (priority 5)
- **Async processing** - Action Scheduler (WooCommerce vendor library) for background notifications and AI enrichment

## Layers

**Presentation (Shortcodes & AJAX):**
- Purpose: Entry points for user interactions - renders forms, lists, and processes input
- Location: `src/{Module}/Shortcodes/*.php`, `src/{Module}/Ajax/*.php`
- Contains: Shortcode registration, AJAX action handlers, direct output rendering
- Depends on: Controllers, Services, Models
- Used by: Front-end forms, AJAX requests, WordPress hooks

**Controller:**
- Purpose: Orchestrates business logic, coordinates services, renders views, manages HTTP lifecycle
- Location: `src/{Module}/Controllers/{Module}Controller.php`, `src/{Module}/Controllers/{Module}AjaxController.php`
- Contains: Shortcode callbacks, AJAX handlers, hook registration, view rendering via `wecoza_view()` or `wecoza_component()`
- Depends on: Services, Models, Views
- Used by: Plugin bootstrap (`wecoza-core.php`), WordPress hooks
- Pattern: Controllers inherit from `BaseController`, override `registerHooks()` to wire WordPress actions/filters

**Service:**
- Purpose: Business logic layer - CRUD operations, data transformations, cross-entity orchestration
- Location: `src/{Module}/Services/{Module}Service.php`
- Contains: Public methods for create, read, update, delete, bulk operations, calculations
- Depends on: Repositories, Models
- Used by: Controllers, other Services, special-purpose handlers
- Pattern: Services instantiate repositories, call repository methods, return hydrated Models

**Model:**
- Purpose: Data representation with type casting, hydration, and persistence helpers
- Location: `src/{Module}/Models/{Module}Model.php`
- Contains: Properties matching DB columns, type definitions, static methods for data access (delegates to Repository)
- Depends on: Repository (for persistence), Enums
- Used by: Services, Controllers, Views
- Pattern: Models extend `BaseModel`, use static `$table`, `$casts`, `$fillable`, provide magic getters/setters for snake_case ↔ camelCase conversion

**Repository:**
- Purpose: Data access layer - all database queries encapsulated here
- Location: `src/{Module}/Repositories/{Module}Repository.php`
- Contains: SELECT queries with column whitelisting, JOIN logic, result hydration to Models
- Depends on: PostgresConnection (singleton)
- Used by: Services, Models (static persistence methods)
- Pattern: Repositories extend `BaseRepository`, whitelist columns via `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`

**View:**
- Purpose: HTML template rendering with data passed from controllers
- Location: `views/{module}/{view-name}.view.php` or `views/{module}/{view-name}.php`
- Contains: HTML markup, PHP loops, conditional rendering, no business logic
- Depends on: Nothing (receives pure data)
- Used by: Controllers via `wecoza_view()` helper
- Pattern: Templates use extracted variables, call `wecoza_component()` for partials

**Core Infrastructure:**
- Purpose: Framework abstractions and database connection
- Location: `core/Abstract/`, `core/Database/`, `core/Helpers/`
- Contains: `BaseController`, `BaseModel`, `BaseRepository`, `PostgresConnection` (singleton), helper functions
- Depends on: Nothing (foundational)
- Used by: All modules

## Layers (Detail by Module)

**Learners Module:**
- Controllers: `LearnerController` (shortcodes), AJAX handlers in `Ajax/LearnerAjaxHandlers.php`, `ProgressionAjaxHandlers.php`
- Services: `LearnerService`, `ProgressionService`, `PortfolioUploadService`
- Models: `LearnerModel`, `LearnerProgressionModel`
- Repositories: `LearnerRepository`, `LearnerProgressionRepository`
- Shortcodes: display, capture, update, progression admin, progression report, regulatory export

**Classes Module:**
- Controllers: `ClassController`, `ClassAjaxController`, `QAController`, `PublicHolidaysController`, `ClassTypesController`
- Services: `ScheduleService`, `UploadService`, `AttendanceService`, `FormDataProcessor`
- Models: `ClassModel`, `QAModel`, `QAVisitModel`
- Repositories: `ClassRepository`, `AttendanceRepository`
- AJAX: `AttendanceAjaxHandlers.php`, `ClassStatusAjaxHandler.php`
- Shortcodes: capture, display, single display

**Events Module (Complex):**
- Controllers: `TaskController`, `MaterialTrackingController`
- Services: `EventDispatcher` (captures changes), `NotificationProcessor`, `NotificationEnricher` (async), `NotificationEmailer` (async), `MaterialNotificationService`
- Models: `ClassEventModel` (no file, DTO-based), `ClassTaskModel`
- Repositories: `ClassEventRepository`, `ClassTaskRepository`, `MaterialTrackingRepository`
- DTOs: `ClassEventDTO` (captures event metadata)
- Enums: `EventType` (INSERT, UPDATE, DELETE)
- Views: Templates for tasks, material tracking, AI summary, system pulse
- Presenters: `ClassTaskPresenter`, `MaterialTrackingPresenter`, `AISummaryPresenter`, `NotificationEmailPresenter`
- Shortcodes: event tasks, material tracking, AI summary, LP collision audit, system pulse
- Admin: Settings page for notifications config

**Agents Module:**
- Controllers: `AgentsController`
- Models: `AgentModel`
- Repositories: `AgentRepository`
- Ajax: `AgentsAjaxHandlers.php`
- Services: None (CRUD via repository directly)
- Shortcodes: capture, display, single display
- Views: capture form, display table/list, single agent display

**Clients Module:**
- Controllers: `ClientsController`, `LocationsController`
- Models: `ClientModel`, `LocationModel`
- Repositories: `ClientRepository`, `LocationRepository`
- Ajax: `ClientAjaxHandlers.php`
- Services: None (CRUD via repository directly)
- Shortcodes: capture clients, display clients, update clients, capture locations, list locations, edit locations
- Views: client forms, client display table, location forms, location list

**Feedback Module:**
- Controllers: `FeedbackController`
- Services: `FeedbackService` (rounds limiting, AI enrichment)
- Repositories: `FeedbackRepository`
- Ajax: Direct handlers in controller
- Shortcodes: feedback widget, feedback dashboard
- Views: widget form, dashboard display

**LookupTables Module:**
- Controllers: `LookupTableController`
- Repositories: `LookupTableRepository` (generic table CRUD)
- Ajax: `LookupTableAjaxHandler.php`
- No models (generic record handling)
- Shortcodes: manage lookup tables

**Settings Module:**
- Controllers: `SettingsPage` (register admin menu page)
- No models/repos (stores data via WP options)

**ShortcodeInspector Module:**
- Controllers: `ShortcodeInspector` (register Tools menu page)
- Purpose: Debugging - lists all registered shortcodes and their attributes

**Dev Module:**
- Controllers: `DevToolbarController` (debug mode only, registers debug toolbar in footer)

## Data Flow

**Form Submission (Learner Create Example):**

1. User fills `[wecoza_learners_form]` shortcode → HTML form
2. Form POSTs to `/wp-admin/admin-ajax.php?action=update_learner`
3. AJAX handler in `src/Learners/Ajax/LearnerAjaxHandlers.php::handle_update_learner()`
   - Verifies nonce via `check_ajax_referer('learners_nonce', 'nonce', false)`
   - Sanitizes POST fields via `wecoza_sanitize_value()` helpers
   - Calls `LearnerService::createLearner($sanitized_data)`
4. Service validates and delegates to Repository
5. Repository constructs INSERT via `PostgresConnection::prepare()` → PDO statement
6. Database executes, returns inserted row
7. Repository hydrates returned row to `LearnerModel` instance
8. Service returns model to AJAX handler
9. Handler sends JSON response with model data via `wp_send_json_success()`
10. Frontend JavaScript processes response, redirects or updates DOM

**Event Notification Pipeline (Class Change Capture):**

1. Class updated via form → `ClassController::updateClass()` shortcode callback
2. Controller calls `EventDispatcher::dispatchClassEvent(EventType::UPDATE, $classId, $newRow, $oldRow)`
3. Dispatcher compares `$oldRow` and `$newRow` against `SIGNIFICANT_CLASS_FIELDS` list
4. If significant change detected:
   - Creates `ClassEventDTO` with event metadata, field changes, user info
   - Inserts to `class_events` table via `ClassEventRepository`
   - Schedules async action: `as_enqueue_async_action('wecoza_process_event', [$eventId])`
5. Action Scheduler cron fires (hourly) → `wecoza_process_event` hook
   - `NotificationEnricher::enrich($eventId)` loads event, determines recipients
   - Enriches with AI summary if configured and OpenAI enabled
   - For each recipient: `as_enqueue_async_action('wecoza_send_notification_email', [...])`
6. Action Scheduler cron fires → `wecoza_send_notification_email` hook
   - `NotificationEmailer::send($eventId, $recipient, $emailContext)`
   - Renders email template via `NotificationEmailPresenter`
   - Sends via WordPress `wp_mail()`
   - Logs result

**Material Tracking Notifications (Separate Flow):**

1. Daily cron fires (scheduled on activation) → `wecoza_material_notifications_check` hook
2. `MaterialNotificationService::findOrangeStatusClasses()` (7-day warning)
   - Queries classes with material delivery < 7 days away
   - Sends notification email to relevant staff
3. `MaterialNotificationService::findRedStatusClasses()` (5-day critical)
   - Queries classes with material delivery < 5 days away
   - Sends critical notification email

**State Management:**
- **Request scope:** Controllers pass data via method parameters and return values
- **Module scope:** Services instantiated per-request (no static state except singletons like `PostgresConnection`)
- **Persistent state:** All long-lived state stored in PostgreSQL (`learners`, `classes`, `agents`, `clients`, `class_events`, `class_tasks`, etc.)
- **WordPress option scope:** Plugin settings stored via `get_option()` / `update_option()` (e.g., `wecoza_postgres_password`)
- **Transient caching:** Form validation errors, UI state via `set_transient()` / `get_transient()` (expires hourly, daily, etc.)

## Key Abstractions

**BaseController:**
- Purpose: Common controller functionality - database access, view rendering, AJAX helpers
- Examples: `LearnerController`, `ClassController`, `AgentsController`, `ClientsController`
- Pattern: Child classes override `registerHooks()` to wire WordPress actions/filters, implement shortcode callbacks and AJAX methods
- Protected methods: `db()` (get PostgresConnection), `render()` (output view), `component()` (render partial), `sendSuccess()`/`sendError()` (AJAX JSON responses), `requireNonce()`, `requireCapability()`

**BaseModel:**
- Purpose: Data representation with type casting, property hydration, array conversion
- Examples: `LearnerModel`, `ClassModel`, `AgentModel`, `ClientModel`
- Pattern: Define static `$table`, `$casts`, `$fillable` properties; access properties via magic getters/setters with automatic snake_case ↔ camelCase conversion
- Static methods: `getById()`, `getAll()`, `count()` - delegate to Repository static methods
- Instance methods: `toArray()`, `fillable()`, `hydrate()`

**BaseRepository:**
- Purpose: Data access with security controls (column whitelisting) and result hydration
- Examples: `LearnerRepository`, `ClassRepository`, `AgentRepository`
- Pattern: Override `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()` to whitelist safe columns; implement SELECT logic
- Query methods: `findById()`, `findAll()`, `find()` (with WHERE/ORDER BY), `create()`, `update()`, `delete()`, `count()`
- Security: All WHERE/ORDER BY column names validated against whitelists before SQL construction

**EventDispatcher:**
- Purpose: Bridge between domain changes (class create/update) and notification pipeline
- Examples: `src/Events/Services/EventDispatcher.php`
- Pattern: Compares old/new data, creates `ClassEventDTO`, inserts to `class_events`, schedules async processing
- Detection: Only captures events for `SIGNIFICANT_CLASS_FIELDS` (class_status, start_date, end_date, learner_ids, etc.) to avoid notification spam
- Returns: Event ID (0 if skipped due to non-significant fields)

**Shortcode Pattern:**
- Purpose: Convert shortcode registration into controller methods
- Examples: `[wecoza_display_learners]` → `LearnerController::renderLearnerList()`, `[wecoza_capture_class]` → `ClassController::captureClassShortcode()`
- Pattern: `add_shortcode()` in `registerHooks()`, shortcode callback renders view via `wecoza_view()` helper
- Benefits: Shortcodes feel like WordPress but are actually controller methods with full access to services/models

**DTO (Data Transfer Object):**
- Purpose: Immutable data objects for passing structured data through pipelines
- Examples: `ClassEventDTO` (event metadata for notification pipeline)
- Pattern: Holds only properties, no logic; passed through service → repository → storage
- Benefits: Type-safe, serializable, clear contracts between layers

## Entry Points

**Plugin Bootstrap:**
- Location: `wecoza-core.php` lines 166-701
- Triggers: WordPress `plugins_loaded` hook (priority 5)
- Responsibilities:
  - Registers PSR-4 autoloader for all `WeCoza\*` namespaces
  - Requires helper functions from `core/Helpers/functions.php`
  - Loads Action Scheduler from vendor directory
  - Fires `do_action('wecoza_core_loaded')` for dependent plugins
  - Instantiates all module controllers (LearnerController, ClassController, etc.)
  - Registers AJAX handlers for notifications dashboard
  - Schedules cron jobs (material notifications, notification processor)
  - Enqueues frontend assets (CSS, JS with nonces)

**Shortcode Callbacks:**
- Location: `src/{Module}/Controllers/` or `src/{Module}/Shortcodes/`
- Triggers: WordPress shortcode processor (when user embeds `[wecoza_*]`)
- Responsibilities: Fetch data via service, render view via `wecoza_view()`, return HTML

**AJAX Handlers:**
- Location: `src/{Module}/Ajax/*.php`
- Triggers: JavaScript `wp.ajax.post()` or `jQuery.post()` to `/wp-admin/admin-ajax.php?action=...`
- Responsibilities: Verify nonce, sanitize input, call service, return JSON response via `wp_send_json_success()` or `wp_send_json_error()`
- Pattern: Procedural functions registered via `add_action('wp_ajax_*')`, not class methods

**WP-CLI Commands:**
- Location: `wecoza-core.php` lines 884-917
- Triggers: `wp wecoza test-db` or `wp wecoza version`
- Responsibilities: Test database connection, show version info

**WordPress Hooks (Cron Jobs):**
- `wecoza_material_notifications_check` (daily) → `MaterialNotificationService::findOrangeStatusClasses()` / `findRedStatusClasses()`
- `wecoza_process_notifications` (hourly) → `NotificationProcessor::process()`
- `wecoza_process_event` (async) → `NotificationEnricher::enrich($eventId)`
- `wecoza_send_notification_email` (async) → `NotificationEmailer::send($eventId, $recipient, $context)`

**Activation/Deactivation:**
- Location: `wecoza-core.php` lines 710-847
- Triggers: Plugin activation/deactivation
- Responsibilities: Check PHP/extension versions, set default options, register capabilities, schedule cron jobs

## Error Handling

**Strategy:** Exceptions bubble up from Repository → Service → Controller, caught at boundary (AJAX handler, shortcode callback), converted to user-friendly JSON or HTML error messages.

**Patterns:**
- **Database errors:** `PDOException` caught in Repository, wrapped in `RuntimeException` with context message, logged via `wecoza_log()`
- **Validation errors:** Service methods throw `InvalidArgumentException` or `DomainException` with user-facing message
- **Authorization errors:** Controller methods check `current_user_can()` early, throw before accessing data
- **Logging:** `wecoza_log($msg, $level)` writes to `/opt/lampp/htdocs/wecoza/wp-content/debug.log` (only if `WP_DEBUG` enabled)
- **User feedback:** `wp_send_json_error(['message' => '...'], 403)` for AJAX, admin notices for critical issues

## Cross-Cutting Concerns

**Logging:**
- Framework: `wecoza_log($msg, $level = 'info')` function writes to debug.log
- Enabled: Only when `WP_DEBUG` and `WP_DEBUG_LOG` constants defined
- Usage: Services log significant operations (learner created, event processed, email sent), errors always logged

**Validation:**
- Input sanitization: `wecoza_sanitize_value($value, $type)` validates and casts (`string`, `email`, `int`, `float`, `bool`, `json`, `date`)
- Form validation: Services throw exceptions for invalid state (learner must have first/last name, class dates must be valid)
- Database schema validation: Repositories whitelist columns to prevent SQL injection

**Authentication:**
- Entire WP environment requires login (no public pages)
- All shortcodes/AJAX check `current_user_can()` before accessing data
- Special capability `manage_learners` required to access learner PII (registered on activation)

**Authorization:**
- Capabilities: `manage_learners`, `view_material_tracking`, `manage_material_tracking`, `manage_wecoza_clients`, `manage_wecoza_agents` (admin-only)
- Checks: Controllers verify capability at handler entry point, fail early with error
- Data access: Repositories don't filter by user (assumption: if you have capability, you can see all data)

---

*Architecture analysis: 2026-03-03*
