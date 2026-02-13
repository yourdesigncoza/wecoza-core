# Phase 35: Events Module Fixes - Research

**Researched:** 2026-02-13
**Domain:** WordPress template escaping and security best practices
**Confidence:** HIGH

## Summary

Phase 35 addresses WordPress escaping best practices violations and database consistency issues in the Events module. The audit identified four specific violations: (1) presenter-generated HTML output without explicit escaping in views, (2) incomplete database updates in `markDelivered()`, and (3) duplicate JavaScript code.

The core issue is philosophical: WordPress strongly advocates for "late escaping" where all output is escaped at the point of rendering in template files, not pre-escaped in PHP classes. While the current implementation is functionally secure (presenters escape individual values before wrapping in HTML tags), it violates WordPress VIP Documentation standards and makes code review more difficult.

**Primary recommendation:** Add `wp_kses_post()` wrapping at all output points for presenter-generated HTML, update `markDelivered()` to sync the tracking table, and consolidate duplicate JavaScript.

## Standard Stack

### Core WordPress Functions

| Function | Purpose | Use Case |
|----------|---------|----------|
| `esc_html()` | Strip all HTML tags | Text content that should contain no HTML |
| `esc_attr()` | Escape for HTML attributes | Data attributes, input values, CSS classes |
| `wp_kses_post()` | Allow WordPress post HTML only | Content that should contain limited safe HTML |
| `wp_kses($text, $allowed_html)` | Custom HTML whitelist | Content with specific allowed tags |

**Why these are standard:**
- WordPress Coding Standards mandate late escaping at output point
- Theme Review Team requires all dynamic output to be escaped
- VIP Documentation explicitly requires escaping even "safe" generated HTML

### Installation
No new dependencies required. Functions are WordPress core.

## Architecture Patterns

### Pattern 1: Late Escaping in Templates

**What:** All escaping happens at the point of output in template files, not in PHP classes.

**When to use:** Always, per WordPress Coding Standards and VIP Documentation.

**Example:**
```php
// WRONG: Pre-escaped in presenter class
class MyPresenter {
    private function getBadgeHtml(): string {
        return '<span class="badge">' . esc_html($text) . '</span>';
    }
}
// In template:
<?php echo $data['badge_html']; ?> // No escaping visible

// CORRECT: Escape at output point
class MyPresenter {
    private function getBadgeHtml(): string {
        return '<span class="badge">' . esc_html($text) . '</span>';
    }
}
// In template:
<?php echo wp_kses_post($data['badge_html']); ?> // Explicit escaping
```

**Source:** [WordPress VIP Documentation - Validating, sanitizing, and escaping](https://docs.wpvip.com/security/validating-sanitizing-and-escaping/)

### Pattern 2: Database Sync for Dual Storage

**What:** When data exists in both JSONB column and dedicated table columns, update both locations.

**When to use:** Material tracking stores `status` in `classes.event_dates` JSONB and `class_material_tracking.delivery_status` column.

**Example:**
```php
// Update JSONB in classes table
$sql = 'UPDATE classes SET event_dates = jsonb_set(...)';
// ALSO update dedicated tracking table
$sql2 = 'UPDATE class_material_tracking
         SET materials_delivered_at = NOW(), delivery_status = \'delivered\'
         WHERE class_id = :class_id';
```

**Why both locations:**
- Dashboard queries join tracking table for performance
- JSONB stores authoritative event date structure
- Tracking table provides indexed queries and notification history

### Pattern 3: Single Source of Truth for JavaScript

**What:** Inline JavaScript handlers should exist in exactly one location.

**Where:** Either in PHP class that renders settings page OR in view template, not both.

**Current situation:** Test notification handler exists in:
- `SettingsPage.php:320-346` (active)
- `views/events/admin/notification-settings.php:80-112` (unused template)

**Resolution:** Remove from unused template file.

### Anti-Patterns to Avoid

- **Pre-escaping in presenters without late escaping in templates:** Makes code review difficult, violates WordPress standards
- **Partial database updates:** Updating JSONB but not tracking table creates data inconsistency
- **Copy-pasted JavaScript:** Maintenance nightmare, increases bundle size

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTML sanitization | Custom strip_tags logic | `wp_kses_post()` or `wp_kses()` | Handles edge cases, entity encoding, nested tags, protocol validation |
| Output escaping | Manual htmlspecialchars | `esc_html()`, `esc_attr()`, `esc_url()` | Context-aware, handles encoding, maintained by WordPress security team |
| SQL injection prevention | String concatenation with escaping | Prepared statements with PDO | Prevents second-order injection, handles encoding |

**Key insight:** WordPress security functions have been battle-tested across millions of sites. Custom solutions miss edge cases.

## Common Pitfalls

### Pitfall 1: "It's already safe" assumption

**What goes wrong:** Developer assumes presenter-generated HTML is safe and outputs it unescaped.

**Why it happens:** Functional security (presenters do escape individual values) appears sufficient.

**How to avoid:** Follow WordPress standards: escape late, escape at output point, even if "already safe."

**Warning signs:**
- Code review comments asking "where is this escaped?"
- Template outputs `$data['*_html']` without `wp_kses_post()`
- Audit findings marked as "WARN: Low risk but deviates from best practices"

**Source:** [WordPress VIP Documentation - Code reviews and deploys can happen faster because it can be deemed safe for output at a glance](https://docs.wpvip.com/security/validating-sanitizing-and-escaping/)

### Pitfall 2: Forgotten tracking table updates

**What goes wrong:** `markDelivered()` updates `classes.event_dates` JSONB but not `class_material_tracking` table, leaving `materials_delivered_at` column perpetually NULL.

**Why it happens:** JSONB became source of truth, dedicated table columns forgotten.

**How to avoid:**
1. When schema has dedicated columns, keep them in sync with JSONB
2. Document dual-storage decision in code comments
3. Integration tests should verify both locations

**Warning signs:**
- Database column exists but has no write path
- Queries join tracking table but rely solely on JSONB for delivery status
- Audit identifies "orphaned columns"

### Pitfall 3: Duplicate JavaScript from copy-paste

**What goes wrong:** Settings page JavaScript handler defined in both PHP class and unused view template.

**Why it happens:** Template refactored to render fields directly in PHP class, but old template file not deleted.

**How to avoid:**
1. Delete unused template files immediately after refactoring
2. Use single inline JS block per admin page
3. Grep for duplicate selectors: `grep -r "\.wecoza-test-notification" --include="*.php"`

**Warning signs:**
- Multiple files defining same click handler
- View template exists but no code calls `wecoza_view()` to render it
- JS handlers fire twice on button click

## Code Examples

### Example 1: Escaping presenter-generated HTML at output

**Current (violates WordPress standards):**
```php
// views/events/ai-summary/card.php:54
<?php if ($summary['has_summary']): ?>
    <div class="fs-9 text-body">
        <?php echo $summary['summary_html']; ?> // WARN: no escaping
    </div>
<?php endif; ?>
```

**Correct (explicit escaping at output):**
```php
// views/events/ai-summary/card.php:54
<?php if ($summary['has_summary']): ?>
    <div class="fs-9 text-body">
        <?php echo wp_kses_post($summary['summary_html']); ?> // Explicit escaping
    </div>
<?php endif; ?>
```

**Source:** [WordPress Plugin Handbook - Securing Output](https://developer.wordpress.org/plugins/security/securing-output/)

### Example 2: Updating tracking table in markDelivered()

**Current (incomplete):**
```php
// src/Events/Repositories/MaterialTrackingRepository.php:86-135
public function markDelivered(int $classId, int $eventIndex): void
{
    // Updates classes.event_dates JSONB
    $sql = 'UPDATE classes SET event_dates = jsonb_set(...) WHERE class_id = :class_id';
    $stmt->execute([':class_id' => $classId, ...]);
    // BUG: class_material_tracking.materials_delivered_at never set
}
```

**Correct (sync both locations):**
```php
public function markDelivered(int $classId, int $eventIndex): void
{
    // Update JSONB (source of truth)
    $sql1 = 'UPDATE classes SET event_dates = jsonb_set(...) WHERE class_id = :class_id';
    $stmt1->execute([':class_id' => $classId, ...]);

    // Sync tracking table
    $sql2 = 'UPDATE class_material_tracking
             SET materials_delivered_at = NOW(),
                 delivery_status = \'delivered\',
                 updated_at = NOW()
             WHERE class_id = :class_id';
    $stmt2->execute([':class_id' => $classId]);
}
```

### Example 3: Removing duplicate JavaScript

**Duplicate 1 (active - keep):**
```php
// src/Events/Admin/SettingsPage.php:320-346
<script>
(function($) {
    $(document).on('click', '.wecoza-test-notification', function(e) {
        // Handler implementation
    });
})(jQuery);
</script>
```

**Duplicate 2 (unused - delete):**
```php
// views/events/admin/notification-settings.php:80-112
<script>
(function($) {
    $(document).on('click', '.wecoza-test-notification', function(e) {
        // Identical handler implementation
    });
})(jQuery);
</script>
```

**Resolution:** Delete `views/events/admin/notification-settings.php` entirely (file is unused).

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Pre-escape in PHP, output raw HTML | Escape at output point in templates | WordPress VIP guidance ~2015 | Faster code reviews, security scanners can verify safety |
| `esc_html()` for all contexts | Context-aware functions (`wp_kses_post` for HTML, `esc_attr` for attributes) | WordPress 2.8+ | Prevents context-specific XSS |
| Single data storage location | Dual storage (JSONB + dedicated columns) | Phase 18 (Events module) | Performance vs flexibility tradeoff |

**Current best practice (2026):**
- Late escaping is mandatory per WordPress Theme Review Guidelines
- VIP Code Analysis flags unescaped output even if "safe"
- Presenter pattern acceptable if templates add explicit escaping

## Open Questions

**None.** Requirements are clear from audit findings and WordPress documentation.

## Sources

### Primary (HIGH confidence)
- [WordPress Developer Reference - Escaping Data](https://developer.wordpress.org/apis/security/escaping/)
- [WordPress VIP Documentation - Validating, sanitizing, and escaping](https://docs.wpvip.com/security/validating-sanitizing-and-escaping/)
- [WordPress Plugin Handbook - Securing Output](https://developer.wordpress.org/plugins/security/securing-output/)
- Codebase audit: `docs/formfieldanalysis/events-module-audit.md` (2026-02-12)

### Secondary (MEDIUM confidence)
- [Afteractive - Escaping HTML in WordPress Templates to Improve Security](https://www.afteractive.com/blog/escaping-html-wordpress)
- [WP Punk - Best guide to security output for WordPress](https://wp-punk.com/best-guide-to-security-output-for-wordpress/)

### Verified via Context7
- WordPress Core Library (`/websites/developer_wordpress_reference_classes`) - `wp_kses()`, `esc_html()` examples

## Metadata

**Confidence breakdown:**
- WordPress escaping functions: HIGH - Official documentation + Context7
- Late escaping requirement: HIGH - VIP Documentation explicit requirement
- Database sync pattern: HIGH - Verified current schema and code paths
- Duplicate JS identification: HIGH - Files examined directly

**Research date:** 2026-02-13
**Valid until:** 2026-04-13 (60 days - WordPress security practices stable)

## Implementation Checklist

For planner to translate into tasks:

- [ ] EVT-01: Add `wp_kses_post()` wrapping in 3 view files (card.php:54, timeline.php:120, item.php:79)
- [ ] EVT-02: Add `wp_kses_post()` wrapping in list-item.php:55,60 for badge HTML
- [ ] EVT-03: Update `MaterialTrackingRepository::markDelivered()` to also UPDATE `class_material_tracking` table
- [ ] EVT-04: Delete unused `views/events/admin/notification-settings.php` file (duplicate JS)
- [ ] Verification: Grep for unescaped `*_html` outputs in events views
- [ ] Verification: Test material delivery marks tracking table columns correctly
- [ ] Verification: Confirm single test notification handler fires once
