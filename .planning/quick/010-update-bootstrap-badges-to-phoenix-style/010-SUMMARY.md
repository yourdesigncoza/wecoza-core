# Quick Task 010: Update Bootstrap Badges to Phoenix Style

## Task
Convert all Bootstrap badge classes (`bg-*`) to Phoenix theme style (`badge-phoenix badge-phoenix-*`).

## Changes

**8 files updated:**

| File | Badge Changes |
|------|---------------|
| `views/classes/components/classes-display.view.php` | `bg-primary bg-opacity-10 text-primary` → `badge-phoenix badge-phoenix-primary` |
| `views/learners/components/learner-progressions.php` | `bg-primary` → `badge-phoenix badge-phoenix-primary`, `bg-success` → `badge-phoenix badge-phoenix-success` |
| `views/events/event-tasks/main.php` | `bg-primary bg-opacity-10 text-primary` → `badge-phoenix badge-phoenix-primary` |
| `views/classes/components/single-class-display.view.php` | `bg-warning`, `bg-info`, `bg-secondary`, `bg-success` → Phoenix equivalents |
| `views/classes/components/single-class/details-staff.php` | `bg-light text-dark` → `badge-phoenix badge-phoenix-secondary` |
| `views/classes/components/single-class/qa-reports.php` | `bg-secondary` → `badge-phoenix badge-phoenix-secondary` |
| `views/classes/components/class-capture-partials/create-class.php` | `bg-danger`, `bg-info` → Phoenix equivalents |
| `views/classes/components/class-capture-partials/update-class.php` | `bg-danger`, `bg-info` → Phoenix equivalents |

## Phoenix Badge Pattern

```html
<span class="badge badge-phoenix badge-phoenix-{color}">Label</span>
```

Available colors: `primary`, `secondary`, `success`, `warning`, `danger`, `info`

## Commit
`9742c0b` - style(badges): convert Bootstrap badges to Phoenix style

## Date
2026-02-05
