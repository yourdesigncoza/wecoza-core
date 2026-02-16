---
phase: 38-address-storage-normalization
plan: 02
subsystem: agents
tags: [data-migration, dual-write, address-normalization, backward-compatibility]
dependency_graph:
  requires: ["38-01"]
  provides: ["agent-address-dual-write", "location-table-integration"]
  affects: ["agent-crud", "agent-forms", "agent-repository", "agent-service"]
tech_stack:
  added: []
  patterns: ["dual-read", "dual-write", "graceful-degradation", "direct-sql"]
key_files:
  created: []
  modified:
    - src/Agents/Models/AgentModel.php
    - src/Agents/Repositories/AgentRepository.php
    - src/Agents/Services/AgentService.php
decisions:
  - Use direct SQL via wecoza_db() instead of LocationsModel to bypass longitude/latitude validation
  - Concatenate address_line_2 into street_address when syncing to locations table
  - Implement graceful degradation: location sync failure doesn't block agent save
  - Use case-insensitive exact match on all 5 fields for duplicate detection (not LIKE matching)
metrics:
  duration_seconds: 177
  tasks_completed: 2
  files_modified: 3
  commits: 2
  completed_at: "2026-02-16T13:40:27Z"
---

# Phase 38 Plan 02: Agent Address Dual-Read/Dual-Write Implementation Summary

Implemented dual-read and dual-write functionality for agent addresses using the shared locations table while maintaining full backward compatibility with inline address columns.

## What Was Built

**Dual-Read Pattern:**
- AgentRepository reads addresses from `locations` table via `LEFT JOIN` on `location_id`
- Falls back to inline columns (`residential_address_line`, `city`, etc.) when `location_id` is NULL
- Single `resolveAddressFields()` method centralizes address resolution logic (DRY)
- All read methods (getAgent, getAgentByEmail, getAgentByIdNumber, getAgents) use unified pattern

**Dual-Write Pattern:**
- AgentService syncs address data to `locations` table during form submission via `syncAddressToLocation()`
- Creates new location records or updates existing ones based on `location_id`
- Detects duplicates via case-insensitive exact match on all 5 address fields
- Sets `location_id` on agent record, linking to normalized location data
- Old inline columns STILL get written (existing sanitization unchanged)

**Direct SQL Approach:**
- Uses `wecoza_db()->insert()` and `wecoza_db()->update()` for location operations
- Bypasses `LocationsModel` entirely to avoid longitude/latitude validation requirements
- Agent forms don't capture coordinates, but DB schema allows NULL for these fields
- `LocationsModel::validate()` enforces non-null coords at PHP level, not SQL level

## Tasks Completed

### Task 1: Update AgentModel and AgentRepository for dual-read (ea9ae4b)
- Added `location_id` to AgentModel `$defaults` array
- Added `location_id` to AgentRepository insert/update column whitelists
- Updated all read methods with LEFT JOIN to `public.locations` table
- Implemented private `resolveAddressFields()` method for address fallback logic
- Added location_id sanitization with null coercion for empty values

**Files modified:**
- `src/Agents/Models/AgentModel.php`
- `src/Agents/Repositories/AgentRepository.php`

### Task 2: Update AgentService for dual-write location management (8b6aea7)
- Implemented `syncAddressToLocation()` for location creation/update during form submission
- Maps agent fields to locations schema: `residential_address_line` + `address_line_2` → `street_address`
- Uses direct SQL for duplicate check (exact case-insensitive match on all 5 fields)
- Integrated into `handleAgentFormSubmission()` after validation, before save
- Graceful degradation: location sync failure logged but doesn't block agent save

**Files modified:**
- `src/Agents/Services/AgentService.php`

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

**Syntax checks:** All files pass `php -l`

**Code patterns verified:**
- `resolveAddressFields` appears in 5 locations (4 call sites + 1 definition)
- `LEFT JOIN.*locations` appears in 4 read methods
- `syncAddressToLocation` appears in 2 locations (1 call site + 1 definition)
- `wecoza_db()` used for direct SQL (insert, update, select)
- `LocationsModel` NOT imported or used (only mentioned in comment explaining bypass)
- `location_id` in column whitelists and sanitization

**Data flow:**
1. Form submission → collectFormData() → validate()
2. syncAddressToLocation() creates/updates location record
3. location_id set on agent data
4. createAgent/updateAgent saves both location_id AND inline columns (dual-write)
5. Read operations JOIN locations table, fall back to inline columns if needed

## Technical Notes

**Address Line 2 Handling:**
During dual-write, `address_line_2` is concatenated into `street_address` with a comma separator. On dual-read, the original `address_line_2` value is preserved in the inline column (not extracted from concatenated location data). This is an acceptable limitation during the dual-write period.

**Duplicate Detection Strategy:**
Uses exact case-insensitive match on all 5 fields (street_address, suburb, town, province, postal_code), NOT the flexible LIKE matching in `LocationsModel::checkDuplicates()`. This ensures agents with identical addresses reuse the same location record.

**Graceful Degradation:**
If location sync fails (DB error, validation issue), the error is logged via `wecoza_log()` but the agent save continues with inline columns. This prevents address sync issues from blocking agent CRUD operations.

**Backward Compatibility:**
- No changes to return types or data shapes
- Consumers (controllers, AJAX handlers, views) see identical data structure
- Old inline columns continue to be populated and queryable
- Existing queries that don't JOIN locations still work (use inline data)

## Self-Check

**Created files verified:**
- None (no new files created)

**Modified files verified:**
```bash
[ -f "src/Agents/Models/AgentModel.php" ] && echo "FOUND: src/Agents/Models/AgentModel.php" || echo "MISSING: src/Agents/Models/AgentModel.php"
[ -f "src/Agents/Repositories/AgentRepository.php" ] && echo "FOUND: src/Agents/Repositories/AgentRepository.php" || echo "MISSING: src/Agents/Repositories/AgentRepository.php"
[ -f "src/Agents/Services/AgentService.php" ] && echo "FOUND: src/Agents/Services/AgentService.php" || echo "MISSING: src/Agents/Services/AgentService.php"
```
**Result:** All files exist

**Commits verified:**
```bash
git log --oneline --all | grep -E "ea9ae4b|8b6aea7"
```
**Result:**
- ea9ae4b: feat(38-02): implement dual-read address resolution for agents
- 8b6aea7: feat(38-02): implement dual-write location management in AgentService

## Self-Check: PASSED

All modified files exist. All commits present in git history. Code passes syntax checks. Dual-read and dual-write patterns implemented as specified.
