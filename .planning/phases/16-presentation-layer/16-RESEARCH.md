# Phase 16: Presentation Layer - Research

**Researched:** 2026-02-03
**Domain:** PHP Presenters, WordPress Views, JavaScript AJAX, Bootstrap 5 UI components
**Confidence:** HIGH

## Summary

Researched the presentation layer updates needed for Phase 16. The backend work (TaskManager, TaskController) is complete from Phase 14-15. Phase 16 focuses on ensuring the UI correctly displays event-based tasks and sends the right parameters (class_id instead of log_id) to the backend.

Key findings:
- JavaScript in EventTasksShortcode.php still sends `log_id` but TaskController now expects `class_id`
- ClassTaskPresenter already correctly presents TaskCollection with open/completed segregation
- Views render data-attributes correctly with both `data-log-id` and `data-class-id`
- ClassTaskService returns `manageable: true` for all classes (Phase 14 change)
- Badge shows "Open +N" format already implemented in presenter
- Agent Order Number task UI already handles required note validation

**Primary recommendation:** Update JavaScript to send `class_id` instead of `log_id`, remove redundant log_id data attributes from views, and verify ClassTaskPresenter correctly formats all event-based tasks.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.0+ | Server-side presenter logic | Match expressions, typed properties, existing requirement |
| Bootstrap | 5.x | UI component framework | Phoenix theme dependency, existing usage |
| Vanilla JS | ES5+ | Client-side AJAX and DOM manipulation | No build step, existing inline scripts in shortcode |
| WordPress | 6.0+ | AJAX infrastructure, template rendering | Native AJAX handlers, nonce verification |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Bootstrap Icons | 1.x | Badge and button icons | Already used in table headers |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inline JS in shortcode | Separate JS file | Inline works, no build step needed, maintains current pattern |
| Vanilla JS | jQuery | Vanilla JS sufficient, no jQuery dependency needed |
| PHP array transforms | Twig/Blade | PHP templates work, no templating engine needed |

**Installation:**
```bash
# No new dependencies required
# Existing: Bootstrap 5.x, PHP 8.0+, WordPress 6.0+
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/
├── Views/Presenters/
│   └── ClassTaskPresenter.php   # VERIFY: present() and presentTasks() work correctly
├── Shortcodes/
│   └── EventTasksShortcode.php  # MODIFY: JS sends class_id instead of log_id
└── Controllers/
    └── TaskController.php       # Already expects class_id (Phase 15)

views/events/
└── event-tasks/
    └── main.php                 # MODIFY: Remove data-log-id attributes
```

### Pattern 1: Presenter Transforms Domain Models to View Data
**What:** ClassTaskPresenter transforms TaskCollection to arrays suitable for rendering
**When to use:** All view rendering of task data
**Example:**
```php
// Source: Current ClassTaskPresenter.php (already implemented)
public function presentTasks(TaskCollection $tasks): array
{
    $open = [];
    $completed = [];

    foreach ($tasks->all() as $task) {
        $payload = [
            'id' => $task->getId(),
            'label' => $task->getLabel(),
        ];

        if ($task->isCompleted()) {
            $payload['completed_by'] = $this->resolveUserName($task->getCompletedBy());
            $payload['completed_at'] = $this->formatCompletedAt($task->getCompletedAt());
            $payload['note'] = $task->getNote();
            $payload['reopen_label'] = __('Reopen', 'wecoza-events');
            $completed[] = $payload;
        } else {
            // Handle open tasks with note requirements...
            $open[] = $payload;
        }
    }

    return ['open' => $open, 'completed' => $completed];
}
```

### Pattern 2: Data Attributes for JavaScript State
**What:** HTML data-* attributes pass server state to JavaScript
**When to use:** AJAX handlers need identifiers, configuration
**Example:**
```html
<!-- Source: Current main.php pattern (needs update) -->
<div class="wecoza-task-panel-content"
     data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
     data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>">
    <!-- Remove data-log-id, it's obsolete -->
</div>
```

### Pattern 3: JavaScript FormData for AJAX
**What:** FormData collects form values for AJAX POST
**When to use:** Task completion/reopen actions
**Example:**
```javascript
// Source: Current EventTasksShortcode.php (needs update)
var formData = new FormData();
formData.append('action', 'wecoza_events_task_update');
formData.append('nonce', wrapper.dataset.nonce);
formData.append('class_id', panel.dataset.classId || '');  // CHANGED from log_id
formData.append('task_id', taskItem.dataset.taskId || '');
formData.append('task_action', button.dataset.action || '');
```

### Anti-Patterns to Avoid
- **Sending log_id to TaskController:** TaskController expects class_id (Phase 15 change).
- **Hardcoding task IDs in JavaScript:** Task IDs are dynamic (event-0, event-1, etc.), use data attributes.
- **Complex DOM manipulation without escaping:** Always use escapeHtml() for user content.
- **Assuming TaskCollection is empty for new classes:** All classes have at least agent-order task.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| User name resolution | Custom user lookup | `get_userdata()` + display_name | WordPress handles user data, caching |
| Date formatting | Manual date parsing | `mysql2date()` for MySQL, `DateTimeImmutable` for ISO | WordPress and PHP handle timezones |
| HTML escaping | Regex-based escaping | `esc_html()`, `esc_attr()` in PHP, `escapeHtml()` in JS | XSS protection built-in |
| Search tokenization | Custom search parsing | Existing normaliseForIndex() pattern | Already tested, handles edge cases |

**Key insight:** The presenter layer is already mostly complete. Phase 16 is verification and a single JavaScript parameter change, not a rewrite.

## Common Pitfalls

### Pitfall 1: JavaScript Still Sends log_id
**What goes wrong:** AJAX calls fail or return 400 "Invalid task request" errors
**Why it happens:** EventTasksShortcode.php JavaScript sends `log_id`, TaskController expects `class_id`
**How to avoid:** Update formData.append to use 'class_id' and panel.dataset.classId
**Warning signs:** Console errors on task completion, tasks not updating

### Pitfall 2: Empty Task Lists Confused with Missing Tasks
**What goes wrong:** UI shows "No classes available" when class has no events (only agent-order)
**Why it happens:** Confusion between empty TaskCollection vs class with only agent-order task
**How to avoid:** Agent Order Number task is always present. Empty class_change_logs is expected.
**Warning signs:** Classes without events not appearing in dashboard

### Pitfall 3: Stale data-log-id Attributes Cause Confusion
**What goes wrong:** Developer sees data-log-id in HTML and thinks it's used
**Why it happens:** Views still render log_id even though it's obsolete
**How to avoid:** Remove data-log-id attributes from views, keep only data-class-id
**Warning signs:** Debugging code that reads from unused attribute

### Pitfall 4: Agent Order Number Note Validation Bypassed
**What goes wrong:** Agent Order Number task completed without order number
**Why it happens:** JavaScript validation skipped or note_required not set
**How to avoid:** Verify data-note-required="1" on agent-order task note input
**Warning signs:** order_nr field is empty but task shows completed

### Pitfall 5: Badge Class Mismatch After AJAX Update
**What goes wrong:** Badge shows wrong color after completing/reopening tasks
**Why it happens:** JavaScript updateSummaryStatus() uses wrong CSS class pattern
**How to avoid:** Verify badge-phoenix-* classes match presenter output
**Warning signs:** Badge stays warning color after all tasks completed

### Pitfall 6: Search Tokens Not Updated After Task Change
**What goes wrong:** Completed task still appears when filtering by open task type
**Why it happens:** updateRowFilterMetadata() not called or not working correctly
**How to avoid:** Verify AJAX success handler calls updateRowFilterMetadata()
**Warning signs:** Stale search results after task status changes

## Code Examples

Verified patterns from existing codebase:

### JavaScript Parameter Fix (Required Change)
```javascript
// Source: EventTasksShortcode.php - Line 585
// BEFORE:
formData.append('log_id', panel.dataset.logId || '');

// AFTER:
formData.append('class_id', panel.dataset.classId || '');
```

### ClassTaskPresenter Status Badge (Already Implemented)
```php
// Source: ClassTaskPresenter.php - formatTaskStatusBadge()
private function formatTaskStatusBadge(int $openCount): array
{
    if ($openCount > 0) {
        return [
            'label' => sprintf(__('Open +%d', 'wecoza-events'), $openCount),
            'class' => 'badge-phoenix-warning',
        ];
    }

    return [
        'label' => strtoupper(__('Completed', 'wecoza-events')),
        'class' => 'badge-phoenix-secondary',
    ];
}
```

### Agent Order Number Task Presentation (Already Implemented)
```php
// Source: ClassTaskPresenter.php - presentTasks()
$isAgentOrderTask = $task->getId() === 'agent-order';
if ($isAgentOrderTask) {
    $payload['note_label'] = __('Order number', 'wecoza-events');
    $payload['note_placeholder'] = __('Order Number Required', 'wecoza-events');
    $payload['note_required'] = true;
    $payload['note_required_message'] = __(
        'Enter the agent order number before completing this task.',
        'wecoza-events'
    );
}
```

### View Data Attribute Pattern (Needs Cleanup)
```php
// Source: main.php - Line 203
// BEFORE:
<div class="p-4 wecoza-task-panel-content"
     data-log-id="<?php echo esc_attr((string) ($class['log_id'] ?? '')); ?>"
     data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
     data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>">

// AFTER (remove data-log-id):
<div class="p-4 wecoza-task-panel-content"
     data-class-id="<?php echo esc_attr((string) $class['id']); ?>"
     data-manageable="<?php echo $class['manageable'] ? '1' : '0'; ?>">
```

### JavaScript Response Handler (Already Implemented)
```javascript
// Source: EventTasksShortcode.php - AJAX success handler
.then(function(payload) {
    // ... update open/completed lists ...
    updateSummaryStatus(wrapper, panelRow, data.tasks.open || []);
    updateRowFilterMetadata(wrapper, panelRow, data.tasks.open || []);
    applyTaskFilters(wrapper);
})
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| log_id in AJAX requests | class_id in AJAX requests | v1.2 (Phase 15/16) | Matches TaskController signature |
| TaskCollection from change logs | TaskCollection from event_dates | v1.2 (Phase 14) | Tasks always current, no stale logs |
| Some classes not manageable | All classes manageable | v1.2 (Phase 14) | No skip logic needed in presenter |
| Operation badge (New/Update) | Change badge still present but informational | v1.2 | Legacy display, may be removed later |

**Deprecated/outdated:**
- log_id parameter: Replaced by class_id (class_change_logs dropped in Phase 13)
- data-log-id attribute: Remove from views, use data-class-id only
- manageable=false logic: All classes manageable now (Phase 14)

## Open Questions

Things that couldn't be fully resolved:

1. **Change badge relevance (New/Update display)**
   - What we know: ClassTaskPresenter still formats operation as New/Update badge
   - What's unclear: Without change logs, how is operation determined?
   - Recommendation: ClassTaskService no longer returns 'operation' field. Presenter should handle missing operation gracefully. Consider removing change badge entirely in future cleanup.

2. **Filtering by open task type after AJAX**
   - What we know: updateRowFilterMetadata() updates data-open-tasks attribute
   - What's unclear: Are all edge cases covered (event added, event removed)?
   - Recommendation: Test with different task completion scenarios. Edge cases handled by full page reload which rebuilds data.

3. **Performance with many tasks per class**
   - What we know: AJAX response includes full task list, DOM is rebuilt
   - What's unclear: Performance with 50+ events per class
   - Recommendation: Accept current approach for v1.2. Future optimization: partial DOM updates.

## Sources

### Primary (HIGH confidence)
- Current codebase: ClassTaskPresenter.php, EventTasksShortcode.php, main.php, TaskController.php
- Phase 14 research: .planning/phases/14-task-system-refactor/14-RESEARCH.md
- Phase 15 research: .planning/phases/15-bidirectional-sync/15-RESEARCH.md

### Secondary (MEDIUM confidence)
- Bootstrap 5 badge documentation: https://getbootstrap.com/docs/5.0/components/badge/
- WordPress AJAX documentation: https://developer.wordpress.org/plugins/javascript/ajax/

### Tertiary (LOW confidence)
- None - all findings verified with codebase analysis

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Bootstrap 5, vanilla JS, WordPress AJAX all verified in codebase
- Architecture: HIGH - Presenter pattern well-established, changes are minimal
- Pitfalls: HIGH - JavaScript log_id bug identified directly in code review
- Code examples: HIGH - All examples from current codebase

**Research date:** 2026-02-03
**Valid until:** 2026-03-03 (30 days - stable UI patterns, no external dependency changes)

## Implementation Summary

Phase 16 requires minimal code changes:

1. **JavaScript parameter fix** (EventTasksShortcode.php)
   - Change `formData.append('log_id', ...)` to `formData.append('class_id', ...)`
   - This is the critical fix that enables AJAX to work

2. **View cleanup** (views/events/event-tasks/main.php)
   - Remove `data-log-id` attributes (2 occurrences)
   - Keep `data-class-id` attributes (already present)

3. **Presenter verification** (ClassTaskPresenter.php)
   - Verify formatClassRow() handles missing operation gracefully
   - Verify presentTasks() correctly handles event-based tasks

4. **UI verification** (manual testing)
   - All classes appear in dashboard (even those with no events)
   - Open Tasks column shows pending events + Agent Order Number
   - Completed Tasks column shows completed events with user/timestamp
   - Complete/Reopen buttons work
   - Badge updates correctly after AJAX
   - Search and filter functionality preserved
