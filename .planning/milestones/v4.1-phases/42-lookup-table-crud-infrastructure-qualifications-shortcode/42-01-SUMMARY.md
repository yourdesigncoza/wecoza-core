---
phase: 42-lookup-table-crud-infrastructure-qualifications-shortcode
plan: 01
subsystem: LookupTables
tags: [lookup-tables, crud, ajax, repository, shortcode, wecoza-core]
dependency_graph:
  requires:
    - core/Abstract/BaseController.php
    - core/Helpers/AjaxSecurity.php
    - core/Database/PostgresConnection.php
  provides:
    - WeCoza\LookupTables\Repositories\LookupTableRepository
    - WeCoza\LookupTables\Ajax\LookupTableAjaxHandler
    - WeCoza\LookupTables\Controllers\LookupTableController
    - shortcode:wecoza_manage_qualifications
    - shortcode:wecoza_manage_placement_levels
    - ajax:wecoza_lookup_table
  affects:
    - wecoza-core.php (autoloader + module init)
tech_stack:
  added:
    - src/LookupTables/ module directory
  patterns:
    - Config-driven repository (runtime-injected table config, not static)
    - Single AJAX endpoint with sub_action dispatch
    - SHORTCODE_MAP for tag-to-config dispatch in controller
key_files:
  created:
    - src/LookupTables/Repositories/LookupTableRepository.php
    - src/LookupTables/Ajax/LookupTableAjaxHandler.php
    - src/LookupTables/Controllers/LookupTableController.php
  modified:
    - wecoza-core.php
decisions:
  - "LookupTableRepository does not extend BaseRepository because BaseRepository uses static $table; runtime config injection requires a standalone class"
  - "TABLES constant lives in LookupTableController so AjaxHandler gets config via getTableConfig() — single source of truth"
  - "SHORTCODE_MAP constant added to controller for clean tag-to-tableKey dispatch without if/else chains"
metrics:
  duration: "2 minutes"
  completed: "2026-02-17T17:50:39Z"
  tasks_completed: 2
  tasks_total: 2
  files_created: 3
  files_modified: 1
---

# Phase 42 Plan 01: Lookup Table CRUD Infrastructure Summary

**One-liner:** Config-driven CRUD repository + single AJAX endpoint + shortcode controller for generic lookup table management, wired into WeCoza Core plugin lifecycle.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Create LookupTableRepository + LookupTableAjaxHandler | 6cd71da | src/LookupTables/Repositories/LookupTableRepository.php, src/LookupTables/Ajax/LookupTableAjaxHandler.php |
| 2 | Create LookupTableController + register in wecoza-core.php | c0ef218 | src/LookupTables/Controllers/LookupTableController.php, wecoza-core.php |

## What Was Built

### LookupTableRepository

Generic CRUD repository that accepts a config array at construction time instead of using static class properties. This enables a single class to operate against any of the configured lookup tables at runtime.

- `findAll()` — SELECT * ORDER BY pk ASC
- `findById(int $id)` — SELECT * WHERE pk = :id
- `insert(array $data)` — INSERT whitelisted columns RETURNING pk
- `update(int $id, array $data)` — UPDATE whitelisted columns WHERE pk = :pk_id
- `delete(int $id)` — DELETE WHERE pk = :id
- Private `filterColumns()` — keys filtered against config `columns` whitelist
- Private `quoteIdentifier()` — sanitizes and double-quotes all table/column names

### LookupTableAjaxHandler

Single AJAX endpoint (`wp_ajax_wecoza_lookup_table`) that dispatches to CRUD operations via `$_POST['sub_action']`:

- `list` — nonce only (read), returns `{items: [...]}`
- `create` — nonce + capability; sanitizes POST columns; returns `{id: N}`
- `update` — nonce + capability; validates id; sanitizes columns; returns success bool
- `delete` — nonce + capability; validates id; returns success bool
- Invalid table_key → 400 "Invalid table."
- Unknown sub_action → 400 "Invalid action."

### LookupTableController

Extends BaseController. Single controller for all lookup tables:

- `private const TABLES` — defines config for `qualifications` and `placement_levels`
- `private const SHORTCODE_MAP` — maps shortcode tag strings to table keys
- `getTableConfig(string $key): ?array` — static accessor used by AjaxHandler
- `registerShortcodes()` — registers both shortcodes pointing to `renderManageTable()`
- `renderManageTable()` — dispatches via SHORTCODE_MAP, capability-gated, renders `lookup-tables/manage` view
- `enqueueAssets()` — conditionally loads `lookup-table-manager.js` only when page contains a lookup shortcode

### wecoza-core.php Changes

1. Autoloader: `'WeCoza\\LookupTables\\'` mapped to `src/LookupTables/`
2. Module init in `plugins_loaded` (after Agents Module, before Dev Toolbar):
   - `new LookupTableController()` — registers shortcodes + asset hooks
   - `new LookupTableAjaxHandler()` — registers AJAX action

## Decisions Made

1. **Standalone repository**: `LookupTableRepository` does not extend `BaseRepository` because `BaseRepository` uses `protected static string $table = ''` which is class-level, not instance-level. Runtime config injection requires a standalone class with `private array $config`.

2. **Config in Controller**: The `TABLES` constant lives in `LookupTableController` (not in AjaxHandler). The AjaxHandler calls `LookupTableController::getTableConfig()` to retrieve config — single source of truth, no duplication.

3. **SHORTCODE_MAP constant**: Added `private const SHORTCODE_MAP` to controller for clean tag-to-tableKey dispatch. Avoids if/else chains when adding future shortcodes.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

Files verified:
- [x] src/LookupTables/Repositories/LookupTableRepository.php — FOUND
- [x] src/LookupTables/Ajax/LookupTableAjaxHandler.php — FOUND
- [x] src/LookupTables/Controllers/LookupTableController.php — FOUND
- [x] wecoza-core.php modified with autoloader + module init

Commits verified:
- [x] 6cd71da — feat(42-01): create LookupTableRepository and LookupTableAjaxHandler
- [x] c0ef218 — feat(42-01): create LookupTableController and wire into plugin lifecycle

All php -l syntax checks: PASSED
All grep verifications: PASSED
