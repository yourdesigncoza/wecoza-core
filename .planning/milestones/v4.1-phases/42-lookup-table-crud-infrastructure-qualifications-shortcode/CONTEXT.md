# Phase 42 Context: Lookup Table CRUD Infrastructure + Qualifications Shortcode

## Problem

`learner_qualifications` and `learner_placement_level` are reference/lookup tables with no admin UI. Data must be managed via raw SQL. These tables were wiped by "Wipe All" (now fixed) and had empty data causing dropdown failures (WEC-177).

## Creative Approach: Generic Lookup Table Manager

Instead of building separate full modules per table, build a **single reusable Lookup Table Manager** that works for any simple reference table via configuration.

### Why Generic?
- Both tables are simple: 2-3 columns (id, name, optional description)
- Avoids duplicating 10+ files per table
- Easy to add more lookup tables later (e.g., disability types, employment statuses)
- Single shortcode pattern, single JS file, single view template

## Architecture

### New Files

```
src/LookupTables/
  Controllers/LookupTableController.php   — Shortcode registration + rendering
  Repositories/LookupTableRepository.php  — Generic CRUD (extends BaseRepository pattern)
  Ajax/LookupTableAjaxHandler.php         — Single AJAX handler for all lookup tables

views/lookup-tables/
  manage.view.php                         — Single view: table + inline add/edit form

assets/js/lookup-tables/
  lookup-table-manager.js                 — Single JS: AJAX CRUD, inline editing, delete confirm
```

### Modified Files

```
wecoza-core.php                           — Register LookupTableController + AJAX handler
```

### Table Configuration (in Controller)

```php
private const TABLES = [
    'qualifications' => [
        'table'      => 'learner_qualifications',
        'pk'         => 'id',
        'columns'    => ['qualification'],
        'labels'     => ['Qualification'],
        'title'      => 'Manage Qualifications',
        'capability' => 'manage_options',
    ],
    'placement_levels' => [
        'table'      => 'learner_placement_level',
        'pk'         => 'placement_level_id',
        'columns'    => ['level', 'level_desc'],
        'labels'     => ['Level Code', 'Description'],
        'title'      => 'Manage Placement Levels',
        'capability' => 'manage_options',
    ],
];
```

### Actual DB Schema

**learner_qualifications:** `id` (int PK), `qualification` (varchar, nullable)
**learner_placement_level:** `placement_level_id` (int PK), `level` (varchar, NOT NULL), `level_desc` (varchar, nullable)

### Shortcode Usage

```
[wecoza_manage_qualifications]      -> renders qualifications CRUD table
[wecoza_manage_placement_levels]    -> renders placement levels CRUD table (Phase 43)
```

## UI Design

- Phoenix-styled table with existing `table-sm`, `badge-phoenix` patterns
- Inline "Add New" row at top of table
- Edit/Delete action buttons per row (pencil icon, trash icon)
- Edit converts row cells to inputs inline
- Delete shows confirm dialog
- Success/error notifications via existing Phoenix alert pattern

## Reusable Components

- `BaseRepository` for CRUD operations (`core/Abstract/BaseRepository.php`)
- `AjaxSecurity` for nonce/capability checks (`core/Helpers/AjaxSecurity.php`)
- `wecoza_view()` for rendering
- `wecoza_sanitize_value()` for input sanitization
- Phoenix theme classes from `/home/laudes/zoot/projects/phoenix/phoenix-extracted`

## Security

- Nonce verification on all AJAX calls
- `manage_options` capability required (admin only)
- Column whitelisting via config (no arbitrary table/column access)
- Input sanitization via `wecoza_sanitize_value()`

## Patterns to Follow

- AJAX handler pattern: see `src/Agents/Ajax/AgentsAjaxHandlers.php`
- Controller registration: see `wecoza-core.php` plugins_loaded hook
- Namespace: `WeCoza\LookupTables\`
- Autoloader already maps `WeCoza\` prefixes in `wecoza-core.php`
