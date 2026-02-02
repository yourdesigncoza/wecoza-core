# Phase 3: Bootstrap Integration - Research

**Researched:** 2026-02-02
**Domain:** WordPress plugin modular initialization
**Confidence:** HIGH

## Summary

Bootstrap integration for WordPress plugin modules follows well-established patterns in the WordPress ecosystem. The wecoza-core plugin already demonstrates a mature initialization pattern with the Learners and Classes modules, which serves as the authoritative reference for Events module integration.

The standard approach uses three integration points: (1) PSR-4 autoloader registration in the main plugin file, (2) module initialization via static `register()` methods called from the `plugins_loaded` hook at priority 5, and (3) capability management in activation/deactivation hooks. The Events module has seven components with static `register()` methods that need to be called during initialization.

The existing wecoza-core bootstrap (wecoza-core.php) already uses the correct hook priorities and patterns. The Events module migration requires minimal changes to the existing bootstrap: add autoloader mapping, call component `register()` methods, and optionally add event-specific capabilities to activation/deactivation hooks.

**Primary recommendation:** Follow the established wecoza-core module initialization pattern—add Events namespace to autoloader, call component `register()` methods from `plugins_loaded` hook at priority 5, manage capabilities in activation/deactivation hooks.

## Standard Stack

The established libraries/tools for WordPress plugin module integration:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Core | 6.0+ | Hook system (plugins_loaded, init, activation/deactivation) | Official WordPress plugin API |
| PHP SPL | 8.0+ | spl_autoload_register() for PSR-4 autoloading | Native PHP autoloading mechanism |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Composer | 2.x | Optional PSR-4 autoloader (wecoza-core uses spl_autoload_register) | Large plugins with external dependencies |
| WP-CLI | Latest | CLI command registration via WP_CLI::add_command() | Administrative commands |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Manual require_once chain | PSR-4 autoloader | Manual requires don't scale; error-prone; autoloader is WordPress best practice |
| init hook (priority 10) | plugins_loaded hook (priority 5) | plugins_loaded fires earlier, ensures core loads before dependent plugins |
| Separate plugin activation | Integrated module initialization | Separate plugins require independent activation; unified plugin is simpler for users |

**Installation:**
No external dependencies required. WordPress core and PHP 8.0+ provide all necessary functionality.

## Architecture Patterns

### Recommended Project Structure
```
wecoza-core.php              # Main plugin file
├── PSR-4 autoloader         # Maps WeCoza\Events\* → src/Events/
├── plugins_loaded hook      # Priority 5, initializes modules
├── activation hook          # Adds capabilities, flushes rewrite rules
└── deactivation hook        # Removes capabilities, flushes rewrite rules

src/Events/
├── Controllers/             # AJAX handlers with register() methods
├── Shortcodes/             # Shortcode classes with register() methods
├── Admin/                  # Admin pages with register() methods
├── CLI/                    # WP-CLI commands with register() methods
├── Models/                 # Domain objects (no registration needed)
├── Repositories/           # Data access (no registration needed)
├── Services/               # Business logic (no registration needed)
├── Support/                # Utilities (no registration needed)
└── Views/                  # Templates (no registration needed)
```

### Pattern 1: PSR-4 Autoloader Registration
**What:** Map namespace prefix to directory path using spl_autoload_register()
**When to use:** Main plugin file initialization, before any class usage
**Example:**
```php
// Source: wecoza-core/wecoza-core.php (lines 46-73)
spl_autoload_register(function (string $class) {
    $namespaces = [
        'WeCoza\\Core\\' => WECOZA_CORE_PATH . 'core/',
        'WeCoza\\Learners\\' => WECOZA_CORE_PATH . 'src/Learners/',
        'WeCoza\\Classes\\' => WECOZA_CORE_PATH . 'src/Classes/',
        // ADD: 'WeCoza\\Events\\' => WECOZA_CORE_PATH . 'src/Events/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        $prefixLength = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLength) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $prefixLength);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
```

### Pattern 2: Static register() Method
**What:** Components with hooks/registrations expose a static `register()` method
**When to use:** Controllers, Shortcodes, Admin pages, CLI commands
**Example:**
```php
// Source: src/Events/Shortcodes/EventTasksShortcode.php (lines 50-54)
final class EventTasksShortcode
{
    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self();
        add_shortcode('wecoza_event_tasks', [$instance, 'render']);
    }
}

// Source: src/Events/Controllers/TaskController.php (lines 40-45)
final class TaskController
{
    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self();
        add_action('wp_ajax_wecoza_events_task_update', [$instance, 'handleUpdate']);
        add_action('wp_ajax_nopriv_wecoza_events_task_update', [$instance, 'handleUnauthorized']);
    }
}
```

### Pattern 3: Module Initialization from plugins_loaded Hook
**What:** Call component register() methods from plugins_loaded hook at priority 5
**When to use:** Module setup that must run before dependent plugins (priority 10+)
**Example:**
```php
// Source: wecoza-core.php (lines 135-227)
add_action('plugins_loaded', function () {
    // Load configuration
    $config = wecoza_config('app');

    // Initialize text domain
    load_plugin_textdomain('wecoza-core', false, dirname(WECOZA_CORE_BASENAME) . '/languages');

    // Fire wecoza_core_loaded hook for dependent plugins
    do_action('wecoza_core_loaded');

    // Initialize modules
    if (class_exists(\WeCoza\Learners\Controllers\LearnerController::class)) {
        new \WeCoza\Learners\Controllers\LearnerController();
    }

    // For Events module, call static register() methods:
    // WeCoza\Events\Shortcodes\EventTasksShortcode::register();
    // WeCoza\Events\Shortcodes\MaterialTrackingShortcode::register();
    // WeCoza\Events\Shortcodes\AISummaryShortcode::register();
    // WeCoza\Events\Controllers\TaskController::register();
    // WeCoza\Events\Controllers\MaterialTrackingController::register();
    // WeCoza\Events\Admin\SettingsPage::register();
    // WeCoza\Events\CLI\AISummaryStatusCommand::register();
}, 5);
```

### Pattern 4: Activation/Deactivation Capability Management
**What:** Add/remove custom capabilities on plugin activation/deactivation
**When to use:** Capabilities that control access to module features
**Example:**
```php
// Source: wecoza-core.php (lines 235-284, 286-302)
register_activation_hook(__FILE__, function () {
    // Check requirements (PHP version, extensions)
    // Set default options
    // Flush rewrite rules

    // Add custom capabilities
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_learners');
        // ADD: $admin->add_cap('manage_events'); (if needed)
    }

    do_action('wecoza_core_activated');
});

register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules

    // Remove custom capabilities
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('manage_learners');
        // ADD: $admin->remove_cap('manage_events'); (if needed)
    }

    do_action('wecoza_core_deactivated');
});
```

### Anti-Patterns to Avoid
- **Instantiating in global scope:** Create instances inside hooks, not at file load time (prevents execution order issues)
- **Using init hook for early initialization:** plugins_loaded (priority 5) ensures wecoza-core loads before dependent plugins; init fires too late
- **Separate plugin activation:** Users expect unified wecoza-core plugin, not separate activations per module
- **Manual require_once chains:** PSR-4 autoloader handles dependencies automatically; manual requires are fragile
- **Mixing constructor-based and static registration:** Existing modules use both patterns (LearnerController constructor registers hooks, Events uses static register()). Maintain consistency within each module.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Class autoloading | Custom __autoload or require chains | PSR-4 via spl_autoload_register() | WordPress and PHP ecosystem standard; handles namespaces correctly |
| Hook priority ordering | Trial-and-error priority values | WordPress load order documentation + existing patterns | plugins_loaded (5) → init (10) → wp_loaded is established sequence |
| WP-CLI command registration | Custom CLI interface | WP_CLI::add_command() in conditional (defined('WP_CLI') && WP_CLI) | Official WP-CLI API; automatic help generation; argument parsing |
| Activation/deactivation logic | Custom database flags | register_activation_hook() and register_deactivation_hook() | WordPress core API; fired at correct lifecycle points |
| Asset enqueuing | Direct <script> tags or manual wp_head hooks | wp_enqueue_scripts action with wp_enqueue_script/style | Handles dependencies, versioning, conditional loading |

**Key insight:** WordPress provides a comprehensive plugin API that handles initialization, hooks, capabilities, and asset management. Hand-rolling these systems creates maintenance burden and compatibility issues. The wecoza-core codebase already demonstrates correct usage patterns—follow them for consistency.

## Common Pitfalls

### Pitfall 1: Incorrect Hook Priority
**What goes wrong:** Using default priority (10) for plugins_loaded causes dependent plugins to load before wecoza-core finishes initialization
**Why it happens:** WordPress default priority is 10; developers often omit the priority parameter
**How to avoid:** Explicitly specify priority 5 for wecoza-core's plugins_loaded hook: `add_action('plugins_loaded', $callback, 5)`
**Warning signs:** Dependent plugins report "Class not found" errors; wecoza_core_loaded hook fires after dependent plugins expect it

### Pitfall 2: Autoloader Namespace Mismatch
**What goes wrong:** PSR-4 autoloader fails to load classes silently, resulting in "Class not found" fatal errors
**Why it happens:** Namespace prefix in autoloader doesn't match actual class namespaces, or directory structure doesn't match namespace hierarchy
**How to avoid:** Verify namespace mapping: `'WeCoza\\Events\\'` (note trailing backslash and double-escaping) → `src/Events/` (note trailing slash)
**Warning signs:** Fatal error "Class 'WeCoza\Events\...' not found"; classes load when manually required but not via autoloader

### Pitfall 3: Premature Class Instantiation
**What goes wrong:** Classes instantiated before autoloader is registered cause fatal errors; classes instantiated before WordPress is ready cause unexpected behavior
**Why it happens:** Code execution at file load time (global scope) runs before plugin initialization hooks
**How to avoid:** All class instantiation and static method calls must occur inside plugins_loaded or later hooks
**Warning signs:** Fatal errors during plugin file load; undefined constants (ABSPATH, WP_DEBUG); missing WordPress functions

### Pitfall 4: Forgetting WP-CLI Conditional
**What goes wrong:** WP_CLI class references in non-CLI context cause fatal errors
**Why it happens:** WP_CLI class only exists when WP-CLI is running; web requests don't load WP-CLI
**How to avoid:** Wrap all WP_CLI usage in conditional: `if (defined('WP_CLI') && WP_CLI) { WP_CLI::add_command(...); }`
**Warning signs:** Fatal error "Class 'WP_CLI' not found" on web requests; CLI commands work but web pages crash

### Pitfall 5: Confusing Deactivation with Uninstall
**What goes wrong:** Deactivation hook deletes permanent data (options, database tables), preventing clean reactivation
**Why it happens:** Developers assume deactivation means "remove everything"
**How to avoid:** Deactivation should only remove temporary data (caches, transients, capabilities). Use uninstall.php for permanent deletion.
**Warning signs:** Plugin settings lost after deactivation/reactivation; database tables dropped on deactivation; user complaints about lost configuration

### Pitfall 6: Asset Enqueuing Without Conditional Loading
**What goes wrong:** JavaScript/CSS files loaded on every page, even when shortcodes/features aren't used
**Why it happens:** wp_enqueue_scripts callback unconditionally enqueues assets
**How to avoid:** Check context before enqueuing: Classes module uses `shouldEnqueueAssets()` method. Events module has no separate assets (shortcodes include inline JS), so this isn't applicable.
**Warning signs:** Slow page loads; console errors about missing elements; assets loaded on unrelated pages

## Code Examples

Verified patterns from the wecoza-core codebase:

### Complete Module Bootstrap Sequence
```php
// Source: wecoza-core.php (consolidated from multiple sections)

// 1. PSR-4 Autoloader (lines 46-73)
spl_autoload_register(function (string $class) {
    $namespaces = [
        'WeCoza\\Core\\' => WECOZA_CORE_PATH . 'core/',
        'WeCoza\\Learners\\' => WECOZA_CORE_PATH . 'src/Learners/',
        'WeCoza\\Classes\\' => WECOZA_CORE_PATH . 'src/Classes/',
        'WeCoza\\Events\\' => WECOZA_CORE_PATH . 'src/Events/', // ADD THIS
    ];
    foreach ($namespaces as $prefix => $baseDir) {
        $prefixLength = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLength) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $prefixLength);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 2. Module Initialization (lines 135-227)
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wecoza-core', false, dirname(WECOZA_CORE_BASENAME) . '/languages');
    do_action('wecoza_core_loaded');

    // Initialize Events Module
    if (class_exists(\WeCoza\Events\Shortcodes\EventTasksShortcode::class)) {
        \WeCoza\Events\Shortcodes\EventTasksShortcode::register();
    }
    if (class_exists(\WeCoza\Events\Shortcodes\MaterialTrackingShortcode::class)) {
        \WeCoza\Events\Shortcodes\MaterialTrackingShortcode::register();
    }
    if (class_exists(\WeCoza\Events\Shortcodes\AISummaryShortcode::class)) {
        \WeCoza\Events\Shortcodes\AISummaryShortcode::register();
    }
    if (class_exists(\WeCoza\Events\Controllers\TaskController::class)) {
        \WeCoza\Events\Controllers\TaskController::register();
    }
    if (class_exists(\WeCoza\Events\Controllers\MaterialTrackingController::class)) {
        \WeCoza\Events\Controllers\MaterialTrackingController::register();
    }
    if (class_exists(\WeCoza\Events\Admin\SettingsPage::class)) {
        \WeCoza\Events\Admin\SettingsPage::register();
    }
    if (class_exists(\WeCoza\Events\CLI\AISummaryStatusCommand::class)) {
        \WeCoza\Events\CLI\AISummaryStatusCommand::register();
    }
}, 5); // Priority 5: before dependent plugins

// 3. WP-CLI Commands (lines 330-356)
if (defined('WP_CLI') && WP_CLI) {
    // Events CLI already registered via AISummaryStatusCommand::register()
    // No additional WP_CLI setup needed
}
```

### Events Component Registration Pattern
```php
// Source: src/Events/Shortcodes/EventTasksShortcode.php (lines 28-54)
final class EventTasksShortcode
{
    private ClassTaskService $service;
    private ClassTaskPresenter $presenter;
    private TemplateRenderer $renderer;
    private WordPressRequest $request;

    public function __construct(
        ?ClassTaskService $service = null,
        ?ClassTaskPresenter $presenter = null,
        ?TemplateRenderer $renderer = null,
        ?WordPressRequest $request = null
    ) {
        $this->service = $service ?? new ClassTaskService();
        $this->presenter = $presenter ?? new ClassTaskPresenter();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->request = $request ?? new WordPressRequest();
    }

    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self();
        add_shortcode('wecoza_event_tasks', [$instance, 'render']);
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        // Shortcode implementation
    }
}
```

### Events AJAX Controller Pattern
```php
// Source: src/Events/Controllers/TaskController.php (lines 20-90)
final class TaskController
{
    private TaskManager $manager;
    private ClassTaskPresenter $presenter;
    private WordPressRequest $request;
    private JsonResponder $responder;

    public function __construct(
        ?TaskManager $manager = null,
        ?ClassTaskPresenter $presenter = null,
        ?WordPressRequest $request = null,
        ?JsonResponder $responder = null
    )
    {
        $this->manager = $manager ?? new TaskManager();
        $this->presenter = $presenter ?? new ClassTaskPresenter();
        $this->request = $request ?? new WordPressRequest();
        $this->responder = $responder ?? new JsonResponder();
    }

    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self();
        add_action('wp_ajax_wecoza_events_task_update', [$instance, 'handleUpdate']);
        add_action('wp_ajax_nopriv_wecoza_events_task_update', [$instance, 'handleUnauthorized']);
    }

    public function handleUnauthorized(): void
    {
        $this->responder->error(__('Authentication required.', 'wecoza-events'), 401);
    }

    public function handleUpdate(): void
    {
        check_ajax_referer('wecoza_events_tasks', 'nonce');
        // AJAX handler implementation
    }
}
```

### Events WP-CLI Command Pattern
```php
// Source: src/Events/CLI/AISummaryStatusCommand.php (lines 21-32)
final class AISummaryStatusCommand extends WP_CLI_Command
{
    public static function register(): void
    {
        WP_CLI::add_command('wecoza ai-summary status', new self());
    }

    /**
     * Display AI summary metrics for the last N hours.
     *
     * ## OPTIONS
     *
     * [--hours=<int>]
     * : Time window in hours (default 24).
     */
    public function status(array $args, array $assocArgs): void
    {
        // Command implementation
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate wecoza-events-plugin | Integrated Events module in wecoza-core | Phase 3 (current) | Single plugin activation; unified codebase; shared infrastructure |
| Manual require_once chains | PSR-4 autoloader | WordPress 4.6+ (2016) | Automatic class loading; cleaner code; easier maintenance |
| Global functions for hooks | Static register() methods on classes | Modern PHP (5.3+, 2009) | Better encapsulation; testability; no global namespace pollution |
| Constructor-based hook registration | Static register() methods | Varies by module | Events uses static pattern; Learners uses constructor pattern; both work |
| Procedural shortcode callbacks | Class-based shortcode handlers | Modern WordPress (3.6+, 2013) | Dependency injection; state management; testability |

**Deprecated/outdated:**
- **Global $wpdb for non-WordPress tables:** wecoza-core uses PostgresConnection singleton for PostgreSQL database access
- **Direct PDO injection in constructors:** Events repositories extend BaseRepository (uses singleton pattern)
- **Separate plugin activation for each module:** Unified wecoza-core plugin handles all modules

## Open Questions

Things that couldn't be fully resolved:

1. **Should Events module have dedicated capabilities?**
   - What we know: Learners module uses `manage_learners` capability for PII access
   - What's unclear: Whether Events features require capability restrictions, or if default WordPress capabilities (manage_options, edit_posts) suffice
   - Recommendation: Review Events features during planning. If no PII or sensitive operations, omit custom capabilities. Can always add later.

2. **Should Events have separate text domain?**
   - What we know: Events classes use 'wecoza-events' text domain; wecoza-core uses 'wecoza-core' text domain
   - What's unclear: Whether to migrate strings to unified 'wecoza-core' domain or maintain 'wecoza-events'
   - Recommendation: Maintain 'wecoza-events' domain for now (easier rollback if issues arise). Can unify text domains in future cleanup phase.

3. **Do Events shortcodes need dedicated pages like Classes module?**
   - What we know: Classes module creates required pages on initialization (src/Classes/Controllers/ClassController.php lines 48-103)
   - What's unclear: Whether Events shortcodes are meant to be embedded in existing pages or need dedicated routes
   - Recommendation: Defer to Phase 4 (Shortcode/AJAX Registration). Check events plugin documentation and existing usage patterns.

## Sources

### Primary (HIGH confidence)
- wecoza-core/wecoza-core.php - Existing module initialization patterns (lines 46-356)
- wecoza-core/src/Events/ - Events module structure and component patterns (migrated in Phase 1-2)
- [WordPress Plugins: Activation/Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) - Official WordPress documentation
- [WordPress plugins_loaded Hook Reference](https://developer.wordpress.org/reference/hooks/plugins_loaded/) - Official WordPress hook documentation

### Secondary (MEDIUM confidence)
- [WordPress Plugin Best Practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/) - Official WordPress developer guidelines
- [WordPress Hooks Bootcamp](https://kinsta.com/blog/wordpress-hooks/) - Community resource on action/filter usage
- [WordPress Plugin Load Order](https://docs.wpvip.com/plugins/load-order/) - WordPress VIP documentation on hook priorities

### Tertiary (LOW confidence)
None - all findings verified against official WordPress documentation or wecoza-core codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress core APIs are stable and well-documented; wecoza-core demonstrates working patterns
- Architecture: HIGH - Existing Learners and Classes modules provide authoritative reference implementation
- Pitfalls: HIGH - Based on official WordPress documentation and observed patterns in wecoza-core codebase

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - WordPress plugin API is stable)
