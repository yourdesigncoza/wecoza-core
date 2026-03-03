# Phase 17: Code Cleanup - Research

**Researched:** 2026-02-05
**Domain:** Legacy Code Removal & Dependency Analysis
**Confidence:** HIGH

## Summary

This research verifies the safety of removing 6 deprecated files that were part of the OLD trigger-based notification system, which was replaced in Phases 13-18. The class_change_logs table and its PostgreSQL triggers were dropped in Phase 13, making these files non-functional remnants.

**Key findings:**
- 4 files exist, 2 were already deleted in prior phases
- class_change_logs table was dropped in Phase 13 (confirmed in ROADMAP.md)
- Remaining references are from OLD system components that are also slated for deletion
- One test file (AISummarizationTest.php) references the old table and will need updating
- NO active production code depends on these files

**Primary recommendation:** Safe to delete all 6 files. Update AISummarizationTest.php to use new class_events table instead of class_change_logs.

## File Deletion Safety Analysis

### CLEAN-01: ClassChangeSchema.php

**Status:** EXISTS - `/src/Events/Models/ClassChangeSchema.php`

**What it does:** Creates class_change_logs table, PostgreSQL trigger function, and trigger on classes table. This was the infrastructure for the OLD trigger-based system.

**Referenced by:**
- ClassChangeController.php (line 8, 24) - Also deprecated (not in cleanup list but unused)

**Instantiated by:**
- ClassChangeController.php (line 24, 68) - Also deprecated

**In Container.php:** NO

**Database dependency:** Creates table `class_change_logs` which was DROPPED in Phase 13

**Verdict:** **SAFE TO DELETE**
- Table it creates no longer exists
- Only referenced by ClassChangeController which is also unused
- Part of old trigger infrastructure

### CLEAN-02: ClassChangeListener.php

**Status:** EXISTS - `/src/Events/Services/ClassChangeListener.php`

**What it does:** PostgreSQL LISTEN/NOTIFY consumer that reads from class_change_channel and logs to file. Used for real-time monitoring of trigger events.

**Referenced by:**
- ClassChangeController.php (line 9, 26) - Also deprecated

**Instantiated by:**
- ClassChangeController.php (line 26, 63) - Also deprecated

**In Container.php:** NO

**Database dependency:** Listens to PostgreSQL notifications from trigger (dropped in Phase 13)

**Verdict:** **SAFE TO DELETE**
- Trigger that sends notifications no longer exists
- Only referenced by ClassChangeController which is also unused
- Part of old trigger infrastructure

### CLEAN-03: TaskTemplateRegistry.php

**Status:** EXISTS - `/src/Events/Services/TaskTemplateRegistry.php`

**What it does:** Returns hardcoded task templates for INSERT/UPDATE/DELETE operations. This was used to generate tasks stored in class_change_logs.tasks JSONB column.

**Referenced by:**
- TaskManager.php (line 32, 37, 54, 59) - ACTIVE FILE
- Container.php (line 15, 22, 33-40, 54, 66) - ACTIVE FILE

**Instantiated by:**
- Container.php (line 36) - service container
- TaskManager.php (line 37) - fallback constructor

**In Container.php:** YES - registered as singleton

**Current usage:** TaskManager still imports and uses it BUT:
- TaskManager methods that use it: `getTasksWithTemplate()`, `fetchOperation()`, `getTasksForLog()`, `saveTasksForLog()`, `getPreviousTasksSnapshot()`
- All these methods query class_change_logs table which was DROPPED in Phase 13
- The NEW system in Phase 14+ builds tasks from `classes.event_dates` JSONB, NOT from templates

**Verdict:** **SAFE TO DELETE**
- References exist but query dropped table
- New task system (buildTasksFromEvents) doesn't use templates
- Task templates concept obsolete after Phase 14 refactor

### CLEAN-04: ClassChangeLogRepository.php

**Status:** EXISTS - `/src/Events/Repositories/ClassChangeLogRepository.php`

**What it does:** Repository for class_change_logs table. Provides `exportLogs()` and `getLogsWithAISummary()` methods.

**Referenced by:**
- ClassChangeController.php (line 7, 25) - Also deprecated
- AISummaryDisplayService.php (line 10, 18) - Still exists but DISABLED

**Instantiated by:**
- ClassChangeController.php (line 25) - Also deprecated
- AISummaryDisplayService.php (line 18) - Still exists but registration DISABLED (wecoza-core.php lines 211-220 commented out per debug/resolved/ai-summaries-missing-table.md)

**In Container.php:** NO

**Database dependency:** Queries table `class_change_logs` which was DROPPED in Phase 13

**Test references:**
- tests/Events/AISummarizationTest.php (line 22, 442, 454) - Test file that needs updating

**Verdict:** **SAFE TO DELETE**
- Table it queries no longer exists
- AISummaryShortcode registration is commented out (disabled)
- Test file will need updating to use new class_events table

### CLEAN-05: ClassChangeLogDTO.php

**Status:** ALREADY DELETED - File does not exist

**References:** NONE found in codebase

**Verdict:** **ALREADY REMOVED** - No action needed

### CLEAN-06: ChangeOperation.php

**Status:** ALREADY DELETED - File does not exist

**References:** NONE found in codebase

**Verdict:** **ALREADY REMOVED** - No action needed

## Architecture Patterns

### Pattern 1: Safe Dependency Deletion

**What:** Remove files in reverse dependency order to avoid breaking references during deletion.

**Deletion order:**
1. ClassChangeController.php (not in cleanup list but should be removed first)
2. ClassChangeListener.php (depends on ClassChangeController)
3. ClassChangeSchema.php (depends on ClassChangeController)
4. ClassChangeLogRepository.php (used by AISummaryDisplayService - already disabled)
5. TaskTemplateRegistry.php (used by TaskManager - methods query dropped table)

**Why this order:**
- ClassChangeController references all other files, remove it first
- Repository and schema are data layer - remove after controller
- TaskTemplateRegistry has most references but they're all dead code paths

### Pattern 2: Orphaned Service Detection

**What:** Identify services that are still instantiable but have no active entry points.

**Example:**
- AISummaryDisplayService still exists as a class
- Its registration in wecoza-core.php is commented out (lines 211-220)
- No shortcode or AJAX handler can invoke it
- Safe to leave or remove (not in Phase 17 scope)

### Anti-Patterns to Avoid

**DON'T delete without verifying table existence:** TaskTemplateRegistry appears heavily used, but those code paths all query dropped table.

**DON'T assume Container registration means active use:** Container.php references can be dead if the service itself queries dropped tables.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dependency graph analysis | Manual file-by-file checking | grep/rg with output modes | Miss hidden dependencies |
| Table existence verification | Assume from code comments | Check schema/ + ROADMAP.md | Documentation may be stale |
| Test file updates | Delete tests that fail | Update to use new table | Preserve test coverage |

**Key insight:** When removing database-backed features, verify table existence in schema files AND project documentation, not just code comments.

## Common Pitfalls

### Pitfall 1: Circular Dependency Confusion

**What goes wrong:** TaskManager imports TaskTemplateRegistry, Container registers it, looks heavily used - researcher hesitates to recommend deletion.

**Why it happens:** Not tracing through to the actual database queries.

**How to avoid:** For every "looks active" service, trace to final database query. If table doesn't exist, entire chain is dead.

**Warning signs:** Repository methods that return empty arrays without errors (table gone, queries fail silently).

### Pitfall 2: Test File Blindness

**What goes wrong:** Delete files, tests fail with "class not found" errors.

**Why it happens:** Tests aren't in src/ directory, easy to miss in grep searches.

**How to avoid:** Always search tests/ directory separately for references.

**Warning signs:** Test file imports deleted classes or references dropped tables.

### Pitfall 3: Commented-Out Registration False Safety

**What goes wrong:** Think "registration is commented out, safe to delete dependencies".

**Why it happens:** Commented code can be uncommented by mistake.

**How to avoid:** Verify the REASON for commenting out (in this case, missing table). If root cause persists, dependencies are truly safe.

**Warning signs:** Debug notes mentioning "disable until X is fixed" - may indicate temporary state.

## Code Examples

### Safe Deletion Pattern

```php
// Before: Container.php lines 33-40
public static function taskTemplateRegistry(): TaskTemplateRegistry
{
    if (self::$taskTemplateRegistry === null) {
        self::$taskTemplateRegistry = new TaskTemplateRegistry();
    }
    return self::$taskTemplateRegistry;
}

// After: Remove method AND property (line 22)
// No need for this service - task templates obsolete after Phase 14
```

### TaskManager Cleanup

```php
// Before: TaskManager.php lines 32-37
private TaskTemplateRegistry $registry;

public function __construct(?TaskTemplateRegistry $registry = null)
{
    $this->db = PostgresConnection::getInstance();
    $this->registry = $registry ?? new TaskTemplateRegistry();
}

// After: Remove property and constructor parameter
public function __construct()
{
    $this->db = PostgresConnection::getInstance();
}

// Remove methods: getTasksWithTemplate(), fetchOperation(), getTasksForLog(),
// saveTasksForLog(), getPreviousTasksSnapshot()
// These all query class_change_logs table (dropped in Phase 13)
```

### Test File Update Pattern

```php
// Before: tests/Events/AISummarizationTest.php line 22
use WeCoza\Events\Repositories\ClassChangeLogRepository;

// After: Update to use new notification system
use WeCoza\Events\Repositories\ClassEventRepository;

// Update test methods to query class_events table instead of class_change_logs
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PostgreSQL triggers auto-log changes to class_change_logs | Manual EventDispatcher calls from controllers to class_events | Phase 13 (DB Cleanup) | Simpler, user controls events |
| Task templates persisted in JSONB | Tasks built at query time from event_dates | Phase 14 (Task Refactor) | No template concept needed |
| ClassChangeLogRepository for AI summaries | NotificationDashboardService for class_events | Phase 18 (Notifications) | Unified notification model |
| AISummaryShortcode queries class_change_logs | Notification dashboard displays class_events | Phase 18-04 | New UI, new data source |

**Deprecated/outdated:**
- **class_change_logs table:** Dropped in Phase 13, replaced by class_events
- **Trigger-based event capture:** Manual dispatch from controllers (Phase 18-05)
- **Task template system:** Tasks derived from event_dates JSONB (Phase 14)
- **ClassChangeController:** CLI tool for trigger management, obsolete after trigger removal
- **AISummaryShortcode:** Registration disabled in wecoza-core.php after table drop

## Open Questions

1. **Should ClassChangeController be deleted in Phase 17?**
   - What we know: Not in CLEAN requirements, but unused after Phase 13
   - What's unclear: Whether to include in this cleanup or separate phase
   - Recommendation: Include in Phase 17 as prerequisite deletion (deleting it first makes other deletions cleaner)

2. **Should AISummaryDisplayService be deleted?**
   - What we know: Still exists, registration commented out, queries dropped table
   - What's unclear: Whether it's planned for revival or permanent removal
   - Recommendation: Leave as-is (not in Phase 17 scope), mark as technical debt

3. **Should TaskManager dead methods be removed?**
   - What we know: Methods querying class_change_logs still exist but unreachable
   - What's unclear: Whether they're kept for backwards compatibility
   - Recommendation: Remove in Phase 17 - they cannot function without table

## Sources

### Primary (HIGH confidence)

- **Codebase inspection:** Direct file reads of all 6 files + referencing files
- **ROADMAP.md:** Explicit statement "Table class_change_logs no longer exists" (Phase 13)
- **STATE.md:** Decision "Replace triggers with manual events - Implemented (18-05)"
- **debug/resolved/ai-summaries-missing-table.md:** Root cause analysis confirming table drop and feature disabling

### Secondary (MEDIUM confidence)

- **grep analysis:** Verified import statements and instantiation patterns across src/
- **Container.php:** Service registration patterns showing DI relationships
- **AISummarizationTest.php:** Test file import verification

## Metadata

**Confidence breakdown:**
- File existence/deletion: HIGH - direct filesystem checks
- Reference analysis: HIGH - comprehensive grep with multiple patterns
- Table existence: HIGH - confirmed in ROADMAP.md and debug notes
- Safety verdict: HIGH - all references traced to dropped table or deprecated controller

**Research date:** 2026-02-05
**Valid until:** Indefinite (historical code analysis, not dependent on external APIs)

## Critical User Concern: No Functional Code Loss

**USER REQUIREMENT:** "Assurance that we are NOT deleting any functional code. Verify each file is truly unused."

### Verification Evidence

**For each file, verified:**

1. **ClassChangeSchema.php**
   - ✅ Creates table that was DROPPED in Phase 13
   - ✅ Only referenced by ClassChangeController (also unused)
   - ✅ No WP hooks register it
   - ✅ Cannot cause errors when removed (already non-functional due to table drop)

2. **ClassChangeListener.php**
   - ✅ Listens to PostgreSQL notifications from trigger that was DROPPED
   - ✅ Only referenced by ClassChangeController (also unused)
   - ✅ No WP hooks register it
   - ✅ Cannot cause errors when removed (trigger no longer sends notifications)

3. **TaskTemplateRegistry.php**
   - ✅ Used ONLY by TaskManager methods that query dropped table
   - ✅ New task system (buildTasksFromEvents) doesn't use it
   - ✅ Container registration exists but service methods are unreachable
   - ✅ Cannot cause errors when removed (code paths already fail on table query)

4. **ClassChangeLogRepository.php**
   - ✅ Queries table that was DROPPED in Phase 13
   - ✅ AISummaryShortcode registration DISABLED (wecoza-core.php lines 211-220)
   - ✅ No AJAX handlers call it
   - ✅ Cannot cause errors when removed (already causes errors if accidentally invoked)

5. **ClassChangeLogDTO.php**
   - ✅ ALREADY DELETED - no verification needed

6. **ChangeOperation.php**
   - ✅ ALREADY DELETED - no verification needed

### What Would Break If We Delete

**Answer: NOTHING functional**

- No production code paths can reach these files
- Registration commented out or non-existent
- Database table they depend on was dropped 5 phases ago
- New system (class_events + NotificationDashboardService) fully operational

**The ONLY thing that will break:**
- tests/Events/AISummarizationTest.php (test file for OLD system)
- Solution: Update test to use new class_events table

### PHP Fatal Error Prevention

**Concern:** Deleting classes that are imported but not instantiated could cause "Class not found" errors.

**Analysis:**
- TaskTemplateRegistry: Imported by TaskManager (line 32) and Container (line 15)
  - **Safe:** Remove import from TaskManager, remove method from Container
  - **Safe:** No autoload errors because we're also removing the methods that reference it

- ClassChangeLogRepository: Imported by AISummaryDisplayService (line 10)
  - **Safe:** AISummaryDisplayService itself is not registered/invoked
  - **Alternative:** Could also delete AISummaryDisplayService (out of scope for Phase 17)

- ClassChangeSchema/Listener: Imported only by ClassChangeController
  - **Safe:** ClassChangeController itself is not registered in wecoza-core.php

**Verification strategy:**
- Delete files
- Remove import statements
- Run: `php -l` (syntax check) on modified files
- Check debug.log for fatal errors after plugin reload
