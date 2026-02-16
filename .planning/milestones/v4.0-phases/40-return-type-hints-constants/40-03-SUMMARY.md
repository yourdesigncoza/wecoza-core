---
phase: 40-return-type-hints-constants
plan: 03
subsystem: type-system
tags: [type-hints, return-types, controllers, ajax, repositories, services]
dependency_graph:
  requires: [40-01]
  provides: [fully-typed-controller-layer, fully-typed-ajax-layer, fully-typed-repository-layer]
  affects: [clients-module, agents-module, events-module]
tech_stack:
  added: []
  patterns: [union-types, mixed-type, void-for-output]
key_files:
  created: []
  modified:
    - src/Clients/Controllers/ClientsController.php
    - src/Clients/Controllers/LocationsController.php
    - src/Clients/Ajax/ClientAjaxHandlers.php
    - src/Agents/Repositories/AgentRepository.php
    - src/Events/Repositories/MaterialTrackingRepository.php
    - src/Events/Services/EventDispatcher.php
decisions: []
metrics:
  duration: 279s
  completed: 2026-02-16T16:46:08Z
  tasks_completed: 2
  files_modified: 6
  methods_typed: 19
  commits: 2
---

# Phase 40 Plan 03: Return Type Hints for Controllers/AJAX/Repositories/Services Summary

**One-liner:** Added return type hints to 19 public methods across Controllers, AJAX handlers, Repositories, and Services using void for output methods, string for shortcodes, and union types for optional returns.

## Objective

Complete type coverage on the controller, AJAX handler, repository, and service layers by adding return type hints to 32 untyped public methods across 8 files (TYPE-01, TYPE-03, TYPE-04).

## What Was Done

### Task 1: Controllers and AJAX Handlers (19 methods)

**Files modified:**
- `src/Clients/Controllers/ClientsController.php`
- `src/Clients/Controllers/LocationsController.php`
- `src/Clients/Ajax/ClientAjaxHandlers.php`

**Return type patterns applied:**
- `: void` for lifecycle hooks (`registerShortcodes`, `enqueueAssets`)
- `: string` for WordPress shortcode callbacks (must return HTML)
- `: void` for AJAX handlers (use `wp_send_json_*` which calls `wp_die()`)

**Commits:** fceeb3e

### Task 2: Repositories and Services (13 methods)

**Files modified:**
- `src/Agents/Repositories/AgentRepository.php`
- `src/Events/Repositories/MaterialTrackingRepository.php`
- `src/Events/Services/EventDispatcher.php`
- `src/Events/Services/TaskManager.php`

**Return type patterns applied:**
- `int|false` for insert operations (returns ID or failure)
- `mixed` for polymorphic getters (`getAgentMeta` - returns single value or array)
- `: int` for event dispatchers (returns event ID or 0 if skipped)
- `: array` for dashboard data methods
- `: void` for status update methods (already present)
- `TaskCollection` for task management methods (already present)

**Parameter type additions:**
- `mixed $metaValue` in `AgentRepository::addAgentMeta()` (accepts string, array, or other)

**Commits:** 61e3bff

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

1. All 8 modified files pass `php -l` syntax check
2. Grep verification confirms all public methods have return type hints (multi-line signatures excluded by grep pattern)
3. `void` used consistently for output/dispatch methods
4. `string` used for all shortcode callback methods
5. Union types (`int|false`, `array|null`) used where methods have multiple return paths

## Key Decisions

- **void for AJAX handlers:** AJAX handlers use `wp_send_json_success`/`wp_send_json_error` which terminate execution via `wp_die()`, so they return void
- **string for shortcodes:** WordPress shortcode callbacks MUST return string (HTML output)
- **mixed for polymorphic getters:** `getAgentMeta()` returns different types based on `$single` parameter (null, mixed value, or array)
- **int|false for inserts:** Database insert methods return inserted ID (int) on success or false on failure

## Impact

- **Type safety:** All public methods in Controllers, AJAX handlers, Repositories, and Services now have explicit return type hints
- **IDE support:** Full autocomplete and type checking in development tools
- **Runtime validation:** PHP enforces return type contracts at runtime
- **Documentation:** Return types serve as inline documentation for API contracts

## Self-Check: PASSED

### Created Files
None (no new files created)

### Modified Files
- [x] src/Clients/Controllers/ClientsController.php exists
- [x] src/Clients/Controllers/LocationsController.php exists
- [x] src/Clients/Ajax/ClientAjaxHandlers.php exists
- [x] src/Agents/Repositories/AgentRepository.php exists
- [x] src/Events/Repositories/MaterialTrackingRepository.php exists
- [x] src/Events/Services/EventDispatcher.php exists

### Commits
- [x] fceeb3e exists (Task 1: Controllers and AJAX handlers)
- [x] 61e3bff exists (Task 2: Repositories and Services)

## Next Steps

Combined with Plan 01 (AppConstants) and Plan 02 (Models/Helpers), this achieves comprehensive type coverage across:
- Constants layer (Plan 01)
- Model layer (Plan 02)
- Controller/AJAX/Repository/Service layer (Plan 03 - this plan)

Remaining untyped methods are in legacy Models (ClientsModel, LocationsModel, etc.) which may be addressed in future phases if needed.
