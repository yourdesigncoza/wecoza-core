# Phase 21: Foundation Architecture - Research

**Researched:** 2026-02-11
**Domain:** WordPress Plugin Module Integration, PSR-4 Autoloading, MVC Architecture Migration
**Confidence:** HIGH

## Summary

Phase 21 involves migrating the standalone wecoza-clients-plugin into wecoza-core's modular architecture. The source plugin exists at `.integrate/wecoza-clients-plugin/` with fully functional code using its own namespace (`WeCozaClients\`), database service (`DatabaseService`), bootstrap system, and asset management.

The migration requires:
1. Namespace transformation from `WeCozaClients\` to `WeCoza\Clients\`
2. Replacing standalone `DatabaseService` with core's `wecoza_db()` singleton
3. Adopting core's view rendering system (`wecoza_view()` instead of local `view()`)
4. Moving assets to core's asset structure and using core's enqueue patterns
5. Registering shortcodes and AJAX handlers through core's entry point

**Primary recommendation:** Follow the exact patterns established by `src/Learners/` and `src/Classes/` modules. These provide proven templates for Controllers, Models, Repositories, Services, Ajax handlers, and Shortcodes within wecoza-core architecture.

## Standard Stack

### Core Dependencies
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| PHP | 8.0+ | Language runtime | Required by wecoza-core, enables match expressions and typed properties |
| WordPress | 6.0+ | CMS platform | Minimum version for wecoza-core compatibility |
| PostgreSQL | 12+ | Database | Wecoza-core uses PostgreSQL exclusively via `wecoza_db()` |
| PSR-4 Autoloading | - | Class loading | Established in wecoza-core's autoloader for all modules |

### Wecoza-Core Infrastructure (Already Present)
| Component | Location | Purpose | Usage Pattern |
|-----------|----------|---------|---------------|
| `PostgresConnection` | `core/Database/` | Singleton DB connection | Access via `wecoza_db()` helper |
| `BaseController` | `core/Abstract/` | Controller base class | Extend for all controllers |
| `BaseModel` | `core/Abstract/` | Model base class | Extend for all models |
| `BaseRepository` | `core/Abstract/` | Repository base class | Extend for all repositories |
| `AjaxSecurity` | `core/Helpers/` | AJAX nonce/capability checks | Use for all AJAX handlers |
| View Helpers | `core/Helpers/functions.php` | Rendering utilities | `wecoza_view()`, `wecoza_component()` |

### Source Plugin Stack (To Be Replaced)
| Component | Current | Replacement | Migration Action |
|-----------|---------|-------------|------------------|
| `WeCozaClients\` namespace | Custom | `WeCoza\Clients\` | Rename namespace across all files |
| `DatabaseService` (static PDO wrapper) | Standalone | `wecoza_db()` singleton | Replace all `DatabaseService::` calls |
| `view()` function | Bootstrap helper | `wecoza_view()` | Update all view rendering calls |
| `config()` function | Bootstrap helper | `wecoza_config()` | Update config access |
| `asset_url()` function | Bootstrap helper | `wecoza_asset_url()` | Update asset URL generation |

**Installation:** No external packages required. All infrastructure exists in wecoza-core.

## Architecture Patterns

### Recommended Project Structure (Target State)
```
src/Clients/
├── Ajax/                    # AJAX handlers (pattern: LearnerAjaxHandlers.php)
│   └── ClientAjaxHandlers.php
├── Controllers/             # HTTP/shortcode controllers
│   ├── ClientsController.php
│   └── LocationsController.php
├── Models/                  # Data models (extend BaseModel)
│   ├── ClientsModel.php
│   ├── LocationsModel.php
│   ├── SitesModel.php
│   └── ClientCommunicationsModel.php
├── Repositories/            # Data access layer (extend BaseRepository)
│   ├── ClientRepository.php
│   └── LocationRepository.php
├── Services/                # Business logic services
│   └── ClientValidationService.php (if needed)
└── Shortcodes/              # Shortcode handlers (optional, can stay in Controller)
    └── ClientShortcodes.php

views/clients/              # View templates (.view.php or .php)
├── components/
│   ├── client-capture-form.view.php
│   ├── client-update-form.view.php
│   └── location-capture-form.view.php
└── display/
    ├── clients-table.view.php
    └── locations-list.view.php

assets/js/clients/          # JavaScript files
├── client-capture.js
├── clients-table.js
├── location-capture.js
└── locations-list.js

config/                     # Configuration (merge into core config/)
└── clients.php             # Client-specific config (SETA lists, validation rules)
```

### Pattern 1: Namespace Migration
**What:** Transform `WeCozaClients\` to `WeCoza\Clients\` throughout codebase
**When to use:** First step of migration, enables PSR-4 autoloading
**Example:**
```php
// BEFORE (source plugin)
namespace WeCozaClients\Controllers;
use WeCozaClients\Models\ClientsModel;
use WeCozaClients\Services\Database\DatabaseService;

class ClientsController {
    protected $model = null;

    protected function getModel() {
        if ($this->model === null) {
            $this->model = new ClientsModel();
        }
        return $this->model;
    }
}

// AFTER (wecoza-core integration)
namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Models\ClientsModel;

class ClientsController extends BaseController {
    protected $model = null;

    protected function getModel() {
        if ($this->model === null) {
            $this->model = new ClientsModel();
        }
        return $this->model;
    }
}
```

### Pattern 2: Database Access Replacement
**What:** Replace standalone `DatabaseService` static methods with core's `wecoza_db()` singleton
**When to use:** All database queries in Models and Repositories
**Example:**
```php
// BEFORE (source plugin - static DatabaseService)
use WeCozaClients\Services\Database\DatabaseService;

class ClientsModel {
    public function getAll($filters = []) {
        $sql = "SELECT * FROM clients WHERE deleted_at IS NULL";
        return DatabaseService::getAll($sql, $params);
    }

    public function insert($data) {
        return DatabaseService::insert('clients', $data);
    }
}

// AFTER (wecoza-core - wecoza_db() singleton)
namespace WeCoza\Clients\Models;

use WeCoza\Core\Abstract\BaseModel;

class ClientsModel extends BaseModel {
    protected string $table = 'clients';

    public function getAll($filters = []) {
        $db = wecoza_db();
        $sql = "SELECT * FROM clients WHERE deleted_at IS NULL";
        return $db->getAll($sql, $params);
    }

    public function insert($data) {
        $db = wecoza_db();
        return $db->insert($this->table, $data);
    }
}
```

### Pattern 3: View Rendering System
**What:** Replace bootstrap's `view()` function with core's `wecoza_view()` helper
**When to use:** All template rendering in Controllers and Shortcodes
**Example:**
```php
// BEFORE (source plugin - local view() function)
namespace WeCozaClients\Controllers;

class ClientsController {
    public function captureClientShortcode($atts) {
        $data = ['errors' => [], 'client' => []];
        return \WeCozaClients\view('components/client-capture-form', $data, true);
    }
}

// AFTER (wecoza-core - wecoza_view() helper)
namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;

class ClientsController extends BaseController {
    public function captureClientShortcode($atts) {
        $data = ['errors' => [], 'client' => []];
        return wecoza_view('clients/components/client-capture-form', $data, true);
    }
}

// Note: View path changes from 'components/...' to 'clients/components/...'
// Views move from app/Views/ to views/clients/
```

### Pattern 4: AJAX Handler Registration
**What:** Use core's `AjaxSecurity` class for nonce/capability checks in AJAX handlers
**When to use:** All AJAX endpoints
**Example:**
```php
// BEFORE (source plugin - manual nonce verification)
namespace WeCozaClients\Controllers;

class ClientsController {
    protected function registerAjaxHandlers() {
        add_action('wp_ajax_wecoza_save_client', [$this, 'ajaxSaveClient']);
    }

    public function ajaxSaveClient() {
        // Manual nonce check
        if (!wp_verify_nonce($_POST['nonce'], 'wecoza_clients_nonce')) {
            wp_send_json_error('Invalid nonce');
            wp_die();
        }

        // Manual capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            wp_die();
        }

        // Process...
    }
}

// AFTER (wecoza-core - AjaxSecurity pattern)
namespace WeCoza\Clients\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;

class ClientAjaxHandlers {
    public function __construct() {
        add_action('wp_ajax_wecoza_save_client', [$this, 'saveClient']);
    }

    public function saveClient(): void {
        // Single call handles nonce + capability + error response
        AjaxSecurity::requireNonce('clients_nonce_action');
        AjaxSecurity::requireCapability('manage_clients');

        // Process...
        AjaxSecurity::sendSuccess('Client saved', ['client_id' => $id]);
    }
}
```

### Pattern 5: Module Initialization
**What:** Register module classes in wecoza-core.php entry point
**When to use:** Final step to enable module
**Example:**
```php
// In wecoza-core.php (after existing modules)

// Initialize Clients Module
if (class_exists(\WeCoza\Clients\Controllers\ClientsController::class)) {
    new \WeCoza\Clients\Controllers\ClientsController();
}

if (class_exists(\WeCoza\Clients\Controllers\LocationsController::class)) {
    new \WeCoza\Clients\Controllers\LocationsController();
}

if (class_exists(\WeCoza\Clients\Ajax\ClientAjaxHandlers::class)) {
    new \WeCoza\Clients\Ajax\ClientAjaxHandlers();
}
```

### Pattern 6: Asset Registration
**What:** Register JS/CSS through WordPress enqueue system in Controller
**When to use:** All client-side assets
**Example:**
```php
// BEFORE (source plugin - manual asset loading in view templates)
// In view template:
<script src="<?php echo WECOZA_CLIENTS_JS_URL . 'client-capture.js'; ?>"></script>

// AFTER (wecoza-core - proper enqueue in Controller)
namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;

class ClientsController extends BaseController {
    protected function registerHooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('init', [$this, 'registerShortcodes']);
    }

    public function enqueueAssets(): void {
        global $post;

        // Only load on pages with our shortcodes
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'wecoza_capture_clients')) {
            return;
        }

        wp_enqueue_script(
            'wecoza-client-capture',
            wecoza_js_url('clients/client-capture.js'),
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-client-capture', 'wecozaClients', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clients_nonce_action')
        ]);
    }
}
```

### Pattern 7: Configuration Merge
**What:** Move plugin-specific config into core's config/ structure
**When to use:** Plugin has configuration data (SETA lists, validation rules, etc.)
**Example:**
```php
// BEFORE (source plugin - config/app.php)
return array(
    'validation_rules' => [...],
    'seta_options' => [...],
    'controllers' => [...]
);

// AFTER (wecoza-core - config/clients.php)
// New file: config/clients.php
return [
    'validation_rules' => [
        'client_name' => ['required', 'max:255'],
        'company_registration_nr' => ['required', 'unique:clients'],
        'seta' => ['required', 'in:' . implode(',', array_keys($this->getSETAOptions()))],
    ],

    'seta_options' => [
        'AgriSETA' => 'AgriSETA',
        'BANKSETA' => 'BANKSETA',
        // ... full list
    ],
];

// Access in code:
$config = wecoza_config('clients');
$setaOptions = $config['seta_options'] ?? [];
```

### Anti-Patterns to Avoid

- **Don't keep bootstrap.php**: Source plugin's bootstrap system is incompatible with core's PSR-4 autoloader. Remove entirely.
- **Don't preserve plugin constants**: `WECOZA_CLIENTS_*` constants should be replaced with core's `WECOZA_CORE_*` equivalents.
- **Don't mix database access patterns**: All queries must use `wecoza_db()` - don't keep any `DatabaseService::` calls.
- **Don't register shortcodes in `init` action**: Controllers auto-register on instantiation in wecoza-core.php.
- **Don't create new view helper functions**: Use existing `wecoza_view()`, `wecoza_component()`, `wecoza_array_get()`, etc.
- **Don't add CSS to plugin**: Per project rules, ALL CSS goes to theme directory at `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Database connection pooling | Custom PDO wrapper | `wecoza_db()` singleton | Already handles lazy loading, SSL, timezone |
| View template system | Custom include logic | `wecoza_view()` / `wecoza_component()` | Handles paths, data extraction, error logging |
| AJAX security checks | Manual nonce/capability code | `AjaxSecurity::requireNonce()` / `requireCapability()` | Centralized, tested, standardized error responses |
| Input sanitization | Custom sanitize functions | `wecoza_sanitize_value()` | Handles 15+ types: string, email, int, float, bool, json, date, url, etc. |
| Array access with defaults | `isset() ?: default` chains | `wecoza_array_get($array, 'key.nested', $default)` | Dot notation, null-safe |
| Asset URL generation | Manual path concatenation | `wecoza_js_url()` / `wecoza_css_url()` / `wecoza_asset_url()` | Handles versioning, correct paths |
| Environment detection | `is_admin() && !wp_doing_ajax()` | `wecoza_is_admin_area()` / `wecoza_is_ajax()` | Consistent across modules |
| Debug logging | `error_log()` directly | `wecoza_log($msg, $level)` | Respects WP_DEBUG, adds context |

**Key insight:** Wecoza-core provides a complete framework. The Clients module should leverage these utilities rather than duplicating infrastructure. Source plugin was standalone by necessity; now it can use shared services.

## Common Pitfalls

### Pitfall 1: Forgetting to Update Autoloader Registration
**What goes wrong:** Adding new `src/Clients/` namespace but forgetting to register it in `wecoza-core.php` autoloader
**Why it happens:** Autoloader registration is separate from module code - easy to overlook
**How to avoid:** Check existing autoloader in `wecoza-core.php` line ~48 and add:
```php
$namespaces = [
    'WeCoza\\Core\\' => WECOZA_CORE_PATH . 'core/',
    'WeCoza\\Learners\\' => WECOZA_CORE_PATH . 'src/Learners/',
    'WeCoza\\Classes\\' => WECOZA_CORE_PATH . 'src/Classes/',
    'WeCoza\\Events\\' => WECOZA_CORE_PATH . 'src/Events/',
    'WeCoza\\Clients\\' => WECOZA_CORE_PATH . 'src/Clients/', // ADD THIS
];
```
**Warning signs:** Fatal error "Class 'WeCoza\Clients\...' not found" despite file existing at correct path

### Pitfall 2: Inconsistent Database Method Usage
**What goes wrong:** Mixing `DatabaseService::` static calls with `wecoza_db()->` instance calls, causing connection issues
**Why it happens:** Incomplete migration - some files updated, others missed
**How to avoid:**
1. Search codebase for `DatabaseService::` before committing
2. Use `wecoza_db()` exclusively - it returns `PostgresConnection` instance
3. All database methods: `getAll()`, `getRow()`, `getValue()`, `insert()`, `update()`, `delete()`, `query()`, `beginTransaction()`, `commit()`, `rollback()`
**Warning signs:** Intermittent database errors, "Call to undefined method", transactions not rolling back

### Pitfall 3: View Path Confusion
**What goes wrong:** Views not rendering because path changed from `components/form` to `clients/components/form`
**Why it happens:** Source plugin had flat view structure; core uses module-prefixed paths
**How to avoid:**
- Source plugin: `view('components/client-capture-form')` → File at `app/Views/components/client-capture-form.view.php`
- Wecoza-core: `wecoza_view('clients/components/client-capture-form')` → File at `views/clients/components/client-capture-form.view.php`
- ALWAYS include module prefix in path: `clients/...`
**Warning signs:** "View file not found" errors in debug log, blank shortcode output

### Pitfall 4: AJAX Nonce Mismatches
**What goes wrong:** JavaScript sends nonce with one action name, PHP verifies with different action name
**Why it happens:** Source plugin had custom nonce actions; core has standardized naming
**How to avoid:**
1. Pick consistent nonce action (e.g., `clients_nonce_action`)
2. Use same action in THREE places:
   - PHP creation: `wp_create_nonce('clients_nonce_action')`
   - JS localization: `wp_localize_script(..., ['nonce' => wp_create_nonce('clients_nonce_action')])`
   - PHP verification: `AjaxSecurity::requireNonce('clients_nonce_action')`
**Warning signs:** "Invalid security token" errors on valid user actions, AJAX 403 responses

### Pitfall 5: Asset Loading on Every Page
**What goes wrong:** Client JS/CSS loads on every frontend page, slowing site
**Why it happens:** Forgetting conditional checks in `enqueueAssets()` method
**How to avoid:**
```php
public function enqueueAssets(): void {
    global $post;

    // CRITICAL: Only load on pages with relevant shortcodes
    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $has_client_shortcode = has_shortcode($post->post_content, 'wecoza_capture_clients')
        || has_shortcode($post->post_content, 'wecoza_display_clients')
        || has_shortcode($post->post_content, 'wecoza_update_clients');

    if (!$has_client_shortcode) {
        return;
    }

    // Now enqueue assets...
}
```
**Warning signs:** PageSpeed Insights shows unused JS/CSS on pages without client functionality

### Pitfall 6: Bootstrap.php Functions Still Referenced
**What goes wrong:** Code calls `\WeCozaClients\view()`, `\WeCozaClients\config()`, etc. - functions that no longer exist
**Why it happens:** Bootstrap file removed but function calls not updated
**How to avoid:**
1. Delete `app/bootstrap.php` early in migration
2. Search for `\WeCozaClients\` namespace prefix before completing migration
3. Replace ALL bootstrap functions:
   - `view()` → `wecoza_view()`
   - `config()` → `wecoza_config()`
   - `asset_url()` → `wecoza_asset_url()`
   - `plugin_path()` → `wecoza_plugin_path()`
   - `plugin_log()` → `wecoza_log()`
**Warning signs:** Fatal error "Call to undefined function WeCozaClients\view()"

### Pitfall 7: CSS Added to Plugin Directory
**What goes wrong:** CSS files added to `assets/css/clients/` but styles don't apply (per project rules, ignored by theme)
**Why it happens:** Developer habit from other projects, not reading project-specific CSS rules
**How to avoid:**
- **NEVER create CSS files in plugin** - they will be ignored
- **ALL CSS goes to theme**: `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`
- Use existing Phoenix theme classes first (check `/home/laudes/zoot/projects/phoenix/phoenix-extracted`)
- Only add custom CSS when Phoenix doesn't have needed styles
**Warning signs:** Styles work in isolation but not on live site, CSS rules not applying

## Code Examples

Verified patterns from wecoza-core existing modules:

### Example 1: Controller Structure
```php
// File: src/Clients/Controllers/ClientsController.php
namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Models\ClientsModel;
use WeCoza\Clients\Repositories\ClientRepository;

class ClientsController extends BaseController
{
    private ?ClientRepository $repository = null;

    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_capture_clients', [$this, 'captureClientShortcode']);
        add_shortcode('wecoza_display_clients', [$this, 'displayClientsShortcode']);
        add_shortcode('wecoza_update_clients', [$this, 'updateClientShortcode']);
    }

    private function getRepository(): ClientRepository
    {
        if ($this->repository === null) {
            $this->repository = new ClientRepository();
        }
        return $this->repository;
    }

    public function captureClientShortcode($atts): string
    {
        $data = ['errors' => [], 'client' => []];
        return wecoza_view('clients/components/client-capture-form', $data, true);
    }

    public function enqueueAssets(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post')) return;

        if (has_shortcode($post->post_content, 'wecoza_capture_clients')) {
            wp_enqueue_script(
                'wecoza-client-capture',
                wecoza_js_url('clients/client-capture.js'),
                ['jquery'],
                WECOZA_CORE_VERSION,
                true
            );

            wp_localize_script('wecoza-client-capture', 'wecozaClients', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('clients_nonce_action')
            ]);
        }
    }
}
```

### Example 2: Model with Repository Pattern
```php
// File: src/Clients/Models/ClientsModel.php
namespace WeCoza\Clients\Models;

use WeCoza\Core\Abstract\BaseModel;

class ClientsModel extends BaseModel
{
    protected string $table = 'clients';
    protected string $primaryKey = 'client_id';

    protected array $fillable = [
        'client_name',
        'company_registration_nr',
        'seta',
        'client_status',
        'financial_year_end',
        'contact_person',
        'contact_person_email',
        'contact_person_cellphone',
    ];

    protected array $jsonFields = []; // If any JSONB columns

    protected array $dateFields = [
        'financial_year_end',
        'bbbee_verification_date',
    ];

    public function validate(array $data): array
    {
        $errors = [];
        $config = wecoza_config('clients');
        $rules = $config['validation_rules'] ?? [];

        // Apply validation rules...
        if (empty($data['client_name'])) {
            $errors['client_name'] = 'Client name is required';
        }

        return $errors;
    }
}

// File: src/Clients/Repositories/ClientRepository.php
namespace WeCoza\Clients\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use WeCoza\Clients\Models\ClientsModel;

class ClientRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return ClientsModel::class;
    }

    protected function getAllowedOrderColumns(): array
    {
        return ['client_name', 'company_registration_nr', 'created_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['client_status', 'seta', 'main_client_id'];
    }

    public function getMainClients(): array
    {
        $db = wecoza_db();
        $sql = "SELECT * FROM clients WHERE main_client_id IS NULL AND deleted_at IS NULL ORDER BY client_name";
        return $db->getAll($sql);
    }
}
```

### Example 3: AJAX Handler Class
```php
// File: src/Clients/Ajax/ClientAjaxHandlers.php
namespace WeCoza\Clients\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Clients\Repositories\ClientRepository;

class ClientAjaxHandlers
{
    private ClientRepository $repository;

    public function __construct()
    {
        $this->repository = new ClientRepository();
        $this->registerHandlers();
    }

    private function registerHandlers(): void
    {
        add_action('wp_ajax_wecoza_save_client', [$this, 'saveClient']);
        add_action('wp_ajax_wecoza_get_client', [$this, 'getClient']);
        add_action('wp_ajax_wecoza_delete_client', [$this, 'deleteClient']);
    }

    public function saveClient(): void
    {
        AjaxSecurity::requireNonce('clients_nonce_action');
        AjaxSecurity::requireCapability('manage_clients');

        $clientData = AjaxSecurity::sanitizeArray($_POST['client'] ?? [], [
            'client_name' => 'text',
            'company_registration_nr' => 'text',
            'contact_person_email' => 'email',
        ]);

        try {
            $clientId = $this->repository->create($clientData);
            AjaxSecurity::sendSuccess('Client saved successfully', ['client_id' => $clientId]);
        } catch (\Exception $e) {
            wecoza_log('Client save failed: ' . $e->getMessage(), 'error');
            AjaxSecurity::sendError('Failed to save client');
        }
    }

    public function getClient(): void
    {
        AjaxSecurity::requireNonce('clients_nonce_action');
        AjaxSecurity::requireCapability('view_clients');

        $clientId = (int) ($_POST['client_id'] ?? 0);

        if (!$clientId) {
            AjaxSecurity::sendError('Invalid client ID');
            return;
        }

        $client = $this->repository->find($clientId);

        if (!$client) {
            AjaxSecurity::sendError('Client not found');
            return;
        }

        AjaxSecurity::sendSuccess('Client retrieved', ['client' => $client]);
    }
}
```

### Example 4: View Template
```php
// File: views/clients/components/client-capture-form.view.php
<?php
/**
 * Client Capture Form Component
 *
 * @var array $client Client data
 * @var array $errors Validation errors
 */

if (!defined('ABSPATH')) exit;

$config = wecoza_config('clients');
$setaOptions = $config['seta_options'] ?? [];
?>

<div class="wecoza-client-capture-form">
    <form id="client-capture-form" class="needs-validation" novalidate>
        <?php wp_nonce_field('clients_nonce_action', 'clients_nonce'); ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="client_name" class="form-label">Client Name <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control <?php echo isset($errors['client_name']) ? 'is-invalid' : ''; ?>"
                    id="client_name"
                    name="client_name"
                    value="<?php echo esc_attr($client['client_name'] ?? ''); ?>"
                    required
                >
                <?php if (isset($errors['client_name'])): ?>
                    <div class="invalid-feedback"><?php echo esc_html($errors['client_name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label for="company_registration_nr" class="form-label">Company Registration Number</label>
                <input
                    type="text"
                    class="form-control"
                    id="company_registration_nr"
                    name="company_registration_nr"
                    value="<?php echo esc_attr($client['company_registration_nr'] ?? ''); ?>"
                >
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="seta" class="form-label">SETA <span class="text-danger">*</span></label>
                <select class="form-select" id="seta" name="seta" required>
                    <option value="">Select SETA</option>
                    <?php foreach ($setaOptions as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($client['seta'] ?? '', $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-phoenix-primary">Save Client</button>
        </div>
    </form>
</div>
```

### Example 5: JavaScript Asset
```javascript
// File: assets/js/clients/client-capture.js
(function($) {
    'use strict';

    $(document).ready(function() {
        const form = $('#client-capture-form');

        if (!form.length) return;

        form.on('submit', function(e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                e.stopPropagation();
                form.addClass('was-validated');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'wecoza_save_client');
            formData.append('nonce', wecozaClients.nonce);

            $.ajax({
                url: wecozaClients.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Client saved successfully!');
                        form[0].reset();
                        form.removeClass('was-validated');
                    } else {
                        alert('Error: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function(xhr) {
                    alert('Server error: ' + xhr.status);
                }
            });
        });
    });
})(jQuery);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Standalone plugin with separate namespace | Module integrated into core | Phase 21 (v2.0) | Shared infrastructure, consistent patterns |
| Custom `DatabaseService` static wrapper | Core's `wecoza_db()` singleton | Phase 21 | Single connection pool, lazy loading |
| Plugin-specific bootstrap system | Core PSR-4 autoloader | Phase 21 | No manual requires, faster loading |
| Local view rendering function | Core's `wecoza_view()` helper | Phase 21 | Consistent paths, better error handling |
| Manual AJAX security checks | `AjaxSecurity` utility class | Phase 21 | Centralized, standardized, less boilerplate |
| CSS in plugin directory | CSS in theme directory | Project standard | Enforced by project rules |

**Deprecated/outdated:**
- `WeCozaClients\` namespace: Replaced with `WeCoza\Clients\`
- `app/bootstrap.php`: Removed, replaced by core autoloader
- `DatabaseService` class: Replaced by `wecoza_db()` singleton
- Plugin-specific constants (`WECOZA_CLIENTS_*`): Use core constants (`WECOZA_CORE_*`)
- Local helper functions: Use core helpers from `core/Helpers/functions.php`

## Source Plugin Analysis

### Current Structure (In `.integrate/wecoza-clients-plugin/`)
```
Source Plugin Structure:
app/
├── Controllers/
│   ├── ClientsController.php      # 900+ lines, handles shortcodes + AJAX
│   └── LocationsController.php    # 500+ lines
├── Models/
│   ├── ClientsModel.php           # Schema mapping, validation, JSONB handling
│   ├── LocationsModel.php
│   ├── SitesModel.php
│   └── ClientCommunicationsModel.php
├── Services/
│   └── Database/
│       └── DatabaseService.php    # Static PDO wrapper, 529 lines
├── Views/
│   ├── components/                # Form templates
│   └── display/                   # Table templates
├── Helpers/
│   └── ViewHelpers.php            # Form field rendering utilities
└── bootstrap.php                  # Autoloader, helpers, initialization

config/
└── app.php                        # Validation rules, SETA options, controllers

assets/
└── js/
    ├── client-capture.js
    ├── clients-table.js
    ├── location-capture.js
    └── locations-list.js

schema/
└── wecoza_db_full_bu_oct_13_b.sql # Database backup (clients, locations, sites tables)
```

### Key Migration Points

1. **Namespace Transformation**
   - All 19 PHP files use `WeCozaClients\` namespace
   - Need mechanical find/replace: `WeCozaClients\` → `WeCoza\Clients\`
   - Affects: Controllers (2), Models (4), Services (1), Helpers (1)

2. **Database Access Refactor**
   - `DatabaseService` has 20+ method calls across codebase
   - All use static pattern: `DatabaseService::getAll()`, `DatabaseService::insert()`
   - Must replace with: `wecoza_db()->getAll()`, `wecoza_db()->insert()`
   - Key files: ClientsModel.php, LocationsModel.php, SitesModel.php

3. **View Rendering Migration**
   - 15+ `view()` calls in Controllers
   - Path transformation needed: `'components/form'` → `'clients/components/form'`
   - Move from `app/Views/` to `views/clients/`

4. **Asset Registration**
   - 6 JavaScript files in `assets/js/`
   - Currently loaded via manual script tags in views
   - Must convert to proper `wp_enqueue_script()` in Controller

5. **Configuration Merge**
   - `config/app.php` has validation rules, SETA options, controller list
   - Split into: Keep validation/SETA in new `config/clients.php`, discard bootstrap config

6. **Bootstrap Elimination**
   - `app/bootstrap.php` provides: autoloader, helpers (`view()`, `config()`, `asset_url()`), initialization
   - All functions have core equivalents - must replace ALL calls
   - Delete file entirely after migration

### Database Schema
From `schema/wecoza_db_full_bu_oct_13_b.sql` (backup from Oct 13):
- **clients** table: ~25 columns, JSONB fields for flexible data, hierarchical relationships via `main_client_id`
- **locations** table: Place data with lat/lng (Google Maps integration)
- **sites** table: Client locations/branches
- **client_communications** table: Contact history
- All use soft deletes (`deleted_at`)
- PostgreSQL-specific features: JSONB, GIN indexes, triggers for timestamps

**Migration note:** Schema already exists in production. Phase 21 is code migration only - no schema changes.

## Open Questions

1. **Configuration File Strategy**
   - What we know: Source plugin has `config/app.php` with validation rules, SETA options, controller list
   - What's unclear: Should this become `config/clients.php` or merge into existing `config/app.php`?
   - Recommendation: Create separate `config/clients.php` for module-specific config (validation rules, SETA lists). Keep core's `config/app.php` for global settings. Precedent: Other modules don't have separate config files, but Clients has substantial configuration data.

2. **AJAX Handler Organization**
   - What we know: Source has 10+ AJAX handlers in ClientsController; Learners module has separate `Ajax/LearnerAjaxHandlers.php`
   - What's unclear: Should Clients follow Learners pattern (separate Ajax/ directory) or keep in Controller?
   - Recommendation: Create `src/Clients/Ajax/ClientAjaxHandlers.php` following Learners pattern. Separates concerns, keeps Controller focused on HTTP/shortcodes.

3. **Repository Layer Adoption**
   - What we know: Source uses Models directly; core has BaseRepository pattern; Learners module uses Repositories
   - What's unclear: Should Clients adopt Repository pattern or keep direct Model access?
   - Recommendation: Add Repository layer for consistency with Learners module. Creates `ClientRepository`, `LocationRepository` extending `BaseRepository`. Models focus on data representation, Repositories handle queries.

4. **ViewHelpers Integration**
   - What we know: Source plugin has `app/Helpers/ViewHelpers.php` (350+ lines) with form field rendering methods
   - What's unclear: Should this become part of core's shared helpers or stay module-specific?
   - Recommendation: Keep as `src/Clients/Helpers/ViewHelpers.php` initially. It's Clients-specific (client field rendering). Can be promoted to core later if other modules need it.

5. **Google Maps API Key Management**
   - What we know: Source plugin uses Google Maps for location capture, stores API key in `wecoza_agents_google_maps_api_key` option
   - What's unclear: Should Clients module create new option or continue using shared key?
   - Recommendation: Continue using shared `wecoza_agents_google_maps_api_key` option. Maps functionality is organization-wide, not module-specific.

## Sources

### Primary (HIGH confidence)
- Wecoza-core codebase inspection: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/`
  - Examined: `wecoza-core.php` (entry point, autoloader, module initialization)
  - Examined: `core/Abstract/` (BaseController, BaseModel, BaseRepository patterns)
  - Examined: `core/Database/PostgresConnection.php` (singleton DB connection)
  - Examined: `core/Helpers/functions.php` (helper functions) and `AjaxSecurity.php`
  - Examined: `src/Learners/` module structure (reference architecture)
  - Examined: `src/Classes/` module structure (alternative reference)
  - Examined: `src/Events/` module structure (newest module patterns)
- Source plugin codebase: `.integrate/wecoza-clients-plugin/`
  - Examined: `wecoza-clients-plugin.php` (entry point)
  - Examined: `app/bootstrap.php` (current architecture)
  - Examined: `app/Controllers/ClientsController.php` (current patterns)
  - Examined: `app/Models/ClientsModel.php` (data layer)
  - Examined: `app/Services/Database/DatabaseService.php` (to be replaced)
  - Examined: `config/app.php` (configuration structure)
  - Examined: `CLAUDE.md` (plugin documentation)
  - Examined: `README.md` (feature overview)
- Project documentation:
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/CLAUDE.md` (architecture patterns)
  - `/home/laudes/.claude/CLAUDE.md` (project-wide rules: DRY, Git, CSS location)
  - `.planning/phases/21-foundation-architecture/` (phase requirements)

### Secondary (MEDIUM confidence)
- WordPress Codex patterns for plugin architecture (standard hooks, enqueue patterns)
- PSR-4 autoloading standards (namespace to directory mapping)

### Tertiary (LOW confidence)
- None - research based entirely on codebase inspection and project documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components exist in codebase, versions documented
- Architecture patterns: HIGH - Patterns verified in 3 existing modules (Learners, Classes, Events)
- Migration approach: HIGH - Clear source and target structures identified
- Pitfalls: HIGH - Based on common patterns seen across existing modules
- Open questions: MEDIUM - Require user decisions on organizational preferences

**Research date:** 2026-02-11
**Valid until:** 2026-03-15 (30 days - stable architecture, unlikely to change)

**Completeness:** This research covers all 8 requirements (ARCH-01 through ARCH-08) and provides concrete migration paths for each component. Ready for planning phase.
