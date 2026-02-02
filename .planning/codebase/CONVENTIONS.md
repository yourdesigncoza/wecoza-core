# Coding Conventions

**Analysis Date:** 2026-02-02

## Naming Patterns

**Files:**
- PHP classes: PascalCase (e.g., `LearnerModel.php`, `BaseController.php`)
- PHP files (non-classes): kebab-case with descriptive names (e.g., `security-test.php`, `ajax-utils.js`)
- Directories: kebab-case (e.g., `views/components/`, `assets/js/`)
- WordPress shortcodes: Use `wecoza_` prefix (e.g., `[wecoza_display_learners]`, `[wecoza_capture_class]`)
- AJAX actions: Use `wecoza_` prefix (e.g., `wp_ajax_wecoza_get_learner`)

**Classes:**
- Extend from base abstractions: `BaseController`, `BaseRepository`, `BaseModel`
- Example: `class LearnerController extends BaseController` in `src/Learners/Controllers/LearnerController.php`
- Example: `class LearnerRepository extends BaseRepository` in `src/Learners/Repositories/LearnerRepository.php`

**Functions:**
- Global helper functions use `wecoza_` prefix (e.g., `wecoza_view()`, `wecoza_db()`, `wecoza_sanitize_value()`)
- Protected/private methods in classes use camelCase (e.g., `registerHooks()`, `getRepository()`, `findByIdWithMappings()`)
- Public methods use camelCase (e.g., `getLearner()`, `createLearner()`, `updateLearner()`)

**Variables:**
- Local variables and properties: camelCase (e.g., `$learnerModel`, `$repository`, `$response`)
- Class properties: camelCase with type hints (e.g., `protected ?LearnerRepository $repository = null`)
- Database columns: snake_case (e.g., `first_name`, `sa_id_no`, `created_at`)
- Model properties: camelCase, matching database columns via conversion (e.g., `$firstName`, `$saIdNo`)
- HTML/form data in POST/GET: snake_case (e.g., `$_POST['first_name']`)

**Types & Constants:**
- Class constants: UPPER_SNAKE_CASE (not observed in current code, use when needed)
- Database table names: plural, lowercase (e.g., `learners`, `classes`)
- Model types in `$casts`: lowercase types (e.g., `'id' => 'int'`, `'created_at' => 'date'`)

## Code Style

**Formatting:**
- No explicit formatter configured (not ESLint, Prettier, or PHPStan found in repo)
- Follow PSR-12 standard conventions (PHP naming/spacing)
- Line length: Not enforced (files use natural line breaks)
- Indentation: 4 spaces (PHP) and 4 spaces (JavaScript)

**PHP:**
- Use strict typing: All files begin with namespace declarations and have type hints
- Example: `public function findById(int $id): ?array`
- Use null coalescing: `$value ?? $default`
- Use null-safe operator in PHP 8+: `$object?->method()`
- Arrow functions in closures: `fn($key) => in_array($key, $allowed, true)`
- Match expressions for type-safe conditionals: Supported by PHP 8.0+ requirement

**JavaScript:**
- Use strict mode: `'use strict';` at top of IIFE/modules
- Arrow functions: `function(data) => { ... }` in promises, callbacks
- Template literals for dynamic strings: `` `[WeCozaAjax] ${method} ${action}` ``
- Use `const` for variables that won't be reassigned, `let` for those that will

**Comments:**
- PHPDoc headers on classes and public methods (see `BaseController.php`, `BaseRepository.php`)
- Inline comments for non-obvious logic
- Section comments using banner style: `/* |--- Section Name --- |*/ `
- Example: See organization of `BaseController.php` sections

## Import Organization

**PHP - Class imports (use statements):**
1. Core/vendor classes (WordPress, external libraries)
2. Internal WeCoza Core classes
3. Module-specific classes

Order example from `LearnerController.php`:
```php
use WeCoza\Core\Abstract\BaseController;
use WeCoza\Learners\Models\LearnerModel;
use WeCoza\Learners\Repositories\LearnerRepository;
```

**JavaScript - Module imports:**
1. DOM/jQuery selectors at top of IIFE
2. Configuration objects
3. Utility functions

Example from `ajax-utils.js`:
```javascript
(function(global, $) {
    'use strict';
    const DEFAULT_CONFIG = { ... };
    const LOADING_TEMPLATE = `...`;
    const WeCozaAjax = { ... };
```

**Path Aliases:**
- None detected in current setup
- Composer autoload uses PSR-4 mapping: `WeCoza\Core\` → `core/`, `WeCoza\Learners\` → `src/Learners/`, `WeCoza\Classes\` → `src/Classes/`

## Error Handling

**PHP Error Handling:**

**Exceptions:**
- Catch generic `Exception` in try-catch blocks: `catch (Exception $e)`
- Log errors to WordPress error log: `error_log("WeCoza Core: Message: " . $e->getMessage())`
- Return safe defaults on error: `return null;`, `return [];`, `return false;`

Example from `BaseRepository.php`:
```php
try {
    // Database operation
    $result = $this->db->query($sql, $params);
    return $result;
} catch (Exception $e) {
    error_log("WeCoza Core: Repository operation error: " . $e->getMessage());
    return null; // or [] or false depending on context
}
```

**Security-First Error Handling:**
- SQL injection: Use column whitelisting in repositories via `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, etc.
- No raw column names in queries. Always validate via whitelist.
- Example from `BaseRepository.php`:
```php
protected function validateOrderColumn(string $orderBy, string $default = 'created_at'): string
{
    $allowed = $this->getAllowedOrderColumns();
    return in_array($orderBy, $allowed, true) ? $orderBy : $default;
}
```

**AJAX/Security Error Responses:**
- Use `sendError($message, $code)` from BaseController or AjaxSecurity
- Always verify nonce: `$this->requireNonce('action_name', 'nonce')`
- Always check capability: `$this->requireCapability('manage_learners')`
- Exit after sending error: `exit;` (prevents further execution)

Example from `LearnerController.php`:
```php
$this->requireNonce('learners_nonce_action', 'nonce');
$this->requireCapability('manage_learners');
// Process request...
```

**JavaScript Error Handling:**
- Use Promise `.catch()` for AJAX errors
- Log errors in debug mode: `if (config.debug) console.error(...)`
- Return error objects with status, statusText, and xhr: See `ajax-utils.js` line 272

## Logging

**Framework:** `error_log()` (native PHP error logging) and WordPress `error_log()`

**Patterns:**
- Only log when `WP_DEBUG` is true: Check in `wecoza_log()` function
- Prefix logs with module name: `"[WeCoza Core][Level]"` format
- Log in try-catch blocks on database errors

Example from `wecoza_log()`:
```php
function wecoza_log(string $message, string $level = 'info'): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    $prefix = sprintf('[WeCoza Core][%s]', strtoupper($level));
    error_log("{$prefix} {$message}");
}
```

**JavaScript Logging:**
- Use `console.log()`, `console.error()` for development/debugging
- Wrap in debug mode checks: `if (config.debug) console.log(...)`

Example from `ajax-utils.js`:
```javascript
if (config.debug) {
    console.log(`[WeCozaAjax] ${method} ${action}`, requestData);
}
```

## Comments

**When to Comment:**
- Complex logic: Why something is done a certain way (not what it does)
- Security considerations: Explain why column whitelisting is necessary
- Non-obvious workarounds: Document temporary solutions or gotchas
- Architecture notes: See `LearnerModel.php` comments on property design

Example from `LearnerModel.php` (lines 8-12):
```php
/**
 * ARCHITECTURE NOTE: Some properties hold display values (names) instead of raw IDs
 * because the DB query transforms them via JOINs/CASE statements. This is intentional:
 * - Display: Model holds "NL2" (name) for direct template output
 * - Forms: Compare by name for pre-selection, submit IDs
 */
```

**JSDoc/PHPDoc:**
- Use on all public methods and classes
- Include `@param`, `@return`, `@throws` tags
- Reference file paths in examples

Example from `BaseController.php`:
```php
/**
 * Find records by criteria
 *
 * @param array $criteria Field => value pairs
 * @param int $limit Max records
 * @return array Array of matching records
 */
public function findBy(array $criteria, int $limit = 50): array
```

## Function Design

**Size Guidelines:**
- Keep functions focused on single responsibility
- Private helper methods for common patterns
- Example: `validateOrderColumn()` is 4 lines - validates and returns safe value

**Parameters:**
- Use type hints on all parameters: `function findById(int $id): ?array`
- Use named parameters for clarity: `: string $orderBy = 'created_at'`
- Use default values for optional parameters
- Avoid boolean parameters (use named constants/enums instead)

**Return Values:**
- Explicit return types: `void`, `bool`, `?string`, `array`, etc.
- Return null for "not found" cases: `return null;`
- Return empty array for "no results": `return [];`
- Return boolean for success/failure: `return true;` / `return false;`
- Return object instances from factory methods: `return new static($data);`

**Example from `BaseRepository.php`:**
```php
public function findById(int $id): ?array { return ... }
public function findAll(int $limit = 50, int $offset = 0): array { return ... }
public function insert(array $data): ?int { return ... }
public function update(int $id, array $data): bool { return ... }
public function delete(int $id): bool { return ... }
```

## Module Design

**Exports:**
- PHP classes export via namespace
- Global helper functions registered in `core/Helpers/functions.php`
- JavaScript exports via IIFE with global assignment: `global.WeCozaAjax = WeCozaAjax;`

Example from `ajax-utils.js`:
```javascript
(function(global, $) {
    const WeCozaAjax = { ... };
    global.WeCozaAjax = WeCozaAjax;
})(window, jQuery);
```

**Barrel Files:**
- Not used in this codebase
- Each module is self-contained

**Repository Pattern:**
- All data access through Repository classes
- Controllers depend on Models, Models delegate to Repositories
- Example: `LearnerController` → `LearnerModel` → `LearnerRepository` → Database

**Model/View/Controller Organization:**
- Models: `src/[Module]/Models/` - Contain properties, getters, setters
- Repositories: `src/[Module]/Repositories/` - Contain CRUD operations
- Controllers: `src/[Module]/Controllers/` - Contain business logic, hook registration
- Views: `views/` - PHP templates
- Services: `src/[Module]/Services/` - Optional: Complex business logic (e.g., `ProgressionService`)

## Security Conventions

**Input Validation:**
- Use `wecoza_sanitize_value($value, $type)` for type-safe sanitization
- Supported types: `string`, `int`, `float`, `bool`, `email`, `url`, `json`, `array`, `date`, `datetime`, `raw`
- Always sanitize before database queries

**Column Whitelisting:**
- Every Repository must override `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`
- Example from `LearnerRepository.php`:
```php
protected function getAllowedOrderColumns(): array {
    return ['id', 'first_name', 'surname', 'email_address', 'created_at', 'updated_at', ...];
}
```

**CSRF Protection:**
- All AJAX handlers require nonce verification: `$this->requireNonce('action_name')`
- Create nonces in templates with `wp_create_nonce('action_name')`
- Use `AjaxSecurity::requireNonce()` as static alternative

**Capabilities:**
- Use custom capability `manage_learners` for learner data
- Check capability before processing: `$this->requireCapability('manage_learners')`
- Registered on plugin activation in `wecoza-core.php`

---

*Convention analysis: 2026-02-02*
