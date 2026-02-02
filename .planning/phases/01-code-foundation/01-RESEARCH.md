# Phase 1: Code Foundation - Research

**Researched:** 2026-02-02
**Domain:** PHP namespace migration and PSR-4 autoloading
**Confidence:** HIGH

## Summary

Phase 1 involves migrating the events module from the standalone wecoza-events-plugin (~7,700 lines, 33+ PHP files) into wecoza-core's modular architecture. The primary technical challenge is converting the namespace from `WeCozaEvents\*` to `WeCoza\Events\*`, reorganizing from `includes/*` to `src/Events/*`, replacing the events plugin's custom database connection class with wecoza-core's PostgresConnection singleton, and ensuring Composer's PSR-4 autoloader is properly configured.

This is a well-established pattern in PHP modernization. The target architecture (PSR-4, Repository pattern, namespace-based organization) already exists in wecoza-core's Learners and Classes modules, providing concrete examples to follow. The main risks are namespace references in templates/views, static calls to the old database connection class, and ensuring the autoloader is regenerated after file moves.

**Primary recommendation:** Follow the existing wecoza-core patterns exactly (Learners/Classes modules), migrate all code at once (not layer-by-layer), use direct substitution for database connection (no wrapper needed), and regenerate autoloader after every structural change.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Composer | 2.x | PSR-4 autoloading | Industry standard for PHP dependency management and autoloading |
| PHP | 8.0+ | Language runtime | Required by wecoza-core, supports typed properties and match expressions |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| composer dump-autoload | built-in | Regenerate classmap | After adding/moving/renaming any class files |
| composer dump-autoload -o | built-in | Optimized autoloader | Production deployments (generates authoritative classmap) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Composer PSR-4 | Custom autoloader | PSR-4 is industry standard, custom autoloaders are unmaintainable |
| Direct migration | Gradual/layer-by-layer | Direct migration is cleaner for this size (~7,700 lines), avoids dual-namespace complexity |
| Wrapper for DB connection | Direct substitution | Both use PostgresConnection singleton pattern, wrapper adds unnecessary indirection |

**Installation:**
```bash
# Already configured in wecoza-core's composer.json
# After migration, regenerate autoloader:
composer dump-autoload
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/
├── Controllers/           # AJAX handlers, page controllers
├── Models/                # Data models (Task, ClassChange, MaterialTracking)
├── Repositories/          # Database access layer (extends BaseRepository)
├── Services/              # Business logic (AISummaryService, TaskTemplateRegistry)
├── Shortcodes/            # WordPress shortcode handlers
├── CLI/                   # WP-CLI commands
├── Support/               # Utilities (Container, FieldMapper, OpenAIConfig)
└── Views/                 # NOT in src/Events/ - goes to views/events/
    └── Presenters/        # View-specific formatting classes
```

**Critical:** Views go in `views/events/` (top-level), NOT `src/Events/Views/`. This matches existing wecoza-core pattern where views/ is separate from src/.

### Pattern 1: Namespace Migration (Direct Replacement)
**What:** Change all `namespace WeCozaEvents\*` to `namespace WeCoza\Events\*` and update all `use` statements accordingly.
**When to use:** For every PHP file in the migration.
**Example:**
```php
// BEFORE (events plugin)
namespace WeCozaEvents\Models;
use WeCozaEvents\Database\Connection;

// AFTER (wecoza-core)
namespace WeCoza\Events\Models;
use WeCoza\Core\Database\PostgresConnection;
```

### Pattern 2: Repository Database Connection Replacement
**What:** Replace `Connection::getPdo()` and `Connection::getSchema()` with `PostgresConnection::getInstance()` pattern.
**When to use:** In all Repository classes that currently use the events plugin's Database\Connection class.
**Example:**
```php
// BEFORE (events plugin pattern)
private PDO $pdo;
private string $schema;

public function __construct(?PDO $pdo = null, ?string $schema = null)
{
    $this->pdo = $pdo ?? Connection::getPdo();
    $this->schema = $schema ?? Connection::getSchema();
}

// AFTER (wecoza-core pattern - following BaseRepository)
protected PostgresConnection $db;

public function __construct()
{
    $this->db = PostgresConnection::getInstance();
}

// Then use $this->db->query() or $this->db->getPdo() for raw PDO access
```

### Pattern 3: PSR-4 Composer Configuration
**What:** Add namespace mapping to composer.json autoload section.
**When to use:** Immediately after creating src/Events/ structure.
**Example:**
```json
{
    "autoload": {
        "psr-4": {
            "WeCoza\\Core\\": "core/",
            "WeCoza\\Learners\\": "src/Learners/",
            "WeCoza\\Classes\\": "src/Classes/",
            "WeCoza\\Events\\": "src/Events/"
        }
    }
}
```

### Pattern 4: Extending BaseRepository
**What:** Make Repository classes extend WeCoza\Core\Abstract\BaseRepository and implement column whitelisting methods.
**When to use:** For ClassTaskRepository, ClassChangeLogRepository, MaterialTrackingRepository.
**Example:**
```php
// Source: wecoza-core/src/Learners/Repositories/LearnerRepository.php
namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

class ClassTaskRepository extends BaseRepository
{
    protected static string $table = 'class_change_logs';
    protected static string $primaryKey = 'log_id';

    // Security: Whitelist columns allowed in queries
    protected function getAllowedOrderColumns(): array
    {
        return ['log_id', 'class_id', 'changed_at', 'operation'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['log_id', 'class_id', 'operation'];
    }

    // Custom queries that need more than BaseRepository provides
    public function fetchClasses(int $limit, string $sortDirection): array
    {
        $sql = "SELECT * FROM classes ORDER BY original_start_date $sortDirection LIMIT :limit";
        $stmt = $this->db->query($sql, ['limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

### Anti-Patterns to Avoid
- **Layer-by-layer migration:** Don't migrate Controllers first, then Models, etc. Migrate all at once to avoid dual-namespace complexity and broken cross-references.
- **Custom autoloader:** Don't create a custom autoloader. Use Composer's PSR-4.
- **Forgetting dump-autoload:** After moving/renaming files, always run `composer dump-autoload` or you'll get "class not found" errors.
- **Hardcoded paths:** Don't use `__DIR__` or hardcoded paths to include files. Let PSR-4 autoloader handle it.
- **Database connection wrapper:** Don't create a wrapper or adapter for the old Connection class. Do direct substitution.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PSR-4 autoloading | Custom `__autoload()` or manual requires | Composer's PSR-4 autoloader | Industry standard, handles edge cases (case sensitivity, nested namespaces), optimized for production |
| Database connection | Keep old Connection class or create wrapper | PostgresConnection singleton (already exists) | Shared connection pool, lazy loading, transaction support, SSL mode for managed databases |
| Repository base class | Copy-paste repository methods | Extend BaseRepository | Column whitelisting security, pagination, transaction helpers already implemented |
| View rendering | Manual `include` statements | `wecoza_view()` and `wecoza_component()` helpers | Consistent path resolution, data scoping, return vs echo support |
| Input sanitization | Manual validation | `wecoza_sanitize_value()` and AjaxSecurity class | Type-specific sanitization (email, int, json, date), CSRF protection |

**Key insight:** wecoza-core already has mature infrastructure (BaseRepository, PostgresConnection, view helpers, security utilities). The migration is adapting events code to this existing infrastructure, not building new infrastructure.

## Common Pitfalls

### Pitfall 1: Forgotten namespace updates in templates/views
**What goes wrong:** PHP templates in Views/ may have `<?php use WeCozaEvents\Models\Task; ?>` that break after namespace change.
**Why it happens:** Templates aren't caught by simple find-replace because they mix PHP and HTML.
**How to avoid:**
1. Search for ALL occurrences: `grep -r "WeCozaEvents" --include="*.php"` (not just .php in src/)
2. Check Views/ and Presenters/ directories specifically
3. Test-render each shortcode after migration to catch view errors early
**Warning signs:** "Class not found" errors from template rendering, shortcodes showing blank output

### Pitfall 2: Autoloader not regenerated after file moves
**What goes wrong:** Classes moved to new namespace throw "Class not found" errors even though files exist.
**Why it happens:** Composer's classmap is cached. It doesn't automatically detect file moves.
**How to avoid:**
1. Run `composer dump-autoload` immediately after moving/renaming files
2. Add to migration checklist: "Regenerate autoloader" after each structural change
3. For production: use `composer dump-autoload -o` (optimized/authoritative)
**Warning signs:** "Class 'WeCoza\Events\Models\Task' not found" when class file exists in correct location

### Pitfall 3: Database connection constructor signature mismatch
**What goes wrong:** Repository constructors accepting `?PDO $pdo` and `?string $schema` parameters break when switching to PostgresConnection singleton.
**Why it happens:** Events plugin's Connection class uses static methods, wecoza-core uses singleton instance pattern.
**How to avoid:**
1. Replace constructor completely with BaseRepository pattern (no parameters)
2. Change `$this->pdo->prepare()` to `$this->db->getPdo()->prepare()` or `$this->db->query()`
3. Remove schema parameter - BaseRepository doesn't use it (tables are in public schema by default)
**Warning signs:** Type errors on Repository construction, PDO method calls failing

### Pitfall 4: Schema-qualified table names
**What goes wrong:** Events plugin may use `{$schema}.classes` in queries, but wecoza-core doesn't use schema qualification (assumes 'public').
**Why it happens:** Events plugin's Connection class exposes `getSchema()` method, allowing schema-aware queries.
**How to avoid:**
1. Remove schema qualification from SQL queries: `SELECT * FROM {$schema}.classes` becomes `SELECT * FROM classes`
2. Verify all tables are in 'public' schema (default for PostgreSQL)
3. If multi-schema is needed, handle it in PostgresConnection, not individual repositories
**Warning signs:** SQL errors about missing schema, queries failing that worked in events plugin

### Pitfall 5: Views location confusion
**What goes wrong:** Putting views in `src/Events/Views/` instead of top-level `views/events/`.
**Why it happens:** Events plugin uses `includes/Views/`, which seems parallel to `src/Events/`.
**How to avoid:**
1. Check existing modules: Learners views are in `views/learners/`, NOT `src/Learners/Views/`
2. Views are templates (not autoloaded classes), so they live outside src/
3. Use `wecoza_view('events/dashboard', $data)` not direct includes
**Warning signs:** View templates not found, autoloader errors for View files

## Code Examples

Verified patterns from existing wecoza-core codebase:

### Database Query in Repository
```php
// Source: wecoza-core/src/Learners/Repositories/LearnerRepository.php (lines 142-158)
namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;
use PDO;
use Exception;

class ClassTaskRepository extends BaseRepository
{
    protected static string $table = 'class_change_logs';
    protected static string $primaryKey = 'log_id';

    public function findTaskById(int $id): ?array
    {
        $sql = "SELECT * FROM class_change_logs WHERE log_id = :id";

        try {
            $stmt = $this->db->query($sql, ['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log("WeCoza Events: ClassTaskRepository findTaskById error: " . $e->getMessage());
            return null;
        }
    }
}
```

### Column Whitelisting for Security
```php
// Source: wecoza-core/core/Abstract/BaseRepository.php (lines 59-101)
namespace WeCoza\Events\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

class MaterialTrackingRepository extends BaseRepository
{
    protected static string $table = 'material_tracking';
    protected static string $primaryKey = 'id';

    // Security: Only these columns can be used in ORDER BY
    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'created_at', 'material_name', 'status'];
    }

    // Security: Only these columns can be filtered in WHERE clauses
    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'class_id', 'material_name', 'status'];
    }

    // Security: Only these columns can be inserted
    protected function getAllowedInsertColumns(): array
    {
        return ['class_id', 'material_name', 'quantity', 'status', 'created_at'];
    }

    // Security: Only these columns can be updated
    protected function getAllowedUpdateColumns(): array
    {
        return ['material_name', 'quantity', 'status', 'updated_at'];
    }
}
```

### Shortcode Registration
```php
// Source: wecoza-core/src/Learners/Shortcodes/ (existing pattern)
namespace WeCoza\Events\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class EventTasksShortcode
{
    public static function register(): void
    {
        add_shortcode('wecoza_event_tasks', [self::class, 'render']);
    }

    public static function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 50,
            'sort' => 'desc'
        ], $atts, 'wecoza_event_tasks');

        return wecoza_view('events/event-tasks/main', [
            'limit' => (int) $atts['limit'],
            'sort' => $atts['sort']
        ], true); // true = return as string
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom database Connection class | PostgresConnection singleton | wecoza-core v1.0 (2024) | Shared connection pool, lazy loading, SSL support for managed databases |
| Manual `require_once` chains | Composer PSR-4 autoloading | wecoza-core v1.0 (2024) | No manual includes, optimized autoloader for production |
| Flat `includes/` directory | Modular `src/Module/` structure | wecoza-core v1.0 (2024) | Clear separation of concerns (Controllers, Models, Repositories, Services) |
| Direct PDO queries in controllers | Repository pattern with BaseRepository | wecoza-core v1.0 (2024) | SQL injection protection via column whitelisting, reusable query patterns |
| Direct `include` for views | `wecoza_view()` helper | wecoza-core v1.0 (2024) | Consistent path resolution, data scoping, caching support |

**Deprecated/outdated:**
- `WeCozaEvents\Database\Connection::getPdo()`: Replaced by `PostgresConnection::getInstance()->getPdo()`
- `WeCozaEvents\Database\Connection::getSchema()`: No longer needed (tables use public schema by default)
- Schema-qualified table names (`{$schema}.classes`): Use unqualified names (`classes`)
- Manual namespace includes in views: Use `wecoza_view()` helper instead

## Open Questions

1. **Does events plugin use schema qualification for multi-tenancy?**
   - What we know: Connection class has `getSchema()` method, repositories may use `{$schema}.table_name` pattern
   - What's unclear: Whether this is actually used in production or just defensive coding
   - Recommendation: Inspect SQL queries in repositories for schema variables. If found and actively used, discuss with user before removing. If not used, remove schema qualification during migration.

2. **Are there any event-specific database triggers or views that depend on old namespace?**
   - What we know: Phase 2 handles database triggers/schema, but triggers might reference old class names in comments or metadata
   - What's unclear: Whether PostgreSQL triggers/functions contain hardcoded namespace strings
   - Recommendation: Defer to Phase 2, but note for planner: "Check database triggers for hardcoded namespace references"

3. **How are Views/Presenters used - are they autoloaded or manually included?**
   - What we know: Events plugin has `Views/Presenters/` directory with classes like MaterialTrackingPresenter
   - What's unclear: Whether these are PSR-4 autoloaded classes or manually included templates
   - Recommendation: If Presenter classes are proper PHP classes (not templates), they should go in `src/Events/Views/Presenters/` and be autoloaded. If they're template helpers, they stay as-is in `views/events/presenters/`. Examine file content to determine.

## Sources

### Primary (HIGH confidence)
- wecoza-core codebase: `/src/Learners/`, `/src/Classes/`, `/core/Abstract/BaseRepository.php`, `/core/Database/PostgresConnection.php`
- wecoza-events-plugin codebase: `/includes/Models/`, `/includes/Controllers/`, `/includes/class-wecoza-events-database.php`
- composer.json PSR-4 configuration (wecoza-core existing setup)

### Secondary (MEDIUM confidence)
- [A Guide to Creating a PSR-4 Compatible WordPress Plugin - DLX Plugins](https://dlxplugins.com/tutorials/creating-a-psr-4-autoloading-wordpress-plugin)
- [PSR-4 autoloading in plugins and themes | Medium](https://medium.com/write-better-wordpress-code/psr-4-autoloading-in-plugins-and-themes-720f459f4df4)
- [Implementing namespaces and coding standards in WordPress plugin development – WordPress Developer Blog](https://developer.wordpress.org/news/2025/09/implementing-namespaces-and-coding-standards-in-wordpress-plugin-development/)
- [Autoloader optimization - Composer](https://getcomposer.org/doc/articles/autoloader-optimization.md)
- [Command-line interface / Commands - Composer](https://getcomposer.org/doc/03-cli.md)

### Tertiary (LOW confidence)
- [Refactoring PHP Projects: Implementing Namespaces for Better Code Organization - DopeThemes](https://www.dopethemes.com/refactoring-php-projects-implementing-namespaces-for-better-code-organization/)
- [How to Migrate Legacy PHP Applications Without Stopping Development of New Features | Rector](https://getrector.com/blog/how-to-migrate-legacy-php-applications-without-stopping-development-of-new-features)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Composer PSR-4 is industry standard, already used in wecoza-core
- Architecture: HIGH - Exact patterns exist in Learners/Classes modules, verified from actual codebase
- Pitfalls: HIGH - Based on common namespace migration issues + specific wecoza-core patterns observed in code

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - stable domain, PHP/Composer standards don't change rapidly)
