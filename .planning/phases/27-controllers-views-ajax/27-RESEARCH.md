# Phase 27: Controllers, Views, JS, AJAX - Research

**Researched:** 2026-02-12
**Domain:** WordPress Plugin MVC Architecture Migration
**Confidence:** HIGH

## Summary

Phase 27 migrates the Agents module from standalone plugin architecture to WeCoza Core's standardized MVC pattern. This involves: (1) Controller migration extending BaseController with `registerHooks()`, (2) AJAX handler extraction using AjaxSecurity pattern, (3) View template migration to `views/agents/` with `.view.php` extension, (4) JS asset migration with unified localization, and (5) wiring in wecoza-core.php.

The research confirms all patterns exist in Learners and Classes modules, providing HIGH confidence templates to follow. Key findings: BaseController provides complete security/sanitization layer, existing localization patterns have critical bugs (mixed casing, multiple objects, direct response access), and conditional asset enqueuing prevents unnecessary loading.

**Primary recommendation:** Follow Learners module patterns for consistency—it has cleanest separation of concerns and most recent security implementations.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| BaseController | Core 1.0.0 | Abstract controller base class | Provides AJAX security, sanitization, view rendering |
| AjaxSecurity | Core 1.0.0 | Centralized AJAX security helper | Static methods for nonce, capability, validation |
| wecoza_view() | Core 1.0.0 | View template loader | Standardized view rendering with data injection |
| wecoza_component() | Core 1.0.0 | Component partial loader | Reusable UI components from views/components/ |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_localize_script() | WordPress Core | Pass PHP data to JavaScript | Every AJAX-enabled shortcode |
| wp_create_nonce() | WordPress Core | Generate CSRF tokens | All AJAX endpoints and forms |
| add_shortcode() | WordPress Core | Register shortcode handlers | Public-facing features |
| wp_ajax_* hooks | WordPress Core | Register AJAX endpoints | Authenticated operations only |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| BaseController | Direct AJAX handlers | Loses security layer, duplicates code |
| AjaxSecurity static | BaseController methods | Already available, use either |
| wecoza_view() | Direct template includes | Loses path abstraction, harder to maintain |

**Installation:**
No external dependencies—all utilities are in wecoza-core.

## Architecture Patterns

### Recommended Project Structure
```
src/Agents/
├── Controllers/
│   └── AgentsController.php        # Extends BaseController
├── Ajax/
│   └── AgentsAjaxHandlers.php      # Uses AjaxSecurity pattern
├── Models/                         # Already exists from Phase 26
│   └── AgentModel.php
├── Repositories/                   # Already exists from Phase 26
│   └── AgentRepository.php
views/agents/
├── components/
│   ├── agent-capture-form.view.php
│   └── agent-fields.view.php
├── display/
│   ├── agent-display-table.view.php
│   ├── agent-display-table-rows.view.php
│   ├── agent-pagination.view.php
│   └── agent-single-display.view.php
assets/js/agents/
├── agents-app.js                   # Main application logic
├── agent-form-validation.js
├── agents-ajax-pagination.js
├── agents-table-search.js
└── agent-delete.js
```

### Pattern 1: Controller Registration (BaseController Extension)
**What:** Controller extends BaseController, overrides `registerHooks()`, self-registers on instantiation
**When to use:** Every module controller
**Example:**
```php
// Source: LearnerController.php
namespace WeCoza\Learners\Controllers;

use WeCoza\Core\Abstract\BaseController;

class LearnerController extends BaseController
{
    private ?LearnerRepository $repository = null;

    /**
     * Register WordPress hooks
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);

        // AJAX endpoints (authenticated users only)
        add_action('wp_ajax_wecoza_get_learner', [$this, 'ajaxGetLearner']);
        add_action('wp_ajax_wecoza_get_learners', [$this, 'ajaxGetLearners']);
        add_action('wp_ajax_wecoza_update_learner', [$this, 'ajaxUpdateLearner']);
        add_action('wp_ajax_wecoza_delete_learner', [$this, 'ajaxDeleteLearner']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_learner_capture', [$this, 'renderCaptureForm']);
        add_shortcode('wecoza_learner_display', [$this, 'renderLearnerList']);
    }
}
```

**Key points:**
- Constructor calls `registerHooks()` automatically
- No need to manually call `add_action('plugins_loaded')`
- Instantiate in wecoza-core.php to activate

### Pattern 2: AJAX Handler Security (AjaxSecurity Static Methods)
**What:** Use `AjaxSecurity::requireNonce()`, `requireCapability()`, `sendSuccess()`, `sendError()`
**When to use:** All AJAX endpoints
**Example:**
```php
// Source: LearnerAjaxHandlers.php
use WeCoza\Core\Helpers\AjaxSecurity;

public function ajaxDeleteLearner(): void
{
    // Security layer
    AjaxSecurity::requireNonce('learners_nonce_action');
    AjaxSecurity::requireCapability('manage_learners');

    $id = AjaxSecurity::post('id', 'int');

    if (!$id) {
        AjaxSecurity::sendError('Invalid learner ID', 400);
        return;
    }

    if ($this->deleteLearner($id)) {
        AjaxSecurity::sendSuccess([], 'Learner deleted successfully');
    } else {
        AjaxSecurity::sendError('Failed to delete learner', 500);
    }
}
```

**Security checklist:**
- ✅ Nonce verification (CSRF protection)
- ✅ Capability check (authorization)
- ✅ Input sanitization via `AjaxSecurity::post()` with type
- ✅ Standardized responses (`sendSuccess`/`sendError`)

### Pattern 3: View Migration (wecoza_view() Function)
**What:** Replace `load_template()` calls with `wecoza_view()`, use `.view.php` extension
**When to use:** All template rendering
**Example:**
```php
// OLD (standalone plugin):
$this->load_template('agent-capture-form.php', $data, 'forms');

// NEW (wecoza-core):
return wecoza_view('agents/components/agent-capture-form', $data, true);
```

**Transformation rules:**
| Old Path | New Path |
|----------|----------|
| `templates/forms/file.php` | `views/agents/components/file.view.php` |
| `templates/display/file.php` | `views/agents/display/file.view.php` |
| `templates/partials/file.php` | `views/agents/components/file.view.php` |

**Key points:**
- No `.php` extension in view name (auto-appended)
- `$return = true` returns string, `false` echoes directly
- Views inherit all passed `$data` variables via extract

### Pattern 4: Unified Localization (Single Object, CamelCase)
**What:** One `wp_localize_script()` per module with camelCase keys
**When to use:** Every JS asset registration
**Example:**
```php
// Source: ClassController.php
wp_localize_script('wecoza-class-js', 'wecozaClass', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wecoza_class_nonce'),
    'siteAddresses' => ClassRepository::getSiteAddresses(),
    'debug' => defined('WP_DEBUG') && WP_DEBUG,
    'conflictCheckEnabled' => true
]);
```

**JavaScript access:**
```javascript
jQuery.ajax({
    url: wecozaClass.ajaxUrl,
    method: 'POST',
    data: {
        action: 'wecoza_class_action',
        nonce: wecozaClass.nonce
    },
    success: function(response) {
        // Access via response.data, NOT response directly
        console.log(response.data.message);
    }
});
```

**Critical bug fixes:**
- **Bug #3:** Source has 3 localization objects (`agents_nonce`, `wecoza_agents_ajax`, `wecoZaAgentsDelete`). Unify into single `wecozaAgents` object.
- **Bug #4:** All JS must access `response.data.*`, never `response.*` directly (WordPress standard).
- **Casing:** Use camelCase for all object keys (`ajaxUrl`, not `ajax_url`).

### Pattern 5: Conditional Asset Enqueuing
**What:** Only load JS/CSS when shortcode is present on page
**When to use:** All module assets
**Example:**
```php
// Source: ClassController.php
public function enqueueAssets(): void
{
    if (!$this->shouldEnqueueAssets()) {
        return;
    }

    wp_enqueue_script(
        'wecoza-class-js',
        WECOZA_CORE_URL . 'assets/js/classes/class-capture.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // ... localization here
}

private function shouldEnqueueAssets(): bool
{
    global $post;

    if (!$post) {
        return false;
    }

    $shortcodes = ['wecoza_capture_class', 'wecoza_display_classes', 'wecoza_display_single_class'];

    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}
```

### Pattern 6: Core Wiring (wecoza-core.php Initialization)
**What:** Instantiate controller in wecoza-core.php after autoloader
**When to use:** Final integration step
**Example:**
```php
// Source: wecoza-core.php (Classes module)
add_action('plugins_loaded', function () {
    // ... other modules ...

    // Initialize Agents Module
    if (class_exists(\WeCoza\Agents\Controllers\AgentsController::class)) {
        new \WeCoza\Agents\Controllers\AgentsController();
    }
}, 10);
```

**Key points:**
- No need to call `initialize()` method—`registerHooks()` runs in constructor
- Use priority 10 (after wecoza-core loads at priority 5)
- Check `class_exists()` before instantiation

### Anti-Patterns to Avoid
- **Manual nonce checks:** Use `AjaxSecurity::requireNonce()` instead of `check_ajax_referer()`
- **Direct `$_POST` access:** Use `AjaxSecurity::post()` or `BaseController->input()` for sanitization
- **Nopriv AJAX handlers:** NEVER register `wp_ajax_nopriv_*` hooks—entire WP environment requires login
- **Mixed localization objects:** One object per module, not one per JS file
- **Direct response access in JS:** Always use `response.data.*`, never `response.message` directly

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AJAX security | Custom nonce/capability checks | `AjaxSecurity::requireNonce()` + `requireCapability()` | Handles edge cases (CSRF, timing attacks, expired nonces) |
| Input sanitization | `sanitize_text_field()` per field | `AjaxSecurity::post($key, $type)` or `BaseController->input()` | Type-aware sanitization (int, email, json, date, etc.) |
| View rendering | `include` with manual path construction | `wecoza_view()` helper | Automatic path resolution, data extraction, error handling |
| JSON responses | Manual `wp_send_json_success()` calls | `AjaxSecurity::sendSuccess()` / `sendError()` | Standardized format, HTTP status codes, consistent structure |
| Asset conditional loading | Enqueue everywhere | `shouldEnqueueAssets()` pattern | Prevents loading 10KB+ JS on pages that don't need it |

**Key insight:** WordPress AJAX has numerous footguns (nonce expiry, capability checks across roles, JSON format inconsistency). BaseController and AjaxSecurity abstract these with battle-tested implementations from Learners/Classes modules.

## Common Pitfalls

### Pitfall 1: Nopriv AJAX Handlers for "Public" Data
**What goes wrong:** Developer registers `wp_ajax_nopriv_wecoza_agents_paginate` thinking pagination is "safe" for unauthenticated users.
**Why it happens:** Misunderstanding requirement "entire WP environment requires login"—no pages accessible without authentication.
**How to avoid:** NEVER register nopriv handlers. Source plugin has Bug #12 (nopriv handlers present). Remove both.
**Warning signs:** Seeing `add_action('wp_ajax_nopriv_*')` anywhere in Agents code.

**Correct pattern:**
```php
// WRONG (from source):
add_action('wp_ajax_nopriv_wecoza_agents_paginate', [...]);

// RIGHT:
add_action('wp_ajax_wecoza_agents_paginate', [...]);
// No nopriv registration at all
```

### Pitfall 2: Multiple Localization Objects
**What goes wrong:** Source plugin has 3 localization objects (`agents_nonce`, `wecoza_agents_ajax`, `wecoZaAgentsDelete`) with inconsistent casing. JS files can't find expected properties.
**Why it happens:** Organic growth without refactoring—each feature added its own localization.
**How to avoid:** Single unified object with camelCase keys. Example: `wecozaAgents` contains `ajaxUrl`, `nonce`, `deleteNonce`, `paginationNonce`.
**Warning signs:** JS console errors `Cannot read property 'ajax_url' of undefined` despite script being loaded.

**Correct pattern:**
```php
wp_localize_script('wecoza-agents-app', 'wecozaAgents', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wecoza_agents_nonce'),
    'deleteNonce' => wp_create_nonce('wecoza_delete_agent'),
    'paginationNonce' => wp_create_nonce('wecoza_agents_pagination'),
    'debug' => WP_DEBUG
]);
```

### Pitfall 3: Direct Response Access in JavaScript
**What goes wrong:** JS accesses `response.message` instead of `response.data.message`, causing undefined errors or missing data.
**Why it happens:** Confusion about `wp_send_json_success()` format—wraps data in `{success: true, data: {...}}` structure.
**How to avoid:** Always access `response.data.*` for success, `response.data.message` for error. AjaxSecurity enforces this structure.
**Warning signs:** AJAX works in PHP but JS can't access returned data despite 200 status.

**Correct pattern:**
```javascript
// WRONG:
success: function(response) {
    console.log(response.message); // undefined!
}

// RIGHT:
success: function(response) {
    console.log(response.data.message); // works
}
```

### Pitfall 4: AJAX Action Naming Inconsistency
**What goes wrong:** Source has inconsistent action naming—some use `wecoza_agents_*`, others use bare `delete_agent`. JS action doesn't match PHP hook.
**Why it happens:** Bug #10 in source—delete action not standardized.
**How to avoid:** All AJAX actions must have `wecoza_agents_` prefix. Example: `wecoza_agents_delete`, `wecoza_agents_paginate`.
**Warning signs:** AJAX request returns `-1` (WordPress "no handler found" response).

**Correct pattern:**
```php
// PHP registration:
add_action('wp_ajax_wecoza_agents_delete', [$this, 'handleDelete']);

// JS invocation:
data: {
    action: 'wecoza_agents_delete', // exact match
    nonce: wecozaAgents.deleteNonce
}
```

### Pitfall 5: Mixing BaseController and AjaxSecurity Methods
**What goes wrong:** Developer uses `AjaxSecurity::requireNonce()` in one handler, `$this->requireNonce()` in another, causing confusion.
**Why it happens:** Both BaseController and AjaxSecurity provide identical security methods—unclear which to use.
**How to avoid:** Pick one consistently per file. **Recommendation:** Use BaseController methods (`$this->requireNonce()`) in controllers, `AjaxSecurity::` in standalone AJAX handlers.
**Warning signs:** Mixed method calls in same class.

**Correct pattern:**
```php
// In AgentsController (extends BaseController):
public function ajaxDeleteAgent(): void
{
    $this->requireNonce('agents_nonce_action');
    $this->requireCapability('manage_options');
    $id = $this->input('id', 'int');
    // ...
}

// In AgentsAjaxHandlers (standalone):
public function handlePagination(): void
{
    AjaxSecurity::requireNonce('agents_nonce_action');
    AjaxSecurity::requireCapability('manage_options');
    $page = AjaxSecurity::post('page', 'int');
    // ...
}
```

### Pitfall 6: View Path Confusion
**What goes wrong:** Developer passes `views/agents/components/form` to `wecoza_view()` causing "template not found" error.
**Why it happens:** `wecoza_view()` expects relative path from `views/` directory, not full path.
**How to avoid:** Always use `agents/components/form` format, never `views/agents/...`.
**Warning signs:** "Template not found" errors despite file existing.

**Correct pattern:**
```php
// WRONG:
wecoza_view('views/agents/components/form', $data, true);
wecoza_view('/agents/components/form', $data, true);

// RIGHT:
wecoza_view('agents/components/form', $data, true);
// OR with explicit .view.php:
wecoza_view('agents/components/form.view', $data, true);
```

## Code Examples

Verified patterns from Learners/Classes modules:

### Controller Structure (Complete Example)
```php
<?php
// Source: LearnerController.php (adapted for Agents)
namespace WeCoza\Agents\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Agents\Models\AgentModel;
use WeCoza\Agents\Repositories\AgentRepository;

class AgentsController extends BaseController
{
    private ?AgentRepository $repository = null;

    protected function registerHooks(): void
    {
        // Register shortcodes
        add_action('init', [$this, 'registerShortcodes']);

        // Register AJAX handlers (authenticated only)
        add_action('wp_ajax_wecoza_agents_delete', [$this, 'ajaxDeleteAgent']);
        add_action('wp_ajax_wecoza_agents_paginate', [$this, 'ajaxPaginateAgents']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_capture_agents', [$this, 'renderCaptureForm']);
        add_shortcode('wecoza_display_agents', [$this, 'renderAgentsList']);
        add_shortcode('wecoza_single_agent', [$this, 'renderSingleAgent']);
    }

    private function getRepository(): AgentRepository
    {
        if ($this->repository === null) {
            $this->repository = new AgentRepository();
        }
        return $this->repository;
    }

    // AJAX handler example
    public function ajaxDeleteAgent(): void
    {
        $this->requireNonce('agents_nonce_action');
        $this->requireCapability('manage_options');

        $id = $this->input('id', 'int');

        if (!$id) {
            $this->sendError('Invalid agent ID', 400);
            return;
        }

        $agent = AgentModel::getById($id);

        if (!$agent) {
            $this->sendError('Agent not found', 404);
            return;
        }

        if ($agent->delete()) {
            $this->sendSuccess([], 'Agent deleted successfully');
        } else {
            $this->sendError('Failed to delete agent', 500);
        }
    }

    // Shortcode renderer example
    public function renderAgentsList(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 20,
            'show_pagination' => true
        ], $atts);

        $agents = $this->getRepository()->getAll((int) $atts['limit']);

        return $this->render('agents/display/agent-display-table', [
            'agents' => $agents,
            'show_pagination' => $atts['show_pagination'],
            'total_count' => count($agents)
        ], true);
    }
}
```

### AJAX Handler (Standalone File)
```php
<?php
// Source: LearnerAjaxHandlers.php (adapted for Agents)
namespace WeCoza\Agents\Ajax;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Agents\Controllers\AgentsController;

class AgentsAjaxHandlers
{
    private AgentsController $controller;

    public function __construct()
    {
        $this->controller = new AgentsController();
        $this->registerHandlers();
    }

    private function registerHandlers(): void
    {
        add_action('wp_ajax_wecoza_agents_fetch_dropdown_data', [$this, 'handleFetchDropdownData']);
    }

    public function handleFetchDropdownData(): void
    {
        AjaxSecurity::requireNonce('agents_nonce_action');
        // Note: No capability check—dropdown data is not PII

        try {
            $data = $this->controller->getDropdownData();

            AjaxSecurity::sendSuccess([
                'provinces' => $data['provinces'],
                'qualifications' => $data['qualifications'],
                'preferredAreas' => $data['preferred_areas']
            ]);
        } catch (\Exception $e) {
            AjaxSecurity::sendError($e->getMessage(), 500);
        }
    }
}

// Register handlers
add_action('init', function() {
    new AgentsAjaxHandlers();
}, 10);
```

### Asset Enqueuing with Localization
```php
<?php
// Source: ClassController.php (adapted for Agents)
public function enqueueAssets(): void
{
    if (!$this->shouldEnqueueAssets()) {
        return;
    }

    // Main application JS
    wp_enqueue_script(
        'wecoza-agents-app',
        WECOZA_CORE_URL . 'assets/js/agents/agents-app.js',
        ['jquery'],
        WECOZA_CORE_VERSION,
        true
    );

    // Unified localization
    wp_localize_script('wecoza-agents-app', 'wecozaAgents', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('agents_nonce_action'),
        'deleteNonce' => wp_create_nonce('wecoza_delete_agent'),
        'paginationNonce' => wp_create_nonce('wecoza_agents_pagination'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'urls' => [
            'displayAgents' => home_url('/app/all-agents'),
            'viewAgent' => home_url('/app/view-agent'),
            'updateAgent' => home_url('/app/update-agent')
        ]
    ]);
}

private function shouldEnqueueAssets(): bool
{
    global $post;

    if (!$post) {
        return false;
    }

    $shortcodes = [
        'wecoza_capture_agents',
        'wecoza_display_agents',
        'wecoza_single_agent'
    ];

    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}
```

### View Template (Component)
```php
<?php
// Source: views/agents/components/agent-capture-form.view.php
// Variables available: $agent, $mode, $errors

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Agents\Helpers\FormHelpers;
?>

<form id="agents-form" method="POST" class="needs-validation" novalidate>
    <?php wp_nonce_field('submit_agent_form', 'wecoza_agents_form_nonce'); ?>

    <?php if ($mode === 'edit' && !empty($agent['agent_id'])) : ?>
        <input type="hidden" name="editing_agent_id" value="<?php echo esc_attr($agent['agent_id']); ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <label for="first_name" class="form-label">
                First Name <span class="text-danger">*</span>
            </label>
            <input
                type="text"
                id="first_name"
                name="first_name"
                class="form-control <?php echo FormHelpers::get_error_class($errors, 'first_name'); ?>"
                value="<?php echo esc_attr(FormHelpers::get_field_value($agent, 'first_name')); ?>"
                required
            >
            <div class="invalid-feedback">Please provide the first name.</div>
            <?php FormHelpers::display_field_error($errors, 'first_name'); ?>
        </div>

        <!-- More fields... -->
    </div>

    <button type="submit" class="btn btn-primary">
        <?php echo $mode === 'edit' ? 'Update Agent' : 'Create Agent'; ?>
    </button>
</form>
```

### JavaScript AJAX Pattern
```javascript
// Source: agents-app.js (unified approach)
(function($) {
    'use strict';

    // Delete agent handler
    $(document).on('click', '.delete-agent-btn', function(e) {
        e.preventDefault();

        const agentId = $(this).data('id');
        const agentName = $(this).data('name');

        if (!confirm(`Delete agent ${agentName}? This cannot be undone.`)) {
            return;
        }

        $.ajax({
            url: wecozaAgents.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wecoza_agents_delete',
                nonce: wecozaAgents.deleteNonce,
                id: agentId
            },
            success: function(response) {
                // Access via response.data, not response directly
                if (response.success) {
                    console.log(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Delete failed');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('Failed to delete agent. Please try again.');
            }
        });
    });

})(jQuery);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Standalone plugin files | Integrated modules in wecoza-core | Phase 27 (2026-02) | Unified architecture, shared utilities |
| Manual `load_template()` | `wecoza_view()` helper | Core 1.0.0 | Automatic path resolution, data extraction |
| Direct AJAX handlers | BaseController + AjaxSecurity | Core 1.0.0 | Standardized security, reduced boilerplate |
| Multiple localization objects | Single unified object | Phase 27 fix | Consistent JS access, reduced confusion |
| Mixed snake_case/camelCase | Enforced camelCase | Phase 27 fix | JavaScript conventions, IDE autocomplete |
| Nopriv AJAX handlers | Authenticated only | Core 1.0.0 | Aligns with "entire WP requires login" |

**Deprecated/outdated:**
- Standalone plugin architecture: Now integrated into wecoza-core modules
- Manual template loading: Replaced by `wecoza_view()` abstraction
- Direct `wp_send_json_*()` calls: Use `AjaxSecurity::sendSuccess()`/`sendError()`
- Multiple localization objects per plugin: Unified single object pattern
- snake_case in JS localization: Enforce camelCase for all object keys

## Open Questions

1. **Asset Versioning Strategy**
   - What we know: Source uses `rand(10,100)` for cache busting (line 25: `$rand = rand(10,100);`)
   - What's unclear: Production caching strategy—use WECOZA_CORE_VERSION or file modification time?
   - Recommendation: Use `WECOZA_CORE_VERSION` in production, `filemtime()` during development (already pattern in Classes module).

2. **FormHelpers Integration**
   - What we know: AgentModel uses FormHelpers (from Phase 26 D26-02-01), views call `FormHelpers::get_field_value()`
   - What's unclear: Does FormHelpers need registration in wecoza-core.php or is it autoloaded?
   - Recommendation: Verify FormHelpers is in `src/Agents/Helpers/` and autoloaded via PSR-4 (namespace `WeCoza\Agents\Helpers\`).

3. **AJAX Handler Split Strategy**
   - What we know: Learners has both LearnerController AJAX methods AND LearnerAjaxHandlers file
   - What's unclear: When to use controller methods vs standalone AJAX handlers file?
   - Recommendation: Controller methods for model operations (CRUD), standalone file for legacy actions or complex multi-step operations.

## Sources

### Primary (HIGH confidence)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Controllers/LearnerController.php` - Controller pattern reference
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Learners/Ajax/LearnerAjaxHandlers.php` - AJAX handler pattern
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Classes/Controllers/ClassController.php` - Asset enqueuing pattern
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/core/Abstract/BaseController.php` - Base class capabilities
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/core/Helpers/AjaxSecurity.php` - Security helper methods
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/wecoza-core.php` (lines 150-180) - Module initialization pattern

### Secondary (MEDIUM confidence)
- `.integrate/wecoza-agents-plugin/*` - Source plugin structure (contains bugs to fix)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/assets/js/learners/learners-app.js` - JS patterns reference

### Tertiary (LOW confidence)
- None—all research based on existing codebase patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All utilities exist and are proven in Learners/Classes modules
- Architecture: HIGH - Direct templates from 2 production modules
- Pitfalls: HIGH - Documented bugs in source plugin provide concrete examples
- Open questions: MEDIUM - FormHelpers and asset versioning need verification during implementation

**Research date:** 2026-02-12
**Valid until:** 2026-03-12 (30 days—patterns are stable, unlikely to change)

**Source localization issues found:**
- **Bug #3:** 3 localization objects (`agents_nonce`, `wecoza_agents_ajax`, `wecoZaAgentsDelete`)
- **Bug #4:** JS accesses `response.*` directly instead of `response.data.*`
- **Bug #10:** Inconsistent AJAX action naming (some lack `wecoza_agents_` prefix)
- **Bug #12:** Nopriv handlers present (lines in DisplayAgentShortcode.php)
- **Casing:** Mixed snake_case and camelCase in localization keys

**Files requiring migration:**
- Controllers: 1 file (AgentsController.php)
- AJAX: 1 file (AgentsAjaxHandlers.php)
- Views: 6 files (form, fields, table, table-rows, pagination, single display)
- JS: 5 files (app, validation, pagination, search, delete)
- Core wiring: 1 section in wecoza-core.php
