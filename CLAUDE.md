# WeCoza Core Plugin

WordPress plugin providing core infrastructure: PostgreSQL database, MVC architecture, shared utilities.

## Architecture

```
core/                  # Framework abstractions
  Abstract/            # BaseController, BaseModel, BaseRepository
  Database/            # PostgresConnection (singleton)
  Helpers/             # functions.php, AjaxSecurity
src/
  Learners/            # Learner module
    Ajax/              # AJAX handlers
    Controllers/
    Models/
    Repositories/
    Services/          # ProgressionService, PortfolioUploadService
    Shortcodes/
  Classes/             # Classes module
    Controllers/       # ClassController, QAController, PublicHolidaysController
    Models/            # ClassModel, QAModel, QAVisitModel
    Repositories/
    Services/          # ScheduleService, UploadService, FormDataProcessor
views/                 # PHP templates (.view.php or .php)
  components/          # Reusable partials
config/                # Configuration files (app.php)
assets/
  css/
  js/
    classes/           # 10+ JS files for class management
    learners/          # 4 JS files for learner management
schema/                # Database schema backups
```

## Namespaces (PSR-4)

- `WeCoza\Core\` → `core/`
- `WeCoza\Learners\` → `src/Learners/`
- `WeCoza\Classes\` → `src/Classes/`

## Key Functions

**Database & Config:**
- `wecoza_db()` - Get PostgreSQL connection
- `wecoza_config($name)` - Load config file (cached)

**View Rendering:**
- `wecoza_view($path, $data, $return)` - Render view template
- `wecoza_component($component, $data, $return)` - Render component from `views/components/`

**Asset URLs:**
- `wecoza_asset_url($asset)` - Get asset URL
- `wecoza_css_url($file)` - Get CSS URL
- `wecoza_js_url($file)` - Get JS URL

**Paths:**
- `wecoza_plugin_path($path)` - Get full server path
- `wecoza_core_path($path)` - Get core directory path

**Environment:**
- `wecoza_is_admin_area()` - Check if in WP admin (excluding AJAX)
- `wecoza_is_ajax()` - Check if AJAX request
- `wecoza_is_rest()` - Check if REST request

**Utilities:**
- `wecoza_log($msg, $level)` - Debug logging (WP_DEBUG only)
- `wecoza_sanitize_value($value, $type)` - Sanitize input (string, email, int, float, bool, json, date, etc.)
- `wecoza_array_get($array, $key, $default)` - Dot notation array access
- `wecoza_snake_to_camel($value)` / `wecoza_camel_to_snake($value)` - Case conversion

## Shortcodes

**Learners:**
- `[wecoza_display_learners]` - List all learners
- `[wecoza_learners_form]` - Create learner form
- `[wecoza_single_learner_display]` - Individual learner view
- `[wecoza_learners_update_form]` - Edit learner form

**Classes:**
- `[wecoza_capture_class]` - Create/edit class
- `[wecoza_display_classes]` - List classes
- `[wecoza_display_single_class]` - Class detail view

## LP Progression Tracking (WEC-168)

Tracks learner progress through Learning Programmes:
- **One LP at a time:** Enforces single active LP per learner
- **Hours tracking:** `hours_trained`, `hours_present`, `hours_absent`
- **Progress:** Calculated from hours present vs product duration
- **Portfolio:** Required file upload for LP completion
- **Statuses:** in_progress, completed, on_hold

Key service: `ProgressionService` with `startLearnerProgression()`, `markLPComplete()`, `logHours()`

## Database

- PostgreSQL (not MySQL)
- Connection: `WeCoza\Core\Database\PostgresConnection::getInstance()`
- Password stored in WP option: `wecoza_postgres_password`
- Lazy-loaded: Connection defers until first query

## Security

- **Authentication:** Entire WP environment requires login. Unauthenticated users cannot access any pages.
- **Capability:** Learner PII access requires `manage_learners` capability (Admin only)
- **SQL Injection:** Repositories use column whitelisting via `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`
- **CSRF:** AJAX handlers require nonce via `AjaxSecurity::requireNonce('learners_nonce_action')`
- **Input:** `AjaxSecurity` provides `verifyNonce()`, `checkCapability()`, `sanitizeArray()`, `validateUploadedFile()`

## Custom Hooks

- `wecoza_core_loaded` - Fired after plugin fully initialized
- `wecoza_core_activated` - Fired on activation
- `wecoza_core_deactivated` - Fired on deactivation

## WP-CLI Commands

```bash
wp wecoza test-db    # Test PostgreSQL connection
wp wecoza version    # Show plugin version
```

## Debugging

- **Debug log:** `/opt/lampp/htdocs/wecoza/wp-content/debug.log`
- Use `wecoza_log($msg, $level)` for plugin-specific logging
- Requires `WP_DEBUG` and `WP_DEBUG_LOG` enabled in wp-config.php

## Gotchas

- Requires PHP 8.0+ (match expressions, typed properties)
- Requires WordPress 6.0+
- Requires `pdo_pgsql` PHP extension
- Plugin loads at priority 5 on `plugins_loaded`
- Dependent plugins should use priority 10+
- Views support both `.view.php` and `.php` extensions


## Database Access Restrictions
- postgres-do MCP tool has read-only access only
- Only the following SQL operations are allowed: SELECT, WITH, EXPLAIN, ANALYZE, SHOW
- No INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, or other write operations permitted


## CSS Styles Location
- ALL CSS styles must be added to: `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`
- Do NOT create separate CSS files in plugin directories or elsewhere
- Always append new styles to the existing ydcoza-styles.css file 
- The '/home/laudes/zoot/projects/phoenix/phoenix-extracted' folder & all it's content is to be used to guide your UI, never make any changes to anything inside the folder
