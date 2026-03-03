# Codebase Structure

**Analysis Date:** 2026-03-03

## Directory Layout

```
wecoza-core/
├── wecoza-core.php           # Plugin entry point: constants, autoloader, module initialization
├── composer.json             # PHP dependencies (Action Scheduler from WooCommerce)
├── core/                      # Framework abstractions and infrastructure
│   ├── Abstract/
│   │   ├── BaseController.php # Common controller functionality
│   │   ├── BaseModel.php      # Common model functionality
│   │   ├── BaseRepository.php # Common repository functionality
│   │   └── AppConstants.php   # Shared constants
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
│   │   │   ├── LearnerService.php
│   │   │   ├── ProgressionService.php # LP assignment and tracking
│   │   │   └── PortfolioUploadService.php
│   │   ├── Ajax/
│   │   │   ├── LearnerAjaxHandlers.php
│   │   │   ├── ProgressionAjaxHandlers.php
│   │   │   └── AttendanceAjaxHandlers.php
│   │   ├── Enums/
│   │   ├── Shortcodes/
│   │   │   ├── learners-display-shortcode.php
│   │   │   ├── learners-capture-shortcode.php
│   │   │   ├── learner-single-display-shortcode.php
│   │   │   ├── learners-update-shortcode.php
│   │   │   ├── progression-admin-shortcode.php
│   │   │   ├── progression-report-shortcode.php
│   │   │   └── regulatory-export-shortcode.php
│   ├── Classes/               # Class management module
│   │   ├── Controllers/
│   │   │   ├── ClassController.php
│   │   │   ├── ClassAjaxController.php
│   │   │   ├── QAController.php
│   │   │   ├── PublicHolidaysController.php
│   │   │   └── ClassTypesController.php
│   │   ├── Models/
│   │   │   ├── ClassModel.php
│   │   │   ├── QAModel.php
│   │   │   └── QAVisitModel.php
│   │   ├── Repositories/
│   │   │   ├── ClassRepository.php
│   │   │   └── AttendanceRepository.php
│   │   ├── Services/
│   │   │   ├── ScheduleService.php
│   │   │   ├── UploadService.php
│   │   │   ├── AttendanceService.php
│   │   │   └── FormDataProcessor.php
│   │   ├── Ajax/
│   │   │   ├── AttendanceAjaxHandlers.php
│   │   │   └── ClassStatusAjaxHandler.php
│   ├── Events/                # Event/notification system (complex)
│   │   ├── Controllers/
│   │   │   ├── TaskController.php
│   │   │   └── MaterialTrackingController.php
│   │   ├── Services/
│   │   │   ├── EventDispatcher.php # Captures domain changes
│   │   │   ├── NotificationProcessor.php # Cron job entry point
│   │   │   ├── NotificationEnricher.php # Async enrichment
│   │   │   ├── NotificationEmailer.php # Async email sending
│   │   │   └── MaterialNotificationService.php
│   │   ├── Repositories/
│   │   │   ├── ClassEventRepository.php
│   │   │   ├── ClassTaskRepository.php
│   │   │   └── MaterialTrackingRepository.php
│   │   ├── Models/
│   │   │   ├── ClassEventModel.php (sparse)
│   │   │   └── TaskCollection.php
│   │   ├── DTOs/
│   │   │   └── ClassEventDTO.php # Event metadata
│   │   ├── Enums/
│   │   │   └── EventType.php # INSERT, UPDATE, DELETE
│   │   ├── Views/
│   │   │   ├── Presenters/ # Email/UI presentation logic
│   │   │   │   ├── ClassTaskPresenter.php
│   │   │   │   ├── MaterialTrackingPresenter.php
│   │   │   │   ├── AISummaryPresenter.php
│   │   │   │   └── NotificationEmailPresenter.php
│   │   │   ├── ConsoleView.php
│   │   │   ├── TemplateRenderer.php
│   │   ├── Shortcodes/
│   │   │   ├── EventTasksShortcode.php
│   │   │   ├── MaterialTrackingShortcode.php
│   │   │   ├── AISummaryShortcode.php
│   │   │   ├── LPCollisionAuditShortcode.php
│   │   │   └── SystemPulseShortcode.php
│   │   ├── Admin/
│   │   │   └── SettingsPage.php
│   │   ├── CLI/
│   │   │   └── AISummaryStatusCommand.php
│   │   ├── Support/
│   │   │   ├── OpenAIConfig.php
│   │   │   ├── FieldMapper.php
│   │   │   ├── Container.php
│   │   │   └── WordPressRequest.php
│   ├── Agents/                # Agent management module
│   │   ├── Controllers/
│   │   │   └── AgentsController.php
│   │   ├── Models/
│   │   │   └── AgentModel.php
│   │   ├── Repositories/
│   │   │   └── AgentRepository.php
│   │   ├── Ajax/
│   │   │   └── AgentsAjaxHandlers.php
│   │   └── Services/ (minimal)
│   ├── Clients/               # Client management module
│   │   ├── Controllers/
│   │   │   ├── ClientsController.php
│   │   │   └── LocationsController.php
│   │   ├── Models/
│   │   │   ├── ClientModel.php
│   │   │   └── LocationModel.php
│   │   ├── Repositories/
│   │   │   ├── ClientRepository.php
│   │   │   └── LocationRepository.php
│   │   ├── Ajax/
│   │   │   └── ClientAjaxHandlers.php
│   │   └── Services/ (minimal)
│   ├── Feedback/              # User feedback module
│   │   ├── Controllers/
│   │   │   └── FeedbackController.php
│   │   ├── Services/
│   │   │   └── FeedbackService.php
│   │   ├── Repositories/
│   │   │   └── FeedbackRepository.php
│   │   ├── Shortcodes/
│   │   │   ├── FeedbackWidgetShortcode.php
│   │   │   └── FeedbackDashboardShortcode.php
│   │   └── Support/ (helper classes)
│   ├── LookupTables/          # Generic lookup table CRUD
│   │   ├── Controllers/
│   │   │   └── LookupTableController.php
│   │   ├── Repositories/
│   │   │   └── LookupTableRepository.php
│   │   └── Ajax/
│   │       └── LookupTableAjaxHandler.php
│   ├── Settings/              # Plugin settings UI
│   │   └── SettingsPage.php
│   ├── ShortcodeInspector/    # Debugging utility
│   │   └── ShortcodeInspector.php
│   └── Dev/                   # Development tools
│       └── DevToolbarController.php
├── views/                     # HTML templates (PHP)
│   ├── components/            # Shared partials
│   │   └── [reusable components]
│   ├── learners/              # Learner-specific templates
│   │   ├── components/
│   │   │   ├── learner-form-fields.view.php
│   │   │   ├── learner-table.view.php
│   │   │   └── ...
│   │   └── [list, form, detail views]
│   ├── classes/               # Class-specific templates
│   │   ├── components/
│   │   │   ├── class-capture-partials/ # Form field partials
│   │   │   ├── single-class/
│   │   │   └── ...
│   │   └── [list, form, detail views]
│   ├── agents/                # Agent templates
│   │   ├── components/
│   │   ├── display/
│   │   └── [form, list views]
│   ├── clients/               # Client templates
│   │   ├── components/
│   │   ├── display/
│   │   └── [form, list views]
│   ├── events/                # Event/notification templates
│   │   ├── admin/
│   │   ├── ai-summary/
│   │   ├── event-tasks/
│   │   ├── material-tracking/
│   │   ├── lp-collision-audit/
│   │   └── system-pulse/
│   ├── feedback/              # Feedback widget and dashboard
│   │   ├── widget.view.php
│   │   └── dashboard.view.php
│   └── lookup-tables/         # Lookup table management UI
│       └── manage.view.php
├── assets/                    # Frontend CSS/JavaScript
│   ├── js/
│   │   ├── learners/
│   │   │   ├── learners-app.js
│   │   │   ├── portfolio-upload.js
│   │   │   ├── progression.js
│   │   │   └── [4+ files]
│   │   ├── classes/
│   │   │   ├── class-management.js
│   │   │   ├── attendance.js
│   │   │   ├── qa-tracking.js
│   │   │   ├── schedule.js
│   │   │   ├── utils/
│   │   │   └── [10+ files]
│   │   ├── agents/
│   │   ├── clients/
│   │   ├── feedback/
│   │   ├── lookup-tables/
│   │   └── dev/
│   │       └── form-fillers/ # Development utilities
│   └── css/
│       └── learners-style.css # Learner styles
├── config/                    # Configuration files
│   └── app.php                # Database, cache, paths config
├── schema/                    # Database schema backups
├── tests/                     # Test files
│   ├── Events/
│   │   ├── EmailNotificationTest.php
│   │   ├── AISummarizationTest.php
│   │   ├── PIIDetectorTest.php
│   │   ├── MaterialTrackingTest.php
│   │   └── TaskManagementTest.php
│   └── integration/
│       ├── agents-feature-parity.php
│       ├── clients-feature-parity.php
│       ├── verify-architecture.php
│       └── security-test.php
├── docs/                      # Documentation
│   ├── analyzer/              # Analysis outputs
│   ├── example-html/
│   ├── formfieldanalysis/
│   ├── learner-progression/
│   ├── notification-mail/
│   ├── plans/
│   └── todo/
├── vendor/                    # Composer dependencies
│   └── woocommerce/
│       └── action-scheduler/  # Async action processing
├── .planning/                 # Planning documents
│   ├── codebase/              # Architecture analysis (this file)
│   ├── phases/                # Implementation phases
│   ├── milestones/            # Release planning
│   ├── debug/                 # Bug tracking
│   └── quick/                 # Quick fixes
├── daily-updates/             # Development log
└── .claude/                   # Claude development metadata
```

## Directory Purposes

**wecoza-core.php:**
- Purpose: Plugin bootstrap and initialization
- Contains: Plugin header, PSR-4 autoloader, module instantiation, asset enqueueing, activation/deactivation hooks
- Key exports: Constants (WECOZA_CORE_VERSION, WECOZA_CORE_PATH, etc.), Actions (`wecoza_core_loaded`, `wecoza_core_activated`)

**core/Abstract/:**
- Purpose: Base classes for extending throughout the plugin
- Contains: `BaseController`, `BaseModel`, `BaseRepository`, `AppConstants`
- Usage: All module classes extend these; provides common methods and patterns

**core/Database/:**
- Purpose: Database connection management
- Contains: `PostgresConnection` singleton
- Key feature: Lazy-loaded connection that defers until first query; supports SSL connections

**core/Helpers/:**
- Purpose: Global utility functions and security helpers
- Contains: `functions.php` (view rendering, config, paths, asset URLs, input sanitization), `AjaxSecurity.php` (nonce/capability checking)
- Exported to global scope: `wecoza_*` functions available everywhere

**src/Learners/:**
- Purpose: Learner module - complete CRUD and progression tracking
- Organization: Controllers → Models/Services → Repositories → Views/AJAX
- Key feature: `ProgressionService` handles complex LP assignment with collision detection; learner PII access controlled via `manage_learners` capability

**src/Classes/:**
- Purpose: Class module - schedule, QA tracking, attendance, assignments
- Organization: Multiple controllers for different concerns (class CRUD, AJAX, QA, holidays)
- Key features: `FormDataProcessor`, `ScheduleService` handle complex data transformations; attendance tracking; class status transitions

**src/Events/:**
- Purpose: Event capture and notification pipeline - most complex module
- Organization: Controllers → Services (dispatcher, processor, enricher, emailer) → Repositories → Views/Shortcodes
- Key features: Captures significant class changes, enriches with AI summaries, sends async emails via Action Scheduler

**src/Agents/:**
- Purpose: Agent management module - legacy integration from wecoza-agents-plugin
- Organization: Simple CRUD via Controller → Repository
- Key tables: `agents`, `agent_meta`, `agent_notes`, `agent_absences`

**src/Clients/:**
- Purpose: Client and location management module
- Organization: Two controllers (Clients, Locations), shared data model pattern
- Key tables: Client and location master data

**src/Feedback/:**
- Purpose: User feedback collection and dashboard
- Organization: Controller → Service → Repository
- Key feature: Limits follow-up rounds, AI enrichment of feedback

**src/LookupTables/:**
- Purpose: Generic lookup table CRUD interface
- Organization: Generic repository pattern, no models (direct record handling)
- Key feature: Reusable for any lookup table (qualifications, provinces, etc.)

**src/Settings/:**
- Purpose: Plugin settings admin page
- Organization: Simple settings registration via WordPress options API

**src/ShortcodeInspector/:**
- Purpose: Debugging tool - lists all registered shortcodes
- Location: Tools menu in WordPress admin

**views/:**
- Purpose: HTML template output
- Structure: Global `components/` for shared partials; module-specific subdirectories for page templates
- Rendering: Via `wecoza_view()` (page templates) and `wecoza_component()` helper functions (partials)
- Extension: Both `.php` and `.view.php` extensions supported

**assets/:**
- Purpose: Frontend CSS/JavaScript
- Enqueued via: Controller `wp_enqueue_scripts` hooks in module bootstrapping
- Nonce/URL injection: `wp_localize_script()` passes AJAX URLs and nonces to JavaScript globals

**config/:**
- Purpose: Centralized configuration
- Loaded via: `wecoza_config('app')` helper; cached after first load
- Contains: Database credentials, cache settings, path configurations

## Key File Locations

**Entry Points:**
- `wecoza-core.php` (line 166): Main plugin initialization
- `src/Learners/Controllers/LearnerController.php`: Learner module
- `src/Classes/Controllers/ClassController.php`: Classes module
- `src/Events/Services/EventDispatcher.php`: Event capture entry point

**Configuration:**
- `config/app.php`: Database host, port, SSL mode, cache expiration
- `wecoza-core.php` (lines 119-153): Asset enqueueing and localization

**Core Logic:**
- `src/Learners/Services/ProgressionService.php`: LP assignment, collision detection
- `src/Classes/Services/ScheduleService.php`: Class schedule calculations
- `src/Events/Services/EventDispatcher.php`: Class change capture
- `src/Events/Services/NotificationProcessor.php`: Cron entry point for notifications

**Database Access:**
- `src/Learners/Repositories/LearnerRepository.php`: Learner CRUD with complex queries
- `src/Classes/Repositories/ClassRepository.php`: Class data with caching
- `src/Events/Repositories/ClassEventRepository.php`: Event storage and retrieval
- `core/Database/PostgresConnection.php`: Connection management and query execution

**Views/Presentation:**
- `views/learners/`: Learner list, form, detail templates
- `views/classes/`: Class list, form, detail, QA templates
- `views/events/`: Task list, material tracking, AI summary, collision audit, system pulse
- `views/components/`: Shared partials (alerts, badges, forms)

**Testing:**
- `tests/Events/`: Event notification pipeline tests
- `tests/integration/`: Feature parity checks, architecture validation
- `tests/security-test.php`: Security validation tests

## Naming Conventions

**Files:**

- **PHP Classes:** PascalCase (e.g., `LearnerModel.php`, `ClassController.php`, `EventDispatcher.php`)
- **View Files:** kebab-case with `.view.php` or `.php` extension (e.g., `learner-form.view.php`, `single-class-display.view.php`)
- **AJAX/Shortcode Files:** kebab-case (e.g., `learners-capture-shortcode.php`, `learner-single-display-shortcode.php`)
- **Shortcode Functions:** kebab-case (e.g., `handle_update_learner`, `handle_fetch_learners_data`)

**Directories:**

- **Namespaces:** PascalCase matching directory structure (e.g., `WeCoza\Learners\Models`, `WeCoza\Events\Services`)
- **Module Directories:** PascalCase (e.g., `Learners/`, `Classes/`, `Events/`)
- **Subdirectories:** PascalCase (Controllers, Models, Repositories, Services, Ajax, Shortcodes, Views, DTOs, Enums)
- **Template Subdirectories:** kebab-case (e.g., `class-capture-partials/`, `lp-collision-audit/`)

**Functions:**

- **Global Helpers:** snake_case with `wecoza_` prefix (e.g., `wecoza_view()`, `wecoza_sanitize_value()`, `wecoza_config()`, `wecoza_log()`)
- **AJAX Handlers:** snake_case with `handle_` prefix (e.g., `handle_fetch_learners_data()`, `handle_update_learner()`)
- **Class Methods:** camelCase (e.g., `getLearner()`, `findById()`, `registerHooks()`, `dispatchClassEvent()`)

**Variables:**

- **Properties:** camelCase (e.g., `$learnerId`, `$progressionService`, `$repository`)
- **Local Variables:** camelCase (e.g., `$learners`, `$classId`, `$response`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `WECOZA_CORE_VERSION`, `CACHE_DURATION`, `SIGNIFICANT_CLASS_FIELDS`)

**Database:**

- **Table Names:** snake_case (e.g., `learners`, `classes`, `learner_progressions`, `class_events`)
- **Columns:** snake_case (e.g., `learner_id`, `class_id`, `created_at`, `class_status`)
- **Model Properties:** camelCase (e.g., `$learnerId`, `$firstName`, `$progressPercentage`)
- **Enums:** PascalCase in PHP 8.1+ (e.g., `EventType::INSERT`, `EventType::UPDATE`, `EventType::DELETE`)

## Where to Add New Code

**New Learner Feature:**
- **Service:** `src/Learners/Services/[Feature]Service.php` (if complex), or add methods to `LearnerService.php`
- **Data Access:** Add query methods to `src/Learners/Repositories/LearnerRepository.php`
- **UI:** `views/learners/[feature-name].php` or `views/learners/components/[component].php`
- **AJAX:** Add handler to `src/Learners/Ajax/LearnerAjaxHandlers.php`
- **Tests:** `tests/[FeatureName]Test.php`

**New Class Feature:**
- **Service:** `src/Classes/Services/[Feature]Service.php`, or add to existing service
- **Data Access:** Add query methods to `src/Classes/Repositories/ClassRepository.php`
- **UI:** `views/classes/[feature-name].view.php` or `views/classes/components/[component]`
- **Controller:** Use existing `ClassController.php` or create new `[Feature]Controller.php`
- **Tests:** `tests/[FeatureName]Test.php`

**New Event/Notification Feature:**
- **Service:** `src/Events/Services/[Feature]Service.php` (follows pipeline: dispatcher → processor → enricher → emailer)
- **Repository:** `src/Events/Repositories/[Feature]Repository.php`
- **Presenter:** `src/Events/Views/Presenters/[Feature]Presenter.php` (for UI rendering)
- **Shortcode:** `src/Events/Shortcodes/[Feature]Shortcode.php`
- **Views:** `views/events/[feature-name]/` subdirectory
- **Tests:** `tests/Events/[Feature]Test.php`

**New Model/Entity:**
- **Model:** `src/[Module]/Models/[Entity]Model.php` extending `BaseModel`
- **Repository:** `src/[Module]/Repositories/[Entity]Repository.php` extending `BaseRepository`
- **Whitelisting:** Define `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`
- **Enums:** `src/[Module]/Enums/[Entity]Status.php` for status values, etc.

**Shared Utilities:**
- **Helper Functions:** Add to `core/Helpers/functions.php` with `wecoza_` prefix
- **Base Classes:** Add to `core/Abstract/` if shared across multiple modules
- **Components:** Add to `views/components/` if used by multiple modules

**Frontend Assets:**
- **Learner JS:** `assets/js/learners/[feature-name].js`
- **Class JS:** `assets/js/classes/[feature-name].js`
- **Utilities:** `assets/js/classes/utils/[utility-name].js` (shared class utilities)
- **Styles:** Append to existing `assets/css/learners-style.css` (do NOT create separate CSS files)

## Special Directories

**views/components/:**
- Purpose: Shared HTML partials used by multiple modules
- Generated: No (manually created)
- Committed: Yes
- Loaded via: `wecoza_component('component-name')` helper
- Examples: Alerts, badges, form fields, pagination

**schema/:**
- Purpose: Database schema backups and migration documentation
- Generated: Database exports
- Committed: Yes (for version control and reference)

**tests/:**
- Purpose: Unit and security tests
- Generated: No (manually written)
- Committed: Yes
- Key files: `Events/`, `integration/` subdirectories
- Pattern: Test classes follow PHPUnit conventions

**docs/:**
- Purpose: Development documentation, analysis, planning
- Generated: Yes (via analysis tools) and manually (plans, todo)
- Committed: Selectively
- Subdirectories: `analyzer/` (outputs), `todo/` (task tracking), `plans/` (design docs)

**daily-updates/:**
- Purpose: Development work logs and progress reports
- Generated: Manual daily entries
- Committed: Yes (for project history)

**.planning/:**
- Purpose: Architecture and planning documents
- Generated: Yes (via GSD mapper)
- Committed: Yes
- Contents: `ARCHITECTURE.md`, `STRUCTURE.md`, `CONVENTIONS.md`, `TESTING.md`, `STACK.md`, `INTEGRATIONS.md`, `CONCERNS.md`
- Subdirectories: `codebase/` (analysis docs), `phases/` (implementation phases), `milestones/` (release planning), `debug/` (bug tracking), `quick/` (quick fixes)

## File Organization Patterns

**Controller Pattern:**
```php
// src/Learners/Controllers/LearnerController.php
class LearnerController extends BaseController {
    private ?LearnerService $learnerService = null;

    protected function registerHooks(): void {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerShortcodes(): void {
        add_shortcode('wecoza_learners_form', [$this, 'renderCaptureForm']);
    }

    public function renderCaptureForm(): string {
        return $this->render('learners/capture-form', [...]);
    }
}
```

**Model Pattern:**
```php
// src/Learners/Models/LearnerModel.php
class LearnerModel extends BaseModel {
    protected static string $table = 'learners';
    protected static array $casts = ['id' => 'int', 'cityTownId' => 'int'];
    protected ?int $id = null;
    protected string $firstName = '';
    protected string $surname = '';

    public static function getById(int $id): ?self {
        return LearnerRepository::getInstance()->findById($id);
    }
}
```

**Repository Pattern:**
```php
// src/Learners/Repositories/LearnerRepository.php
class LearnerRepository extends BaseRepository {
    protected static string $table = 'learners';

    protected function getAllowedFilterColumns(): array {
        return ['id', 'first_name', 'surname', 'email_address'];
    }

    public function findByEmail(string $email): ?array {
        // Query with whitelisting applied
    }
}
```

**Service Pattern:**
```php
// src/Learners/Services/LearnerService.php
class LearnerService {
    private LearnerRepository $repository;

    public function __construct() {
        $this->repository = new LearnerRepository();
    }

    public function getLearner(int $id): ?LearnerModel {
        return LearnerModel::getById($id);
    }
}
```

**AJAX Handler Pattern:**
```php
// src/Learners/Ajax/LearnerAjaxHandlers.php
function handle_update_learner(): void {
    verify_learner_access('learners_nonce');
    $learner_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    $service = get_learner_service();
    $result = $service->updateLearner($learner_id, $_POST);

    if ($result) {
        wp_send_json_success(['message' => 'Learner updated']);
    } else {
        wp_send_json_error(['message' => 'Update failed'], 500);
    }
}

add_action('wp_ajax_update_learner', 'handle_update_learner');
add_action('wp_ajax_nopriv_update_learner', function() {
    wp_send_json_error(['message' => 'Unauthorized'], 403);
});
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

**Shortcode Pattern:**
```php
// src/Learners/Shortcodes/learners-display-shortcode.php
add_shortcode('wecoza_display_learners', function($atts) {
    $service = new LearnerService();
    $learners = $service->getLearners();

    return wecoza_view('learners/display', ['learners' => $learners], true);
});
```

---

*Structure analysis: 2026-03-03*
