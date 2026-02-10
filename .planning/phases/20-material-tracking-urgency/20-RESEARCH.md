# Phase 20: Material Tracking Urgency Indicators - Research

**Researched:** 2026-02-10
**Domain:** Visual urgency indicators via CSS borders, date calculations in PHP/PostgreSQL, Phoenix theme color variables
**Confidence:** HIGH

## Summary

Phase 20 adds visual urgency indicators to the Material Tracking Dashboard by applying color-coded left borders to table rows based on delivery date proximity. The system uses a two-tier urgency model: red borders for overdue/today items (urgent), orange borders for items 1-3 days away (approaching), and no border for items with 3+ days lead time (comfortable). Only pending deliveries receive urgency borders; completed/delivered items never show urgency styling.

The technical domain spans three areas: (1) date calculations to determine urgency tier (PHP `strtotime()` or PostgreSQL date arithmetic), (2) Phoenix theme color variable usage (`var(--phoenix-danger)` and `var(--phoenix-warning)`), and (3) CSS class application to table row elements. The implementation builds on existing patterns from Phase 19's Material Tracking Dashboard refactor.

**Primary recommendation:** Compute urgency tier in PHP (MaterialTrackingPresenter) using `strtotime()` date comparisons. Add CSS classes `urgency-overdue` and `urgency-approaching` to table rows. Style with 3px solid left borders using Phoenix color variables `var(--phoenix-danger)` and `var(--phoenix-warning)`. This matches existing WeCoza patterns for status indicators and maintains separation between presentation logic (PHP) and styling (CSS).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Urgency thresholds:**
- **Red** — delivery date is today OR has passed, AND status is still pending (today counts as expired)
- **Orange** — delivery date is 1-3 calendar days away (upcoming, not yet expired)
- **No border** — delivery date is more than 3 days away (no urgency, no visual indicator needed)
- Red persists as long as the item remains pending — clears only when marked delivered/cancelled/etc.

**Border styling:**
- 3px solid left border on each `<tr>` row
- Color matches urgency tier: red / orange (two-tier system, no green)
- Use Phoenix theme color variables (not hardcoded hex)
- No background tint — border only
- Delivered/completed rows: no urgency border (only pending rows get borders)

**Delivery date text:**
- No color change on delivery date text — border alone communicates urgency
- No "X days overdue" subtext

**Status badge:**
- PENDING badge stays as-is regardless of urgency
- No OVERDUE badge — the left border is the sole urgency indicator

### Claude's Discretion

- Exact Phoenix color variable mapping for red/orange
- Whether urgency is computed in PHP (presenter) or JS (client-side)
- CSS class naming convention

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope

</user_constraints>

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.0+ | Date calculations | WeCoza Core requirement, `strtotime()` and date comparisons |
| Phoenix Theme | Current | Color variables | Project-wide design system, maintains consistency |
| Bootstrap CSS | 5.3 | Utility classes | Foundation for Phoenix, provides border/color patterns |
| PostgreSQL | 12+ | Date columns | Data source for `event_dates[].date` field (ISO 8601 strings) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `strtotime()` | PHP 8.0+ | Parse date strings | Converting ISO date to timestamp for comparison |
| `gmdate()` / `wp_date()` | PHP/WP | Current date | Get today's date for comparison (timezone-aware) |
| CSS custom properties | CSS3 | Phoenix color vars | `var(--phoenix-danger)`, `var(--phoenix-warning)` |
| PHP `match()` | PHP 8.0+ | Status mapping | Already used in MaterialTrackingPresenter for badges |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP date calc | JavaScript client-side | JS requires duplicate logic, doesn't work with SSR/caching |
| Phoenix color vars | Hardcoded hex (#dc3545) | Breaks theme consistency, can't adapt to dark mode |
| CSS classes | Inline styles | Harder to maintain, no reusability, violates separation of concerns |
| `strtotime()` | `DateTime` objects | DateTime is more robust but overkill for simple comparisons |

**Installation:**
```bash
# No additional installation needed - all tools are standard
# Phoenix color variables already defined in theme
# PHP date functions are built-in
```

## Architecture Patterns

### Existing Project Structure
```
src/Events/
├── Views/Presenters/
│   └── MaterialTrackingPresenter.php   # ⚠️ ADD urgency calculation method
└── Repositories/
    └── MaterialTrackingRepository.php  # No change - event_date already fetched

views/events/material-tracking/
├── list-item.php                       # ⚠️ ADD urgency CSS class to <tr>
└── dashboard.php                       # No change - styling is per-row

CSS location:
/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
# ⚠️ ADD urgency border styles
```

### Pattern 1: PHP Date Comparison for Urgency
**What:** Calculate days between delivery date and today, map to urgency tier
**When to use:** Server-side urgency calculation in presenter layer
**Example:**
```php
// Source: Adapted from MaterialNotificationService.php line 73 (PostgreSQL pattern)
// Implemented in MaterialTrackingPresenter

private function calculateUrgency(string $eventDate, string $eventStatus): string
{
    // Only pending items get urgency indicators
    if (strtolower($eventStatus) !== 'pending') {
        return 'none';
    }

    $today = strtotime(gmdate('Y-m-d'));  // Normalize to date only (no time)
    $deliveryTimestamp = strtotime($eventDate);

    if ($deliveryTimestamp === false) {
        return 'none';  // Invalid date
    }

    $daysUntilDelivery = (int) (($deliveryTimestamp - $today) / 86400);  // 86400 sec/day

    // Red: today or past (0 or negative)
    if ($daysUntilDelivery <= 0) {
        return 'overdue';
    }

    // Orange: 1-3 days away
    if ($daysUntilDelivery >= 1 && $daysUntilDelivery <= 3) {
        return 'approaching';
    }

    // No urgency: 4+ days away
    return 'none';
}
```

**Key insight:** Use `gmdate('Y-m-d')` to get UTC date without time component. Divide timestamp difference by 86400 (seconds per day) to get integer day count. Status must be "pending" to show urgency.

### Pattern 2: Phoenix Color Variable Usage
**What:** Apply Phoenix theme color variables for consistent urgency styling
**When to use:** CSS rules for urgency borders
**Example:**
```css
/* Source: Verified from ydcoza-styles.css lines 55-71, 585-600 */
/* Location: /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css */

/* Material Tracking Urgency Indicators */
#material-tracking-table tbody tr.urgency-overdue {
  border-left: 3px solid var(--phoenix-danger);
}

#material-tracking-table tbody tr.urgency-approaching {
  border-left: 3px solid var(--phoenix-warning);
}

/* No border for .urgency-none or non-pending rows */
```

**Key insight:** Phoenix color variables are already defined theme-wide: `var(--phoenix-danger)` for red, `var(--phoenix-warning)` for orange. These adapt to theme modes automatically. Other examples in codebase use 4px or 5px borders, but user specified 3px.

### Pattern 3: CSS Class Application in Presenter
**What:** Add urgency CSS class to presenter output for view consumption
**When to use:** Enhancing presenter data with presentation metadata
**Example:**
```php
// Source: Adapted from MaterialTrackingPresenter.php presentRecords() pattern
// Lines 21-46 show existing presenter structure

public function presentRecords(array $records): array
{
    $presented = [];

    foreach ($records as $record) {
        $urgency = $this->calculateUrgency(
            $record['event_date'] ?? '',
            $record['event_status'] ?? 'pending'
        );

        $presented[] = [
            // ... existing fields ...
            'event_date' => $this->formatDate($record['event_date'] ?? null),
            'event_status' => strtolower((string) ($record['event_status'] ?? 'pending')),
            'urgency_class' => $urgency !== 'none' ? 'urgency-' . $urgency : '',
            'status_badge_html' => $this->getEventStatusBadge(...),
            // ... rest of fields ...
        ];
    }

    return $presented;
}
```

**Key insight:** Presenter already formats data for view consumption (lines 26-42). Add `urgency_class` field to existing structure. Empty string when no urgency, otherwise `urgency-overdue` or `urgency-approaching`.

### Pattern 4: View Template Row Markup
**What:** Apply urgency class to table row element
**When to use:** Rendering table rows in list-item.php view
**Example:**
```php
// Source: list-item.php lines 21-26 (existing <tr> structure)
// Add urgency class to existing data attributes

<tr data-status="<?php echo esc_attr($record['delivery_status']); ?>"
    data-class-id="<?php echo esc_attr((string) $record['class_id']); ?>"
    data-class-code="<?php echo esc_attr($record['class_code']); ?>"
    data-client-name="<?php echo esc_attr($record['client_name']); ?>"
    data-start-date="<?php echo esc_attr($record['original_start_date']); ?>"
    data-event-date="<?php echo esc_attr($record['event_date']); ?>"
    class="<?php echo esc_attr($record['urgency_class'] ?? ''); ?>">
    <!-- existing table cells -->
</tr>
```

**Key insight:** Existing `<tr>` has data attributes for sorting/filtering (lines 21-26). Add `class` attribute with urgency class. Use `esc_attr()` for security. Empty string is valid (no class applied).

### Anti-Patterns to Avoid
- **Client-side date calculation:** Breaks with caching, requires timezone handling in JS, duplicates logic
- **Hardcoded color hex values:** `#dc3545` instead of `var(--phoenix-danger)` breaks theme consistency
- **Background color tints:** User explicitly rejected background styling (border only)
- **Modifying status badges:** PENDING badge must remain unchanged regardless of urgency
- **Adding "X days overdue" text:** User explicitly rejected text annotations

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date arithmetic | Manual day counting | `strtotime()` + division | Handles leap years, DST, edge cases correctly |
| Phoenix color values | Color picker + hardcode | `var(--phoenix-danger)` | Theme adaptation, dark mode support, maintainability |
| Status-to-urgency mapping | IF-ELSE chains | PHP 8.0 `match()` | Already used in codebase (line 104-108), cleaner |
| CSS class construction | String concatenation | Ternary with empty fallback | Avoids empty classes, cleaner HTML |

**Key insight:** WeCoza codebase already uses Phoenix color variables extensively (ydcoza-styles.css lines 55-71, 218, 585-600, 829-833, 1575-1583). Reuse pattern, don't invent new approach.

## Common Pitfalls

### Pitfall 1: Timezone Mismatches in Date Comparison
**What goes wrong:** User's local date (2026-02-10) vs server UTC date (2026-02-09) causes off-by-one urgency errors
**Why it happens:** `strtotime()` uses server timezone, `event_date` is stored as ISO 8601 string
**How to avoid:** Use `gmdate('Y-m-d')` for UTC normalization. Compare dates only (not datetime). PostgreSQL stores dates as DATE type, no time component.
**Warning signs:** Urgency borders appear one day early/late, especially for users far from server timezone

**Example:**
```php
// WRONG: Uses server local time, can drift from UTC
$today = strtotime(date('Y-m-d'));

// CORRECT: Uses UTC, matches PostgreSQL DATE behavior
$today = strtotime(gmdate('Y-m-d'));
```

### Pitfall 2: Showing Urgency for Non-Pending Items
**What goes wrong:** Completed/delivered items show red/orange borders even though they're done
**Why it happens:** Urgency calculation only checks date, not status
**How to avoid:** Check `event_status !== 'pending'` FIRST, return 'none' early for non-pending items
**Warning signs:** Delivered items with left borders, user confusion about what "urgent" means

**Example:**
```php
// WRONG: Delivered item 2 days ago shows red border
if ($daysUntilDelivery <= 0) { return 'overdue'; }

// CORRECT: Non-pending items never get urgency borders
if (strtolower($eventStatus) !== 'pending') { return 'none'; }
if ($daysUntilDelivery <= 0) { return 'overdue'; }
```

### Pitfall 3: Invalid Date Handling
**What goes wrong:** `strtotime()` returns `false` for invalid dates, causing PHP warnings/errors
**Why it happens:** Event dates could be malformed, NULL, or empty strings
**How to avoid:** Check `strtotime()` result for `false`, return 'none' for invalid dates
**Warning signs:** PHP warnings in debug.log, blank urgency classes, inconsistent border display

**Example:**
```php
// WRONG: Assumes date is always valid
$daysUntilDelivery = ($deliveryTimestamp - $today) / 86400;

// CORRECT: Handle invalid dates gracefully
$deliveryTimestamp = strtotime($eventDate);
if ($deliveryTimestamp === false) {
    return 'none';  // Invalid date, no urgency
}
```

### Pitfall 4: Off-by-One Day Boundaries
**What goes wrong:** Delivery date "2026-02-10", today "2026-02-10", calculates as 0.5 days (orange) instead of 0 days (red)
**Why it happens:** Comparing timestamps with time components instead of normalized dates
**How to avoid:** Normalize both dates to midnight UTC before comparison using `gmdate('Y-m-d')`
**Warning signs:** Items due "today" showing orange instead of red, urgency changes mid-day

**Example:**
```php
// WRONG: Compares timestamps with time components
$today = time();  // e.g., 1707584943 (includes hours/minutes/seconds)
$delivery = strtotime($eventDate);  // e.g., 1707580800 (midnight)
$days = ($delivery - $today) / 86400;  // Could be 0.5, -0.3, etc.

// CORRECT: Normalize to date-only comparison
$today = strtotime(gmdate('Y-m-d'));  // Midnight UTC
$delivery = strtotime($eventDate);     // Already midnight (DATE field)
$days = (int) (($delivery - $today) / 86400);  // Always integer
```

### Pitfall 5: CSS Specificity Conflicts
**What goes wrong:** Urgency border doesn't appear because other CSS rules override it
**Why it happens:** Generic `tr` styles or table hover states have higher specificity
**How to avoid:** Use specific selector: `#material-tracking-table tbody tr.urgency-overdue`
**Warning signs:** Urgency classes present in HTML but no visible borders, borders disappear on hover

**Example:**
```css
/* WRONG: Too generic, can be overridden */
tr.urgency-overdue { border-left: 3px solid var(--phoenix-danger); }

/* CORRECT: Specific selector matches dashboard table structure */
#material-tracking-table tbody tr.urgency-overdue {
  border-left: 3px solid var(--phoenix-danger);
}
```

## Code Examples

Verified patterns from official sources:

### Date Calculation Pattern (PostgreSQL Reference)
```php
// Source: MaterialNotificationService.php line 73
// Shows PostgreSQL date arithmetic pattern (adapted to PHP)

// PostgreSQL pattern (for reference):
// WHERE c.original_start_date = CURRENT_DATE + INTERVAL '7 days'

// PHP equivalent for urgency calculation:
private function calculateUrgency(string $eventDate, string $eventStatus): string
{
    if (strtolower($eventStatus) !== 'pending') {
        return 'none';
    }

    $today = strtotime(gmdate('Y-m-d'));
    $deliveryTimestamp = strtotime($eventDate);

    if ($deliveryTimestamp === false) {
        return 'none';
    }

    $daysUntilDelivery = (int) (($deliveryTimestamp - $today) / 86400);

    return match (true) {
        $daysUntilDelivery <= 0 => 'overdue',        // Red: today or past
        $daysUntilDelivery <= 3 => 'approaching',    // Orange: 1-3 days
        default => 'none',                           // No border: 4+ days
    };
}
```

### Phoenix Color Variables (Existing Usage)
```css
/* Source: ydcoza-styles.css lines 55-71 (Status Indicators with Phoenix Colors) */
/* Pattern already used for .wecoza-status-tile elements */

.wecoza-status-tile.open-task {
  border-left: 4px solid var(--phoenix-warning);
  background: linear-gradient(90deg, rgba(229, 120, 11, 0.05) 0%, transparent 100%);
}

.wecoza-status-tile.overdue {
  border-left: 4px solid var(--phoenix-danger);
  background: linear-gradient(90deg, rgba(250, 59, 29, 0.05) 0%, transparent 100%);
}

/* Adapted for material tracking (border only, no background): */
#material-tracking-table tbody tr.urgency-overdue {
  border-left: 3px solid var(--phoenix-danger);
}

#material-tracking-table tbody tr.urgency-approaching {
  border-left: 3px solid var(--phoenix-warning);
}
```

### Presenter Integration Pattern
```php
// Source: MaterialTrackingPresenter.php lines 21-46 (presentRecords structure)

public function presentRecords(array $records): array
{
    $presented = [];

    foreach ($records as $record) {
        $urgency = $this->calculateUrgency(
            $record['event_date'] ?? '',
            $record['event_status'] ?? 'pending'
        );

        $presented[] = [
            'class_id' => (int) $record['class_id'],
            'class_code' => esc_html((string) ($record['class_code'] ?? 'N/A')),
            'class_subject' => esc_html((string) ($record['class_subject'] ?? 'N/A')),
            'client_name' => esc_html((string) ($record['client_name'] ?? 'N/A')),
            'site_name' => esc_html((string) ($record['site_name'] ?? 'N/A')),
            'original_start_date' => $this->formatDate($record['original_start_date'] ?? null),
            'event_date' => $this->formatDate($record['event_date'] ?? null),
            'event_description' => esc_html((string) ($record['event_description'] ?? '')),
            'event_index' => (int) ($record['event_index'] ?? 0),
            'event_status' => strtolower((string) ($record['event_status'] ?? 'pending')),
            'notification_type' => (string) ($record['notification_type'] ?? ''),
            'notification_sent_at' => $this->formatDateTime($record['notification_sent_at'] ?? null),
            'notification_badge_html' => $this->getNotificationBadge($record['notification_type'] ?? null),
            'status_badge_html' => $this->getEventStatusBadge(strtolower((string) ($record['event_status'] ?? 'pending'))),
            'delivery_status' => $this->mapEventStatus(strtolower((string) ($record['event_status'] ?? 'pending'))),
            'urgency_class' => $urgency !== 'none' ? 'urgency-' . $urgency : '',  // NEW field
        ];
    }

    return $presented;
}
```

### View Template Row Markup
```php
// Source: list-item.php lines 21-26 (existing <tr> structure)

<tr data-status="<?php echo esc_attr($record['delivery_status']); ?>"
    data-class-id="<?php echo esc_attr((string) $record['class_id']); ?>"
    data-class-code="<?php echo esc_attr($record['class_code']); ?>"
    data-client-name="<?php echo esc_attr($record['client_name']); ?>"
    data-start-date="<?php echo esc_attr($record['original_start_date']); ?>"
    data-event-date="<?php echo esc_attr($record['event_date']); ?>"
    class="<?php echo esc_attr($record['urgency_class'] ?? ''); ?>">

    <!-- Class Code/Subject -->
    <td class="py-2 align-middle ps-3">
        <span class="fw-medium">
            <?php echo esc_html($record['class_code']); ?> - <?php echo esc_html($record['class_subject']); ?>
        </span>
    </td>

    <!-- ... rest of cells ... -->
</tr>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No visual urgency | Border-based urgency tiers | Phase 20 (v1.3) | Immediate visual scanning for urgent deliveries |
| Text-based urgency ("5d", "7d") | Visual borders + notification badges | Phase 18-20 (v1.3) | Badges show notification status, borders show date urgency |
| Single urgency level | Two-tier system (red/orange) | Phase 20 (v1.3) | Differentiates "urgent now" from "upcoming soon" |

**Deprecated/outdated:**
- Text annotations for urgency: Now handled by left border color
- Background color tints for status: User explicitly rejected, border-only approach
- Hardcoded color values: Phoenix variables provide theme consistency

**Current best practice:**
- Phoenix color variables: `var(--phoenix-danger)` and `var(--phoenix-warning)`
- Server-side urgency calculation: Consistent with caching, timezone-safe
- Separation of concerns: Presenter calculates urgency, CSS handles styling

## Bootstrap Color Utilities Reference

**Source:** Bootstrap 5.3 Documentation (via Context7)

### Border Color Classes
Bootstrap provides theme-based border color utilities:

```html
<!-- Standard border colors -->
<span class="border border-primary"></span>
<span class="border border-danger"></span>
<span class="border border-warning"></span>
<span class="border border-success"></span>

<!-- Subtle variants (softer shades) -->
<span class="border border-danger-subtle"></span>
<span class="border border-warning-subtle"></span>
```

**Phoenix Integration:**
- Phoenix theme overrides Bootstrap color variables
- `var(--phoenix-danger)` maps to Bootstrap's danger color system
- `var(--phoenix-warning)` maps to Bootstrap's warning color system
- Custom CSS uses Phoenix variables directly for consistency

### Text Color Classes
```html
<p class="text-danger">.text-danger</p>
<p class="text-warning bg-dark">.text-warning</p>
```

**Note:** User explicitly rejected text color changes for delivery date. Border color is sole urgency indicator.

## Urgency Logic Summary

| Condition | Days Until Delivery | Status | Urgency Tier | CSS Class | Border Color |
|-----------|---------------------|--------|--------------|-----------|--------------|
| Overdue | ≤ 0 (today or past) | Pending | Red | `urgency-overdue` | `var(--phoenix-danger)` |
| Approaching | 1-3 days | Pending | Orange | `urgency-approaching` | `var(--phoenix-warning)` |
| Comfortable | 4+ days | Pending | None | (empty) | No border |
| Completed | Any | Delivered/Completed/etc. | None | (empty) | No border |
| Invalid | N/A | Any | None | (empty) | No border |

**Calculation formula:**
```php
$daysUntilDelivery = (int) (($deliveryTimestamp - $todayTimestamp) / 86400);
```

Where:
- `$todayTimestamp = strtotime(gmdate('Y-m-d'))` (UTC normalized)
- `$deliveryTimestamp = strtotime($eventDate)` (ISO 8601 date string)
- Division by 86400 converts seconds to days
- Cast to `(int)` ensures whole number day count

## Open Questions

None identified. All technical decisions have clear answers:

1. **PHP vs JavaScript date calculation:** PHP is correct choice (server-side, timezone-safe, cached)
2. **Phoenix color variables:** Verified as `var(--phoenix-danger)` and `var(--phoenix-warning)`
3. **CSS class naming:** `urgency-overdue` and `urgency-approaching` follow existing kebab-case pattern
4. **Border width:** User specified 3px solid
5. **Status filtering:** Only pending items get urgency borders (clearly specified)

## Sources

### Primary (HIGH confidence)
- **WeCoza Core codebase** (wecoza-core plugin)
  - `src/Events/Views/Presenters/MaterialTrackingPresenter.php` - Presenter structure, badge patterns
  - `src/Events/Services/MaterialNotificationService.php` - Date calculation patterns (line 73)
  - `views/events/material-tracking/list-item.php` - Table row structure (lines 21-26)
  - `views/events/material-tracking/dashboard.php` - Table structure, existing styles
- **Phoenix Theme CSS** (wecoza_3_child_theme)
  - `includes/css/ydcoza-styles.css` - Phoenix color variable usage (lines 55-71, 218, 585-600)
- **Phase 20 CONTEXT.md** - User decisions on thresholds, styling, constraints

### Secondary (MEDIUM confidence)
- **Bootstrap 5.3 Documentation** (Context7: /websites/getbootstrap)
  - Color utility classes: https://getbootstrap.com/docs/5.3/utilities/colors
  - Border utilities: https://getbootstrap.com/docs/5.3/utilities/borders
  - Confirmed `border-danger`, `border-warning` classes and variable patterns
- **Phase 19 RESEARCH.md** - Material Tracking Dashboard architecture, JSONB query patterns

### Tertiary (LOW confidence)
- None used - all research from primary and verified secondary sources

## Metadata

**Confidence breakdown:**
- Date calculation patterns: HIGH - Verified from MaterialNotificationService.php existing code
- Phoenix color variables: HIGH - Verified from ydcoza-styles.css usage (15+ occurrences)
- CSS border patterns: HIGH - Verified from existing status tile implementations
- Presenter integration: HIGH - Verified from MaterialTrackingPresenter.php structure
- Bootstrap integration: MEDIUM - Verified via Context7, Phoenix may customize further

**Research date:** 2026-02-10
**Valid until:** 2026-03-10 (30 days - stable domain, CSS/PHP patterns unlikely to change)

---

## RESEARCH COMPLETE

Phase 20 research is complete. Ready for planning.

**Key Findings:**
1. Phoenix color variables verified: `var(--phoenix-danger)` for red, `var(--phoenix-warning)` for orange
2. Date calculation pattern exists in MaterialNotificationService.php - adapt to PHP `strtotime()` for urgency
3. Presenter already structures data for view - add `urgency_class` field to existing array
4. CSS border pattern already used 15+ times in ydcoza-styles.css - apply to table rows
5. User constraints clearly defined: two-tier system, pending-only, border-only, no text changes

**Implementation Approach:**
- **Presenter:** Add `calculateUrgency()` method, extend `presentRecords()` to include urgency class
- **View:** Add `class="<?php echo esc_attr($record['urgency_class']); ?>"` to `<tr>` in list-item.php
- **CSS:** Add `.urgency-overdue` and `.urgency-approaching` rules with 3px solid borders using Phoenix variables
- **Files modified:** MaterialTrackingPresenter.php, list-item.php, ydcoza-styles.css (3 files total)
