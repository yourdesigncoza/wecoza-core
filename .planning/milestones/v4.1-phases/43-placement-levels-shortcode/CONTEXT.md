# Phase 43 Context: Placement Levels Shortcode

## Problem

The `learner_placement_level` table (3 columns: `placement_level_id`, `level`, `level_desc`) has no admin UI. Needs the same CRUD management as qualifications.

## Approach

**Thin phase** — reuse 100% of Phase 42 infrastructure. Only changes needed:

1. Add `placement_levels` config entry to `LookupTableController::TABLES`
2. Register `[wecoza_manage_placement_levels]` shortcode in the controller
3. Register the new shortcode in `wecoza-core.php` (if not auto-registered by controller)
4. Add autoloader entry for `WeCoza\LookupTables\` namespace (if not done in Phase 42)

## Table Schema

```
learner_placement_level:
  placement_level_id  (int, PK)
  level               (varchar, NOT NULL)
  level_desc          (varchar, nullable)
```

## Config Entry

```php
'placement_levels' => [
    'table'      => 'learner_placement_level',
    'pk'         => 'placement_level_id',
    'columns'    => ['level', 'level_desc'],
    'labels'     => ['Level Code', 'Description'],
    'title'      => 'Manage Placement Levels',
    'capability' => 'manage_options',
],
```

## Shortcode

```
[wecoza_manage_placement_levels]
```

## Dependencies

- Phase 42 must be complete (generic LookupTables module exists)
- Same view template, JS file, AJAX handler, repository — all reused via config

## Verification

- Load shortcode page — table displays current placement levels
- Add/edit/delete rows — all persist
- Learner capture form dropdown still populates from same table
