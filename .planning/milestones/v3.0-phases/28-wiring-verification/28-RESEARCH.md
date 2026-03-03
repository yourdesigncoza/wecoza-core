# Phase 28: Wiring Verification & Fixes - Research

**Researched:** 2026-02-12
**Domain:** WordPress shortcode integration verification, AJAX wiring validation, frontend debugging
**Confidence:** HIGH

## Summary

Phase 28 verifies that the Agents module wiring (from Phase 27) renders clean HTML, has no integration bugs, and follows established patterns. This is a debugging/verification phase, not a feature-building phase.

The primary risks are:
1. **Bug #11** — Hardcoded AJAX URLs in `<script>` tags bypass `wp_localize_script()`
2. **Bug #13** — AJAX action names missing `wecoza_agents_` prefix break handler routing
3. **Bug #14** — Multiple nonce names between PHP/JS cause verification failures

All three bugs stem from Phase 27 migration. The solution is systematic verification across views/JS, fixing any hardcoded values or mismatched identifiers.

**Primary recommendation:** Use automated grep + manual browser testing to catch wiring bugs before they reach production.

## Standard Stack

### Core Verification Tools
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Debug Log | WP 6.0+ | PHP error logging | Built into WordPress, no external deps |
| Browser DevTools | Chrome/Firefox | JS console errors, network inspection | Standard web debugging |
| WP-CLI | Latest | Programmatic shortcode verification | Official WordPress CLI |
| php -l | PHP 8.0+ | Syntax validation | Native PHP linter |
| grep/ripgrep | System | Pattern matching for bugs | Fast text search |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Query Monitor | Latest WP plugin | Advanced debugging, hook introspection | When debug.log insufficient |
| Xdebug | PHP extension | Step debugging | Complex logic issues |

**Installation:**
Already available in project environment. No new installations needed.

## Architecture Patterns

### Recommended Verification Structure
```
Phase 28 Verification Flow:
1. Static Analysis (grep patterns)
2. Syntax Validation (php -l)
3. Shortcode Existence (WP-CLI)
4. Browser Rendering (manual)
5. AJAX Testing (DevTools Network tab)
6. Debug Log Review (debug.log)
```

### Pattern 1: Hardcoded AJAX URL Detection (Bug #11)
**What:** Views must use `wecozaAgents.ajaxUrl` from localization, NOT hardcoded URLs.
**When to use:** Every view with inline `<script>` tags.
**Example:**
```bash
# BAD: Hardcoded URL
grep -r "admin-ajax.php" views/agents/ --include="*.php"
# Expected: 0 matches

# GOOD: Localized variable
grep -r "wecozaAgents.ajaxUrl" assets/js/agents/ --include="*.js"
# Expected: Multiple matches
```

**Source:** Phase 27 bug warnings, wecoza-core AJAX security patterns

### Pattern 2: AJAX Action Name Verification (Bug #13)
**What:** All AJAX actions must have `wecoza_agents_` prefix for consistent routing.
**When to use:** Every AJAX call in JS files or inline scripts.
**Example:**
```bash
# Verify PHP handlers
grep "wp_ajax_wecoza_agents_" wecoza-core.php src/Agents/Ajax/
# Expected: wecoza_agents_paginate, wecoza_agents_delete

# Verify JS action names
grep "action.*wecoza_agents_" assets/js/agents/ -r
# Expected: All AJAX calls use prefixed actions
```

**Source:** AgentsAjaxHandlers.php lines 52-54, Phase 27 bug warning #10

### Pattern 3: Nonce Consistency Check (Bug #14)
**What:** PHP and JS must use SAME nonce name: `'agents_nonce_action'`.
**When to use:** Every nonce creation/verification pair.
**Example:**
```php
// PHP (AgentsController.php line 373)
'nonce' => wp_create_nonce('agents_nonce_action'),

// PHP (AgentsAjaxHandlers.php line 65)
AjaxSecurity::requireNonce('agents_nonce_action');

// JS (agents-ajax-pagination.js line 158)
nonce: wecozaAgents.paginationNonce  // Created from 'agents_nonce_action'
```

**Source:** Phase 27 bug warning #14, AgentsController unified localization

### Pattern 4: DOM ID Matching
**What:** JS selectors must match exact DOM IDs in view templates.
**When to use:** Every `$('#id')` selector in JS.
**Example:**
```bash
# Extract JS selectors
grep -oh '\$.*#[a-z-]*' assets/js/agents/*.js | sort -u
# Output: $('#agents-container'), $('#agents-display-data'), etc.

# Verify IDs exist in views
grep 'id="agents-container"' views/agents/ -r
grep 'id="agents-display-data"' views/agents/ -r
```

**Source:** Phase 27 view migration, JS files

### Anti-Patterns to Avoid
- **Inline AJAX URLs:** Never `url: '/wp-admin/admin-ajax.php'` — always use localized variable
- **Direct response access:** Never `response.message` — always `response.data.message` (Bug #4)
- **Nopriv handlers:** Never `wp_ajax_nopriv_*` — entire WP requires login (Bug #12)
- **Multiple nonces:** Never create separate nonces per feature — standardize on one

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AJAX URL localization | Hardcoded URLs in JS | `wp_localize_script()` | Handles multisite, SSL, URL changes automatically |
| Nonce verification | Manual `$_POST['nonce']` checks | `AjaxSecurity::requireNonce()` | Centralized, consistent, logged failures |
| Error logging | `error_log()` | `wecoza_log($msg, $level)` | Conditional (WP_DEBUG only), structured |
| Shortcode existence check | Manual function_exists | `WP-CLI: wp eval 'shortcode_exists(...)'` | Programmatic, scriptable |

**Key insight:** WordPress provides built-in patterns for all these operations. Custom solutions introduce bugs (like Bug #11, #13, #14).

## Common Pitfalls

### Pitfall 1: Assuming Clean Migration = Working Integration
**What goes wrong:** Code passes syntax check but fails at runtime due to mismatched identifiers.
**Why it happens:** Static analysis can't catch logic errors (wrong nonce name, wrong action string, wrong DOM ID).
**How to avoid:**
1. Grep for identifiers used in JS
2. Verify matching PHP definitions exist
3. Test in browser with Network tab open
**Warning signs:**
- AJAX returns 400/403 immediately
- JS console shows "undefined" for localized variables
- PHP debug.log shows "nonce verification failed"

### Pitfall 2: Inline Scripts with Hardcoded Values
**What goes wrong:** View templates have `<script>` tags with hardcoded AJAX URLs or action names (Bug #11, #13).
**Why it happens:** Migrated code preserved inline scripts instead of externalizing to JS files.
**How to avoid:**
1. Grep all views for `<script>` tags
2. Check for `admin-ajax.php`, `action:`, or string literals
3. Move dynamic values to `wp_localize_script()` if found
**Warning signs:**
- AJAX calls work locally but fail on different WP installations
- Multisite installations break AJAX
- SSL/non-SSL switching breaks requests

### Pitfall 3: Multiple Nonce Names for Same Module
**What goes wrong:** PHP creates 3 different nonces, JS doesn't know which to use (Bug #14).
**Why it happens:** Each feature (pagination, delete, search) creates its own nonce.
**How to avoid:**
1. Standardize on ONE nonce per module: `'agents_nonce_action'`
2. Use same nonce for all AJAX operations in that module
3. Verify localization object has single `nonce` property
**Warning signs:**
- `wecozaAgents.nonce`, `wecozaAgents.deleteNonce`, `wecozaAgents.paginationNonce` all exist
- Some AJAX calls work, others fail with nonce error
- Debug log shows "Nonce verification failed for agents_nonce_action" but different nonce was sent

### Pitfall 4: JS Files Load but Don't Execute
**What goes wrong:** JS enqueued but functions never run, events don't bind.
**Why it happens:**
- Dependency mismatch (jQuery not loaded first)
- DOM IDs don't exist (wrong template loaded)
- JS errors block execution
**How to avoid:**
1. Check `wp_enqueue_script()` dependencies array
2. Verify template actually renders targeted DOM IDs
3. Check browser console for syntax/reference errors
**Warning signs:**
- DevTools shows JS file loaded (200 status)
- Console shows "$ is not defined" or "cannot find element"
- Click handlers don't fire

## Code Examples

Verified patterns from codebase:

### Verify Shortcode Exists (WP-CLI)
```bash
# Source: Phase 28 verification commands
wp eval 'foreach(["wecoza_capture_agents","wecoza_display_agents","wecoza_single_agent"] as $s) echo shortcode_exists($s)?"OK: $s\n":"FAIL: $s\n";'
```

### Check for Hardcoded AJAX URLs (grep)
```bash
# Source: Phase 27 bug warning #11
grep -r "admin-ajax.php" views/agents/ --include="*.php" | wc -l
# Expected: 0 (all should use wecozaAgents.ajaxUrl)
```

### Verify AJAX Action Prefixes (grep)
```bash
# Source: Phase 27 bug warning #13
grep "action.*wecoza_agents_" assets/js/agents/ -r --include="*.js"
# Expected: All AJAX calls use wecoza_agents_paginate, wecoza_agents_delete
```

### Verify No Nopriv Handlers (grep)
```bash
# Source: Phase 27 bug warning #12
grep -r "wp_ajax_nopriv" src/Agents/ --include="*.php" | wc -l
# Expected: 0 (entire WP requires login)
```

### Check Debug Log for PHP Errors (bash)
```bash
# Source: WeCoza CLAUDE.md debugging workflow
tail -50 /opt/lampp/htdocs/wecoza/wp-content/debug.log | grep -i "fatal\|warning\|notice"
# Expected: No errors related to agents module
```

### Verify DOM ID Matching (grep + manual)
```bash
# Extract selectors from JS
grep -oh "\$('#[a-z-]*')" assets/js/agents/*.js | sort -u

# Then manually verify each ID exists in views
grep 'id="agents-container"' views/agents/ -r
grep 'id="agents-display-data"' views/agents/ -r
grep 'id="agents-form"' views/agents/ -r
```
**Source:** agents-ajax-pagination.js SELECTORS object

### Unified Localization Object (PHP)
```php
// Source: AgentsController.php lines 371-387
wp_localize_script('wecoza-agents-app', 'wecozaAgents', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('agents_nonce_action'),
    'deleteNonce' => wp_create_nonce('wecoza_delete_agent'),
    'paginationNonce' => wp_create_nonce('wecoza_agents_pagination'),
    // ... rest of localization
]);
```
**Note:** Bug #14 identified — should use ONE nonce, not three. Fix: remove `deleteNonce` and `paginationNonce`, use `nonce` everywhere.

### AJAX Handler with AjaxSecurity (PHP)
```php
// Source: AgentsAjaxHandlers.php lines 62-66
public function handlePagination(): void
{
    // Verify nonce (Bug #4 fix: use AjaxSecurity)
    AjaxSecurity::requireNonce('agents_nonce_action');

    // Get sanitized POST data
    $page = AjaxSecurity::post('page', 'int', 1);
    // ... rest of handler
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual nonce checks | `AjaxSecurity::requireNonce()` | v2.0 Clients Integration | Centralized, logged, consistent error handling |
| Multiple localization objects | Unified `wecozaAgents` object | Phase 27 | Single source of truth, fewer global vars |
| Hardcoded AJAX URLs | `wp_localize_script()` | WordPress best practice | Multisite/SSL compatible |
| `wp_send_json_*` directly | `AjaxSecurity::sendSuccess/Error()` | v2.0 Clients Integration | Consistent response format |

**Deprecated/outdated:**
- `agents_nonce`, `wecoza_agents_ajax`, `wecoZaAgentsDelete` separate objects — replaced by unified `wecozaAgents`
- Manual `$_POST['nonce']` verification — replaced by `AjaxSecurity::requireNonce()`
- Direct `response.message` access — replaced by `response.data.message` (Bug #4)

## Verification Checklist

### Static Analysis
- [ ] `grep -r "admin-ajax.php" views/agents/ --include="*.php" | wc -l` returns 0
- [ ] `grep -r "wp_ajax_nopriv" src/Agents/ --include="*.php" | wc -l` returns 0
- [ ] `grep "wp_ajax_wecoza_agents_" src/Agents/Ajax/AgentsAjaxHandlers.php` shows exactly 2 actions
- [ ] All JS files use `wecozaAgents.ajaxUrl`, not hardcoded URLs
- [ ] All JS AJAX calls use `action: 'wecoza_agents_*'` format

### Syntax Validation
- [ ] `find src/Agents/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"` returns empty
- [ ] All view files pass `php -l` check

### Shortcode Verification
- [ ] `wp eval 'echo shortcode_exists("wecoza_capture_agents")?"OK\n":"FAIL\n";'` returns OK
- [ ] `wp eval 'echo shortcode_exists("wecoza_display_agents")?"OK\n":"FAIL\n";'` returns OK
- [ ] `wp eval 'echo shortcode_exists("wecoza_single_agent")?"OK\n":"FAIL\n";'` returns OK

### Browser Rendering
- [ ] Navigate to page with `[wecoza_capture_agents]` — renders form, no PHP errors
- [ ] Navigate to page with `[wecoza_display_agents]` — renders table, no PHP errors
- [ ] Navigate to page with `[wecoza_single_agent agent_id="1"]` — renders detail, no PHP errors
- [ ] Browser console shows no JS errors on any page
- [ ] DevTools Network tab shows all JS files load (200 status)

### AJAX Testing
- [ ] Pagination: Click page 2 → Network tab shows `wecoza_agents_paginate` action, 200 response
- [ ] Search: Type query → Network tab shows AJAX call, table updates
- [ ] Delete: Click delete button → Network tab shows `wecoza_agents_delete` action, 200 response
- [ ] All AJAX responses have `success: true` and `data` object

### Debug Log
- [ ] `tail -100 /opt/lampp/htdocs/wecoza/wp-content/debug.log` shows no fatal/warning/notice for agents
- [ ] No "Nonce verification failed" messages
- [ ] No "Undefined index" or "Undefined variable" messages

### Nonce Consistency
- [ ] AgentsController localization creates ONE nonce: `'agents_nonce_action'`
- [ ] AgentsAjaxHandlers uses same nonce for both `handlePagination()` and `handleDelete()`
- [ ] JS files reference `wecozaAgents.nonce`, not `deleteNonce` or `paginationNonce`

### DOM ID Matching
- [ ] All `$('#...')` selectors in JS exist in view templates
- [ ] `#agents-container` exists in agent-display-table.view.php
- [ ] `#agents-display-data` exists in agent-display-table.view.php
- [ ] `#agents-form` exists in agent-capture-form.view.php

## Known Bugs to Fix

### Bug #11: Hardcoded AJAX URLs in Views
**Location:** Check all `views/agents/**/*.view.php` for `<script>` tags
**Detection:** `grep -r "admin-ajax.php" views/agents/`
**Fix:** Replace with `wecozaAgents.ajaxUrl` from localization

### Bug #13: AJAX Actions Missing Prefix
**Location:** Check inline scripts in views, all JS files
**Detection:** `grep -r "action.*:" views/agents/ assets/js/agents/` — look for non-prefixed actions
**Fix:** Ensure all use `wecoza_agents_paginate` or `wecoza_agents_delete`

### Bug #14: Multiple Nonce Names
**Location:** AgentsController.php `enqueueAssets()` method
**Current state:** Creates 3 nonces: `nonce`, `deleteNonce`, `paginationNonce`
**Fix:** Remove `deleteNonce` and `paginationNonce`, use single `nonce` created from `'agents_nonce_action'`
**Impact:** All JS files must use `wecozaAgents.nonce`, update if they reference other nonce properties

## Open Questions

1. **CSS Styles for Agents Module**
   - What we know: All CSS must go in `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`
   - What's unclear: Are any agent-specific styles needed beyond Phoenix theme classes?
   - Recommendation: Check during browser rendering, add to ydcoza-styles.css only if Phoenix insufficient

2. **File Upload Verification**
   - What we know: AgentsController has `handleFileUploads()` method for signed agreements and criminal records
   - What's unclear: Does file upload work end-to-end? File type validation? Storage path correct?
   - Recommendation: Test in Phase 29 (Feature Verification) with actual file uploads

3. **Google Maps API Integration**
   - What we know: AgentsController enqueues Google Maps API if key exists
   - What's unclear: Is `wecoza_google_maps_api_key` option set? Does address autocomplete work?
   - Recommendation: Test in browser, verify agent-form-validation.js uses Maps API

4. **Working Areas Dropdown**
   - What we know: WorkingAreasService provides area list
   - What's unclear: Is data source correct? Does cascade work (area 1 → area 2 → area 3)?
   - Recommendation: Verify dropdown renders, check if selection logic works

## Sources

### Primary (HIGH confidence)
- AgentsController.php — localization object, asset enqueuing, shortcode rendering
- AgentsAjaxHandlers.php — AJAX action registration, nonce verification pattern
- wecoza-core.php lines 244-250 — module initialization
- agent-display-table.view.php — DOM structure, inline scripts
- agents-ajax-pagination.js — AJAX call patterns, DOM selectors
- agent-delete.js — delete AJAX flow
- Phase 27 ROADMAP bug warnings #11, #13, #14
- WordPress Developer Reference Classes (Context7: /websites/developer_wordpress_reference_classes) — AJAX patterns, error handling

### Secondary (MEDIUM confidence)
- WeCoza CLAUDE.md — debugging workflow, debug.log location
- PROJECT.md — security patterns, AjaxSecurity usage

### Tertiary (LOW confidence)
- None — all findings verified against codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - all tools already in project environment
- Architecture patterns: HIGH - verified against existing code in src/Agents/
- Bug identification: HIGH - specific grep patterns, line numbers, file paths provided
- Pitfalls: HIGH - based on actual bugs documented in Phase 27 warnings

**Research date:** 2026-02-12
**Valid until:** 30 days (stable domain, WordPress patterns don't change rapidly)

**Critical files to verify:**
1. `views/agents/display/agent-display-table.view.php` — inline `<script>` tag (Bug #11 risk)
2. `src/Agents/Controllers/AgentsController.php` lines 371-387 — localization object (Bug #14)
3. `src/Agents/Ajax/AgentsAjaxHandlers.php` lines 52-54 — AJAX action registration (Bug #13)
4. All 5 JS files in `assets/js/agents/` — action names, nonce usage, DOM selectors

**Automated verification commands provided:** 10 grep patterns, 3 WP-CLI commands, 1 debug log check
