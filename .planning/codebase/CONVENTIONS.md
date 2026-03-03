# Coding Conventions

**Analysis Date:** 2026-03-03

## Naming Patterns

**Files:**
- PHP classes: PascalCase, match class name (e.g., `LearnerRepository.php` for class `LearnerRepository`)
- PHP functions: snake_case with `wecoza_` prefix for global helpers (e.g., `wecoza_view()`, `wecoza_config()`)
- PHP test files: `{FeatureName}Test.php` with test runner functions inside (e.g., `EmailNotificationTest.php`)
- JavaScript files: kebab-case (e.g., `class-capture.js`, `ajax-utils.js`, `learner-selection-table.js`)
- Directories: kebab-case (e.g., `form-fillers/`, `event-tasks/`, `classes/`)

**Functions:**
- PHP methods: camelCase (e.g., `findByIdWithMappings()`, `getAllowedOrderColumns()`, `ensureRequiredPages()`)
- JavaScript functions: camelCase in modern code (e.g., `showCustomAlert()`, `getDayIndex()`) or PascalCase for constructors
- Private/protected PHP methods: camelCase with leading underscore discouraged; use visibility modifiers instead
- Trait methods: Follow parent class naming; prefix with `__` only for magic methods (e.g., `detectPIIPattern()`, `maskSouthAfricanID()`)

**Variables:**
- PHP properties: camelCase with type hints (e.g., `private ?PostgresConnection $db`, `protected string $table`)
- JavaScript variables: camelCase (e.g., `holidayOverrides`, `loadingIndicator`, `maxRetries`)
- Constants: UPPERCASE_SNAKE_CASE (e.g., `DEFAULT_CONFIG`, `TIMEOUT_SECONDS`, `LOADING_TEMPLATE`)
- Database columns: snake_case (e.g., `first_name`, `city_town_id`, `created_at`)

**Types:**
- PHP: Use type hints on all parameters and return types (mandatory `declare(strict_types=1);`)
- Nullable types: `?Type` (e.g., `?int`, `?string`, `?PostgresConnection`)
- Union types: `Type1|Type2` (e.g., `string|int`, `array|null`)
- Array types: Use generic syntax in docblocks (e.g., `@var array<string, mixed>`)
- Classes: Full namespace path in imports via `use` statements at top of file

## Code Style

**Formatting:**
- No specific auto-formatter enforced (no .prettierrc or eslint config found)
- 4-space indentation for PHP (observed in all files)
- 2-space indentation for JavaScript (observed in JS files)
- Line endings: Unix (LF)
- Max line length: Not enforced; some lines exceed 100 characters

**Linting:**
- No linting configuration detected (no .eslintrc, .phpcs.xml, or biome.json)
- Code adheres to PSR-12 (PHP-FIG) style guide implicitly through manual review
- PHP: `declare(strict_types=1);` at top of every class file (mandatory)
- PHP: `if (!defined('ABSPATH')) { exit; }` security check after namespace declaration in all plugin files

**Declaration Blocks:**
All PHP files follow this header order:
1. Opening tag: `<?php`
2. Strict types: `declare(strict_types=1);`
3. DocBlock comment (file-level)
4. Namespace declaration
5. Use statements (imports)
6. Security check: `if (!defined('ABSPATH')) { exit; }`
7. Code

Example from `LearnerModel.php`:
```php
<?php
declare(strict_types=1);

/**
 * WeCoza Core - Learner Model
 * ...
 */

namespace WeCoza\Learners\Models;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseModel;
use WeCoza\Learners\Repositories\LearnerRepository;

if (!defined('ABSPATH')) {
    exit;
}
```

## Import Organization

**Order:**
1. Built-in PHP classes/interfaces (e.g., `use PDO;`, `use Exception;`, `use DateTime;`)
2. Standard library utilities (e.g., `use Closure;`, `use ReflectionFunction;`)
3. WeCoza\Core namespace imports (e.g., `use WeCoza\Core\Abstract\BaseRepository;`)
4. Application-specific imports in this order:
   - Abstract base classes
   - Models
   - Repositories
   - Services
   - Controllers
   - Helpers
5. Same-package imports last (e.g., in Learners module, import other Learners classes last)

Example from `AttendanceService.php`:
```php
use WeCoza\Classes\Repositories\AttendanceRepository;
use WeCoza\Classes\Repositories\ClassRepository;
use WeCoza\Learners\Services\ProgressionService;
use WeCoza\Learners\Repositories\LearnerProgressionRepository;
use DateTime;
use Exception;
```

**Path Aliases:**
- No path aliases configured (PSR-4 autoloading via Composer)
- Namespaces map directly to directory structure (e.g., `WeCoza\Learners\` → `src/Learners/`)

**Function Imports (PHP 5.6+ use):**
Functions are imported at top of files that use them extensively:
```php
use function array_merge;
use function count;
use function is_array;
use function wp_json_encode;
use const JSON_PRETTY_PRINT;
```
This improves performance and readability in functions-heavy files.

## Error Handling

**Patterns:**
- **Try-catch blocks:** Wrap database operations and external API calls
- **Exception types:** Use built-in PHP exceptions (`Exception`, `PDOException`) with descriptive messages
- **Custom exceptions:** Not used; standard `Exception` with clear context
- **Error suppression:** Not used (no `@` operator)
- **Logging:** Errors logged via `error_log()` (mapped to WordPress debug log) or `wecoza_log()`

Example from `LearnerRepository::findByIdWithMappings()`:
```php
try {
    $stmt = $this->db->query($sql, ['id' => $id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
} catch (Exception $e) {
    error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::findByIdWithMappings'));
    return null;
}
```

**Error Responses:**
- Services throw exceptions with descriptive messages (e.g., `throw new Exception("Class not found: {$classId}")`)
- AJAX handlers catch exceptions and return JSON error responses via `JsonResponder`
- Controllers propagate exceptions to handlers (not caught at controller level)

## Logging

**Framework:** WordPress native (`error_log()`, `wecoza_log()`)

**Patterns:**
- **Development logging:** Use `wecoza_log($msg, $level)` for WeCoza-specific messages
  - Levels: `'info'`, `'warning'`, `'error'`
  - Example: `wecoza_log('Client validation errors: ' . wp_json_encode($errors), 'warning');`
- **System errors:** Use `error_log()` for PHP errors and exceptions
  - Often combined with `wecoza_sanitize_exception()` to avoid logging sensitive data
  - Example: `error_log(wecoza_sanitize_exception($e->getMessage(), 'LearnerRepository::findByIdWithMappings'));`
- **Condition:** All logging only executes when `WP_DEBUG` is enabled
- **Output:** `/opt/lampp/htdocs/wecoza/wp-content/debug.log`

## Comments

**When to Comment:**
- Complex algorithms (e.g., CTE queries with multiple joins): Use multi-line comments above the block
- Business logic that's not obvious: Brief inline comment explaining intent
- Workarounds for WordPress quirks: Always document why
- Security-sensitive code: Always explain the protection mechanism
- Don't comment obvious code (e.g., `$count = count($items);` needs no comment)

**JSDoc/TSDoc:**
- PHP: Use DocBlocks for classes, methods, and functions (mandatory)
  - Format: `/** Description */` for single line, `/** @return type Description */` for detailed
  - Include `@param`, `@return`, `@throws` tags for public methods
  - Example from `BaseRepository`:
    ```php
    /**
     * Get columns allowed for ORDER BY clauses
     * Override in child classes to expand the list
     *
     * @return array List of allowed column names
     */
    protected function getAllowedOrderColumns(): array
    ```
- JavaScript: Use single-line (`//`) and multi-line (`/* */`) comments, no formal JSDoc
  - Block comments describe intent above functions/objects
  - Inline comments explain non-obvious code
  - Example from `ajax-utils.js`:
    ```javascript
    /**
     * AJAX Utilities - Standardized AJAX response handling for WeCoza Classes Plugin
     *
     * Provides consistent patterns for:
     * - WordPress AJAX requests with nonce handling
     * - Standardized success/error response processing
     * ...
     */
    ```

**Special Comments:**
- TODO/FIXME: Not systematically used; tech debt tracked in CONCERNS.md
- HACK/XXX: Used sparingly in code for temporary workarounds (documented with context)
- Architecture notes: Used in model docblocks for non-obvious design decisions
  - Example from `LearnerModel.php`:
    ```php
    /**
     * ARCHITECTURE NOTE: Some properties hold display values (names) instead of raw IDs
     * because the DB query transforms them via JOINs/CASE statements. This is intentional:
     * - Display: Model holds "NL2" (name) for direct template output
     * - Forms: Compare by name for pre-selection, submit IDs
     * - Updates: AJAX receives IDs from form, passes to DB
     */
    ```

## Function Design

**Size:** No strict limit enforced; range from 5 lines (utility) to 50+ lines (complex queries)
- Average service method: 15-30 lines
- Average repository method: 20-40 lines (especially with error handling)
- Complex methods documented with section comments (e.g., `// ===== Setup Phase =====`)

**Parameters:**
- Maximum 3-4 parameters for public methods (long signatures indicate design smell)
- Use arrays/objects for multiple related parameters
- Type-hint all parameters (enforce with `declare(strict_types=1)`)
- Default values for optional parameters

Example from `AISummaryService::generateSummary()`:
```php
public function generateSummary(array $context, ?array $existing = null): SummaryResultDTO
```

**Return Values:**
- Always type-hint return types
- Return `null` for "not found" rather than `false`
- Return `array` for collections (never return `null` for empty results; return `[]`)
- Return DTOs/objects for complex data structures (not arrays)

Example pattern:
```php
public function findByIdWithMappings(int $id): ?array   // null if not found
public function getAllowedOrderColumns(): array          // [] if none
public function generateSummary(array $context): SummaryResultDTO  // complex result object
```

## Module Design

**Exports:**
- PHP: No explicit export mechanism (no barrel files); import directly from class files
- JavaScript: Modules export via `window.NamespaceName` (global namespace pattern)
  - Example: `WeCozaAjax`, `WeCozaUtils`
- All exports are public; no internal-only export pattern

**Barrel Files:**
- PHP: Not used
- JavaScript: Not used (each JS file loads independently via `wp_enqueue_script()`)

**Visibility:**
- PHP: Use explicit visibility (`public`, `protected`, `private`)
  - Most properties are `protected` to allow subclass extension
  - Most methods are `public` for direct use
- Private methods: Used for internal implementation details (not exposed in tests)
- Static methods: Used for factories and utilities (e.g., `NotificationProcessor::boot()`)

## Trait Usage

**Pattern:** Traits used for cross-cutting concerns:
- `DataObfuscator`: PII masking and data sanitization (used by `AISummaryService`, `PIIDetector`)
- `PIIDetector`: Pattern matching for sensitive data detection
- Applied via `use TraitName;` at class level

Example from `AISummaryService.php`:
```php
final class AISummaryService
{
    use DataObfuscator;
    // ... implementation
}
```

## Class Design

**Abstract Base Classes:**
- `BaseModel`: Base for all data models with type casting and persistence
- `BaseRepository`: Base for data access with column whitelisting and connection management
- `BaseController`: Base for WordPress integration with hook registration and asset enqueueing

**Final Classes:**
- Some service classes marked `final` to prevent subclassing (e.g., `AISummaryService`)
- Controllers and repositories not marked final (allow extension in dependent plugins)

**Constructor Dependency Injection:**
- Services accept dependencies via constructor
- Example from `AISummaryService::__construct()`:
  ```php
  public function __construct(
      private readonly OpenAIConfig $config,
      ?callable $httpClient = null,
      private readonly int $maxAttempts = 3
  )
  ```
- Properties marked `private readonly` when intended as immutable

## Security-First Conventions

**Column Whitelisting (SQL Injection Prevention):**
- Every Repository must override: `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`
- Example from `LearnerRepository`:
  ```php
  protected function getAllowedOrderColumns(): array {
      return ['id', 'first_name', 'surname', 'email_address', 'created_at', 'updated_at', ...];
  }
  ```
- Used in queries to validate column names before building SQL

**Input Sanitization:**
- Use `wecoza_sanitize_value($value, $type)` for type-safe sanitization
- Supported types: `string`, `int`, `float`, `bool`, `email`, `url`, `json`, `array`, `date`, `datetime`, `raw`
- Always sanitize before database queries

**AJAX/Capability Checks:**
- All AJAX handlers require nonce verification: `AjaxSecurity::requireNonce('action_name')`
- Check capability before processing: `AjaxSecurity::checkCapability('manage_learners')`
- No unauthenticated endpoints (no `wp_ajax_nopriv_` hooks registered)

---

*Convention analysis: 2026-03-03*
