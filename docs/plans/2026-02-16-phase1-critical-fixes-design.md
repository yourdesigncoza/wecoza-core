# Phase 1: Critical Fixes Implementation Design

**Date**: 2026-02-16
**Scope**: 30 Critical Issues (12 Security + 18 Architecture)
**Environment**: Staging
**Strategy**: Parallel Implementation with Final Review

---

## Team Architecture

### Implementation Squad (Parallel Work)

**1. Security Specialist Agent**
- **Scope**: 12 critical security vulnerabilities
- **Tasks**:
  - Action-specific nonces (replace generic `learners_nonce_action`)
  - Email validation before `wp_mail()` calls
  - Capability checks in shortcodes (ClassController, others)
  - API key sanitization in error logs (AISummaryService)
  - PII exposure verification (LearnerAjaxHandlers)
- **Files**: ~12 files across Controllers, Ajax, Services

**2. Architecture Specialist Agent**
- **Scope**: Database & repository pattern issues
- **Tasks**:
  - PostgreSQL connection retry logic (remove `$connectionAttempted` flag)
  - `executeTransaction()` helper in BaseRepository
  - Refactor transaction patterns in LearnerRepository (3 locations)
  - Replace direct `$_POST` with BaseController methods
- **Files**: ~8 files in Database, Repositories, Controllers

**3. WordPress Standards Agent**
- **Scope**: WordPress-specific critical issues
- **Tasks**:
  - Replace `date()` with `wp_date()`/`current_time()` (53+ instances)
  - Replace `new DateTime()` with timezone-aware version
  - Action Scheduler error handling for all async hooks
  - Timeout configuration fix (reduce API timeout to 30s)
- **Files**: ~25 files across all modules

### QA Squad

**4. Standards Compliance Agent**
- Reviews all changes using `/wordpress-best-practices` skill
- Validates WordPress coding standards compliance
- Checks proper escaping, sanitization, nonce patterns

**5. Safety Verification Agent**
- Integration testing after all fixes complete
- Verifies no breaking changes
- Tests: database ops, AJAX endpoints, shortcodes, Action Scheduler

---

## Workflow

**Git Strategy:**
- Single worktree: `phase-1-critical-fixes`
- Isolated from main branch for safe rollback
- Frequent commits with descriptive messages

**Task Management:**
- Shared task list (30 discrete tasks)
- Agents claim tasks from their specialty
- Status updates: `in_progress` → `completed`
- Blockers escalated to team lead

**Coordination Pattern:**
```
Team Lead
    ├── Security Agent (12 tasks)
    ├── Architecture Agent (8 tasks)
    ├── WordPress Agent (10 tasks)
    └── Standards Agent (reviews)
            ↓
    Safety Agent (final verification)
```

---

## Implementation Patterns

### Security Fixes

**Action-Specific Nonces:**
```php
// BEFORE
$this->requireNonce('learners_nonce_action');

// AFTER
$this->requireNonce('update_learner_ajax');
$this->requireNonce('delete_learner_ajax');
```

**Email Validation:**
```php
if (!is_email($recipient)) {
    wecoza_log("Invalid email: {$recipient}", 'error');
    return false;
}
$sent = wp_mail($recipient, $subject, $body, $headers);
```

**Shortcode Capability Checks:**
```php
if (!current_user_can('read_classes')) {
    return '<p>You do not have permission to view this content.</p>';
}
```

### Architecture Fixes

**Transaction Helper (BaseRepository):**
```php
protected function executeTransaction(callable $callback): mixed
{
    $this->beginTransaction();
    try {
        $result = $callback();
        $this->commit();
        return $result;
    } catch (Exception $e) {
        $this->rollback();
        throw $e;
    }
}
```

**BaseController Usage:**
```php
// BEFORE
$data = $this->sanitizeLearnerInput($_POST);

// AFTER
$data = [
    'name' => $this->input('name', 'string'),
    'email' => $this->input('email', 'email'),
];
```

### WordPress Standards

**Timezone-Aware Dates:**
```php
// BEFORE
$now = date('Y-m-d H:i:s');
$start = new DateTime($date);

// AFTER
$now = current_time('mysql');
$start = new DateTime($date, wp_timezone());
```

**Action Scheduler Error Handling:**
```php
add_action('wecoza_process_event', function (int $eventId) {
    try {
        if (!class_exists(\WeCoza\Events\Services\NotificationEnricher::class)) {
            throw new RuntimeException('NotificationEnricher class not found');
        }

        $enricher = \WeCoza\Events\Services\NotificationEnricher::boot();
        $result = $enricher->enrich($eventId);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        wecoza_log("Event {$eventId} failed: " . $e->getMessage(), 'error');
        throw $e; // Re-throw for Action Scheduler retry
    }
});
```

---

## Verification Strategy

### Standards Compliance (Standards Agent)
- Cross-reference against `/wordpress-best-practices` skill
- Verify WordPress functions used correctly
- Check nonce/capability patterns
- Validate no direct superglobal access

### Safety Testing (Safety Agent)
- Database operations: Test all CRUD functions
- AJAX endpoints: Verify handler responses
- Shortcodes: Test rendering variations
- Action Scheduler: Confirm async job processing
- Timezone: Test across different WP timezone settings
- Debug log: Confirm zero new errors/warnings

### Acceptance Criteria
- ✅ All 30 critical issues resolved
- ✅ Zero new PHP errors/warnings
- ✅ All existing functionality preserved
- ✅ WordPress coding standards compliance
- ✅ `/wordpress-best-practices` review passed

---

## Deliverables

1. **Git worktree**: `phase-1-critical-fixes` with all changes
2. **Commit log**: Detailed commits per fix category
3. **Verification report**: Safety agent test results
4. **Standards review**: Compliance report
5. **Production-ready code**: Clean, tested, merge-ready

## Success Metrics

- 30/30 critical issues resolved
- 0 breaking changes
- 100% WordPress standards compliance
- All staging tests pass

---

**End of Design**
