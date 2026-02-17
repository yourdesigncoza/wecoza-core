# Theme-to-Plugin Migration Roadmap

## Context

The child theme (`wecoza_3_child_theme`) contains significant business logic, database code, AJAX handlers, CPT registration, and auth gates that belong in `wecoza-core`. Risks of current state:

- **Theme switch = broken app** — auth gates, CPT, AJAX handlers all disappear
- **Duplicate PostgresConnection** — theme's `WeCoza\Services\Database\PostgresConnection` and plugin's `WeCoza\Core\Database\PostgresConnection` are separate singletons holding separate PDO connections to the same DB
- **Namespace collision** — theme's `spl_autoload_register` for `WeCoza\` prefix conflicts with plugin's PSR-4 autoloader
- **Dead code** — `ContactController` reference, orphaned files, unreachable `nopriv` AJAX handlers

## Migration Phases

8 phases, ordered by dependency. Each phase is independently deployable and testable.

---

## Phase 1: Database Layer Consolidation

**Goal:** Eliminate duplicate PostgresConnection. Migrate `Wecoza3_DB` to use plugin's `wecoza_db()`. Move `Wecoza3_Logger` into plugin.

**Why first:** Every subsequent phase depends on having a single, canonical DB layer.

### Files to Create in Plugin

1. **`src/Legacy/Database/LegacyDbWrapper.php`**
   - Namespace: `WeCoza\Legacy\Database`
   - Drop-in replacement for `Wecoza3_DB` that wraps `wecoza_db()` instead of theme's PostgresConnection
   - Public method: `get_pdo(): \PDO` — delegates to `wecoza_db()->getPdo()`
   - `class_alias('WeCoza\Legacy\Database\LegacyDbWrapper', 'Wecoza3_DB')` for backward compat

2. **`src/Legacy/Database/SqlQueryLogger.php`**
   - Namespace: `WeCoza\Legacy\Database`
   - Migrates `Wecoza3_Logger` from theme's `includes/functions/db.php`
   - MySQL connection singleton for `wecoza_sql_queries` table
   - Methods: `get_all_queries()`, `add_query()`, `delete_query()`, `update_query()`, `get_query_by_id()`, `execute_sql_query()`, `log_db_error()`, `log_exception()`
   - MySQL credentials: read from WP options or `wp-config.php` constants
   - `class_alias('WeCoza\Legacy\Database\SqlQueryLogger', 'Wecoza3_Logger')` for backward compat

### Files to Modify in Plugin

3. **`wecoza-core.php`**
   - Add namespace mapping: `'WeCoza\\Legacy\\' => WECOZA_CORE_PATH . 'src/Legacy/'`
   - In `plugins_loaded`: load Legacy DB wrapper and class aliases

### Success Criteria

- `wecoza_db()` returns the only PG connection (single PDO instance per request)
- `new Wecoza3_DB()` works via class alias, delegates to plugin's connection
- `Wecoza3_Logger::get_query_by_id()` works
- `debug.log` has no DB connection errors
- Test `fetch_dynamic_table_data` AJAX call still works

---

## Phase 2: Auth & Access Control

**Goal:** Move authentication gate, login/logout redirects to plugin. App stays locked down even if theme changes.

### Files to Create in Plugin

1. **`src/Auth/Controllers/AuthController.php`**
   - Namespace: `WeCoza\Auth\Controllers`
   - Extends `WeCoza\Core\Abstract\BaseController`
   - `registerHooks()`:
     - `add_action('template_redirect', [$this, 'redirectNonLoggedUsers'])`
     - `add_filter('login_redirect', [$this, 'loginRedirectToHome'], 10, 3)`
     - `add_action('wp_logout', [$this, 'logoutRedirectToHome'])`
     - `add_action('after_setup_theme', [$this, 'hideAdminToolbar'])`

2. **Migrate from theme's `helper.php`:**
   - `wecoza_redirect_non_logged_users()` → `AuthController::redirectNonLoggedUsers()`
     - Fix: sanitize `$_SERVER` values with `esc_url()` before `wp_redirect()`
     - Keep same logic: skip login page, admin, AJAX, feeds, robots, trackbacks
   - `ydcoza_force_login_redirect_to_home()` → `AuthController::loginRedirectToHome()`
   - `ydcoza_logout_redirect_to_home()` → `AuthController::logoutRedirectToHome()`
   - `hide_admin_toolbar_for_non_admins()` → `AuthController::hideAdminToolbar()`

### Files to Modify in Plugin

3. **`wecoza-core.php`**
   - Add namespace mapping: `'WeCoza\\Auth\\' => WECOZA_CORE_PATH . 'src/Auth/'`
   - In `plugins_loaded`: `new \WeCoza\Auth\Controllers\AuthController();`

### Success Criteria

- Unauthenticated visitors redirected to login (test by visiting any page logged out)
- Login redirects to homepage after success
- Logout redirects to homepage
- Admin toolbar hidden for non-admins
- Theme's hooks removed (Phase 8) — plugin handles it

---

## Phase 3: CPT Registration

**Goal:** Move `app` Custom Post Type registration to plugin so it survives theme switches.

### Files to Create in Plugin

1. **`src/App/Controllers/AppPostTypeController.php`**
   - Namespace: `WeCoza\App\Controllers`
   - Extends `BaseController`
   - `registerHooks()`: `add_action('init', [$this, 'registerPostTypes'])`
   - `registerPostTypes()`: exact same args from theme's `ydcoza_register_app_cpt()`:
     - `public => true`, `has_archive => true`, `hierarchical => true`
     - `show_in_rest => true`, `rewrite => ['slug' => 'app']`
     - `supports => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'page-attributes']`
     - `capability_type => 'page'`, `map_meta_cap => true`
     - Fix text domain: change `'text-domain'` to `'wecoza'`
   - `registerHooks()` also: `register_activation_hook` should call `flush_rewrite_rules()` (already done in plugin activation)

### Files to Modify in Plugin

2. **`wecoza-core.php`**
   - Add namespace: `'WeCoza\\App\\' => WECOZA_CORE_PATH . 'src/App/'`
   - In `plugins_loaded`: `new \WeCoza\App\Controllers\AppPostTypeController();`

### Existing Theme File (reference only)

- Source: `themes/wecoza_3_child_theme/includes/functions/helper.php` lines 165-217
- Theme's `single-app.php` template stays in theme (presentation layer)

### Success Criteria

- `app` CPT visible in WP admin
- `/app/` archive URL works
- `single-app.php` in theme still renders correctly
- Gutenberg editor works for `app` posts

---

## Phase 4: AJAX Handlers & Data Layer

**Goal:** Move all data-mutation AJAX handlers from theme to plugin. This is the largest phase.

### Files to Create in Plugin

1. **`src/Legacy/Ajax/TableDataAjaxHandler.php`**
   - Namespace: `WeCoza\Legacy\Ajax`
   - Handles these AJAX actions (migrate from theme's `main-functions.php` + `helper.php`):

   | Action | Method | Source |
   |---|---|---|
   | `fetch_dynamic_table_data` | `fetchDynamicTableData()` | `helper.php:43-89` |
   | `wecoza_get_table_data` | `getTableData()` | `main-functions.php:82-123` |
   | `wecoza_update_table_data` | `updateTableData()` | `main-functions.php:131-146` (stub) |
   | `wecoza_delete_table_data` | `deleteTableData()` | `main-functions.php:152-165` (stub) |
   | `wecoza_update_record` | `updateRecord()` | `main-functions.php:223-300` |

   - Constructor registers all `wp_ajax_*` hooks (drop `nopriv` variants — site requires login)
   - Use `AjaxSecurity::requireNonce('wecoza_table_nonce')` instead of raw `check_ajax_referer()`
   - Use `wecoza_db()` instead of `new Wecoza3_DB()` for PostgreSQL
   - Use `\WeCoza\Legacy\Database\SqlQueryLogger::get_query_by_id()` for stored queries

2. **`src/Legacy/Ajax/TableDataWhitelist.php`**
   - Namespace: `WeCoza\Legacy\Ajax`
   - Migrate whitelist helpers from `main-functions.php`:
     - `getAllowedTables(): array` — filterable via `wecoza_allowed_update_tables`
     - `getAllowedColumns(string $table): array` — filterable via `wecoza_allowed_update_columns`
     - `validateIdentifier(string $id, array $whitelist): bool`
   - Map from `main-functions.php` lines 178-217

3. **`src/Legacy/Services/CachedTableDataService.php`**
   - Namespace: `WeCoza\Legacy\Services`
   - Migrate `wecoza_get_cached_table_data()` + `wecoza_clear_table_data_cache()` from `main-functions.php:43-75`
   - Reads JSON from `WECOZA_PLUGIN_DIR . 'includes/data.json'` (keep same path)
   - Uses WordPress Transients API (`wecoza_table_data_cache`, 1 hour TTL)

4. **`src/Legacy/Ajax/ContactAjaxHandler.php`**
   - Namespace: `WeCoza\Legacy\Ajax`
   - Stub class — register `wp_ajax_wecoza_save_contact` with a proper "not implemented" response instead of the current fatal error (missing `ContactController`)

### Files to Modify in Plugin

5. **`wecoza-core.php`**
   - In `plugins_loaded`:
     ```php
     new \WeCoza\Legacy\Ajax\TableDataAjaxHandler();
     new \WeCoza\Legacy\Ajax\ContactAjaxHandler();
     ```
   - Nonce for table AJAX: add `wp_localize_script` for `wecoza_table_nonce` in the plugin's enqueue hook (currently done by theme's `functions.php`)

### JS Files — No Move Required

- `includes/js/app.js` references `wecoza_ajax.ajax_url` and `wecoza_ajax.nonce` — these are localized by the theme and will continue to work as long as the AJAX action names stay the same

### Success Criteria

- Dynamic table loads via AJAX (test `fetch_dynamic_table_data` action)
- JSON table data loads with pagination/search (test `wecoza_get_table_data` action)
- Record update via modal works (test `wecoza_update_record` action)
- `wecoza_save_contact` returns clean error instead of fatal
- All nonce verification passes

---

## Phase 5: Form ViewHelpers Consolidation

**Goal:** Move generic form HTML helpers to plugin's `core/Helpers/` so all modules can use them.

### Files to Create in Plugin

1. **`core/Helpers/FormRenderer.php`**
   - Namespace: `WeCoza\Core\Helpers`
   - Static class with methods migrated from theme's `app/Helpers/ViewHelpers.php`:

   | Method | From Theme Function |
   |---|---|
   | `selectDropdown($name, $options, $attrs, $selected, $emptyLabel)` | `select_dropdown()` |
   | `selectDropdownWithOptgroups($name, $optgroups, $attrs, $selected, $emptyLabel)` | `select_dropdown_with_optgroups()` |
   | `formInput($type, $name, $label, $attrs, $value, $required, $invalidFb, $validFb)` | `form_input()` |
   | `formTextarea($name, $label, $attrs, $value, $required, $invalidFb, $validFb)` | `form_textarea()` |
   | `formGroup($type, $name, $label, $colClass, $attrs, $value, $required, ...)` | `form_group()` |
   | `formRow($fields)` | `form_row()` |
   | `sectionDivider($classes)` | `section_divider()` |
   | `sectionHeader($title, $description, $titleTag)` | `section_header()` |
   | `button($text, $type, $style, $attrs)` | `button()` |

2. **`core/Helpers/form-renderer-loader.php`**
   - Global wrapper functions for backward compatibility:
     ```php
     function select_dropdown(...$args) { return \WeCoza\Core\Helpers\FormRenderer::selectDropdown(...$args); }
     // etc. for all 9 functions
     ```
   - Required in `wecoza-core.php` alongside `functions.php`

### Note on Existing Plugin FormHelpers

- `src/Agents/Helpers/FormHelpers.php` is **not overlapping** — it handles field-name-to-DB-column mapping for the Agents module only
- No consolidation needed between `FormHelpers` and the new `FormRenderer`

### Success Criteria

- All form rendering works identically (test learner/agent/client capture forms)
- Theme's `view-helpers-loader.php` can be removed (Phase 8)
- Global functions `select_dropdown()`, `form_input()`, etc. still work

---

## Phase 6: Show/Hide Title Feature

**Goal:** Move the "Hide Title" metabox, save handler, and title filters to the plugin.

### Files to Create in Plugin

1. **`src/Legacy/Admin/HideTitleMetabox.php`**
   - Namespace: `WeCoza\Legacy\Admin`
   - Migrate all code from theme's `includes/functions/show-hide-title.php`
   - Post meta key: `_hide_title`
   - Nonce: `hide_title_nonce_action` / `hide_title_nonce`
   - Hooks:
     - `add_meta_boxes` — register checkbox metabox on all public post types
     - `save_post` — save/delete `_hide_title` meta
     - `the_title` filter — return `''` on front-end singular if meta set
     - `get_the_title` filter — same

### Files to Modify in Plugin

2. **`wecoza-core.php`**
   - In `plugins_loaded`: `new \WeCoza\Legacy\Admin\HideTitleMetabox();`

### Success Criteria

- "Hide The Title" checkbox appears in post/page editor
- Checking it hides the title on the front-end
- Unchecking it restores the title
- Works for all public post types

---

## Phase 7: Disabled Features Migration

**Goal:** Move currently-disabled shortcodes and admin tools to plugin (disabled state preserved). Ready for future activation.

### Files to Create in Plugin

1. **`src/Legacy/Shortcodes/DynamicTableShortcode.php`**
   - Namespace: `WeCoza\Legacy\Shortcodes`
   - Migrate `[wecoza_dynamic_table]` from theme's `includes/shortcodes/datatable.php`
   - Shortcode: `wecoza_dynamic_table` with attrs: `sql_id`, `columns`, `exclude_columns_from_editing`
   - Uses `SqlQueryLogger::get_query_by_id()` for stored SQL lookup
   - Inline JS for AJAX data loading + `syncClassLearnersFromTable()`
   - Also migrate helper functions: `wecoza_display_error_alert()`, `is_simple_query()`, `extract_table_name_from_query()`
   - **Register commented out** in `wecoza-core.php` — preserves disabled state

2. **`src/Legacy/Shortcodes/EchartsShortcode.php`**
   - Namespace: `WeCoza\Legacy\Shortcodes`
   - Migrate `[wecoza_echart]` from theme's `includes/shortcodes/echarts-shortcode.php`
   - Also migrate `wecoza_get_chart_data()`, `wecoza_get_chart_option()`, `wecoza_get_tree_option()`, `wecoza_get_sunburst_option()` from `echarts-functions.php`
   - Enqueues ECharts from CDN when shortcode is used
   - **Register commented out**

3. **`src/Legacy/Admin/SqlQueryManager.php`**
   - Namespace: `WeCoza\Legacy\Admin`
   - Migrate from theme's `includes/admin/sql-manager.php`
   - WP Admin submenu page under `wecoza-dashboard`
   - CRUD UI for `wecoza_sql_queries` table via `SqlQueryLogger`
   - **Register commented out**

### Success Criteria

- Files exist in plugin, properly namespaced
- Code is not active (commented out registration)
- Can be activated by uncommenting the registration line

---

## Phase 8: Theme Cleanup & Namespace Fix

**Goal:** Remove all migrated code from theme. Fix namespace collision. Clean up orphaned files.

### Files to Modify in Theme

1. **`functions.php`**
   - Remove `ini_set('display_errors', 1)` (unconditional — dangerous)
   - Remove `WECOZA_PLUGIN_VERSION` constant (misleading alias)
   - Remove `wp_localize_script` for `wecoza_table_nonce` (now done by plugin)

2. **`includes/functions/helper.php`** — Remove these functions (now in plugin):
   - `fetch_dynamic_table_data()` + both `wp_ajax` hooks
   - `ydcoza_register_app_cpt()` + `add_action('init', ...)`
   - `ydcoza_force_login_redirect_to_home()` + `add_filter('login_redirect', ...)`
   - `ydcoza_logout_redirect_to_home()` + `add_action('wp_logout', ...)`
   - `wecoza_redirect_non_logged_users()` + `add_action('template_redirect', ...)`
   - `hide_admin_toolbar_for_non_admins()` + `add_action('after_setup_theme', ...)`
   - Keep: `ydcoza_google_fonts_links()`, `add_type_module_to_gradio_script()`, `add_theme_toggle_nav_item()`, `ydcoza_breadcrumbs()`, `ydcoza_breadcrumbs_after_primary()`, `ydcoza_custom_login_logo*()` functions

3. **`includes/functions/main-functions.php`** — Remove entirely (all functions moved to plugin)

4. **`includes/functions/db.php`** — Replace with dependency check:
   ```php
   <?php
   // Database classes now provided by wecoza-core plugin (v4.1+)
   // Wecoza3_DB and Wecoza3_Logger are available via class_alias shims
   if (!class_exists('Wecoza3_DB', false) && !class_exists(\WeCoza\Legacy\Database\LegacyDbWrapper::class)) {
       wp_die('WeCoza Core plugin is required. Please activate the wecoza-core plugin.');
   }
   ```

5. **`includes/functions/show-hide-title.php`** — Remove entirely (moved to plugin)

6. **`app/bootstrap.php`** — Fix namespace collision:
   - Remove the `spl_autoload_register` for `WeCoza\` namespace entirely
   - The plugin's autoloader handles all `WeCoza\*` classes
   - Keep `view()` and `config()` helper functions if theme views still need them
   - Or better: migrate theme views to use `wecoza_view()` / `wecoza_component()` from plugin

7. **`app/Services/Database/PostgresConnection.php`** — Delete entirely (duplicate)
8. **`app/Services/Database/DatabaseService.php`** — Delete entirely (duplicate)

9. **`app/Helpers/ViewHelpers.php`** — Delete (moved to plugin `FormRenderer`)
10. **`app/Helpers/view-helpers-loader.php`** — Delete (replaced by `form-renderer-loader.php`)

11. **`app/ajax-handlers.php`** — Delete (moved to plugin)

### Files to Delete from Theme (orphaned)

12. `version.php` — never loaded
13. `wecoza-3-child-theme.php` — empty plugin stub, never loaded
14. `includes/db-migrations.php` — orphaned, never loaded
15. `includes/functions/echarts-functions.php` — moved to plugin
16. `includes/shortcodes/datatable.php` — moved to plugin
17. `includes/shortcodes/echarts-shortcode.php` — moved to plugin
18. `includes/admin/sql-manager.php` — moved to plugin
19. `dashboard-timeline-data.json` (527KB) — move to `wp-content/uploads/wecoza/` or plugin's `data/` directory

### Additional Cleanup

20. Remove `nopriv` AJAX registrations that are unreachable (site requires login)
21. Remove duplicate FontAwesome (if `fontawesome-all.min.js` is unused, delete it; CSS CDN version stays)

### Success Criteria

- Theme contains only presentation code: templates, styles, menus, breadcrumbs, branding
- No `spl_autoload_register` for `WeCoza\` in theme
- No DB classes in theme
- No AJAX handlers in theme
- No auth logic in theme
- No CPT registration in theme
- All functionality works exactly as before

---

## Directory Structure After Migration

```
wecoza-core/
  src/
    Legacy/                          # NEW — migrated from theme
      Database/
        LegacyDbWrapper.php          # Wecoza3_DB replacement
        SqlQueryLogger.php           # Wecoza3_Logger migration
      Ajax/
        TableDataAjaxHandler.php     # All table AJAX handlers
        TableDataWhitelist.php       # Table/column whitelists
        ContactAjaxHandler.php       # Stub for dead contact handler
      Services/
        CachedTableDataService.php   # JSON table caching
      Shortcodes/
        DynamicTableShortcode.php    # [wecoza_dynamic_table] (disabled)
        EchartsShortcode.php         # [wecoza_echart] (disabled)
      Admin/
        HideTitleMetabox.php         # Hide title feature
        SqlQueryManager.php          # SQL query admin (disabled)
    Auth/                            # NEW
      Controllers/
        AuthController.php           # Login gate, redirects
    App/                             # NEW
      Controllers/
        AppPostTypeController.php    # 'app' CPT registration
  core/
    Helpers/
      FormRenderer.php               # NEW — generic form HTML helpers
      form-renderer-loader.php       # NEW — global function wrappers
  schema/
    wecoza_sql_queries.sql           # NEW — MySQL table documentation
```

## Verification Plan

After each phase, verify:

1. **Phase 1:** Load any page → check `debug.log` for no DB errors. Test `fetch_dynamic_table_data` AJAX call.
2. **Phase 2:** Log out → confirm redirect to login page. Log in → confirm redirect to homepage. Check admin toolbar hidden for subscribers.
3. **Phase 3:** Visit `/wp-admin/edit.php?post_type=app` → CPT exists. Create a new `app` post → Gutenberg works.
4. **Phase 4:** Test all 5 AJAX actions via browser DevTools Network tab on pages that use dynamic tables.
5. **Phase 5:** Load any form page (learner capture, agent capture) → forms render correctly with dropdowns, inputs, textareas.
6. **Phase 6:** Edit any post → "Hide The Title" metabox visible. Check/uncheck → title shows/hides on front-end.
7. **Phase 7:** Confirm files exist, are not registered (grep for commented-out registration lines).
8. **Phase 8:** Full regression test:
   - All pages load without PHP errors
   - All forms submit correctly
   - All AJAX calls succeed
   - Login/logout flow works
   - `debug.log` has no new errors/warnings
   - Only one PostgreSQL connection per request (check via `pg_stat_activity` or log)

## Risk Notes

- **Phase 1 is the riskiest** — changing the DB singleton affects everything. Test thoroughly. Deploy with `WP_DEBUG=true`.
- **Phase 4 + Phase 8 must be deployed together** or the theme and plugin will register duplicate AJAX hooks.
- **`nopriv` removal** is safe because `wecoza_redirect_non_logged_users()` gates the entire site, but verify no public-facing endpoints exist first.
- **`Wecoza3_Logger` MySQL credentials** must be configured as WP options or `wp-config.php` constants — verify these exist in the target environment.

---

## Appendix: Theme Files That Stay

These are standard Bootscore child theme patterns and should **not** be migrated:

| Item | File | Reason |
|---|---|---|
| Asset enqueuing (`enqueue_assets`, `ydcoza_load_child_style_last`) | `functions.php` | CSS/JS loading is theme responsibility |
| `wecoza_page_needs_forms()` / `wecoza_page_needs_charts()` | `functions.php` | Conditional asset detection |
| `ydcoza_add_resource_hints()` | `functions.php` | HTML head performance tags |
| `ydcoza_print_theme_sniffer()` | `functions.php` | Dark mode `localStorage` script |
| `ydcoza_remove_unwanted_styles()` | `functions.php` | Dequeue parent theme style |
| `WECOZA_THEME_VERSION`, `WECOZA_CHILD_DIR`, `WECOZA_CHILD_URL` | `functions.php` | Theme-specific constants |
| `ydcoza_google_fonts_links()` | `helper.php` | Typography/font loading |
| `add_type_module_to_gradio_script()` | `helper.php` | Asset tag modification for Gradio |
| `add_theme_toggle_nav_item()` | `helper.php` | Dark/light toggle in nav bar |
| `ydcoza_custom_login_logo*()` functions | `helper.php` | Login page branding |
| `Plugin_Templates_Loader` class | `templates-loader.php` | Registers templates from theme directory |
| `NavigationController` + sidebar walker + shortcode | `app/Controllers/` | Navigation/menu rendering |
| `Bootstrap_Sidebar_Walker` | `app/Walkers/` | Menu rendering presentation |
| `view()`, `config()` helper functions | `app/bootstrap.php` | Internal theme utilities |
| `sidebar-menu.view.php` | `app/Views/components/` | Sidebar template |
| All SCSS/CSS files | `assets/scss/`, `includes/css/` | Styling |
| Logo images | `assets/img/logo/` | Branding |
| `header.php`, `single-app.php`, templates | Theme root | Templates |
| `ydcoza_breadcrumbs()` + related | `helper.php` | Presentation/navigation |
| `locale` filter, `pre_site_transient_update_core` filter | `functions.php` | Infrastructure (borderline, leave in theme) |
