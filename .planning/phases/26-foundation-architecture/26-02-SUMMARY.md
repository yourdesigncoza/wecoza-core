---
phase: 26-foundation-architecture
plan: 02
subsystem: agents
tags: [data-layer, repository, model, migration]
dependency_graph:
  requires: [26-01]
  provides: [agent-repository, agent-model]
  affects: []
tech_stack:
  added: []
  patterns: [repository-pattern, standalone-model, column-whitelisting]
key_files:
  created:
    - src/Agents/Repositories/AgentRepository.php
    - src/Agents/Models/AgentModel.php
  modified: []
decisions:
  - id: D26-02-01
    decision: AgentModel is standalone (NOT extending BaseModel)
    rationale: Preserves FormHelpers integration, preferred_areas logic, and get/set/validate cycle from source
    alternatives_considered: Extending BaseModel would break hydration and field mapping
    status: implemented
metrics:
  duration: 4 minutes
  completed: 2026-02-12T11:16:23Z
---

# Phase 26 Plan 02: Repository + Model Summary

**One-liner:** Complete data layer for Agents module — AgentRepository with 23 methods and standalone AgentModel with validation

## What Was Built

Created the core data access layer for the Agents module:

1. **AgentRepository** — Extends BaseRepository with complete CRUD, meta, notes, absences operations
2. **AgentModel** — Standalone model with validation, FormHelpers integration, preferred areas logic

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1a | AgentRepository core CRUD | 923bb0b | src/Agents/Repositories/AgentRepository.php |
| 1b | AgentRepository meta/notes/absences | fa224c8 | src/Agents/Repositories/AgentRepository.php |
| 2 | Standalone AgentModel | 2cec461 | src/Agents/Models/AgentModel.php |

## Implementation Details

### AgentRepository (23 methods total)

**Core CRUD (12 methods):**
- `createAgent()` — Insert with duplicate email/ID number checks
- `getAgent()`, `getAgentByEmail()`, `getAgentByIdNumber()` — Single agent retrieval
- `getAgents()` — Query with status, search, pagination, ORDER BY validation
- `updateAgent()`, `deleteAgent()` (soft), `deleteAgentPermanently()` (hard)
- `countAgents()`, `searchAgents()`, `getAgentsByStatus()`, `bulkUpdateStatus()`

**Meta methods (4):**
- `addAgentMeta()` — Insert or update if exists
- `getAgentMeta()` — Single or all meta, with unserialization
- `updateAgentMeta()` — Compound WHERE (agent_id AND meta_key)
- `deleteAgentMeta()` — Optional meta_key filtering

**Notes methods (3):**
- `addAgentNote()` — Insert with created_by timestamp
- `getAgentNotes()` — Query with note_type filtering, ordering
- `deleteAgentNotes()` — Delete all notes for agent

**Absences methods (4):**
- `addAgentAbsence()` — Insert with created_by timestamp
- `getAgentAbsences()` — Query with date range filtering
- `deleteAgentAbsences()` — Delete all absences for agent

**Security (column whitelisting):**
- `getAllowedOrderColumns()` — 7 columns
- `getAllowedFilterColumns()` — 8 columns
- `getAllowedInsertColumns()` — 47 columns (all agent fields)
- `getAllowedUpdateColumns()` — 45 columns (insert minus created_at/created_by)

**Sanitization:**
- `sanitizeAgentData()` — Field-by-field sanitization with callbacks
- `sanitizeWorkingArea()` — Returns NULL for empty values (foreign key safety)

### AgentModel

**Key characteristics:**
- **NOT** extending BaseModel — standalone with own get/set/validate cycle
- Delegates to AgentRepository for all DB operations (load, save, delete)
- Preserves all source validation logic (SA ID checksum, phone, email, dates)
- Full FormHelpers integration (get_form_field, set_form_field, map_form_to_database)
- Preferred areas logic (get_preferred_areas, set_preferred_areas for 3 columns)
- Utility methods: get_display_name, get_initials, has_quantum_qualification, get_status_label
- Magic methods: __get, __set, __isset for flexible property access
- Modification tracking: is_modified, get_modified_fields

## Deviations from Plan

None — plan executed exactly as written.

## Technical Patterns

### WHERE Clause Adaptation

**Source (DatabaseService array WHERE):**
```php
$this->db->update('table', $data, ['agent_id' => $id])
```

**Target (wecoza_db string WHERE + colon params):**
```php
wecoza_db()->update('table', $data, 'agent_id = :agent_id', [':agent_id' => $id])
```

**Compound WHERE example:**
```php
wecoza_db()->update('agent_meta', ['meta_value' => $value],
    'agent_id = :agent_id AND meta_key = :meta_key',
    [':agent_id' => $id, ':meta_key' => $key]
);
```

### Conditional WHERE

```php
$where = 'agent_id = :agent_id';
$params = [':agent_id' => $agentId];
if (!empty($metaKey)) {
    $where .= ' AND meta_key = :meta_key';
    $params[':meta_key'] = $metaKey;
}
wecoza_db()->delete('agent_meta', $where, $params);
```

## Verification Results

**All checks passed:**

```bash
# Syntax
php -l src/Agents/Repositories/AgentRepository.php  # ✓ No errors
php -l src/Agents/Models/AgentModel.php              # ✓ No errors

# Structure
grep "extends BaseRepository" AgentRepository.php    # ✓ 1 match
grep "extends BaseModel" AgentModel.php              # ✓ 0 matches

# Wiring
grep "AgentRepository" AgentModel.php                # ✓ 3 matches (load, save, delete)

# Forbidden references (all 0)
grep -r "DatabaseService" src/Agents/                # ✓ 0
grep -r "WECOZA_AGENTS_" src/Agents/                 # ✓ 0
grep -r "wecoza_agents_log" src/Agents/              # ✓ 0
grep -r "AgentQueries" src/Agents/                   # ✓ 0

# Security
grep "':agent_id'" AgentRepository.php               # ✓ Multiple colon-prefixed params
grep "getAllowedInsertColumns" AgentRepository.php   # ✓ 4 whitelisting methods
```

## Self-Check: PASSED

**Created files exist:**
- ✓ src/Agents/Repositories/AgentRepository.php
- ✓ src/Agents/Models/AgentModel.php

**Commits exist:**
- ✓ 923bb0b — feat(26-02): create AgentRepository with core CRUD methods
- ✓ fa224c8 — feat(26-02): add meta, notes, absences methods to AgentRepository
- ✓ 2cec461 — feat(26-02): create standalone AgentModel with validation and FormHelpers

## Next Phase Readiness

**Phase 27 prerequisites:**
- ✓ AgentRepository provides full CRUD for controllers/AJAX handlers
- ✓ AgentModel provides validation and FormHelpers integration for forms
- ✓ All wecoza_db() usage follows string WHERE + colon-prefixed params pattern
- ✓ Zero legacy references (DatabaseService, AgentQueries, WECOZA_AGENTS_*)

**No blockers.** Phase 27 can begin immediately.

## Migration Notes

**Source files processed:**
- `.integrate/wecoza-agents-plugin/src/Database/AgentQueries.php` → `src/Agents/Repositories/AgentRepository.php`
- `.integrate/wecoza-agents-plugin/src/Models/Agent.php` → `src/Agents/Models/AgentModel.php`

**Key adaptations:**
- Removed `$this->db` property → Direct `wecoza_db()` calls
- Removed `init_tables()`, `get_table()` → Literal table names
- Changed text domain `wecoza-agents-plugin` → `wecoza-core`
- Renamed `Agent` → `AgentModel` (consistent naming)
- Changed `AgentQueries` → `AgentRepository` in model load/save/delete

**Preserved unchanged:**
- All validation rules and SA ID checksum logic
- FormHelpers integration (get_form_field, set_form_field, map_form_to_database)
- Preferred areas logic (get/set for 3 columns)
- All utility methods (display_name, initials, quantum_qualification, status_label)
- Magic methods and modification tracking

## Success Criteria: MET

- ✓ AgentRepository extends BaseRepository with all 4 column whitelisting methods
- ✓ AgentRepository has 23 methods migrated from AgentQueries
- ✓ All update/delete use string WHERE + colon-prefixed params
- ✓ AgentModel is standalone (NOT BaseModel)
- ✓ AgentModel delegates to AgentRepository (not AgentQueries)
- ✓ Zero DatabaseService, AgentQueries, WECOZA_AGENTS_*, wecoza_agents_log references
- ✓ All PHP files pass syntax check
