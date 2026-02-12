---
phase: 26-foundation-architecture
plan: 01
subsystem: agents
tags: [foundation, infrastructure, autoloading, database]
completed: 2026-02-12

dependency_graph:
  requires: []
  provides:
    - WeCoza\Agents\ namespace autoloading
    - agent_id RETURNING support in PostgresConnection
    - ValidationHelper (SA ID, passport, phone, bank validation)
    - FormHelpers (form-to-database field mapping)
    - WorkingAreasService (working areas lookup)
  affects:
    - Plan 02 (repository/model can now use wecoza_db() correctly)
    - All future Agents module development

tech_stack:
  added:
    - PSR-4 autoloader entry for WeCoza\Agents\ namespace
  patterns:
    - Namespace registration in wecoza-core.php
    - RETURNING clause detection in PostgresConnection::insert()
    - Static validation helpers
    - Static service classes

key_files:
  created:
    - src/Agents/Helpers/ValidationHelper.php
    - src/Agents/Helpers/FormHelpers.php
    - src/Agents/Services/WorkingAreasService.php
  modified:
    - wecoza-core.php (namespace registration)
    - core/Database/PostgresConnection.php (agent_id RETURNING support)

decisions: []

metrics:
  duration: "~2 minutes"
  tasks_completed: 2
  files_created: 3
  files_modified: 2
  commits: 2
---

# Phase 26 Plan 01: Foundation Architecture Summary

**One-liner:** Register WeCoza\Agents\ namespace, add agent_id RETURNING support to PostgresConnection, and migrate 3 helper/service files with text domain standardization.

## Objective

Establish the autoloading foundation and database compatibility for the Agents module, enabling Plan 02 to build repositories and models that use wecoza_db() correctly.

## Tasks Completed

### Task 1: Register namespace and fix PostgresConnection RETURNING detection
**Status:** ✅ Complete
**Commit:** 91b5575

**Changes:**
- Added `'WeCoza\\Agents\\'` namespace mapping to `src/Agents/` in PSR-4 autoloader (wecoza-core.php)
- Added `agent_id` to PostgresConnection::insert() RETURNING candidate list (after 'id' in detection array)
- Enables `wecoza_db()->insert('agents', $data)` to return new agent IDs via RETURNING clause

**Files modified:**
- wecoza-core.php (line 54: namespace registration)
- core/Database/PostgresConnection.php (line 332: agent_id in RETURNING candidates)

**Verification:**
- ✅ Namespace registered: `grep "WeCoza.*Agents" wecoza-core.php` shows entry
- ✅ agent_id in RETURNING: `grep "agent_id" core/Database/PostgresConnection.php` confirms addition
- ✅ PHP syntax valid: Both files pass `php -l` check

---

### Task 2: Migrate helpers with namespace and text domain changes
**Status:** ✅ Complete
**Commit:** 4bc9c90

**Changes:**
- Created `src/Agents/Helpers/` and `src/Agents/Services/` directories
- Copied ValidationHelper.php with all text domain occurrences changed from `'wecoza-agents-plugin'` to `'wecoza-core'` (36 occurrences updated)
- Copied FormHelpers.php (no changes needed - already correct namespace, no text domain references)
- Copied WorkingAreasService.php (no changes needed - already correct namespace, pure data class)

**Files created:**
- src/Agents/Helpers/ValidationHelper.php (611 lines, 13 validation methods, SA ID checksum validation included)
- src/Agents/Helpers/FormHelpers.php (201 lines, form-to-database mapping for 30+ fields)
- src/Agents/Services/WorkingAreasService.php (66 lines, 14 working areas across South Africa)

**Verification:**
- ✅ All 3 files pass PHP syntax check
- ✅ Zero DatabaseService references (0 found)
- ✅ Zero WECOZA_AGENTS_ constants (0 found)
- ✅ Zero wecoza_agents_log references (0 found)
- ✅ Correct namespaces: `WeCoza\Agents\Helpers` (2 files), `WeCoza\Agents\Services` (1 file)
- ✅ Text domain updated: 36 occurrences of 'wecoza-core' in ValidationHelper, 0 'wecoza-agents-plugin' remaining

---

## Deviations from Plan

None - plan executed exactly as written.

## Overall Verification Results

All success criteria met:

1. ✅ WeCoza\Agents\ namespace registered in wecoza-core.php autoloader
2. ✅ agent_id added to PostgresConnection::insert() RETURNING candidates (position 2 after 'id')
3. ✅ ValidationHelper.php exists at src/Agents/Helpers/ with correct namespace
4. ✅ FormHelpers.php exists at src/Agents/Helpers/ with correct namespace
5. ✅ WorkingAreasService.php exists at src/Agents/Services/ with correct namespace
6. ✅ All 5 affected PHP files pass syntax check (no errors detected)
7. ✅ Zero DatabaseService, WECOZA_AGENTS_*, or wecoza_agents_log references in src/Agents/

**Namespace resolution confirmed:**
```
src/Agents/Helpers/FormHelpers.php:namespace WeCoza\Agents\Helpers;
src/Agents/Helpers/ValidationHelper.php:namespace WeCoza\Agents\Helpers;
src/Agents/Services/WorkingAreasService.php:namespace WeCoza\Agents\Services;
```

## Technical Details

### ValidationHelper Capabilities
- **SA ID validation:** 13-digit format with Luhn checksum validation
- **Passport validation:** 6-12 alphanumeric characters (uppercase)
- **Phone validation:** 10-15 digits (SA format aware)
- **Bank account validation:** 9-11 digits
- **Branch code validation:** 6 digits
- **Postal code validation:** 4 digits (SA format)
- **Fallback detection:** Uses `function_exists()` guard for wecoza_agents_validate_sa_id() and wecoza_agents_validate_passport()

### FormHelpers Field Mapping
Maps 30+ form field names to database column names, including:
- Address fields: address_line_1 → residential_address_line, city_town → city
- Banking fields: account_number → bank_account_number
- Identification fields: sa_id_no, id_number → sa_id_no
- File upload fields: agreement_file_path → signed_agreement_file
- Quantum score fields: quantum_maths_score, quantum_science_score
- Working preference fields: preferred_working_area_1/2/3

### WorkingAreasService Coverage
14 working areas across 9 provinces:
- Gauteng: Sandton, Hatfield, Soweto
- Western Cape: Durbanville, Stellenbosch, Paarl
- KwaZulu-Natal: Durban, Pietermaritzburg
- Eastern Cape: Port Elizabeth, East London
- Limpopo: Polokwane
- Northern Cape: Kimberley
- Mpumalanga: Nelspruit
- Free State: Bloemfontein

## Next Phase Readiness

**Blockers:** None

**Ready for Plan 02:** ✅ Yes

Plan 02 can now:
- Use `wecoza_db()->insert('agents', $data)` and receive agent_id back via RETURNING
- Instantiate ValidationHelper, FormHelpers, WorkingAreasService via namespace autoloading
- Build AgentRepository and Agent model without worrying about infrastructure

## Commits

| Hash    | Message                                                                 |
|---------|-------------------------------------------------------------------------|
| 91b5575 | feat(26-01): register Agents namespace and add agent_id RETURNING support |
| 4bc9c90 | feat(26-01): add Agents helper and service classes                      |

## Self-Check: PASSED

**Files created - verification:**
```bash
[ -f "src/Agents/Helpers/ValidationHelper.php" ] && echo "FOUND"  # FOUND
[ -f "src/Agents/Helpers/FormHelpers.php" ] && echo "FOUND"       # FOUND
[ -f "src/Agents/Services/WorkingAreasService.php" ] && echo "FOUND" # FOUND
```

**Commits exist - verification:**
```bash
git log --oneline --all | grep -q "91b5575" && echo "FOUND"  # FOUND
git log --oneline --all | grep -q "4bc9c90" && echo "FOUND"  # FOUND
```

**Namespace registration - verification:**
```bash
grep -q "WeCoza\\\\Agents\\\\" wecoza-core.php && echo "FOUND"  # FOUND
```

**RETURNING support - verification:**
```bash
grep -q "agent_id" core/Database/PostgresConnection.php && echo "FOUND"  # FOUND
```

All verification checks passed. Foundation architecture is ready for Plan 02 execution.
