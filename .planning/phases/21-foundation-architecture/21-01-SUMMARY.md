---
phase: 21
plan: 01
subsystem: clients-foundation
tags: [foundation, data-layer, migration, psr-4]
dependency_graph:
  requires: []
  provides: [clients-namespace, clients-config, clients-models, clients-repositories, clients-viewhelpers]
  affects: []
tech_stack:
  added: [WeCoza\Clients namespace, clients config, 4 Models, 2 Repositories, ViewHelpers]
  patterns: [PSR-4 autoloading, wecoza_db() migration, BaseModel extension, BaseRepository pattern]
key_files:
  created:
    - wecoza-core.php (namespace registration)
    - config/clients.php
    - src/Clients/Helpers/ViewHelpers.php
    - src/Clients/Models/ClientsModel.php
    - src/Clients/Models/LocationsModel.php
    - src/Clients/Models/SitesModel.php
    - src/Clients/Models/ClientCommunicationsModel.php
    - src/Clients/Repositories/ClientRepository.php
    - src/Clients/Repositories/LocationRepository.php
  modified: []
decisions:
  - Convert SETA/province options to associative arrays for ViewHelpers consistency
  - Preserve all column mapping and caching logic from source Models
  - Add protected table/primaryKey properties to Models for BaseModel compatibility
  - Repositories use getModel() method to link to Model classes
metrics:
  duration_minutes: 6
  files_created: 9
  files_modified: 1
  commits: 3
  lines_migrated: ~2000
  completed_date: 2026-02-11
---

# Phase 21 Plan 01: Clients Module Foundation Summary

**One-liner:** PSR-4 namespace registration, config file, ViewHelpers, 4 Models migrated from DatabaseService to wecoza_db(), and 2 Repositories with column whitelisting for Clients module foundation.

## Objective Achievement

Created the complete data layer foundation for the Clients module:
- ✅ Registered `WeCoza\Clients\` namespace in PSR-4 autoloader
- ✅ Created `config/clients.php` with validation rules and dropdown options
- ✅ Migrated ViewHelpers with namespace transformation
- ✅ Migrated all 4 Models from DatabaseService to wecoza_db()
- ✅ Created 2 Repositories extending BaseRepository with security column whitelisting

## Tasks Completed

### Task 1: Register Namespace, Create Config, Migrate ViewHelpers
**Commit:** c466bcc

Registered `WeCoza\Clients\` namespace in wecoza-core.php autoloader after Events namespace. Created config/clients.php extracting only validation_rules, seta_options, province_options, client_status_options, and settings (items_per_page=10) from source plugin. Migrated ViewHelpers.php with:
- Namespace: `WeCozaClients\Helpers` → `WeCoza\Clients\Helpers`
- Config calls: `\WeCozaClients\config('app')` → `wecoza_config('clients')`
- No DatabaseService references (ViewHelpers only renders HTML)

**Files:**
- wecoza-core.php (added namespace)
- config/clients.php (113 lines)
- src/Clients/Helpers/ViewHelpers.php (378 lines)

### Task 2: Migrate All 4 Models from DatabaseService to wecoza_db()
**Commit:** 1b1f3ab

Migrated all 4 Models with comprehensive transformations:

**ClientsModel.php (719 lines):**
- Extended BaseModel, added protected string $table/$primaryKey
- Replaced 14 DatabaseService:: static calls with wecoza_db()-> instance calls
- Changed `\WeCozaClients\config('app')` to `wecoza_config('clients')`
- Preserved all: column mapping/caching, $fillable/$jsonFields, validation logic, JSONB handling, hierarchical queries
- Added abstract method implementations: getById(), save(), update(), delete()

**LocationsModel.php (272 lines):**
- Extended BaseModel, added protected string $table/$primaryKey
- Replaced 7 DatabaseService calls with wecoza_db()->
- Changed config access to wecoza_config('clients')
- Preserved: coordinate normalization, validation, duplicate checking, proximity logic

**SitesModel.php (867 lines):**
- Extended BaseModel, added protected string $table/$primaryKey
- Replaced 12 DatabaseService calls with wecoza_db()->
- Preserved: location caching (transient + option), head site caching, hierarchical site management, hydration logic

**ClientCommunicationsModel.php (139 lines):**
- Extended BaseModel, added protected string $table/$primaryKey
- Replaced 3 DatabaseService calls with wecoza_db()->
- Preserved: communication logging, latest communication queries

**Zero DatabaseService or WeCozaClients references remaining across all Models.**

**Files:**
- src/Clients/Models/ClientsModel.php (719 lines)
- src/Clients/Models/LocationsModel.php (272 lines)
- src/Clients/Models/SitesModel.php (867 lines)
- src/Clients/Models/ClientCommunicationsModel.php (139 lines)

### Task 3: Create ClientRepository and LocationRepository
**Commit:** a199a34

Created two repositories extending BaseRepository with security column whitelisting:

**ClientRepository:**
- Whitelisted columns: 6 for ORDER BY, 4 for filtering, 14 for INSERT, 15 for UPDATE
- Custom methods: `getMainClients()`, `getBranchClients(int)`, `searchClients(string, int)`
- Uses ILIKE for PostgreSQL case-insensitive search

**LocationRepository:**
- Whitelisted columns: 5 for ORDER BY, 3 for filtering, 9 for INSERT, 8 for UPDATE
- Custom methods: `findByCoordinates(float, float, float, int)` using Haversine formula, `checkDuplicates(string, string, ?int)`
- Proximity search with configurable radius in km

Both repositories follow BaseRepository pattern with getModel() linking to Model classes.

**Files:**
- src/Clients/Repositories/ClientRepository.php (185 lines)
- src/Clients/Repositories/LocationRepository.php (173 lines)

## Deviations from Plan

None - plan executed exactly as written.

## Authentication Gates

None encountered.

## Verification Results

✅ All success criteria met:
1. `WeCoza\Clients\` namespace registered in PSR-4 autoloader
2. config/clients.php returns SETA options, validation rules, province/status options
3. All 4 Models extend BaseModel, use wecoza_db() exclusively, zero DatabaseService references
4. Both Repositories extend BaseRepository with column whitelisting
5. ViewHelpers migrated with correct namespace
6. All 7 PHP files pass syntax check

**Final counts:**
- Namespace registered: ✅
- WeCozaClients references: 0
- DatabaseService references: 0
- Syntax errors: 0
- PHP files created: 7
- Total lines: ~2000

## Technical Notes

### Model Migration Approach
- Used sed for bulk transformation on Models (faster than manual editing)
- Preserved all business logic: column mapping, caching, validation, JSONB, hierarchies
- Added BaseModel compatibility: protected string properties, abstract method implementations

### Config Extraction
- Extracted only runtime config (validation, options) - not plugin metadata, controllers, shortcodes, AJAX, assets, capabilities
- Converted array options to associative format for consistency with ViewHelpers

### Repository Security
- Column whitelisting prevents SQL injection via column name manipulation
- All user-controlled ORDER BY, WHERE, INSERT, UPDATE operations restricted to allowed columns
- Custom query methods use prepared statements with parameter binding

## Next Phase Readiness

**Blockers:** None

**Dependencies satisfied for Plan 02 (Controllers, AJAX, Views):**
- ✅ Namespace registered and autoloading works
- ✅ Config available via wecoza_config('clients')
- ✅ Models ready for data operations
- ✅ Repositories ready for Controllers to use
- ✅ ViewHelpers ready for View rendering

Plan 02 can proceed immediately with Controllers, AJAX handlers, Shortcodes, and Views.

## Self-Check

Verifying all claimed files and commits exist:

```bash
# Files created
[ -f "config/clients.php" ] && echo "✓ config/clients.php"
[ -f "src/Clients/Helpers/ViewHelpers.php" ] && echo "✓ ViewHelpers"
[ -f "src/Clients/Models/ClientsModel.php" ] && echo "✓ ClientsModel"
[ -f "src/Clients/Models/LocationsModel.php" ] && echo "✓ LocationsModel"
[ -f "src/Clients/Models/SitesModel.php" ] && echo "✓ SitesModel"
[ -f "src/Clients/Models/ClientCommunicationsModel.php" ] && echo "✓ ClientCommunicationsModel"
[ -f "src/Clients/Repositories/ClientRepository.php" ] && echo "✓ ClientRepository"
[ -f "src/Clients/Repositories/LocationRepository.php" ] && echo "✓ LocationRepository"

# Commits
git log --oneline --all | grep -q "c466bcc" && echo "✓ Commit c466bcc (Task 1)"
git log --oneline --all | grep -q "1b1f3ab" && echo "✓ Commit 1b1f3ab (Task 2)"
git log --oneline --all | grep -q "a199a34" && echo "✓ Commit a199a34 (Task 3)"
```

All verifications executed in final check - all files exist, all commits present, all syntax checks pass.

## Self-Check: PASSED

All files created, all commits exist, all verifications successful.
