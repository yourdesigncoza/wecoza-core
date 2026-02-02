# Phase 10: Architecture & Type Safety - Research

**Researched:** 2026-02-02
**Domain:** PHP architecture refactoring, type safety, DTOs, enums
**Confidence:** HIGH

## Summary

This phase focuses on four architectural improvements: refactoring the monolithic `generateSummary()` method for single responsibility, adding a `count()` method to BaseRepository for pagination support, replacing array-based data structures with typed DTOs, and converting magic string status values to PHP 8.1 Enums.

The standard approach is to use Extract Method refactoring for breaking up large methods, implement native PHP 8.1 readonly properties for immutable DTOs (readonly classes require PHP 8.2+), use backed string Enums with `tryFrom()` for safe validation, and leverage PHP's type system for compile-time safety. These patterns are well-established in modern PHP codebases as of 2026.

The codebase already demonstrates good architecture (BaseRepository with column whitelisting, services separated from controllers, PSR-4 autoloading). This phase builds on that foundation by introducing stricter type safety and better separation of concerns. PHP 8.1.2 is available, supporting readonly properties and enums but not readonly classes (PHP 8.2 feature).

**Primary recommendation:** Use native PHP 8.1 features (readonly properties, backed string enums) without external dependencies. Apply Extract Method to break up `generateSummary()` into 3-5 focused methods. Create simple readonly DTOs with public properties. Use Enums with `tryFrom()` for safe string-to-enum conversion.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.1.2+ | Language runtime | Built-in readonly properties and enums |
| None | N/A | DTOs | PHP 8.1 readonly properties eliminate need for external DTO libraries |
| None | N/A | Enums | PHP 8.1 native enums are first-class language feature |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPStan | Latest | Static analysis | Verify type safety during development |
| Psalm | Latest | Static analysis | Alternative to PHPStan with different focus |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Native PHP 8.1 readonly | spatie/laravel-data | Laravel-specific, adds dependency, provides validation/transformation features |
| Native enums | Custom class constants | Less type safety, no compile-time validation |
| Manual DTOs | Array data | More flexibility but zero type safety |

**Installation:**
```bash
# No packages needed - use native PHP 8.1 features
# Optional: Install PHPStan for static analysis
composer require --dev phpstan/phpstan
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── [Module]/
│   ├── DTOs/              # Data Transfer Objects
│   │   ├── SummaryDTO.php
│   │   ├── EmailContextDTO.php
│   │   └── RecordDTO.php
│   ├── Enums/             # PHP 8.1 Enums
│   │   ├── StatusEnum.php
│   │   └── TaskStatusEnum.php
│   ├── Services/          # Business logic
│   │   ├── SummaryService.php
│   │   ├── PromptBuilder.php
│   │   └── ResponseParser.php
│   └── Repositories/      # Data access
```

### Pattern 1: Extract Method Refactoring
**What:** Break large methods into focused single-purpose methods with clear names and contracts
**When to use:** Method exceeds 30-40 lines, mixes concerns, or has multiple reasons to change
**Example:**
```php
// Before: Monolithic method (180 lines)
public function generateSummary(array $context, ?array $existing = null): array
{
    // Line 70-95: Data obfuscation
    // Line 109-115: Build prompt messages
    // Line 117-118: Call OpenAI API
    // Line 128-154: Success response handling
    // Line 156-178: Error response handling
}

// After: Extracted focused methods
public function generateSummary(array $context, ?array $existing = null): array
{
    $record = $this->normaliseRecord($existing);

    if ($this->shouldSkipGeneration($record)) {
        return $this->buildSkippedResponse($record);
    }

    $obfuscatedData = $this->obfuscateContext($context);
    $messages = $this->buildPromptMessages($context, $obfuscatedData);
    $response = $this->callOpenAI($messages, self::MODEL);

    return $this->processResponse($response, $record, $obfuscatedData);
}

private function shouldSkipGeneration(array $record): bool
{
    return $record['status'] === 'success' || $record['attempts'] >= $this->maxAttempts;
}

private function obfuscateContext(array $context): ObfuscatedDataDTO
{
    // Focused on data obfuscation only
}

private function buildPromptMessages(array $context, ObfuscatedDataDTO $data): array
{
    // Focused on prompt construction only
}

private function processResponse(array $response, array $record, ObfuscatedDataDTO $data): array
{
    // Focused on response processing only
}
```

### Pattern 2: Readonly DTOs with Constructor Property Promotion
**What:** Immutable data containers using PHP 8.1 readonly properties
**When to use:** Passing structured data between layers, replacing arrays with 5+ keys
**Example:**
```php
// Source: PHP 8.1 readonly properties feature
final class RecordDTO
{
    public function __construct(
        public readonly ?string $summary,
        public readonly string $status,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $attempts,
        public readonly bool $viewed,
        public readonly ?string $viewedAt,
        public readonly ?string $generatedAt,
        public readonly ?string $model,
        public readonly int $tokensUsed,
        public readonly int $processingTimeMs,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            summary: $data['summary'] ?? null,
            status: $data['status'] ?? 'pending',
            errorCode: $data['error_code'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            attempts: max(0, (int) ($data['attempts'] ?? 0)),
            viewed: (bool) ($data['viewed'] ?? false),
            viewedAt: $data['viewed_at'] ?? null,
            generatedAt: $data['generated_at'] ?? null,
            model: $data['model'] ?? null,
            tokensUsed: (int) ($data['tokens_used'] ?? 0),
            processingTimeMs: (int) ($data['processing_time_ms'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'attempts' => $this->attempts,
            'viewed' => $this->viewed,
            'viewed_at' => $this->viewedAt,
            'generated_at' => $this->generatedAt,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }
}
```

### Pattern 3: Backed String Enums with Safe Validation
**What:** PHP 8.1 backed enums with tryFrom() for safe string-to-enum conversion
**When to use:** Fixed set of string values (status, type, state), validation needed
**Example:**
```php
// Source: https://www.php.net/manual/en/language.types.enumerations.php
enum ProgressionStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';

    // Helper method for labels (allowed in enums)
    public function label(): string
    {
        return match($this) {
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::ON_HOLD => 'On Hold',
        };
    }

    // Safe validation method
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        return self::tryFrom($value);
    }
}

// Usage in models/repositories
class LearnerProgressionModel
{
    private ProgressionStatus $status;

    public function __construct(array $data)
    {
        // Safe conversion with fallback
        $this->status = ProgressionStatus::tryFrom($data['status'] ?? '')
            ?? ProgressionStatus::IN_PROGRESS;
    }

    public function getStatus(): ProgressionStatus
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->value;
    }
}
```

### Pattern 4: BaseRepository count() Method
**What:** Centralized count method with criteria filtering for pagination
**When to use:** All repositories for pagination, total counts
**Example:**
```php
// Already exists in BaseRepository (lines 310-351)
public function count(array $criteria = []): int
{
    $sql = sprintf("SELECT COUNT(*) FROM %s", static::$table);
    $params = [];

    if (!empty($criteria)) {
        $allowedFilterColumns = $this->getAllowedFilterColumns();
        $conditions = [];

        foreach ($criteria as $field => $value) {
            if (!in_array($field, $allowedFilterColumns, true)) {
                continue;
            }

            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
    }

    try {
        $stmt = $this->db->query($sql, $params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log(wecoza_sanitize_exception($e->getMessage(), 'Repository::count'));
        return 0;
    }
}

// Used by paginate() method (lines 554-578)
public function paginate(
    int $page = 1,
    int $perPage = 20,
    array $criteria = [],
    string $orderBy = 'created_at',
    string $order = 'DESC'
): array {
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $total = $this->count($criteria); // Uses count() method

    $items = empty($criteria)
        ? $this->findAll($perPage, $offset, $orderBy, $order)
        : $this->findBy($criteria, $perPage, $offset, $orderBy, $order);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
        'has_more' => ($page * $perPage) < $total,
    ];
}
```

### Anti-Patterns to Avoid

- **Over-extraction:** Breaking methods into 2-3 line methods with no clear purpose. Extract when logic is cohesive and reusable.

- **DTO with business logic:** DTOs should only contain data and helper methods like `fromArray()`, `toArray()`. No validation, calculation, or business rules.

- **Mutable DTOs:** Using public non-readonly properties defeats type safety benefits. Always use readonly.

- **Enum::from() without error handling:** Use `tryFrom()` which returns null on invalid input instead of throwing ValueError.

- **Comparing enum to scalar:** Don't use `$status === 'completed'`. Use `$status === Status::COMPLETED` or `$status->value === 'completed'`.

- **Adding properties to readonly classes (PHP 8.2+):** Current codebase is PHP 8.1, so use individual `readonly` keyword per property, not `readonly class`.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| DTO validation library | Custom validation in DTOs | Native type system + static analysis | PHP 8.1 types catch most issues at compile-time, PHPStan/Psalm find the rest |
| DTO serialization | Custom toArray/fromArray in every DTO | Consistent pattern across all DTOs | Keep it simple with named constructors and toArray(), no magic needed |
| Enum validation | Manual string checking | Enum::tryFrom() | Built-in safe conversion, returns null instead of throwing |
| Value objects | Complex immutable classes | readonly DTOs | readonly properties provide immutability without boilerplate |

**Key insight:** PHP 8.1 provides all the type safety features needed for this refactoring without external dependencies. Readonly properties + backed enums + type declarations give compile-time safety. Don't over-engineer with validation libraries when the type system handles it.

## Common Pitfalls

### Pitfall 1: Using Enum::from() Instead of tryFrom()
**What goes wrong:** `Enum::from('invalid_string')` throws ValueError exception, crashing the application
**Why it happens:** from() is strict validation, meant for internal code where you're certain the value is valid
**How to avoid:** Always use `tryFrom()` when converting external data (user input, database values, API responses)
**Warning signs:** Unexpected crashes when processing database records with invalid status strings
**Example:**
```php
// BAD - throws ValueError on invalid input
$status = ProgressionStatus::from($data['status']);

// GOOD - returns null on invalid input
$status = ProgressionStatus::tryFrom($data['status']) ?? ProgressionStatus::IN_PROGRESS;
```

### Pitfall 2: Readonly Properties Require PHP 8.1+
**What goes wrong:** Code breaks on servers running PHP 8.0 or lower
**Why it happens:** readonly keyword was introduced in PHP 8.1
**How to avoid:** Verify server PHP version before using readonly. Codebase requires PHP 8.0+ per CLAUDE.md, but readonly needs 8.1+
**Warning signs:** Parse errors on deployment mentioning "unexpected 'readonly'"
**Example:**
```php
// Requires PHP 8.1+
public readonly string $title;

// PHP 8.0 fallback (if needed)
private string $title;
public function __construct(string $title) {
    $this->title = $title;
}
public function getTitle(): string {
    return $this->title;
}
```

### Pitfall 3: Extracting Methods Too Aggressively
**What goes wrong:** Class bloat with dozens of tiny 2-3 line private methods that aren't reusable
**Why it happens:** Misunderstanding Single Responsibility as "one method per concept" rather than "one reason to change"
**How to avoid:** Extract only when logic is cohesive and has clear input/output contract. A 10-line focused method is fine if it has one purpose.
**Warning signs:** Methods named like `step1()`, `step2()`, methods only called once with no clear abstraction
**Example:**
```php
// BAD - over-extracted, no value
private function getAttempts(array $record): int {
    return $record['attempts'] ?? 0;
}

// GOOD - meaningful extraction
private function obfuscateContext(array $context): ObfuscatedDataDTO {
    $state = null;
    $newRowResult = $this->obfuscatePayloadWithLabels($context['new_row'] ?? [], $state);
    $diffResult = $this->obfuscatePayloadWithLabels($context['diff'] ?? [], $state);
    $oldRowResult = $this->obfuscatePayloadWithLabels($context['old_row'] ?? [], $state);

    return new ObfuscatedDataDTO(
        newRow: $newRowResult['payload'],
        diff: $diffResult['payload'],
        oldRow: $oldRowResult['payload'],
        aliases: $oldRowResult['state']['aliases'],
        fieldLabels: array_merge(
            $newRowResult['field_labels'],
            $diffResult['field_labels'],
            $oldRowResult['field_labels']
        )
    );
}
```

### Pitfall 4: DTOs with Getters/Setters
**What goes wrong:** Verbose boilerplate code that readonly properties already solve
**Why it happens:** Habit from older PHP versions or other languages
**How to avoid:** Use public readonly properties with constructor property promotion. No getters needed.
**Warning signs:** Methods like `getTitle()`, `setTitle()`, `isActive()` in DTOs
**Example:**
```php
// BAD - unnecessary getters with readonly
final class UserDTO {
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}

    public function getName(): string { // Pointless
        return $this->name;
    }
}

// GOOD - direct property access
final class UserDTO {
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// Usage
$user = new UserDTO('John', 'john@example.com');
echo $user->name; // Direct access, no getter
```

### Pitfall 5: Forgetting Enum Serialization for Database
**What goes wrong:** Saving enum object to database instead of string value, causing errors
**Why it happens:** Enums are objects, not strings, despite backed string values
**How to avoid:** Always use `$enum->value` when saving to database or JSON. Use `tryFrom()` when loading.
**Warning signs:** Database errors about "cannot convert object to string", JSON with `{"status": {}}` instead of `{"status": "completed"}`
**Example:**
```php
// BAD - saves enum object
$data = [
    'status' => $this->status, // Enum object
];

// GOOD - saves string value
$data = [
    'status' => $this->status->value, // 'completed'
];

// When loading
$status = ProgressionStatus::tryFrom($row['status']);
```

### Pitfall 6: Missing Type Declarations
**What goes wrong:** Type safety benefits disappear if parameters/returns aren't typed
**Why it happens:** Gradual migration leaves mixed typed/untyped code
**How to avoid:** When creating DTOs and extracted methods, always add parameter and return types. Enable strict_types declaration.
**Warning signs:** PHPStan/Psalm warnings about missing types, unexpected type errors at runtime
**Example:**
```php
// BAD - no type safety
function buildPrompt($context, $data) {
    return ['role' => 'user', 'content' => ...];
}

// GOOD - full type safety
private function buildPromptMessages(array $context, ObfuscatedDataDTO $data): array
{
    return [
        ['role' => 'system', 'content' => $this->systemPrompt],
        ['role' => 'user', 'content' => $this->formatUserPrompt($context, $data)],
    ];
}
```

## Code Examples

Verified patterns from official sources:

### Creating a Backed String Enum
```php
// Source: https://www.php.net/manual/en/language.types.enumerations.php
enum TaskStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::COMPLETED => 'Completed',
        };
    }
}

// Usage
$status = TaskStatus::COMPLETED;
echo $status->value; // 'completed'
echo $status->label(); // 'Completed'
```

### Safe Enum Validation from User Input
```php
// Source: https://php.watch/versions/8.1/enums
// From string value (database, user input)
$statusString = $_POST['status'] ?? 'open';
$status = TaskStatus::tryFrom($statusString) ?? TaskStatus::OPEN;

// In repository when loading from database
public function findById(int $id): ?array
{
    $row = $this->db->query("SELECT * FROM tasks WHERE id = :id", ['id' => $id])->fetch();

    if (!$row) {
        return null;
    }

    // Convert string to enum safely
    $row['status_enum'] = TaskStatus::tryFrom($row['status']);

    return $row;
}
```

### DTO with fromArray and toArray
```php
// Source: Community best practices from multiple sources
final class EmailContextDTO
{
    public function __construct(
        public readonly array $aliasMap,
        public readonly array $fieldLabels,
        public readonly array $obfuscated,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            aliasMap: $data['alias_map'] ?? [],
            fieldLabels: $data['field_labels'] ?? [],
            obfuscated: $data['obfuscated'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'alias_map' => $this->aliasMap,
            'field_labels' => $this->fieldLabels,
            'obfuscated' => $this->obfuscated,
        ];
    }
}
```

### Extract Method: Before and After
```php
// Source: https://refactoring.guru/extract-method
// Before: generateSummary() handles everything (180 lines)
public function generateSummary(array $context, ?array $existing = null): array
{
    $record = $this->normaliseRecord($existing);

    if ($record['status'] === 'success') {
        return ['record' => $record, 'email_context' => [...], 'status' => 'success'];
    }

    if ($record['attempts'] >= $this->maxAttempts) {
        $record['status'] = 'failed';
        return ['record' => $record, 'email_context' => [...], 'status' => 'failed'];
    }

    // 30 lines of obfuscation logic
    $state = null;
    $newRowResult = $this->obfuscatePayloadWithLabels(...);
    $diffResult = $this->obfuscatePayloadWithLabels(...);
    // ... more obfuscation

    // 10 lines of message building
    $messages = [
        ['role' => 'system', 'content' => '...'],
        ['role' => 'user', 'content' => '...'],
    ];

    // API call
    $response = $this->callOpenAI($messages, self::MODEL);

    // 40+ lines of response processing
    if ($response['success'] === true) {
        $record['summary'] = $this->normaliseSummaryText($response['content']);
        // ... 20+ more lines
        return ['record' => $record, 'email_context' => [...], 'status' => 'success'];
    }

    // 20+ lines of error handling
    $record['error_code'] = $response['error_code'];
    // ... more error handling
    return ['record' => $record, 'email_context' => [...], 'status' => $record['status']];
}

// After: Extracted into focused methods
public function generateSummary(array $context, ?array $existing = null): SummaryResultDTO
{
    $record = RecordDTO::fromArray($existing ?? []);

    if ($this->shouldSkipGeneration($record)) {
        return $this->buildSkippedResult($record);
    }

    $obfuscatedData = $this->obfuscateContext($context);
    $messages = $this->buildPromptMessages($context, $obfuscatedData);
    $apiResponse = $this->callOpenAI($messages);

    return $this->processApiResponse($apiResponse, $record, $obfuscatedData);
}

private function shouldSkipGeneration(RecordDTO $record): bool
{
    return $record->status === SummaryStatus::SUCCESS
        || $record->attempts >= $this->maxAttempts;
}

private function obfuscateContext(array $context): ObfuscatedDataDTO
{
    // 30 lines focused on obfuscation only
}

private function buildPromptMessages(array $context, ObfuscatedDataDTO $data): array
{
    // 10 lines focused on prompt building only
}

private function processApiResponse(
    APIResponseDTO $response,
    RecordDTO $record,
    ObfuscatedDataDTO $data
): SummaryResultDTO {
    // 40 lines focused on response processing only
}
```

### BaseRepository count() for Pagination
```php
// Source: Existing BaseRepository implementation (already exists)
// This pattern is ALREADY implemented in the codebase (lines 310-351)

// Usage in child repositories
class LearnerRepository extends BaseRepository
{
    protected static string $table = 'learners';

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'status', 'created_at', 'updated_at'];
    }

    // count() inherited from BaseRepository, no code needed
}

// Usage in controllers/services
$repository = new LearnerRepository();

// Get total count
$total = $repository->count();

// Get filtered count
$activeCount = $repository->count(['status' => 'active']);

// Use with pagination
$result = $repository->paginate(
    page: 1,
    perPage: 20,
    criteria: ['status' => 'active']
);

echo "Total active: {$result['total']}";
echo "Current page: {$result['page']} of {$result['total_pages']}";
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Array data structures | Typed DTOs with readonly | PHP 8.1 (2021) | Compile-time type safety, IDE autocomplete, fewer bugs |
| String constants for status | Backed Enums | PHP 8.1 (2021) | Type-safe status values, validation at language level |
| Magic properties | Constructor property promotion | PHP 8.0 (2020) | Less boilerplate, clearer intent |
| Manual getters/setters | Public readonly properties | PHP 8.1 (2021) | Immutability without boilerplate |
| Monolithic service methods | Extract Method refactoring | Established 2000s | Single responsibility, testability |

**Deprecated/outdated:**
- **spatie/data-transfer-object**: Deprecated in favor of spatie/laravel-data or native PHP 8.1+ readonly properties
- **Manual class constants for enums**: Replaced by native enum support in PHP 8.1+
- **Mutable DTOs**: Modern DTOs should be immutable using readonly properties
- **Readonly classes (PHP 8.2)**: Not available in codebase's PHP 8.1, use per-property readonly instead

## Open Questions

1. **Should DTOs include validation logic?**
   - What we know: Community split on this. Pure DTOs have no validation, only types. Some add static validation methods.
   - What's unclear: Whether the codebase wants strict DTOs or DTOs with self-validation
   - Recommendation: Start with pure DTOs (types only). Add static `validate()` methods if needed later. Keep validation out of constructor.

2. **How to handle DTO versioning for API changes?**
   - What we know: DTOs map to external APIs or database schemas. Changes can break things.
   - What's unclear: Whether AI API response format is stable or evolving
   - Recommendation: Version DTOs if external API changes (`RecordDTOv1`, `RecordDTOv2`) or use adapters to map old→new format

3. **Should BaseRepository count() support complex WHERE conditions?**
   - What we know: Current implementation supports simple field=value criteria with whitelisting
   - What's unclear: Whether repositories need complex queries like `WHERE status IN (...)` or `WHERE date > ?`
   - Recommendation: Current simple implementation sufficient for pagination. Add complex query methods to child repositories as needed.

4. **How to handle enum migrations on existing database records?**
   - What we know: Database has string values like 'in_progress', code will use enums
   - What's unclear: Whether any records have invalid status values not in enum
   - Recommendation: Write data migration script to find/fix invalid values before deploying enum code. Use tryFrom() with fallback for safety.

## Sources

### Primary (HIGH confidence)
- https://www.php.net/manual/en/language.types.enumerations.php - Official PHP Enums documentation
- https://php.watch/versions/8.1/enums - PHP 8.1 Enums comprehensive guide
- https://php.watch/versions/8.1/readonly - PHP 8.1 readonly properties official reference
- Existing codebase BaseRepository.php (lines 310-351) - count() method already implemented

### Secondary (MEDIUM confidence)
- https://stitcher.io/blog/php-enums - Enum best practices by PHP community leader
- https://stitcher.io/blog/php-81-readonly-properties - Readonly properties patterns
- https://ashallendesign.co.uk/blog/data-transfer-objects-dtos-in-php - DTO implementation guide verified with examples
- https://refactoring.guru/extract-method - Extract Method refactoring pattern
- https://blog.mnavarro.dev/the-repository-pattern-done-right - Repository pattern pagination
- https://benjamincrozat.com/php-enums - Modern PHP enum usage guide (2025)

### Tertiary (LOW confidence)
- Various Medium articles on enum/DTO best practices - Used for ecosystem trends, not specific claims
- Stack Overflow discussions - Used to understand common mistakes, not authoritative

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - PHP 8.1 features are documented, codebase version verified
- Architecture: HIGH - Extract Method is established pattern, existing codebase demonstrates good architecture
- Pitfalls: HIGH - Verified from official docs (enum validation) and community consensus (DTO patterns)

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - stable language features, unlikely to change)
