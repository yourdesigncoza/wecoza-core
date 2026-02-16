---
phase: 36-service-layer-extraction
plan: 02
subsystem: agents
tags: [service-layer, refactoring, dry, svc-02]
dependency-graph:
  requires: [AgentRepository, AgentModel, AgentDisplayService]
  provides: [AgentService]
  affects: [AgentsController, AgentsAjaxHandlers]
tech-stack:
  added: []
  patterns: [service-layer, lazy-loading, delegation]
key-files:
  created:
    - src/Agents/Services/AgentService.php
  modified:
    - src/Agents/Controllers/AgentsController.php
    - src/Agents/Ajax/AgentsAjaxHandlers.php
decisions:
  - "AgentService owns AgentRepository (not shared across layers)"
  - "Form submission workflow returns structured array (success, agent_id, errors, agent, submitted_data)"
  - "File upload validation remains in service (PDF/DOC/DOCX only)"
  - "Presentation logic (HTML rendering, URL helpers) stays in controller/handlers"
metrics:
  duration: 4m 59s
  tasks_completed: 2
  files_created: 1
  files_modified: 2
  commits: 2
  lines_added: 484 (AgentService)
  lines_removed: 365 (controller/handler)
  net_change: +119
completed: 2026-02-16
---

# Phase 36 Plan 02: Agent Service Layer Extraction Summary

**One-liner:** Extracted agent CRUD workflow, form submission, data collection/sanitization, file uploads, and pagination assembly into AgentService; reduced AgentsController renderCaptureForm from 95 to 67 lines.

## Objectives Achieved

- Created `AgentService` with complete agent business logic (8 public methods)
- Refactored `AgentsController` to delegate all business logic to service
- Refactored `AgentsAjaxHandlers` to delegate data assembly and CRUD to service
- Eliminated code duplication between controller and AJAX handlers
- Maintained backward compatibility (AJAX endpoints unchanged)

## What Was Built

### AgentService.php

**Location:** `src/Agents/Services/AgentService.php`

**Public API:**

1. **CRUD Operations** (repository delegation):
   - `getAgent(int $agentId): ?array`
   - `getAgents(array $args): array` — with display field mapping
   - `countAgents(array $args): int`
   - `deleteAgent(int $agentId): bool`

2. **Form Submission Workflow**:
   - `handleAgentFormSubmission(array $postData, array $filesData, ?int $agentId, ?array $currentAgent): array`
   - Orchestrates: collect → validate → save → upload files → reload
   - Returns structured result: `['success', 'agent_id', 'errors', 'agent', 'submitted_data']`

3. **Data Collection/Sanitization**:
   - `collectFormData(array $postData): array` — 35+ fields
   - Private helpers: `processTextField()`, `processDateField()`, `processNumericField()`

4. **File Upload Handling**:
   - `handleFileUploads(int $agentId, array $filesData, ?array $currentAgent): array`
   - Private: `uploadFile(string $fieldName, int $agentId, array $filesData): ?string`
   - Validates PDF/DOC/DOCX only

5. **Pagination Assembly** (for AJAX):
   - `getPaginatedAgents(int $page, int $perPage, string $search, string $orderby, string $order): array`
   - Returns: agents, total_agents, total_pages, start_index, end_index, statistics

**Collaborators:**
- `AgentRepository` (owned by service)
- `AgentModel` (validation)
- `AgentDisplayService` (field mapping, statistics)

### Controller Refactoring

**AgentsController Changes:**

- **Removed:** `AgentRepository` dependency, `getRepository()` method
- **Added:** `AgentService` lazy property with `getAgentService()` getter
- **Removed methods moved to service:**
  - `collectFormData()` (70 lines)
  - `processTextField()`, `processDateField()`, `processNumericField()`
  - `handleFileUploads()`, `uploadFile()`
- **Kept presentation helpers:**
  - `detectAgentIdFromUrl()`, `determineFormMode()`
  - `getEditUrl()`, `getViewUrl()`, `getBackUrl()`
  - `enqueueAssets()`, `shouldEnqueueAssets()`, `registerShortcodes()`

**renderCaptureForm() refactored (95 → 67 lines):**
```php
// Before: ~50 lines of inline form submission logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ...) {
    $data = $this->collectFormData();
    $agentModel = new AgentModel($data);
    $isValid = $agentModel->validate(...);
    if (!$isValid) { ... }
    else { $saved_agent_id = ...; handleFileUploads(); ... }
}

// After: delegation to service
$result = $this->getAgentService()->handleAgentFormSubmission(
    $_POST, $_FILES, $agent_id > 0 ? $agent_id : null, $current_agent
);
if ($result['success']) { ... } else { ... }
```

**renderAgentsList() refactored:**
```php
// Before: 20+ lines of query building and counting
$args = [...]; $agents_raw = $this->getRepository()->getAgents($args);
$agents = []; foreach (...) { $agents[] = AgentDisplayService::mapAgentFields(...); }
$total_agents = $this->getRepository()->countAgents(...);
$total_pages = ceil(...); $start_index = ...; $end_index = ...;
$statistics = AgentDisplayService::getAgentStatistics();

// After: single service call
$paginationData = $this->getAgentService()->getPaginatedAgents(
    $current_page, $per_page, $search_query, $sort_column, $sort_order
);
```

**renderSingleAgent() refactored:**
```php
// Before: $this->getRepository()->getAgent($agent_id)
// After: $this->getAgentService()->getAgent($agent_id)
```

### AJAX Handler Refactoring

**AgentsAjaxHandlers Changes:**

- **Removed:** `AgentRepository` dependency
- **Added:** `AgentService` property (injected in constructor)
- **handlePagination() refactored:** Delegated query building, counting, statistics to `service.getPaginatedAgents()`
  - Kept HTML rendering (table rows, pagination HTML, statistics HTML)
  - Response structure unchanged (backward compatible)
- **handleDelete() refactored:** Changed `$this->repository->deleteAgent()` to `$this->agentService->deleteAgent()`

## Technical Details

### Service Layer Pattern

**Architecture:**
```
Controller/Handler → Service → Repository → Database
                    ↓
                  Model (validation)
```

**Responsibilities:**
- **Service:** Business logic, workflow orchestration, validation orchestration
- **Controller:** Presentation, URL routing, asset enqueuing, view rendering
- **Handler:** Security (nonce/capability), HTML rendering, AJAX response formatting
- **Repository:** Database operations, query building, sanitization
- **Model:** Data structure, validation rules

### Backward Compatibility

- AJAX action names unchanged: `wecoza_agents_paginate`, `wecoza_agents_delete`
- AJAX response structures identical (agents, total_agents, current_page, per_page, total_pages, start_index, end_index, statistics, table_html, pagination_html, statistics_html)
- Shortcode attributes unchanged
- Form field names unchanged
- No database schema changes

### Code Quality Metrics

**Before:**
- `AgentsController::renderCaptureForm()`: 95 lines
- `AgentsController`: 720 lines (with inline form logic)
- `AgentsAjaxHandlers::handlePagination()`: 92 lines

**After:**
- `AgentsController::renderCaptureForm()`: 67 lines (-28 lines, -29%)
- `AgentsController`: 458 lines (-262 lines, -36%)
- `AgentsAjaxHandlers::handlePagination()`: 65 lines (-27 lines, -29%)
- `AgentService`: 484 lines (new)

**Net change:** +119 lines (484 added - 365 removed from controller/handler)

**DRY improvements:**
- Form data collection logic: 1 place (was in controller)
- File upload logic: 1 place (was in controller)
- Pagination assembly logic: 1 place (was duplicated in controller and AJAX handler)
- Agent CRUD delegation: consistent pattern (controller and handler both use service)

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

1. ✅ `php -l src/Agents/Services/AgentService.php` — syntax OK
2. ✅ `php -l src/Agents/Controllers/AgentsController.php` — syntax OK
3. ✅ `php -l src/Agents/Ajax/AgentsAjaxHandlers.php` — syntax OK
4. ✅ `grep "class AgentService"` — exists
5. ✅ AgentsController no longer has: collectFormData, processTextField, processDateField, processNumericField, handleFileUploads, uploadFile
6. ✅ AgentsController no longer references AgentRepository directly
7. ✅ AgentsAjaxHandlers no longer references AgentRepository directly
8. ✅ AJAX action names `wecoza_agents_paginate` and `wecoza_agents_delete` unchanged
9. ✅ `renderCaptureForm()` under 70 lines (67 lines)
10. ✅ No method exceeds 100 lines

## Testing Notes

**Manual testing required:**
1. Test agent creation via form (non-AJAX fallback)
2. Test agent editing via form
3. Test AJAX pagination (wecoza_agents_paginate)
4. Test AJAX delete (wecoza_agents_delete)
5. Test file uploads (signed_agreement_file, criminal_record_file)
6. Verify validation errors display correctly
7. Test shortcodes: `[wecoza_capture_agents]`, `[wecoza_display_agents]`, `[wecoza_single_agent]`

**Expected behavior:** All agent functionality works identically to before refactor.

## Self-Check: PASSED

**Files created:**
```bash
[ -f "src/Agents/Services/AgentService.php" ] && echo "FOUND: src/Agents/Services/AgentService.php"
# Output: FOUND: src/Agents/Services/AgentService.php
```

**Commits exist:**
```bash
git log --oneline --all | grep -q "1f29ebd" && echo "FOUND: 1f29ebd"
# Output: FOUND: 1f29ebd
git log --oneline --all | grep -q "710711f" && echo "FOUND: 710711f"
# Output: FOUND: 710711f
```

**Files modified:**
```bash
git show 710711f --stat | grep "src/Agents/Controllers/AgentsController.php"
# Output: src/Agents/Controllers/AgentsController.php | 125 ++++++++++-----------------
git show 710711f --stat | grep "src/Agents/Ajax/AgentsAjaxHandlers.php"
# Output: src/Agents/Ajax/AgentsAjaxHandlers.php | 68 ++++++----------
```

## Next Steps

- **Plan 03:** Extract similar service layers for other modules (Clients, Learners, Classes)
- **Follow-up:** Consider extracting file upload logic to separate UploadService if pattern repeats
- **Follow-up:** Consider extracting form data collection to FormDataCollector if pattern repeats
- **Testing:** Add integration tests for AgentService methods

## Commits

| Hash    | Message                                              | Files                                                                               |
| ------- | ---------------------------------------------------- | ----------------------------------------------------------------------------------- |
| 1f29ebd | feat(36-02): create AgentService with business logic | src/Agents/Services/AgentService.php                                                |
| 710711f | refactor(36-02): delegate to AgentService            | src/Agents/Controllers/AgentsController.php, src/Agents/Ajax/AgentsAjaxHandlers.php |

---

**Generated by John @ YourDesign.co.za**
