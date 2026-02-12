---
phase: 27-controllers-views-ajax
plan: 02
subsystem: agents-module
tags:
  - views
  - templates
  - migration
  - forms
  - display-tables
dependency_graph:
  requires:
    - 27-01 (AgentsController provides data contracts)
  provides:
    - 6 view templates in views/agents/
    - Form views for agent capture/edit
    - Display views for agent table and single view
  affects:
    - 27-03 (JavaScript depends on element IDs + data attributes)
tech_stack:
  added:
    - agent-capture-form.view.php (34KB, 40+ fields)
    - agent-fields.view.php (13KB, helper functions)
    - agent-display-table.view.php (16KB, main table with stats)
    - agent-display-table-rows.view.php (3.6KB, AJAX partial)
    - agent-pagination.view.php (4.3KB, AJAX partial)
    - agent-single-display.view.php (45KB, detail view)
  patterns:
    - Standalone views (no $this->, extracted data)
    - wecoza_view() for template includes
    - Text domain: wecoza-core
    - FormHelpers for field rendering/validation
    - Inline URL generation with add_query_arg()
key_files:
  created:
    - views/agents/components/agent-capture-form.view.php
    - views/agents/components/agent-fields.view.php
    - views/agents/display/agent-display-table.view.php
    - views/agents/display/agent-display-table-rows.view.php
    - views/agents/display/agent-pagination.view.php
    - views/agents/display/agent-single-display.view.php
  modified: []
decisions: []
metrics:
  duration_minutes: 7
  completed_date: 2026-02-12
  tasks_completed: 2
  commits: 2
---

# Phase 27 Plan 02: Views Migration Summary

**One-liner:** Migrated 6 agent view templates (34KB form + 45KB single display + 4 table partials) from standalone plugin to wecoza-core with wecoza_view() includes, zero $this-> references, and preserved element IDs/data attributes for JS compatibility.

## What Was Built

### Form Component Views (views/agents/components/)

**1. agent-capture-form.view.php (34KB, 582 lines)**

Comprehensive agent creation/edit form with:
- **Personal Info:** Title, first/second/surname, initials (auto), gender, race
- **Identification:** SA ID/Passport radio selection with conditional fields
- **Contact:** Phone, email
- **SACE Details:** Registration number, registration date, expiry date
- **Address:** Google Places search + manual fields (street, suburb, city, province, postal code)
- **Working Areas:** 3 preferred area dropdowns (first required)
- **Phase & Subjects:** Phase registered, subjects, highest qualification, training date
- **Quantum Tests:** Assessment %, maths score %, science score %
- **Legal:** Criminal record check date + file upload
- **Agreement:** Signed agreement date + file upload (required, protected with defensive JS)
- **Banking:** Bank name, account holder, account number, branch code, account type
- **Submit Button:** Conditional text ("Add New Agent" vs "Update Agent")

**Migration transformations:**
- ✓ Text domain: `wecoza-core` (not `wecoza-agents-plugin`)
- ✓ FormHelpers integration: `get_field_value()`, `get_error_class()`, `display_field_error()`
- ✓ All element IDs preserved: `agents-form`, `sa_id_field`, `passport_field`, etc.
- ✓ Form enctype: `multipart/form-data` for file uploads
- ✓ Nonce field: `wp_nonce_field('submit_agent_form', 'wecoza_agents_form_nonce')`
- ✓ Bootstrap 5 + ydcoza-compact-form classes
- ✓ Defensive JavaScript for signed_agreement_date protection (prevents autofill clearing)

**Variables expected from controller:**
- `$agent` - Current agent data (if editing)
- `$errors` - Form validation errors
- `$mode` - 'add' or 'edit'
- `$atts` - Shortcode attributes
- `$working_areas` - Working areas array for dropdowns

**2. agent-fields.view.php (13KB, 348 lines)**

Reusable form field helper functions (not used by capture form, but available as utility):
- `wecoza_agents_render_text_field()` - Text inputs with error handling
- `wecoza_agents_render_select_field()` - Select dropdowns
- `wecoza_agents_render_textarea_field()` - Textareas
- `wecoza_agents_render_checkbox_field()` - Checkboxes
- `wecoza_agents_render_file_field()` - File uploads
- `wecoza_agents_render_radio_group()` - Radio button groups
- `wecoza_agents_render_date_field()` - Date inputs
- `wecoza_agents_render_email_field()` - Email inputs
- `wecoza_agents_render_phone_field()` - Phone inputs

All wrapped in `function_exists()` guards, use wp_parse_args() for defaults, support error display, help text, and required field indicators.

### Display Views (views/agents/display/)

**1. agent-display-table.view.php (16KB, main table)**

Full-featured agents table with:
- **Header:** Title, search box, refresh/export buttons
- **Statistics strip:** Total agents + custom statistics with Phoenix badges
- **Table:** Sortable columns with Bootstrap icons, data attributes for AJAX
- **Pagination:** Per-page dropdown (10/25/50) + page navigation
- **Export function:** Inline JavaScript `exportClasses()` for CSV export

**Template includes transformed:**
- OLD: `$this->load_template('agent-display-table-rows.php', [...], 'display')`
- NEW: `echo wecoza_view('agents/display/agent-display-table-rows', [...], true);`
- OLD: `$this->load_template('agent-pagination.php', [...], 'display')`
- NEW: `echo wecoza_view('agents/display/agent-pagination', [...], true);`

**Element IDs preserved for JS:**
- `#agents-container` - Main container for AJAX updates
- `#agents-display-data` - Table element
- `#alert-container` - Alert messages
- `.search-input` - Search box
- `.fixed-table-pagination` - Pagination container

**Variables expected:**
`$agents`, `$total_agents`, `$current_page`, `$per_page`, `$total_pages`, `$start_index`, `$end_index`, `$search_query`, `$sort_column`, `$sort_order`, `$columns`, `$atts`, `$can_manage`, `$statistics`

**2. agent-display-table-rows.view.php (3.6KB, AJAX partial)**

Renders table rows for both initial load and AJAX pagination:
- Iterates through `$agents` array
- Renders columns based on `$columns` config
- Special handling for email/phone (clickable links)
- Action buttons: View, Edit (if `$can_manage`), Delete (if `$can_manage`)
- Data attributes: `data-agent-id` on delete button (JS delete handler depends on this)

**URL generation (inline with add_query_arg):**
- View URL: `add_query_arg('agent_id', $agent['id'], home_url('/app/agent-view/'))`
- Edit URL: `add_query_arg(['update' => '', 'agent_id' => $agent['id']], home_url('/new-agents/'))`

**3. agent-pagination.view.php (4.3KB, AJAX partial)**

Pagination controls for AJAX updates:
- "Showing X to Y of Z rows" info
- Per-page dropdown (10/25/50) with `data-per-page` attributes
- Previous/next buttons with `data-page` attributes
- Page number buttons with ellipsis for long ranges
- Disabled state for unavailable navigation

**Variables expected:**
`$current_page`, `$total_pages`, `$per_page`, `$start_index`, `$end_index`, `$total_agents`

**4. agent-single-display.view.php (45KB, detail view)**

Full agent detail page with Phoenix design system:
- **Loading state:** Spinner + message
- **Error state:** Alert with back button
- **Top summary cards (5):** Agent name, ID type, status badge, SACE registration, contact
- **Two-column details tables:**
  - Left: Personal info (ID, name, gender, race, ID number, phone, email, address, preferred areas)
  - Right: Professional & compliance (SACE details, qualification, quantum tests, phase/subjects, training date, banking, created/updated timestamps, notes)
- **Documents section:** Criminal record check + signed agreement with download links

**Date formatting:** Uses `date_i18n($date_format, strtotime($date))` for all dates (no $this->format_date())

**Working areas lookup:** Calls `\WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id($area_id)`

**Variables expected:**
`$agent_id`, `$agent`, `$error`, `$loading`, `$back_url`, `$can_manage`, `$date_format`

## Deviations from Plan

None - plan executed exactly as written. All migration requirements followed:
- ✓ All 6 views created with .view.php extension
- ✓ ABSPATH guards on all files
- ✓ Text domain: 'wecoza-core' not 'wecoza-agents-plugin'
- ✓ NO `$this->` references (views are standalone)
- ✓ Template includes use `wecoza_view()` not `load_template()`
- ✓ URL generation uses `add_query_arg()` inline (not $this->get_*_url())
- ✓ FormHelpers uses `WeCoza\Agents\Helpers\FormHelpers` namespace
- ✓ All element IDs preserved (agents-form, agents-container, agents-display-data)
- ✓ All data attributes preserved (data-agent-id, data-page, data-per-page, data-sortable)

## Migration Patterns Applied

**1. Standalone Views (no class context):**
```php
// OLD (in class method):
$this->load_template('partial.php', $data, 'dir');
$edit_url = $this->get_edit_url($id);

// NEW (in standalone view):
echo wecoza_view('agents/display/partial', $data, true);
$edit_url = add_query_arg(['update' => '', 'agent_id' => $id], home_url('/new-agents/'));
```

**2. Text Domain Replacement:**
```php
// OLD:
__('Text', 'wecoza-agents-plugin')

// NEW:
__('Text', 'wecoza-core')
```

**3. ABSPATH Guard:**
```php
// ALL views start with:
if (!defined('ABSPATH')) {
    exit;
}
```

**4. FormHelpers Usage (unchanged - already correct in source):**
```php
use WeCoza\Agents\Helpers\FormHelpers;

FormHelpers::get_field_value($agent, 'first_name')
FormHelpers::get_error_class($errors, 'email')
FormHelpers::display_field_error($errors, 'phone')
```

## Element ID & Data Attribute Preservation

**Critical for JavaScript compatibility (Phase 27-03):**

**Form IDs:**
- `agents-form` - Main form element (validation JS attaches to this)
- `sa_id_field` / `passport_field` - Conditional ID type fields (toggle JS depends on these)
- `signed_agreement_date` - Agreement date field (defensive JS protects this)
- `google_address_search` - Google Places autocomplete field

**Display IDs:**
- `agents-container` - AJAX update target
- `agents-display-data` - Table element (search/sort JS targets this)
- `alert-container` - Alert message container

**Data Attributes:**
- `data-agent-id` - Delete button attribute (delete handler uses this)
- `data-page` - Pagination link attribute (pagination JS uses this)
- `data-per-page` - Per-page dropdown attribute (pagination JS uses this)
- `data-sortable="true"` - Column sorting indicator
- `data-field` - Column field name for sorting

## URL Generation Strategy

**Controller provides these URLs as variables:**
- `$back_url` - In single display view

**Views generate inline with add_query_arg():**
- Edit URL: `add_query_arg(['update' => '', 'agent_id' => $id], home_url('/new-agents/'))`
- View URL: `add_query_arg('agent_id', $id, home_url('/app/agent-view/'))`

**Hardcoded base URLs (from controller):**
- `/new-agents/` - Capture/edit form page
- `/app/agents/` - Agent list page
- `/app/agent-view/` - Single agent view page

## Verification Results

**All Checks Passed:**
```bash
✓ All 6 view files exist with .view.php extension
✓ All files pass PHP syntax check (php -l)
✓ All files have ABSPATH guard
✓ Zero occurrences of 'wecoza-agents-plugin'
✓ Zero occurrences of 'load_template'
✓ Zero occurrences of 'WECOZA_AGENTS_*'
✓ Zero occurrences of 'wecoza_agents_log()'
✓ Zero occurrences of '$this->'
✓ wecoza_view() calls present in agent-display-table.view.php (2)
✓ FormHelpers import present in agent-capture-form.view.php
```

## Commits

| Hash | Message | Files |
|------|---------|-------|
| b96cef3 | feat(27-02): migrate form component views | agent-capture-form.view.php, agent-fields.view.php |
| de5920e | feat(27-02): migrate display views | agent-display-table.view.php, agent-display-table-rows.view.php, agent-pagination.view.php, agent-single-display.view.php |

## Next Phase Readiness

**Ready for 27-03 (JavaScript):**
- Element IDs preserved for JS attachment: `agents-form`, `agents-container`, `agents-display-data`
- Data attributes preserved: `data-agent-id`, `data-page`, `data-per-page`, `data-sortable`
- Form structure ready for validation JS
- Table structure ready for search/sort/pagination AJAX
- Delete buttons ready for confirmation handler

**Dependencies Satisfied:**
- 27-01 controller provides: `$agent`, `$errors`, `$mode`, `$working_areas`, `$statistics`, `$columns`, `$can_manage`
- FormHelpers available: `get_field_value()`, `get_error_class()`, `display_field_error()`
- wecoza_view() function available for template includes

**Blockers:** None

## Self-Check: PASSED

**Files exist:**
- ✓ views/agents/components/agent-capture-form.view.php (34KB)
- ✓ views/agents/components/agent-fields.view.php (13KB)
- ✓ views/agents/display/agent-display-table.view.php (16KB)
- ✓ views/agents/display/agent-display-table-rows.view.php (3.6KB)
- ✓ views/agents/display/agent-pagination.view.php (4.3KB)
- ✓ views/agents/display/agent-single-display.view.php (45KB)

**Commits exist:**
- ✓ b96cef3 (Form component views)
- ✓ de5920e (Display views)

**All claims verified.**
