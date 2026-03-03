# Phase 12: Performance & Async Processing - Research

**Researched:** 2026-02-02
**Domain:** WordPress Async Processing, Action Scheduler, Batch Processing Optimization
**Confidence:** HIGH

## Summary

Phase 12 implements performance optimizations for the NotificationProcessor to handle high-volume notification processing without blocking web requests. The requirements focus on four key areas: increasing batch limits, implementing async email sending via Action Scheduler, separating AI enrichment from email sending into independent jobs, and improving lock TTL to prevent race conditions.

The good news is that the existing infrastructure (WP-Cron hooks, NotificationProcessor, AISummaryService) provides a solid foundation. The primary work involves:
1. Increasing BATCH_LIMIT from 1 to 50+ (trivial constant change, but requires testing)
2. Integrating Action Scheduler for individual email jobs (decouples wp_mail from the main processing loop)
3. Refactoring the processor to separate AI generation from email sending (two independent async jobs)
4. Increasing lock TTL from 30s to handle longer batch processing windows (60-120s recommended)

**Primary recommendation:** Use Action Scheduler for async email sending (`as_enqueue_async_action`) while keeping the existing WP-Cron for batch scheduling. Separate AI enrichment and email sending into distinct hooks that can fail independently.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Action Scheduler | 3.9.3+ | Async job queue | WordPress ecosystem standard, WooCommerce uses it, 6.4M+ installs |
| WP-Cron | WordPress 6.0+ | Batch scheduling | Already in use, no new dependency for scheduled triggers |
| WordPress Transients | WordPress 6.0+ | Lock mechanism | Already implemented, proven pattern |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_mail() | WordPress 6.0+ | Email sending | Actual send operation (called by Action Scheduler) |
| PostgreSQL | Current | Data store | Existing notification queue in class_change_logs |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Action Scheduler | Custom queue table | AS provides admin UI, logging, retry - don't rebuild |
| WP-Cron batching | Action Scheduler for everything | WP-Cron works fine for batch triggers; AS for individual jobs |
| Transient locks | File locks | Transients work for moderate volume; file locks for extreme cases |

**Installation:**
```bash
composer require woocommerce/action-scheduler
```

Then in the main plugin file (before `plugins_loaded`):
```php
require_once WECOZA_CORE_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/Services/
  NotificationProcessor.php      # Orchestrates batch processing
  NotificationEnricher.php       # NEW: AI summary generation only
  NotificationEmailer.php        # NEW: Email sending only
  NotificationSettings.php       # Existing: recipient config
```

### Pattern 1: Batch Processing with Individual Async Jobs
**What:** NotificationProcessor fetches batch, schedules individual jobs for each notification
**When to use:** High volume processing where individual operations can fail independently
**Example:**
```php
// Source: https://actionscheduler.org/api/
// NotificationProcessor::process() now schedules jobs instead of sending

foreach ($rows as $row) {
    $logId = (int) $row['log_id'];

    // Schedule AI enrichment first (if needed)
    if ($this->needsAIEnrichment($row)) {
        as_enqueue_async_action(
            'wecoza_enrich_notification',
            ['log_id' => $logId],
            'wecoza-notifications'
        );
    } else {
        // Skip straight to email if no AI needed
        as_enqueue_async_action(
            'wecoza_send_notification_email',
            ['log_id' => $logId],
            'wecoza-notifications'
        );
    }

    $latestId = max($latestId, $logId);
}
```

### Pattern 2: Job Chaining (AI -> Email)
**What:** AI enrichment job schedules email job on success
**When to use:** Sequential operations with independent failure modes
**Example:**
```php
// Source: https://actionscheduler.org/usage/
// NotificationEnricher handles 'wecoza_enrich_notification' action

add_action('wecoza_enrich_notification', function (int $logId) {
    $enricher = NotificationEnricher::boot();
    $result = $enricher->enrich($logId);

    if ($result['success']) {
        // Chain to email job
        as_enqueue_async_action(
            'wecoza_send_notification_email',
            ['log_id' => $logId],
            'wecoza-notifications'
        );
    }
    // If AI fails, the record stays in 'pending' for retry or manual review
}, 10, 1);
```

### Pattern 3: Grouped Actions with Priority
**What:** Use Action Scheduler groups for organization and priority for ordering
**When to use:** Multiple notification types with different urgency
**Example:**
```php
// Source: https://actionscheduler.org/api/
// Priority 5 for urgent, 10 for normal (lower = higher priority)

as_enqueue_async_action(
    'wecoza_send_notification_email',
    ['log_id' => $logId],
    'wecoza-notifications',  // group
    false,                    // unique
    5                         // priority (urgent)
);
```

### Pattern 4: Lock TTL with Processing Time Awareness
**What:** Set lock TTL based on expected batch processing time
**When to use:** High-volume batches that may take longer than default TTL
**Example:**
```php
// Current: LOCK_TTL = 30 seconds
// With 50 notifications, even 1 second each = 50 seconds
// Recommended: LOCK_TTL = max_runtime + safety_margin

private const LOCK_TTL = 120;  // 2 minutes
private const MAX_RUNTIME_SECONDS = 90;
private const MIN_REMAINING_SECONDS = 10;
```

### Anti-Patterns to Avoid
- **Calling Action Scheduler before init:** API not available until `init` hook priority 1
- **Not using groups:** Makes admin UI harder to filter
- **Ignoring unique parameter:** Can cause duplicate job scheduling during retries
- **Blocking in job handlers:** Keep individual jobs fast (< 10s each)
- **Not checking Action_Scheduler::is_initialized():** Causes data store issues

## Don't Hand-Roll

Problems that have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Job queue | Custom DB table | Action Scheduler | Built-in UI, logging, retry, cleanup |
| Job scheduling | Custom scheduler | as_enqueue_async_action() | Handles concurrency, memory limits |
| Job locking | Custom locks | Action Scheduler claims | Automatic claiming, timeout handling |
| Batch processing | Manual loops | AS batch size filters | Memory-aware, configurable |
| Failed job retry | Custom retry logic | AS automatic retry | Configurable, exponential backoff |

**Key insight:** Action Scheduler is specifically designed for the exact use case described in PERF-02/PERF-03. It handles memory management, concurrency, admin visibility, and failure recovery automatically.

## Common Pitfalls

### Pitfall 1: Calling AS API Before Init
**What goes wrong:** Actions scheduled but stored incorrectly or lost
**Why it happens:** Data stores not initialized before `init` priority 1
**How to avoid:** Always check `Action_Scheduler::is_initialized()` or use `action_scheduler_init` hook
**Warning signs:** Actions missing from admin UI, silent failures

### Pitfall 2: Lock Expiry During Long Batches
**What goes wrong:** Second process starts while first still running
**Why it happens:** LOCK_TTL (30s) shorter than batch processing time (50+ items)
**How to avoid:** Set LOCK_TTL > MAX_RUNTIME_SECONDS + safety margin
**Warning signs:** Duplicate emails, race condition errors in logs

### Pitfall 3: Memory Exhaustion in Batch Processing
**What goes wrong:** PHP memory limit exceeded, incomplete batches
**Why it happens:** Large BATCH_LIMIT without memory cleanup
**How to avoid:** Keep existing memory cleanup (PERF-05), use AS memory-aware processing
**Warning signs:** Memory errors in debug.log, truncated batches

### Pitfall 4: Blocking Email in Job Handler
**What goes wrong:** Slow SMTP causes job timeouts, queue backup
**Why it happens:** wp_mail() can take several seconds with slow SMTP
**How to avoid:** Keep jobs small (one email per job), use SMTP plugin with queueing
**Warning signs:** Action Scheduler admin shows many past-due actions

### Pitfall 5: Not Separating AI from Email
**What goes wrong:** Email delayed by AI failures, AI rate limits block email
**Why it happens:** Both operations in same synchronous flow
**How to avoid:** Separate hooks (PERF-03), AI schedules email on success
**Warning signs:** All notifications delayed when OpenAI has issues

### Pitfall 6: Duplicate Job Scheduling
**What goes wrong:** Same notification processed multiple times
**Why it happens:** Job scheduled before processing completes, no uniqueness check
**How to avoid:** Use `unique: true` parameter, check `as_has_scheduled_action()` before scheduling
**Warning signs:** Duplicate emails, same log_id in multiple pending actions

## Code Examples

Verified patterns from official sources:

### Installing Action Scheduler via Composer
```php
// Source: https://packagist.org/packages/woocommerce/action-scheduler
// In wecoza-core.php, BEFORE plugins_loaded hook

// Load Action Scheduler from vendor directory
$action_scheduler_path = WECOZA_CORE_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if (file_exists($action_scheduler_path)) {
    require_once $action_scheduler_path;
}
```

### Scheduling Async Email Job
```php
// Source: https://actionscheduler.org/api/
// In NotificationProcessor::process()

as_enqueue_async_action(
    'wecoza_send_notification_email',
    ['log_id' => $logId, 'recipient' => $recipient],
    'wecoza-notifications',  // group for admin filtering
    false,                    // unique - allow multiple (different log_ids)
    10                        // priority - default
);
```

### Registering Action Handler
```php
// Source: https://actionscheduler.org/usage/
// In wecoza-core.php, in the plugins_loaded callback

add_action('wecoza_send_notification_email', function (int $logId, string $recipient) {
    $emailer = NotificationEmailer::boot();
    $emailer->send($logId, $recipient);
}, 10, 2);

add_action('wecoza_enrich_notification', function (int $logId) {
    $enricher = NotificationEnricher::boot();
    $result = $enricher->enrich($logId);

    if ($result->isSuccess()) {
        // Chain to email
        $settings = new NotificationSettings();
        $operation = $result->getOperation();
        $recipient = $settings->getRecipientForOperation($operation);

        if ($recipient !== null) {
            as_enqueue_async_action(
                'wecoza_send_notification_email',
                ['log_id' => $logId, 'recipient' => $recipient],
                'wecoza-notifications'
            );
        }
    }
}, 10, 1);
```

### Checking if Action Scheduler is Ready
```php
// Source: https://github.com/woocommerce/action-scheduler/blob/trunk/docs/api.md

if (function_exists('as_enqueue_async_action') && Action_Scheduler::is_initialized()) {
    // Safe to schedule actions
    as_enqueue_async_action('wecoza_send_notification_email', [...]);
} else {
    // Fallback to synchronous processing
    $this->sendEmailDirectly($logId);
}
```

### Performance Tuning Filters
```php
// Source: https://actionscheduler.org/perf/
// In wecoza-core.php or a dedicated config file

// Increase time limit (default 30s)
add_filter('action_scheduler_queue_runner_time_limit', function () {
    return 60;  // 60 seconds
});

// Increase batch size (default 25)
add_filter('action_scheduler_queue_runner_batch_size', function () {
    return 50;  // Match our BATCH_LIMIT
});

// Optional: Enable concurrent batches on powerful servers
add_filter('action_scheduler_queue_runner_concurrent_batches', function () {
    return 2;  // Process 2 batches in parallel
});
```

### Updated Lock Implementation
```php
// Source: https://urielwilson.com/simple-and-effective-mutual-exclusion-in-wordpress-with-transientlock/
// In NotificationProcessor

private const LOCK_KEY = 'wecoza_notification_processor_lock';
private const LOCK_TTL = 120;  // Increased from 30s for 50+ item batches

private function acquireLock(): bool
{
    $existing = get_transient(self::LOCK_KEY);
    if ($existing !== false) {
        return false;
    }

    // Use atomic operation pattern
    return set_transient(self::LOCK_KEY, time(), self::LOCK_TTL);
}

private function refreshLock(): void
{
    // Extend lock during long processing
    set_transient(self::LOCK_KEY, time(), self::LOCK_TTL);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Sync email in request | Async via WP-Cron | Already implemented | Non-blocking (partial) |
| Single batch processing | Action Scheduler jobs | Phase 12 | True async, independent failure |
| Combined AI + Email | Separate jobs | Phase 12 | AI failures don't block email |
| 30s lock TTL | 120s lock TTL | Phase 12 | Prevents race conditions |
| BATCH_LIMIT = 1 | BATCH_LIMIT = 50+ | Phase 12 | Higher throughput |

**Deprecated/outdated:**
- `prospress/action-scheduler` Composer package: abandoned, use `woocommerce/action-scheduler`
- Synchronous wp_mail in cron: replaced by AS async jobs

## Open Questions

Things that need validation during implementation:

1. **Optimal Batch Size**
   - What we know: BATCH_LIMIT = 50+ required, AS default is 25
   - What's unclear: Exact optimal value depends on server resources
   - Recommendation: Start with 50, monitor memory/timing, adjust via filter

2. **AI Retry Strategy**
   - What we know: AISummaryService has maxAttempts = 3 with backoff
   - What's unclear: Should retries be AS scheduled or immediate?
   - Recommendation: Keep current backoff for same-request retries; AS handles job-level retry

3. **Email Delivery Failures**
   - What we know: wp_mail() returns true/false but doesn't guarantee delivery
   - What's unclear: Should failed sends be retried? How to detect SMTP failures?
   - Recommendation: Log failures, consider SMTP plugin integration for reliability

4. **Action Scheduler Admin Access**
   - What we know: AS provides admin UI at Tools > Scheduled Actions
   - What's unclear: Should WeCoza admins have access? Permission requirements?
   - Recommendation: Available to administrators by default; no extra config needed

## Sources

### Primary (HIGH confidence)
- [Action Scheduler Official Documentation](https://actionscheduler.org/) - API reference, usage patterns
- [Action Scheduler API Reference](https://actionscheduler.org/api/) - Function signatures, parameters
- [Action Scheduler Performance Guide](https://actionscheduler.org/perf/) - Batch size, time limits, concurrency
- [Action Scheduler GitHub](https://github.com/woocommerce/action-scheduler) - Source code, issues, releases
- [Packagist woocommerce/action-scheduler](https://packagist.org/packages/woocommerce/action-scheduler) - Composer installation

### Secondary (MEDIUM confidence)
- [WordPress VIP Action Scheduler Docs](https://docs.wpvip.com/wordpress-on-vip/action-scheduler/) - Enterprise patterns
- [TransientLock Implementation](https://urielwilson.com/simple-and-effective-mutual-exclusion-in-wordpress-with-transientlock/) - Lock patterns
- Existing codebase: NotificationProcessor.php, AISummaryService.php, wecoza-core.php

### Tertiary (LOW confidence)
- WebSearch results on lock TTL and race conditions (general patterns, not WordPress-specific)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Action Scheduler is well-documented, mature (6.4M installs)
- Architecture: HIGH - Patterns verified against official docs and existing codebase
- Pitfalls: HIGH - Documented in official sources and community experience

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - Action Scheduler is stable, infrequent breaking changes)
