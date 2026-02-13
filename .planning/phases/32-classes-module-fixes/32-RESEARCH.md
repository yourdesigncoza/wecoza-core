# Phase 32: Classes Module Fixes - Research

**Researched:** 2026-02-13
**Domain:** Form data integrity, AJAX security, PostgreSQL JSONB handling
**Confidence:** HIGH

## Summary

Phase 32 addresses critical data loss bugs, security vulnerabilities, and data quality issues in the Classes module identified by comprehensive form field audits. The fixes span four key areas: (1) reverse path data loss where `order_nr` disappears on update, (2) initialization bugs where `class_agent` starts as null instead of copying from `initial_class_agent`, (3) defense-in-depth security failures with unauthenticated QA write endpoints, and (4) input validation gaps in date arrays and JSON-decoded IDs.

All issues are well-documented in `docs/formfieldanalysis/classes-audit.md` with exact file paths, line numbers, and recommended fixes. The codebase uses established patterns: `ClassRepository::getSingleClass()` for reads, `FormDataProcessor::processFormData()` for sanitization, `ClassModel` for persistence, and `AjaxSecurity::requireNonce()` for CSRF protection.

**Primary recommendation:** Follow the audit's surgical fixes. Each requirement has a single-file, single-method change with known validation patterns already in use elsewhere in the module (e.g., `isValidDate()`, `intval()`, `sanitizeText()`).

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress | 6.0+ | Platform foundation | Required by plugin architecture |
| PostgreSQL | 13+ | Primary database | Entire codebase uses `wecoza_db()` connection |
| PDO (pdo_pgsql) | PHP 8.0+ | Database driver | Used by `PostgresConnection` singleton |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_ajax / wp_ajax_nopriv | Core WP | AJAX endpoint registration | All async form operations |
| check_ajax_referer() | Core WP | Nonce verification | Every AJAX write operation |
| sanitize_text_field() | Core WP | Input sanitization | Text inputs, dates |
| intval() / array_map() | PHP | Type casting | Integer IDs, foreign keys |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PostgreSQL JSONB | MySQL JSON | JSONB already deployed, migration not in scope |
| Custom nonce system | WordPress nonces | WordPress nonces are standard, no reason to deviate |
| Manual SQL sanitization | Prepared statements | Repo already uses prepared statements via PDO |

**Installation:**
No new dependencies. All fixes use existing WordPress and PHP core functions.

## Architecture Patterns

### Recommended Data Flow (Form → DB)

**Create operation:**
```
HTML form
  ↓ POST to ClassController::createClass()
  ↓ FormDataProcessor::processFormData($_POST) — sanitize & validate
  ↓ FormDataProcessor::populateClassModel($model, $formData) — hydrate model
  ↓ ClassModel::save() — persist via ClassRepository::insertClass()
  ↓ ClassRepository applies column whitelist (getAllowedInsertColumns)
  ↓ Prepared statement to PostgreSQL
```

**Update operation:**
```
ClassRepository::getSingleClass($id) — fetch existing record
  ↓ Controller passes to view
  ↓ View pre-populates form fields
  ↓ User edits & submits
  ↓ FormDataProcessor::processFormData($_POST)
  ↓ ClassModel::update() — persist via ClassRepository::updateClass()
  ↓ Prepared statement to PostgreSQL
```

**Critical:** Reverse path MUST include all fields that forward path writes. Missing fields = data loss on update.

### Pattern 1: AJAX Security (CSRF + Capability)

**What:** Every AJAX endpoint must verify nonce and check capabilities before processing data.

**When to use:** All `wp_ajax_*` handlers that write data (create, update, delete).

**Example:**
```php
// Source: src/Classes/Controllers/QAController.php:194-197
// CORRECT pattern (authenticated write endpoint)
public function createQAVisit(): void
{
    $this->requireNonce('qa_dashboard_nonce'); // CSRF check
    // Optional: $this->requireCapability('manage_classes'); // Capability check

    $visit_data = [
        'class_id' => $this->input('class_id', 'int', 0),
        // ... sanitize all inputs
    ];

    $qa_model = new QAModel();
    $result = $qa_model->createVisit($visit_data);

    if ($result) {
        $this->sendSuccess(['visit_id' => $result]);
    } else {
        $this->sendError('Failed to create QA visit');
    }
}
```

**Registration:**
```php
// WRITE endpoints: NO nopriv
add_action('wp_ajax_create_qa_visit', [$this, 'createQAVisit']);
// NO wp_ajax_nopriv_create_qa_visit registration

// READ endpoints: nopriv OK if public data
add_action('wp_ajax_get_qa_summary', [$this, 'getQASummary']);
add_action('wp_ajax_nopriv_get_qa_summary', [$this, 'getQASummary']);
```

### Pattern 2: Date Array Sanitization

**What:** Arrays of dates submitted from repeating form fields must be sanitized per-entry AND validated as dates.

**When to use:** Any `date[]` input (stop_dates, restart_dates, backup_agent_dates, exception_dates).

**Example:**
```php
// Source: src/Classes/Services/FormDataProcessor.php:519-533
// CORRECT pattern (exception dates validation)
public static function validateExceptionDates(array $exceptionDates): array
{
    $validated = [];

    foreach ($exceptionDates as $exception) {
        if (is_array($exception) && isset($exception['date']) && self::isValidDate($exception['date'])) {
            $validException = [
                'date' => sanitize_text_field($exception['date']),
                'reason' => isset($exception['reason']) ? sanitize_text_field($exception['reason']) : 'No reason specified'
            ];
            $validated[] = $validException;
        }
    }

    return $validated;
}

// Helper used
public static function isValidDate(mixed $date): bool
{
    if (!is_string($date)) {
        return false;
    }

    $timestamp = strtotime($date);
    return $timestamp !== false && date('Y-m-d', $timestamp) === $date;
}
```

**Apply to stop/restart dates (currently missing):**
```php
// FormDataProcessor.php lines 136-148 (BEFORE FIX)
for ($i = 0; $i < count($stopDates); $i++) {
    if (!empty($stopDates[$i]) && isset($restartDates[$i]) && !empty($restartDates[$i])) {
        $stopRestartDates[] = [
            'stop_date' => $stopDates[$i], // ❌ No sanitization
            'restart_date' => $restartDates[$i] // ❌ No validation
        ];
    }
}

// AFTER FIX (apply exception dates pattern)
for ($i = 0; $i < count($stopDates); $i++) {
    $stopDate = self::sanitizeText($stopDates[$i]);
    $restartDate = self::sanitizeText($restartDates[$i]);

    if (self::isValidDate($stopDate) && self::isValidDate($restartDate)) {
        $stopRestartDates[] = [
            'stop_date' => $stopDate,
            'restart_date' => $restartDate
        ];
    }
}
```

### Pattern 3: JSON-Decoded ID Array Sanitization

**What:** After decoding JSON arrays of IDs (learner_ids, exam_learners), filter and cast to integers.

**When to use:** Any JSONB column storing integer arrays.

**Example:**
```php
// FormDataProcessor.php lines 78-95 (CURRENT — partial sanitization)
$learnerIds = [];
if (isset($data['class_learners_data']) && is_string($data['class_learners_data']) && !empty($data['class_learners_data'])) {
    $learnerData = json_decode(stripslashes($data['class_learners_data']), true);
    if (is_array($learnerData)) {
        $learnerIds = $learnerData; // ❌ Raw array, could contain malformed data
    }
}
$processed['learner_ids'] = $learnerIds;

// RECOMMENDED FIX
$learnerIds = [];
if (isset($data['class_learners_data']) && is_string($data['class_learners_data']) && !empty($data['class_learners_data'])) {
    $learnerData = json_decode(stripslashes($data['class_learners_data']), true);
    if (is_array($learnerData)) {
        // Sanitize: filter non-numeric, cast to int, remove zeros/negatives
        $learnerIds = array_filter(
            array_map('intval', $learnerData),
            fn($id) => $id > 0
        );
    }
}
$processed['learner_ids'] = $learnerIds;
```

### Pattern 4: Reverse Path Field Inclusion

**What:** Repository `getSingleClass()` must include ALL fields that the form writes, otherwise updates overwrite with null.

**When to use:** Any time a new field is added to the form or an existing field was missed.

**Example:**
```php
// ClassRepository.php lines 596-625 (getSingleClass method)
public static function getSingleClass(int $class_id): ?array
{
    $classModel = ClassModel::getById($class_id);
    if (!$classModel) {
        return null;
    }

    $result = [
        'class_id' => $classModel->getId(),
        'client_id' => $classModel->getClientId(),
        // ... 20+ fields ...
        'class_notes_data' => $classModel->getClassNotesData(),
        'created_at' => $classModel->getCreatedAt(),
        'updated_at' => $classModel->getUpdatedAt(),
        // ❌ MISSING: 'order_nr' => $classModel->getOrderNr(),
    ];

    // ... rest of method
    return $result;
}

// FIX: Add after line 624
'order_nr' => $classModel->getOrderNr(),
```

### Anti-Patterns to Avoid

- **Registering write endpoints with `wp_ajax_nopriv_`:** Defense-in-depth failure. Even if site requires login globally, AJAX endpoints should enforce authentication.
- **Skipping date validation on date arrays:** `strtotime()` accepts malformed input like "tomorrow" or "last Tuesday". Use `isValidDate()` for strict YYYY-MM-DD validation.
- **Trusting JSON-decoded data structure:** JSON can contain any data type. Always validate and cast after decode.
- **Omitting fields from reverse path:** If FormDataProcessor writes field X, ClassRepository must read field X. Missing = silent data loss.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date validation | Custom regex or strtotime() alone | `FormDataProcessor::isValidDate()` (already exists) | Handles edge cases: strtotime("tomorrow") passes regex, fails strict format check |
| Integer array sanitization | Manual foreach with type checks | `array_filter(array_map('intval', $arr), fn($id) => $id > 0)` | One-liner, handles null/string/negative in single pass |
| CSRF protection | Custom token system | WordPress `check_ajax_referer()` + `wp_create_nonce()` | Standard WP pattern, integrates with session, works with AJAX |
| Column whitelisting | Manual array checks in update methods | `getAllowedUpdateColumns()` + `filterAllowedColumns()` (BaseRepository pattern) | Already implemented, prevents SQL injection via column name |

**Key insight:** WeCoza Core already has validation helpers (`isValidDate()`, `isValidTime()`, `sanitizeText()`), repository column whitelists, and AjaxSecurity utilities. These patterns are proven across Learners/Agents/Clients modules. Don't reinvent for Classes fixes.

## Common Pitfalls

### Pitfall 1: Registering Write AJAX Endpoints with `nopriv`
**What goes wrong:** Unauthenticated users can create/modify/delete data via AJAX, bypassing WordPress authentication.

**Why it happens:** Copy-paste from read-only endpoints (where `nopriv` is sometimes intentional for public data) without considering the operation type.

**How to avoid:**
- Read-only endpoints (`get_*`, `fetch_*`): `nopriv` MAY be OK if data is public
- Write endpoints (`create_*`, `update_*`, `delete_*`, `save_*`, `submit_*`): NEVER use `nopriv`
- When in doubt, omit `nopriv` registration

**Warning signs:**
```php
// ❌ BAD: Write operation with nopriv
add_action('wp_ajax_create_qa_visit', [$this, 'createQAVisit']);
add_action('wp_ajax_nopriv_create_qa_visit', [$this, 'createQAVisit']);

// ✅ GOOD: Write operation, authenticated only
add_action('wp_ajax_create_qa_visit', [$this, 'createQAVisit']);
```

**Current violations in QAController (lines 42, 44, 48, 52):**
- `wp_ajax_nopriv_create_qa_visit` — allows unauthenticated QA visit creation
- `wp_ajax_nopriv_export_qa_reports` — allows unauthenticated export
- `wp_ajax_nopriv_delete_qa_report` — allows unauthenticated deletion
- `wp_ajax_nopriv_submit_qa_question` — allows unauthenticated submissions

### Pitfall 2: Missing Reverse Path Fields
**What goes wrong:** Form field has value on load, user doesn't touch it, submit saves null/empty, original data lost.

**Why it happens:** Developer adds field to form view and FormDataProcessor but forgets to add getter to ClassRepository::getSingleClass().

**How to avoid:**
1. When adding new field, checklist:
   - [ ] HTML form field (view)
   - [ ] FormDataProcessor processes it (forward path)
   - [ ] ClassModel has getter/setter
   - [ ] ClassRepository::getSingleClass() includes it (reverse path)
   - [ ] Repository column whitelist allows it
2. Use audit methodology: trace field from DB → repo → controller → view → POST → processor → model → DB

**Warning signs:**
- Field shows "empty" on edit form despite DB having data
- Field value resets to null/default after update (but create works fine)
- No error messages, just silent data loss

**How to detect early:**
```bash
# Check if field exists in getSingleClass result array
grep "order_nr" src/Classes/Repositories/ClassRepository.php
# If missing from getSingleClass but present in ClassModel, it's a reverse path gap
```

### Pitfall 3: `site_id` Type Inconsistency
**What goes wrong:** Field stored as string when DB expects integer, or vice versa. Queries fail, foreign key checks break.

**Why it happens:** FormDataProcessor checks `!is_array()` but doesn't cast to `int` like it does for `client_id`.

**How to avoid:**
- Check DB schema: if column is `integer` or foreign key, cast with `intval()`
- Be consistent: if `client_id` uses `intval()`, and `site_id` is also an FK, it should too
- Exception: if site IDs can be non-numeric strings (UUID, composite key), keep as string

**Warning signs:**
```php
// FormDataProcessor.php
$processed['client_id'] = isset($data['client_id']) && !empty($data['client_id']) ? intval($data['client_id']) : null; // ✅
$processed['site_id'] = isset($data['site_id']) && !is_array($data['site_id']) ? $data['site_id'] : null; // ❌ No intval
```

**Current state:** `sites` table `site_id` is `integer` (confirmed from schema queries in Learners module). Should be `intval()`.

### Pitfall 4: Static Data Instead of DB Queries
**What goes wrong:** Agent is renamed, deactivated, or new agent added. Class form still shows old hardcoded names. Data integrity suffers when IDs don't match names.

**Why it happens:** Hardcoded arrays are faster to implement initially, no DB query overhead. But data drift makes them a liability.

**How to avoid:**
- Reference data (agents, supervisors, clients, locations) should ALWAYS come from DB
- Use caching if performance is a concern (see `ClassRepository::getLearners()` — uses transient cache)
- Static arrays acceptable ONLY for truly static data (SETA names, enum values like "Pending/Completed/Cancelled")

**Warning signs:**
```php
// ❌ BAD: Hardcoded agent list
public static function getAgents(): array
{
    return [
        ['id' => 1, 'name' => 'Michael M. van der Berg'],
        ['id' => 2, 'name' => 'Thandi T. Nkosi'],
        // ...
    ];
}

// ✅ GOOD: DB query (with optional caching)
public static function getAgents(): array
{
    $cache_key = 'wecoza_class_agents';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $db = wecoza_db();
    $sql = "SELECT agent_id AS id, CONCAT(first_name, ' ', surname) AS name
            FROM agents
            WHERE status = 'active'
            ORDER BY surname, first_name";
    $stmt = $db->query($sql);

    $agents = [];
    while ($row = $stmt->fetch()) {
        $agents[] = [
            'id' => (int)$row['id'],
            'name' => sanitize_text_field($row['name'])
        ];
    }

    set_transient($cache_key, $agents, 12 * HOUR_IN_SECONDS);
    return $agents;
}
```

**Current violations:**
- `ClassRepository::getAgents()` — lines 419-437 — static array of 15 agents
- `ClassRepository::getSupervisors()` — lines 443-451 — static array of 5 supervisors

### Pitfall 5: Pre-selecting Wrong Field in Dropdown
**What goes wrong:** User edits class, sees "Initial Agent" dropdown pre-selected with current agent instead of original agent. Semantically incorrect, causes confusion.

**Why it happens:** Copy-paste error or misunderstanding field semantics: `class_agent` tracks current (changes via replacements), `initial_class_agent` tracks original (never changes).

**How to avoid:**
- Understand field semantics: "initial" = value at creation time, never updated
- Check view template: field named `initial_class_agent` should pre-select from `$data['initial_class_agent']`, NOT `$data['class_agent']`

**Warning signs:**
```php
// update-class.php line 1509
// ❌ WRONG: Pre-selecting initial_class_agent from class_agent value
<option value="<?php echo $agent['id']; ?>"
    <?php echo (isset($data['class_data']['class_agent']) && $data['class_data']['class_agent'] == $agent['id']) ? 'selected' : ''; ?>>
    <?php echo $agent['name']; ?>
</option>

// ✅ CORRECT: Use initial_class_agent for initial_class_agent field
<option value="<?php echo $agent['id']; ?>"
    <?php echo (isset($data['class_data']['initial_class_agent']) && $data['class_data']['initial_class_agent'] == $agent['id']) ? 'selected' : ''; ?>>
    <?php echo $agent['name']; ?>
</option>
```

## Code Examples

Verified patterns from official sources.

### Sanitize and Validate Date Array (Exception Dates)
```php
// Source: src/Classes/Services/FormDataProcessor.php:519-533
public static function validateExceptionDates(array $exceptionDates): array
{
    $validated = [];

    foreach ($exceptionDates as $exception) {
        if (is_array($exception) && isset($exception['date']) && self::isValidDate($exception['date'])) {
            $validException = [
                'date' => sanitize_text_field($exception['date']),
                'reason' => isset($exception['reason']) ? sanitize_text_field($exception['reason']) : 'No reason specified'
            ];
            $validated[] = $validException;
        }
    }

    return $validated;
}
```

### Filter and Cast Integer Array
```php
// Pattern from Learners module (apply to learner_ids/exam_learners)
$learnerIds = array_filter(
    array_map('intval', $rawJsonDecoded),
    fn($id) => $id > 0
);
```

### Query Agents from Database (with Caching)
```php
// Pattern from ClassRepository::getLearners() (lines 278-413)
public static function getAgents(): array
{
    $cache_key = 'wecoza_class_agents';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $db = wecoza_db();
    $sql = "SELECT agent_id, first_name, surname, status
            FROM agents
            WHERE status = 'active'
            ORDER BY surname, first_name";
    $stmt = $db->query($sql);

    $agents = [];
    while ($row = $stmt->fetch()) {
        $agents[] = [
            'id' => (int)$row['agent_id'],
            'name' => sanitize_text_field($row['first_name'] . ' ' . $row['surname'])
        ];
    }

    set_transient($cache_key, $agents, 12 * HOUR_IN_SECONDS);
    return $agents;
}
```

### AJAX Handler with Security
```php
// Source: src/Classes/Controllers/QAController.php:194-228
public function createQAVisit(): void
{
    $this->requireNonce('qa_dashboard_nonce'); // Inherited from BaseController

    $visit_data = [
        'class_id' => $this->input('class_id', 'int', 0),
        'visit_date' => $this->input('visit_date', 'string', ''),
        'visit_type' => $this->input('visit_type', 'string', 'routine'),
        'qa_officer_id' => $this->input('qa_officer_id', 'int', 0),
        // ... more sanitized inputs
    ];

    $qa_model = new QAModel();
    $result = $qa_model->createVisit($visit_data);

    if ($result) {
        $this->sendSuccess(['visit_id' => $result], 'QA visit created successfully');
    } else {
        $this->sendError('Failed to create QA visit');
    }
}
```

### Initialize class_agent from initial_class_agent on Create
```php
// Source: Audit recommendation (CLS-02)
// Location: FormDataProcessor.php after line 68
$processed['class_agent'] = isset($data['class_agent']) && !empty($data['class_agent'])
    ? intval($data['class_agent'])
    : null;
$processed['initial_class_agent'] = isset($data['initial_class_agent']) && !empty($data['initial_class_agent'])
    ? intval($data['initial_class_agent'])
    : null;

// NEW: If class_agent is empty but initial_class_agent is set, copy it
if (empty($processed['class_agent']) && !empty($processed['initial_class_agent'])) {
    $processed['class_agent'] = $processed['initial_class_agent'];
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Static agent arrays | DB queries with caching | Not yet changed (CLS-08) | Agents module exists since Phase 18, agents table live in production |
| `strtotime()` for date validation | `isValidDate()` with strict format check | Already implemented (used in exception dates) | Rejects "tomorrow", "last week" inputs |
| No JSONB ID sanitization | `array_filter(array_map('intval', ...))` | Learners module uses it, Classes needs it | Prevents malformed IDs in JSONB columns |
| Per-module AJAX security | Centralized `AjaxSecurity` helper | Phase 18 (Events module refactor) | Consistent nonce/capability checks |

**Deprecated/outdated:**
- Manual nonce checks with `wp_verify_nonce()`: Use `AjaxSecurity::requireNonce()` instead (includes error response)
- Direct `$_POST` access: Use `AjaxSecurity::post($key, $type)` or BaseController `$this->input()` (auto-sanitizes)
- `sanitize_text_field()` for dates: Use `isValidDate()` first, THEN sanitize (prevents invalid formats)

## Open Questions

1. **Does `site_id` need to be integer or can it be string?**
   - What we know: `sites` table has `site_id` as integer FK, `client_id` uses `intval()` in same processor
   - What's unclear: Any legacy string site IDs in production?
   - Recommendation: Cast to `intval()` to match `client_id` pattern unless confirmed otherwise

2. **Should `class_agent` be updated when agent replacements are made?**
   - What we know: `class_agent` column exists, `agent_replacements` table exists, but no code updates `class_agent` when replacements happen
   - What's unclear: Original intent — is `class_agent` a denormalized "current agent" cache, or is it vestigial?
   - Recommendation: For CLS-02 fix, just initialize it on create. Updating it on replacement is out of scope (would need trigger or service method)

3. **Are there existing classes with `order_nr` data that would be lost?**
   - What we know: Field exists in DB schema, FormDataProcessor processes it, ClassModel has getter/setter
   - What's unclear: Production data — how many classes have non-null `order_nr`?
   - Recommendation: Add field to reverse path regardless. If data exists, fix prevents loss. If data doesn't exist, fix is harmless.

## Sources

### Primary (HIGH confidence)
- docs/formfieldanalysis/classes-audit.md — comprehensive audit with exact line numbers and recommendations
- src/Classes/Repositories/ClassRepository.php — repository with column whitelists and getSingleClass() method
- src/Classes/Services/FormDataProcessor.php — form data processing with validation helpers
- src/Classes/Controllers/QAController.php — AJAX endpoints registration and security patterns
- core/Helpers/AjaxSecurity.php — centralized security utilities
- views/classes/components/class-capture-partials/update-class.php — form view with pre-selection logic

### Secondary (MEDIUM confidence)
- src/Agents/Models/AgentModel.php — agents table structure (agent_id PK, first_name, surname, status)
- src/Classes/Models/ClassModel.php — class entity getters/setters
- BaseRepository pattern (column whitelisting) — used across all modules

### Tertiary (LOW confidence)
- None — all findings verified from codebase analysis

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress/PostgreSQL/PDO confirmed by working codebase
- Architecture: HIGH - All patterns extracted from existing code, not hypothetical
- Pitfalls: HIGH - All identified from actual audit findings with file/line references
- Open questions: MEDIUM - Production data state unknown without DB query

**Research date:** 2026-02-13
**Valid until:** 60 days (stable patterns, unlikely to change without major refactor)
