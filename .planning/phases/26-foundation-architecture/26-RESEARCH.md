# Phase 26: Foundation Architecture - Research

**Researched:** 2026-02-12
**Domain:** PHP Namespace Migration & Database Abstraction Layer Transition
**Confidence:** HIGH

## Summary

Phase 26 migrates the Agents module from a standalone plugin structure to wecoza-core's PSR-4 architecture. This is a **code relocation and API adaptation task**, not a feature development task. The primary challenges are:

1. **Database API Signature Mismatch**: `DatabaseService::update()` and `::delete()` accept associative array WHERE clauses, while `PostgresConnection::update()` and `::delete()` require SQL string WHERE clauses with separate parameter arrays.
2. **Hardcoded RETURNING Clause**: Source `DatabaseService::insert()` uses `RETURNING agent_id`, but target must use generic primary key detection.
3. **Model Architecture Preservation**: Agent model is intentionally standalone (NOT extending BaseModel) and must remain so.

**Primary recommendation:** Migrate files to `src/Agents/` namespace, adapt database method signatures carefully (WHERE array → WHERE string), preserve Agent model as standalone, implement AgentRepository with column whitelisting, and migrate helpers without dependency on plugin-specific constants.

## Standard Stack

### Core Components
| Component | Location | Purpose | Why Standard |
|-----------|----------|---------|--------------|
| PostgresConnection | `core/Database/PostgresConnection.php` | Database singleton with CRUD methods | Wecoza-core's centralized DB abstraction |
| BaseRepository | `core/Abstract/BaseRepository.php` | Repository pattern with column whitelisting | Security-first data access layer |
| wecoza_db() | `core/Helpers/functions.php` | Global database accessor | Consistent API across all modules |

### Supporting
| Component | Location | Purpose | When to Use |
|-----------|----------|---------|-------------|
| BaseModel | `core/Abstract/BaseModel.php` | Model with hydration, casting, toArray() | For new models; NOT for Agent (standalone) |
| wecoza_sanitize_value() | `core/Helpers/functions.php` | Type-safe sanitization | All input sanitization |
| wecoza_log() | `core/Helpers/functions.php` | Debug logging | Replaces `wecoza_agents_log()` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Standalone Agent model | Extend BaseModel | Would break existing get/set/validate cycle; model is intentionally standalone |
| AgentRepository | Keep AgentQueries pattern | Repository extends BaseRepository for column whitelisting security |

**Installation:**
```bash
# No installation - migration task only
# Files already exist in .integrate/wecoza-agents-plugin
```

## Architecture Patterns

### Recommended Project Structure
```
src/Agents/
├── Models/              # AgentModel.php (standalone, NOT BaseModel)
├── Repositories/        # AgentRepository.php (extends BaseRepository)
├── Helpers/             # ValidationHelper, FormHelpers
├── Services/            # WorkingAreasService
└── Controllers/         # Future: Shortcodes will migrate here in Phase 27
```

### Pattern 1: Database API Signature Adaptation

**What:** Transform WHERE clauses from associative arrays to SQL strings with parameter arrays.

**When to use:** Every `update()` and `delete()` call during migration.

**Source (DatabaseService):**
```php
// update(table, data, whereArray)
$this->db->update('agents', $data, ['agent_id' => $id]);

// delete(table, whereArray)
$this->db->delete('agents', ['agent_id' => $id]);
```

**Target (PostgresConnection):**
```php
// update(table, data, whereString, whereParams)
wecoza_db()->update('agents', $data, 'agent_id = :agent_id', [':agent_id' => $id]);

// delete(table, whereString, params)
wecoza_db()->delete('agents', 'agent_id = :agent_id', [':agent_id' => $id]);
```

**Critical:** Parameter keys MUST include colons (`:agent_id`, not `agent_id`).

### Pattern 2: Repository with Column Whitelisting

**What:** AgentRepository extends BaseRepository and defines allowed columns for SQL injection prevention.

**When to use:** All agent database operations.

**Example:**
```php
// Source: wecoza-core/src/Learners/Repositories/LearnerRepository.php
namespace WeCoza\Agents\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

class AgentRepository extends BaseRepository
{
    protected static string $table = 'agents';
    protected static string $primaryKey = 'agent_id';

    protected function getAllowedOrderColumns(): array
    {
        return ['agent_id', 'first_name', 'surname', 'email_address', 'created_at', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['agent_id', 'email_address', 'sa_id_no', 'status', 'created_at'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'first_name', 'second_name', 'surname', 'initials', 'gender', 'race',
            'id_type', 'sa_id_no', 'passport_number',
            'tel_number', 'email_address',
            'residential_address_line', 'address_line_2', 'city', 'province', 'residential_postal_code',
            'preferred_working_area_1', 'preferred_working_area_2', 'preferred_working_area_3',
            'sace_number', 'sace_registration_date', 'sace_expiry_date', 'phase_registered', 'subjects_registered',
            'highest_qualification',
            'quantum_maths_score', 'quantum_science_score', 'quantum_assessment',
            'criminal_record_date', 'criminal_record_file',
            'signed_agreement_date', 'signed_agreement_file',
            'bank_name', 'account_holder', 'bank_account_number', 'bank_branch_code', 'account_type',
            'agent_training_date', 'agent_notes', 'status',
            'created_at', 'updated_at', 'created_by', 'updated_by'
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return array_diff($this->getAllowedInsertColumns(), ['created_at', 'created_by']);
    }
}
```

### Pattern 3: Standalone Model Preservation

**What:** Agent model does NOT extend BaseModel. It has its own get/set/validate cycle.

**Why:** Model has custom logic (`get_preferred_areas()`, `set_preferred_areas()`, `get_form_field()`, FormHelpers integration) that would conflict with BaseModel's hydration.

**Migration:**
```php
// Source: .integrate/wecoza-agents-plugin/src/Models/Agent.php
namespace WeCoza\Agents\Models;
class Agent { /* standalone */ }

// Target: src/Agents/Models/AgentModel.php
namespace WeCoza\Agents\Models;
class AgentModel { /* still standalone, uses wecoza_db() */ }
```

**CRITICAL:** Do NOT extend BaseModel. Keep standalone.

### Pattern 4: Query Method Chaining Replacement

**What:** Replace `$this->db->query()->fetch()` chains with direct `wecoza_db()->getRow()`.

**Source:**
```php
$result = $this->db->query($sql, $params);
return $result ? $result->fetch() : null;
```

**Target:**
```php
return wecoza_db()->getRow($sql, $params) ?: null;
```

**Similar:**
- `query()->fetchAll()` → `getAll()`
- `query()->fetchColumn()` → `getValue()`

### Anti-Patterns to Avoid

- **Extending BaseModel for Agent**: Agent model is intentionally standalone. BaseModel hydration would conflict with custom get/set logic.
- **Hardcoded RETURNING agent_id**: Use `PostgresConnection::insert()` which auto-detects primary key.
- **Forgetting colon in WHERE params**: `[':agent_id' => $id]` not `['agent_id' => $id]`.
- **Using DatabaseService patterns**: Source patterns (array WHERE) don't work in target (string WHERE).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Column whitelisting | Manual array_intersect checks | BaseRepository::getAllowed*Columns() | Security best practice, audit trail |
| Database connection | Custom PDO singleton | wecoza_db() | Centralized, tested, consistent |
| Input sanitization | Custom regex/filters | wecoza_sanitize_value() | Handles 15+ types, unit tested |
| Primary key detection | Hardcoded RETURNING | PostgresConnection::insert() | Auto-detects id/agent_id/client_id/etc |

**Key insight:** wecoza-core provides battle-tested abstractions. Migration is adaptation, not rewrite.

## Common Pitfalls

### Pitfall 1: WHERE Clause Array → String Conversion Mistakes

**What goes wrong:** Forgetting to add colon prefix to parameter keys causes PDO binding failure.

**Why it happens:** Source uses `['agent_id' => $id]`, target requires `[':agent_id' => $id]`.

**How to avoid:**
1. **Pattern match:** Search for all `->update(` and `->delete(` calls.
2. **Transform WHERE:**
   - Source: `['field' => $value]` → Target: `'field = :field'`, `[':field' => $value]`
3. **Verify parameter keys:** Always prefix with colon.

**Warning signs:**
- PDO error: "Invalid parameter number: parameter was not defined"
- Update/delete returns 0 affected rows unexpectedly

**Example fix:**
```php
// WRONG
wecoza_db()->update('agents', $data, 'agent_id = :agent_id', ['agent_id' => $id]);

// CORRECT
wecoza_db()->update('agents', $data, 'agent_id = :agent_id', [':agent_id' => $id]);
```

### Pitfall 2: Hardcoded RETURNING agent_id

**What goes wrong:** `RETURNING agent_id` breaks if table uses different primary key.

**Why it happens:** Source DatabaseService hardcodes `RETURNING agent_id` in insert().

**How to avoid:** Use `wecoza_db()->insert()` which auto-detects primary key via `tableHasColumn()`.

**Warning signs:**
- Insert fails with "column agent_id does not exist"
- Insert succeeds but returns wrong ID

**Example fix:**
```php
// WRONG (source pattern)
$sql = "INSERT INTO ... RETURNING agent_id";

// CORRECT (target pattern)
$id = wecoza_db()->insert('agents', $data);
// PostgresConnection detects 'agent_id' column automatically
```

### Pitfall 3: Forgetting to Replace DatabaseService::getInstance()

**What goes wrong:** Code still references `DatabaseService::getInstance()` instead of `wecoza_db()`.

**Why it happens:** Search/replace misses namespaced calls or property assignments.

**How to avoid:**
1. **Global search:** `grep -r "DatabaseService" src/Agents/`
2. **Replace patterns:**
   - `DatabaseService::getInstance()` → `wecoza_db()`
   - `$this->db = DatabaseService::getInstance()` → (remove, use `wecoza_db()` directly)
   - `use WeCoza\Agents\Database\DatabaseService` → (remove)

**Warning signs:**
- Fatal error: Class 'WeCoza\Agents\Database\DatabaseService' not found
- Code references `$this->db` when it shouldn't exist

### Pitfall 4: Breaking Agent Model's Standalone Architecture

**What goes wrong:** Attempting to extend BaseModel breaks Agent's custom get/set/validate cycle.

**Why it happens:** Misunderstanding that Agent model is intentionally standalone.

**How to avoid:**
1. **DO NOT extend BaseModel** for Agent model.
2. **Preserve:** `get()`, `set()`, `get_form_field()`, `set_form_field()`, FormHelpers integration.
3. **Only change:** Database calls (`DatabaseService` → `wecoza_db()`), namespace.

**Warning signs:**
- Validation errors on fields that should pass
- FormHelpers mapping breaks
- `get_preferred_areas()` returns wrong data

### Pitfall 5: Missing Column in Whitelists

**What goes wrong:** Insert/update silently fails because column not in `getAllowedInsertColumns()` or `getAllowedUpdateColumns()`.

**Why it happens:** Whitelist doesn't include all Agent model fields.

**How to avoid:**
1. **Copy from Agent model defaults** (lines 66-141 in source).
2. **Cross-reference:** All fields in `Agent::$defaults` must be in repository whitelists.
3. **Test:** Verify insert/update includes all expected columns.

**Warning signs:**
- Data missing after save (e.g., `second_name` not saved)
- No error messages, but fields empty in database

## Code Examples

Verified patterns from wecoza-core:

### Database Query Replacement

**Source (AgentQueries.php):**
```php
$result = $this->db->query($sql, $params);
return $result ? $result->fetch() : null;
```

**Target (AgentRepository.php):**
```php
return wecoza_db()->getRow($sql, $params) ?: null;
```

### Update Method Signature Adaptation

**Source (AgentQueries::update_agent):**
```php
public function update_agent($agent_id, $data) {
    $clean_data = $this->sanitize_agent_data($data);
    $clean_data['updated_at'] = current_time('mysql');
    $clean_data['updated_by'] = get_current_user_id();

    $result = $this->db->update(
        $this->get_table('agents'),
        $clean_data,
        array('agent_id' => $agent_id)  // WHERE array
    );

    return $result !== false;
}
```

**Target (AgentRepository::updateAgent):**
```php
public function updateAgent(int $agentId, array $data): bool
{
    $clean_data = $this->sanitize_agent_data($data);
    $clean_data['updated_at'] = current_time('mysql');
    $clean_data['updated_by'] = get_current_user_id();

    $result = wecoza_db()->update(
        'agents',
        $clean_data,
        'agent_id = :agent_id',        // WHERE string
        [':agent_id' => $agentId]      // Params with colons
    );

    return $result !== false;
}
```

### Repository Column Whitelisting

**Source (LearnerRepository.php - proven pattern):**
```php
protected function getAllowedFilterColumns(): array
{
    return [
        'learner_id', 'first_name', 'surname', 'email_address',
        'sa_id_no', 'status', 'created_at'
    ];
}

protected function getAllowedInsertColumns(): array
{
    return [
        'first_name', 'second_name', 'surname', 'gender', 'race',
        'email_address', 'tel_number', /* ... all fillable fields ... */
        'created_at', 'updated_at', 'created_by', 'updated_by'
    ];
}
```

### Agent Model Database Integration

**Source (Agent.php):**
```php
public function save() {
    if (!$this->validate()) {
        return false;
    }

    $agent_queries = new \WeCoza\Agents\Database\AgentQueries();
    $save_data = $this->get_save_data();

    if ($this->id) {
        $success = $agent_queries->update_agent($this->id, $save_data);
        return $success ? $this->id : false;
    } else {
        $id = $agent_queries->create_agent($save_data);
        if ($id) {
            $this->id = $id;
            return $id;
        }
    }
    return false;
}
```

**Target (AgentModel.php):**
```php
public function save() {
    if (!$this->validate()) {
        return false;
    }

    $repository = new \WeCoza\Agents\Repositories\AgentRepository();
    $save_data = $this->get_save_data();

    if ($this->id) {
        $success = $repository->updateAgent($this->id, $save_data);
        return $success ? $this->id : false;
    } else {
        $id = $repository->createAgent($save_data);
        if ($id) {
            $this->id = $id;
            return $id;
        }
    }
    return false;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Plugin-specific DatabaseService | Centralized wecoza_db() | Phase 25 (v2.0) | Consistent API, no duplicate DB classes |
| Hardcoded RETURNING agent_id | Auto-detected primary key | Phase 25 (v2.0) | Works with any table structure |
| Manual column filtering | BaseRepository whitelisting | Phase 25 (v2.0) | SQL injection prevention, audit trail |
| WHERE array signature | WHERE string + params | Phase 25 (v2.0) | Explicit SQL, better debugging |

**Deprecated/outdated:**
- **DatabaseService class**: Replaced by PostgresConnection in wecoza-core
- **DatabaseLogger class**: Not migrated (use wecoza_log() instead)
- **WECOZA_AGENTS_* constants**: Not migrated (use wecoza_plugin_path() instead)
- **wecoza_agents_log()**: Replaced by wecoza_log()

## Open Questions

1. **AgentQueries meta methods (add_agent_meta, get_agent_meta, etc.)**
   - What we know: Source has agent_meta, agent_notes, agent_absences tables
   - What's unclear: Are these tables actively used? Should they be migrated?
   - Recommendation: Migrate table structure, but defer meta/notes/absences CRUD to Phase 27 if not immediately needed

2. **DatabaseLogger migration**
   - What we know: Source has DatabaseLogger for query logging
   - What's unclear: Is this needed? wecoza_log() exists
   - Recommendation: Don't migrate DatabaseLogger. Use wecoza_log() for debugging

3. **FormHelpers field mapping edge cases**
   - What we know: FormHelpers maps form fields to database columns
   - What's unclear: Are all mappings still needed after migration?
   - Recommendation: Migrate FormHelpers as-is, verify mappings during Phase 27 (forms/shortcodes)

## Sources

### Primary (HIGH confidence)
- PostgresConnection API: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/core/Database/PostgresConnection.php` (lines 313-428)
- BaseRepository pattern: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/core/Abstract/BaseRepository.php` (lines 59-101, 381-488)
- Agent model source: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-agents-plugin/src/Models/Agent.php` (full file)
- AgentQueries source: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-agents-plugin/src/Database/AgentQueries.php` (full file)
- DatabaseService API: `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-agents-plugin/src/Database/DatabaseService.php` (lines 200-399)

### Secondary (MEDIUM confidence)
- Phase context from user: Transformation rules table, bug warnings, requirements

### Tertiary (LOW confidence)
- None (all findings verified from source code)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components exist in wecoza-core, patterns proven in Learners/Classes modules
- Architecture: HIGH - Verified from BaseRepository, PostgresConnection, existing model patterns
- Pitfalls: HIGH - Derived from actual API signature differences and requirements warnings

**Research date:** 2026-02-12
**Valid until:** 2026-04-12 (60 days - stable architecture, unlikely to change)
