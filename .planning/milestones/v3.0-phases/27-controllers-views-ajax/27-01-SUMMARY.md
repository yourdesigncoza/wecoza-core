---
phase: 27-controllers-views-ajax
plan: 01
subsystem: agents-module
tags:
  - controller
  - ajax-handlers
  - mvc-layer
  - bug-fixes
  - migration
dependency_graph:
  requires:
    - 26-02 (AgentRepository + AgentModel)
  provides:
    - AgentsController with 3 shortcodes
    - AgentsAjaxHandlers with AjaxSecurity pattern
    - Agents module wiring in wecoza-core.php
  affects:
    - 27-02 (Views migration)
    - 27-03 (JavaScript migration)
tech_stack:
  added:
    - AgentsController extending BaseController
    - AgentsAjaxHandlers using AjaxSecurity
  patterns:
    - BaseController pattern with registerHooks()
    - AjaxSecurity::requireNonce/sendSuccess/sendError
    - Lazy-loaded repository via getRepository()
    - Unified localization object (wecozaAgents)
    - Conditional asset enqueuing via shouldEnqueueAssets()
key_files:
  created:
    - src/Agents/Controllers/AgentsController.php
    - src/Agents/Ajax/AgentsAjaxHandlers.php
  modified:
    - wecoza-core.php (added Agents module initialization)
decisions: []
metrics:
  duration_minutes: 4
  completed_date: 2026-02-12
  tasks_completed: 4
  commits: 4
---

# Phase 27 Plan 01: Controllers & AJAX Handlers Summary

**One-liner:** Created AgentsController with 3 shortcodes, AgentsAjaxHandlers with AjaxSecurity pattern, unified wecozaAgents localization, zero nopriv handlers, and wecoza-core.php wiring.

## What Was Built

### AgentsController (591 lines)

**Public Methods:**
- `registerShortcodes()` - Registers 3 shortcodes
- `renderCaptureForm($atts)` - Agent capture/edit form with POST handling
- `renderAgentsList($atts)` - Paginated agents table with search/sort
- `renderSingleAgent($atts)` - Single agent detail view
- `enqueueAssets()` - Conditional asset loading (5 JS files + Google Maps)
- `shouldEnqueueAssets()` - Checks for shortcode presence

**Private Helpers (11 methods):**
- `collectFormData()` - Sanitizes 40+ form fields from POST
- `validateFormData($data, $current_agent)` - Required fields, SA ID/passport validation, duplicate email/ID checks
- `handleFileUploads($agent_id, $current_agent)` - Processes signed_agreement_file + criminal_record_file
- `uploadFile($field_name, $agent_id)` - wp_handle_upload wrapper
- `mapAgentFields($agent)` - DB → frontend field mapping (surname→last_name, etc.)
- `mapSortColumn($column)` - Frontend → DB column mapping
- `getDisplayColumns($columns_setting)` - 8 default columns with custom override
- `getEditUrl($agent_id)` / `getViewUrl($agent_id)` / `getBackUrl()` - URL generators
- `getAgentStatistics()` - 4 COUNT queries via wecoza_db()
- `detectAgentIdFromUrl($atts)` / `determineFormMode($agent_id, $atts)` - URL parameter detection

**Asset Enqueuing:**
- Google Maps API (if key available)
- agents-app.js (base script)
- agent-form-validation.js
- agents-ajax-pagination.js
- agents-table-search.js
- agent-delete.js

**Unified Localization (Bug #3 fix):**
Single `wecozaAgents` object with camelCase keys:
- `ajaxUrl`, `nonce`, `deleteNonce`, `paginationNonce`
- `debug`, `loadingText`, `errorText`, `confirmDeleteText`, etc.
- `urls` object with displayAgents, viewAgent, captureAgent

### AgentsAjaxHandlers (390 lines)

**AJAX Endpoints:**
- `wp_ajax_wecoza_agents_paginate` → `handlePagination()` (Bug #10: standardized prefix)
- `wp_ajax_wecoza_agents_delete` → `handleDelete()` (Bug #10: standardized prefix)
- **Zero nopriv handlers** (Bug #12: entire WP requires login)

**handlePagination():**
- `AjaxSecurity::requireNonce('agents_nonce_action')`
- Sanitizes: page, per_page, search, orderby, order via `AjaxSecurity::post()`
- Queries agents + count via repository
- Captures HTML partials: table rows, pagination, statistics
- Returns: agents array + HTML + metadata via `AjaxSecurity::sendSuccess()`

**handleDelete():**
- `AjaxSecurity::requireNonce('agents_nonce_action')`
- `AjaxSecurity::requireCapability('edit_others_posts')`
- Validates agent_id > 0
- Soft deletes via `$this->repository->deleteAgent()`
- Returns success/error via `AjaxSecurity::sendSuccess/sendError()`

**Private Helpers (duplicated from controller - DRY to be addressed in future phase):**
- `getAgentStatistics()` - Same COUNT queries
- `getStatisticsHtml($statistics)` - Generates Phoenix badge HTML
- `mapAgentFields($agent)` - Same mapping
- `mapSortColumn($column)` - Same mapping
- `getDisplayColumns($columns_setting)` - Same logic

### wecoza-core.php Wiring

Added Agents module initialization after Clients module (lines 245-252):
```php
// Initialize Agents Module
if (class_exists(\WeCoza\Agents\Controllers\AgentsController::class)) {
    new \WeCoza\Agents\Controllers\AgentsController();
}
if (class_exists(\WeCoza\Agents\Ajax\AgentsAjaxHandlers::class)) {
    new \WeCoza\Agents\Ajax\AgentsAjaxHandlers();
}
```

No `initialize()` call needed - BaseController auto-calls `registerHooks()` in constructor.

## Deviations from Plan

None - plan executed exactly as written. All migration requirements followed:
- NO DatabaseService references → used wecoza_db()
- NO WECOZA_AGENTS_* constants → used WECOZA_CORE_*
- NO wecoza_agents_log() → used wecoza_log()
- Text domain: 'wecoza-core' not 'wecoza-agents-plugin'
- NO nopriv AJAX handlers (Bug #12)
- Unified wecozaAgents localization object (Bug #3)
- All AJAX actions use wecoza_agents_ prefix (Bug #10)

## Critical Bug Fixes Applied

**Bug #3:** Multiple localization objects with mixed casing → Unified `wecozaAgents` with camelCase keys.

**Bug #4:** Direct wp_send_json calls → All responses through `AjaxSecurity::sendSuccess/sendError` for consistent response.data.* access.

**Bug #10:** Inconsistent AJAX action naming → All actions now use `wecoza_agents_` prefix (`wecoza_agents_paginate`, `wecoza_agents_delete`).

**Bug #12:** Nopriv AJAX handlers → Removed entirely. Entire WP environment requires login.

## Architecture Notes

**Controller Pattern:**
- Extends BaseController (constructor → registerHooks)
- Lazy-loads repository via getRepository()
- Uses render() and component() for views
- Handles both AJAX and non-AJAX form submissions

**AJAX Pattern:**
- Standalone handler class (not in controller)
- Constructor registers handlers
- All handlers use AjaxSecurity pattern
- Nonce + capability checks
- Consistent response format

**DRY Consideration:**
mapAgentFields, mapSortColumn, getDisplayColumns, getAgentStatistics are duplicated between controller and AJAX handler. This is acceptable for Phase 27 migration simplicity. Phase 28/29 can refactor into shared trait or service.

**Asset Loading:**
Conditional enqueuing prevents bloat - only loads on pages with shortcodes. Uses `has_shortcode()` check in `shouldEnqueueAssets()`.

## Verification Results

**Syntax Checks:** All files pass `php -l`

**Structure Checks:**
- ✓ AgentsController extends BaseController
- ✓ 3 shortcodes registered
- ✓ 2 AJAX endpoints registered
- ✓ Zero nopriv handlers
- ✓ Unified wecozaAgents localization
- ✓ Conditional asset enqueuing

**Legacy References:** Zero occurrences of:
- DatabaseService
- WECOZA_AGENTS_*
- wecoza_agents_log()
- nopriv handlers

**Module Wiring:**
- ✓ AgentsController initialized in wecoza-core.php
- ✓ AgentsAjaxHandlers initialized in wecoza-core.php

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 00a5edb | feat(27-01): create AgentsController skeleton | AgentsController.php |
| a5674db | feat(27-01): complete AgentsController helper methods | AgentsController.php |
| 68733ce | feat(27-01): create AgentsAjaxHandlers with AjaxSecurity pattern | AgentsAjaxHandlers.php |
| 56bdefe | feat(27-01): wire Agents module in wecoza-core.php | wecoza-core.php |

## Next Phase Readiness

**Ready for 27-02 (Views):**
- Controllers expect views at: `agents/components/agent-capture-form`, `agents/display/agent-display-table`, `agents/display/agent-single-display`
- Data contracts established: $agent, $errors, $statistics, $working_areas
- Shortcode URLs: /new-agents/, /app/agents/, /app/agent-view/

**Ready for 27-03 (JavaScript):**
- wecozaAgents object available with all keys
- 5 JS files to be created: agents-app.js, agent-form-validation.js, agents-ajax-pagination.js, agents-table-search.js, agent-delete.js
- AJAX endpoints ready: wecoza_agents_paginate, wecoza_agents_delete

**Dependencies Satisfied:**
- AgentRepository provides: getAgent(), getAgents(), countAgents(), createAgent(), updateAgent(), deleteAgent(), getAgentByEmail(), getAgentByIdNumber()
- AgentModel provides: Field mapping via FormHelpers
- WorkingAreasService provides: get_working_areas()
- ValidationHelper provides: validate_sa_id(), validate_passport()

**Blockers:** None

## Self-Check: PASSED

**Files exist:**
- ✓ src/Agents/Controllers/AgentsController.php
- ✓ src/Agents/Ajax/AgentsAjaxHandlers.php
- ✓ wecoza-core.php (modified)

**Commits exist:**
- ✓ 00a5edb (AgentsController skeleton)
- ✓ a5674db (Helper methods)
- ✓ 68733ce (AgentsAjaxHandlers)
- ✓ 56bdefe (Module wiring)

**All claims verified.**
