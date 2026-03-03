# Phase 30: Integration Testing & Cleanup - Research

**Researched:** 2026-02-12
**Domain:** WordPress plugin integration verification and migration cleanup
**Confidence:** HIGH

## Summary

Phase 30 is the final phase of the v3.0 Agents Integration milestone. It verifies that the integrated Agents module can fully replace the standalone wecoza-agents-plugin, enabling safe deactivation and removal of the standalone plugin source code. This phase follows the exact pattern established in Phase 25 (v2.0 Integration Testing & Cleanup), which successfully migrated the Clients module.

Unlike feature-building phases, this is a **verification and cleanup phase**. The heavy lifting is already complete: Phase 26 built the foundation architecture, Phase 27 migrated all controllers/views/JS/AJAX, Phase 28 verified wiring, and Phase 29 verified feature parity with comprehensive CLI test script. Phase 30 simply confirms the standalone plugin is no longer needed and cleans up the repository.

**Key insight:** The feature parity test script already exists at `tests/integration/agents-feature-parity.php` (607 lines, comprehensive test suite). Phase 29 verification confirms 22/24 must-haves verified (91.7% pass rate). The standalone plugin has been idle since Phase 26—no code uses it anymore. This phase just formalizes the deactivation and removes the archived source.

**Primary recommendation:** Run existing test script to confirm parity, deactivate standalone plugin via WordPress admin, verify all pages still render correctly, remove `.integrate/wecoza-agents-plugin/` from repository. Follow Phase 25 pattern exactly—it worked perfectly for Clients migration.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Existing test script | Phase 29 | `tests/integration/agents-feature-parity.php` already exists | 607-line comprehensive test suite created in Phase 29 |
| WordPress admin UI | Core | Plugin deactivation interface | Official WordPress plugin management—safe, logged, reversible |
| WP-CLI (optional) | Latest | Command-line plugin deactivation | Alternative to admin UI, useful for scripting |
| Bash scripts | N/A | Pre/post-deactivation verification checks | Simple grep/find commands for reference detection |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Query Monitor | Latest (optional) | Performance verification post-deactivation | Verify no performance regression after cleanup |
| debug.log | WordPress | Error detection during deactivation | Automatic PHP error logging—already enabled in project |
| git status | Git | Track removal of .integrate/ folder | Verify clean deletion before commit |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Manual deactivation | Automated WP-CLI script | Manual safer for first deactivation—can observe errors immediately |
| Grep for references | AST parser (nikic/php-parser) | Grep sufficient for string literal detection—no need for AST complexity |
| Git removal | Move to .integrate/done/ | Phase 25 moved to done/ first, but requirements say "removed from repository"—direct removal is cleaner |

**Installation:**
```bash
# No installation needed—all tools already available
# Test script exists: tests/integration/agents-feature-parity.php
# WordPress admin: Already accessible
# Bash/git: Already in environment
```

## Architecture Patterns

### Recommended Verification Structure

```
tests/integration/
├── clients-feature-parity.php       # Phase 25 reference (v2.0 Clients)
└── agents-feature-parity.php        # Phase 29 created (v3.0 Agents)

.integrate/
└── wecoza-agents-plugin/            # TO BE REMOVED after verification

.planning/phases/30-integration-testing-cleanup/
├── 30-RESEARCH.md                   # This file
├── 30-01-PLAN.md                    # Feature parity test + deactivation plan
└── 30-02-PLAN.md                    # Cleanup and removal plan
```

### Pattern 1: Pre-Deactivation Verification Checklist

**What:** Systematic checks before deactivating standalone plugin to prevent production breakage

**When to use:** Before any deactivation attempt—establishes safety baseline

**Example:**
```bash
#!/bin/bash
# Pre-deactivation safety checks

echo "=== Pre-Deactivation Safety Checks ==="
echo ""

# 1. Verify integrated module loaded
echo "1. Checking integrated Agents module is loaded..."
php -r "require 'wp-load.php'; exit(class_exists('\WeCoza\Agents\Controllers\AgentsController') ? 0 : 1);" && \
  echo "✓ Integrated module loaded" || \
  { echo "✗ ERROR: Integrated module NOT loaded. Abort."; exit 1; }
echo ""

# 2. Run feature parity test
echo "2. Running feature parity test..."
cd /opt/lampp/htdocs/wecoza
php wp-content/plugins/wecoza-core/tests/integration/agents-feature-parity.php
if [ $? -eq 0 ] || [ $? -eq 1 ]; then
  # Exit 1 is acceptable if only agent_meta failures (expected)
  echo "✓ Feature parity test completed"
else
  echo "✗ ERROR: Feature parity test failed unexpectedly. Abort."
  exit 1
fi
echo ""

# 3. Check for standalone plugin active status
echo "3. Checking standalone plugin status..."
if wp plugin is-installed wecoza-agents-plugin 2>/dev/null; then
  if wp plugin is-active wecoza-agents-plugin 2>/dev/null; then
    echo "! Standalone plugin is ACTIVE (will deactivate)"
  else
    echo "✓ Standalone plugin already inactive"
  fi
else
  echo "✓ Standalone plugin not installed (already removed)"
fi
echo ""

# 4. Check for page dependencies
echo "4. Checking for pages using agent shortcodes..."
PAGES=$(wp db query "SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = '_wp_page_template' OR meta_value LIKE '%wecoza%agent%' LIMIT 5" --skip-column-names 2>/dev/null || echo "")
if [ -n "$PAGES" ]; then
  echo "! Found pages potentially using agent features (verify manually)"
else
  echo "✓ No obvious page dependencies found"
fi
echo ""

echo "=== Pre-Deactivation Checks Complete ==="
echo "Ready to deactivate standalone plugin via WordPress admin."
```

**Key points:**
- Test script must pass (or only fail on expected agent_meta table missing)
- Integrated module must be loaded and functional
- Pages using agent shortcodes documented (if any)
- Exit on any unexpected errors

### Pattern 2: Safe Deactivation Workflow

**What:** Step-by-step manual deactivation with verification at each step

**When to use:** After pre-deactivation checks pass

**Example workflow:**
```markdown
# Safe Deactivation Workflow

## Step 1: Backup (Safety Net)
- [ ] Database backup created (PostgreSQL dump)
- [ ] Full site backup created (files + uploads)
- [ ] Backup location documented

## Step 2: Verify Integrated Module
- [ ] Feature parity test passes (tests/integration/agents-feature-parity.php)
- [ ] All agent shortcodes registered in integrated module
- [ ] All AJAX endpoints registered in integrated module

## Step 3: Deactivate Standalone Plugin
- [ ] Navigate to WordPress admin → Plugins
- [ ] Find "WeCoza Agents Plugin" (standalone)
- [ ] Click "Deactivate" button
- [ ] Confirm no PHP errors displayed

## Step 4: Immediate Verification (Within 5 Minutes)
- [ ] Navigate to agent capture form page → renders correctly
- [ ] Navigate to agents display page → renders correctly
- [ ] Navigate to single agent page → renders correctly
- [ ] Check debug.log: No new errors related to agents

## Step 5: Functional Testing
- [ ] Create new agent via form → succeeds and persists
- [ ] Update existing agent → changes save correctly
- [ ] Delete agent → soft-delete works (status='deleted')
- [ ] AJAX pagination on display page → works correctly
- [ ] Statistics badges show correct counts

## Step 6: Re-run Feature Parity Test
- [ ] Run: `php tests/integration/agents-feature-parity.php`
- [ ] All tests still pass (same results as pre-deactivation)

## Step 7: Reactivation Test (Rollback Verification)
- [ ] Reactivate standalone plugin
- [ ] Verify no conflicts or errors
- [ ] Deactivate standalone plugin again
- [ ] Confirms reversibility if needed later

## Step 8: Document Deactivation Success
- [ ] Update STATE.md: Mark standalone plugin as deactivated
- [ ] Note deactivation date and verifier
```

### Pattern 3: Reference Detection and Cleanup Verification

**What:** Automated checks for dangling references before source removal

**When to use:** After successful deactivation, before removing `.integrate/wecoza-agents-plugin/`

**Example:**
```bash
#!/bin/bash
# Dangling reference detection script

echo "=== Dangling Reference Detection ==="
echo ""

WECOZA_ROOT="/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core"
cd "$WECOZA_ROOT"

# 1. Check for old namespace references
echo "1. Checking for WeCoza\Agents\Database\DatabaseService references..."
if grep -r "WeCoza\\\\Agents\\\\Database\\\\DatabaseService" src/Agents/ --include="*.php" -q; then
  echo "✗ FAIL: Found DatabaseService references (old standalone pattern)"
  grep -r "WeCoza\\\\Agents\\\\Database\\\\DatabaseService" src/Agents/ --include="*.php"
  exit 1
else
  echo "✓ PASS: No DatabaseService references found"
fi
echo ""

# 2. Check for WECOZA_AGENTS_* constants
echo "2. Checking for WECOZA_AGENTS_* constant references..."
if grep -r "WECOZA_AGENTS_" src/Agents/ views/agents/ assets/js/agents/ --include="*.php" --include="*.js" -q; then
  echo "✗ FAIL: Found WECOZA_AGENTS_ constant references"
  grep -r "WECOZA_AGENTS_" src/Agents/ views/agents/ assets/js/agents/ --include="*.php" --include="*.js"
  exit 1
else
  echo "✓ PASS: No WECOZA_AGENTS_ constants found"
fi
echo ""

# 3. Check for .integrate/ path references
echo "3. Checking for .integrate/ path references in active code..."
if grep -r "\.integrate/" src/ views/ assets/ config/ wecoza-core.php --include="*.php" --include="*.js" -q; then
  echo "✗ FAIL: Found .integrate/ path references"
  grep -r "\.integrate/" src/ views/ assets/ config/ wecoza-core.php --include="*.php" --include="*.js"
  exit 1
else
  echo "✓ PASS: No .integrate/ path references found"
fi
echo ""

# 4. Check for wecoza-agents-plugin string in active code
echo "4. Checking for 'wecoza-agents-plugin' string references..."
if grep -r "wecoza-agents-plugin" src/ views/ assets/ config/ wecoza-core.php composer.json --include="*.php" --include="*.js" --include="*.json" -q; then
  echo "✗ FAIL: Found wecoza-agents-plugin string references"
  grep -r "wecoza-agents-plugin" src/ views/ assets/ config/ wecoza-core.php composer.json --include="*.php" --include="*.js" --include="*.json"
  exit 1
else
  echo "✓ PASS: No wecoza-agents-plugin references found"
fi
echo ""

# 5. Verify .integrate/wecoza-agents-plugin/ still exists (should exist before removal)
echo "5. Verifying source folder exists..."
if [ -d ".integrate/wecoza-agents-plugin" ]; then
  echo "✓ PASS: .integrate/wecoza-agents-plugin/ exists (ready for removal)"
else
  echo "✗ WARNING: .integrate/wecoza-agents-plugin/ already removed or never existed"
fi
echo ""

# 6. Verify .integrate/done/ doesn't already have agents plugin
echo "6. Checking if agents plugin already in done folder..."
if [ -d ".integrate/done/wecoza-agents-plugin" ]; then
  echo "✗ WARNING: .integrate/done/wecoza-agents-plugin/ already exists"
else
  echo "✓ PASS: done/ folder clear for agents plugin"
fi
echo ""

echo "=== Reference Detection Complete ==="
echo "If all checks passed, safe to remove .integrate/wecoza-agents-plugin/"
```

**Key points:**
- All checks must pass before removal
- Grep searches active code only (not .integrate/ or .git/)
- Fails fast on first violation
- Exit code 1 on any failure (CI/CD compatible)

### Pattern 4: Post-Cleanup Verification

**What:** Final verification that removal didn't break anything

**When to use:** Immediately after `rm -rf .integrate/wecoza-agents-plugin/`

**Example:**
```bash
#!/bin/bash
# Post-cleanup verification

echo "=== Post-Cleanup Verification ==="
echo ""

# 1. Verify folder removed
echo "1. Verifying .integrate/wecoza-agents-plugin/ removed..."
if [ ! -d ".integrate/wecoza-agents-plugin" ]; then
  echo "✓ PASS: Folder removed"
else
  echo "✗ FAIL: Folder still exists"
  exit 1
fi
echo ""

# 2. Run feature parity test again
echo "2. Running feature parity test post-cleanup..."
cd /opt/lampp/htdocs/wecoza
php wp-content/plugins/wecoza-core/tests/integration/agents-feature-parity.php
if [ $? -eq 0 ] || [ $? -eq 1 ]; then
  echo "✓ PASS: Feature parity test still passes"
else
  echo "✗ FAIL: Feature parity test failed after cleanup"
  exit 1
fi
echo ""

# 3. Check git status
echo "3. Checking git status..."
cd /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core
git status --short .integrate/
echo ""

# 4. Verify src/Agents/ still intact
echo "4. Verifying src/Agents/ directory intact..."
if [ -d "src/Agents" ] && [ "$(ls -A src/Agents/)" ]; then
  echo "✓ PASS: src/Agents/ directory exists and not empty"
else
  echo "✗ FAIL: src/Agents/ directory missing or empty"
  exit 1
fi
echo ""

# 5. Quick smoke test: verify controller class loadable
echo "5. Smoke test: AgentsController loadable..."
php -r "require 'wp-load.php'; exit(class_exists('\WeCoza\Agents\Controllers\AgentsController') ? 0 : 1);" && \
  echo "✓ PASS: AgentsController still loadable" || \
  { echo "✗ FAIL: AgentsController NOT loadable"; exit 1; }
echo ""

echo "=== Post-Cleanup Verification Complete ==="
echo "Integration cleanup successful. Ready to commit."
```

### Anti-Patterns to Avoid

- **Deactivating before testing:** Risk: Integrated module might be broken, causes immediate production outage
- **Removing source before deactivation:** Risk: Lose reference implementation if rollback needed, can't compare behaviors
- **Skipping reference detection:** Risk: Code might still import/reference standalone plugin files, breaks after removal
- **Not re-running test after cleanup:** Risk: File removal might have broken autoloader or paths
- **Deleting via file manager instead of git:** Risk: Git won't track deletion, changes not committed

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Plugin deactivation | Custom database update to wp_options | WordPress admin UI or `wp plugin deactivate` | WordPress handles activation hooks, option updates, cache clearing automatically |
| Reference detection | Custom PHP reflection parser | Grep with proper regexes | String literal references are what break—no need to parse PHP AST |
| Test running | Custom test harness | Existing agents-feature-parity.php script | Already comprehensive (607 lines, 22/24 checks), proven in Phase 29 |
| Directory removal | rsync with --delete or custom script | `rm -rf .integrate/wecoza-agents-plugin/` | Simple, reliable, standard Unix command |
| Git staging | Selective `git add` commands | `git add .integrate/` (specific folder) | Scopes commit to cleanup only |

**Key insight:** WordPress plugin migration cleanup is about proving the old plugin is unnecessary and safely removing it. The project already has proven patterns (Phase 25) and comprehensive test infrastructure (Phase 29). Don't reinvent—just follow the established workflow.

## Common Pitfalls

### Pitfall 1: Assuming Deactivation Hook Cleans Up Everything

**What goes wrong:** Standalone plugin might have registered custom post types, taxonomies, or database tables that persist after deactivation

**Why it happens:** WordPress `register_deactivation_hook()` runs custom code, but doesn't auto-clean data—developers must explicitly remove data

**How to avoid:** After deactivation, verify:
- No orphaned custom post types in wp_posts
- No orphaned taxonomies in wp_term_taxonomy
- No orphaned AJAX handlers in wp_filter
- No orphaned shortcodes in global $shortcode_tags

**Warning signs:**
- `get_post_types()` shows types from standalone plugin
- Shortcodes still rendering from standalone (conflict with integrated)
- AJAX endpoints return 500 or duplicate responses

**Verification:**
```php
// Check shortcodes after deactivation
global $shortcode_tags;
$agent_shortcodes = array_filter(
    array_keys($shortcode_tags),
    fn($tag) => strpos($tag, 'wecoza') !== false && strpos($tag, 'agent') !== false
);
// Should show integrated handlers only (from src/Agents/Controllers/)
```

### Pitfall 2: Not Testing Rollback Scenario

**What goes wrong:** Deactivation causes unexpected issue, but can't reactivate standalone plugin cleanly (database state changed)

**Why it happens:** Integrated module might have modified data structure or added fields standalone plugin doesn't recognize

**How to avoid:** Before removing source, verify rollback works:
1. Deactivate standalone
2. Test integrated module
3. Reactivate standalone (confirm no errors)
4. Deactivate standalone again
5. Only then remove source

**Warning signs:**
- Reactivation shows PHP errors or database warnings
- Standalone plugin shows "incompatible data" messages
- Data integrity issues after reactivation

### Pitfall 3: Forgetting About Cron Jobs or Scheduled Tasks

**What goes wrong:** Standalone plugin registered cron jobs that still try to run after deactivation, causing errors

**Why it happens:** WordPress cron persists in wp_options even after plugin deactivation

**How to avoid:** Check wp_cron option for standalone plugin scheduled tasks:
```php
$cron = get_option('cron');
foreach ($cron as $timestamp => $tasks) {
    foreach ($tasks as $hook => $details) {
        if (strpos($hook, 'wecoza_agents_') !== false) {
            // Found standalone plugin cron job
            wp_unschedule_event($timestamp, $hook);
        }
    }
}
```

**Warning signs:**
- debug.log shows errors from cron jobs after deactivation
- wp_cron option grows with failed task attempts

### Pitfall 4: Missing Asset Enqueueing Dependencies

**What goes wrong:** Integrated module depends on standalone plugin assets (JS/CSS) being enqueued first

**Why it happens:** Migration might have forgotten to move asset registration from standalone to integrated

**How to avoid:** After deactivation, check browser DevTools Network tab:
- Verify all CSS files load (200 status)
- Verify all JS files load (200 status)
- Check console for "undefined variable" errors (missing JS dependencies)

**Warning signs:**
- 404 errors for assets in Network tab
- JS console errors: "Uncaught ReferenceError: wecozaAgents is not defined"
- Forms don't submit (missing validation JS)

**Verification:**
```bash
# Check for hardcoded asset URLs pointing to standalone plugin
grep -r "wecoza-agents-plugin" views/agents/ assets/js/agents/ --include="*.php" --include="*.js"
# Should return 0 matches
```

### Pitfall 5: Not Documenting Deactivation Date and State

**What goes wrong:** Months later, someone asks "why is this plugin deactivated?" or "can we delete it?" but no record exists

**Why it happens:** No one updates STATE.md or project documentation after deactivation

**How to avoid:** Immediately after successful deactivation, update:
- `.planning/STATE.md` → Move standalone plugin to "Deprecated/Removed" section
- `.planning/PROJECT.md` → Update "Current State" to reflect integrated module only
- Phase 30 VERIFICATION.md → Document deactivation date, verifier, test results

**Example STATE.md update:**
```markdown
## Deprecated/Removed Plugins

| Plugin | Deactivated | Removed | Reason | Replaced By |
|--------|-------------|---------|--------|-------------|
| wecoza-agents-plugin | 2026-02-12 | 2026-02-12 | v3.0 integration complete | src/Agents/ in wecoza-core |
```

## Code Examples

Verified patterns for Phase 30 implementation:

### Complete Pre-Deactivation Safety Script

```bash
#!/bin/bash
# scripts/pre-deactivation-agents.sh
# Run before deactivating wecoza-agents-plugin

set -e  # Exit on error

WECOZA_ROOT="/opt/lampp/htdocs/wecoza"
CORE_ROOT="$WECOZA_ROOT/wp-content/plugins/wecoza-core"

cd "$WECOZA_ROOT"

echo "====================================="
echo "Pre-Deactivation Safety Checks"
echo "Standalone: wecoza-agents-plugin"
echo "Integrated: src/Agents/ in wecoza-core"
echo "====================================="
echo ""

# 1. Verify integrated module loaded
echo "[1/6] Checking integrated module is loaded..."
if php -r "require 'wp-load.php'; exit(class_exists('\WeCoza\Agents\Controllers\AgentsController') ? 0 : 1);"; then
  echo "✓ PASS: Integrated AgentsController loaded"
else
  echo "✗ FAIL: Integrated module NOT loaded. Cannot deactivate standalone."
  exit 1
fi
echo ""

# 2. Run feature parity test
echo "[2/6] Running feature parity test..."
php "$CORE_ROOT/tests/integration/agents-feature-parity.php"
TEST_EXIT=$?
if [ $TEST_EXIT -eq 0 ]; then
  echo "✓ PASS: Feature parity test passed (all checks)"
elif [ $TEST_EXIT -eq 1 ]; then
  echo "⚠ PARTIAL: Feature parity test passed (some expected failures, e.g., agent_meta table)"
  echo "           This is acceptable if only expected failures documented."
else
  echo "✗ FAIL: Feature parity test failed unexpectedly (exit code $TEST_EXIT)"
  exit 1
fi
echo ""

# 3. Check standalone plugin status
echo "[3/6] Checking standalone plugin status..."
if command -v wp &> /dev/null; then
  if wp plugin is-installed wecoza-agents-plugin --path="$WECOZA_ROOT" 2>/dev/null; then
    if wp plugin is-active wecoza-agents-plugin --path="$WECOZA_ROOT" 2>/dev/null; then
      echo "! INFO: Standalone plugin is ACTIVE (ready to deactivate)"
    else
      echo "✓ PASS: Standalone plugin already INACTIVE"
      echo "        (No deactivation needed, proceed to cleanup)"
    fi
  else
    echo "✓ PASS: Standalone plugin not installed"
    echo "        (Already removed, proceed to cleanup)"
  fi
else
  echo "⚠ WARNING: WP-CLI not available, cannot check plugin status"
  echo "           Verify manually in WordPress admin"
fi
echo ""

# 4. Check for agent shortcodes in content
echo "[4/6] Checking for agent shortcodes in published content..."
# Note: Requires WordPress database access
SHORTCODE_PAGES=$(php -r "
require '$WECOZA_ROOT/wp-load.php';
\$posts = get_posts([
    'post_type' => ['page', 'post'],
    'post_status' => 'publish',
    'numberposts' => -1,
]);
\$count = 0;
foreach (\$posts as \$post) {
    if (has_shortcode(\$post->post_content, 'wecoza_capture_agents') ||
        has_shortcode(\$post->post_content, 'wecoza_display_agents') ||
        has_shortcode(\$post->post_content, 'wecoza_single_agent')) {
        echo \$post->ID . ' (' . \$post->post_title . ')\n';
        \$count++;
    }
}
exit(\$count);
" 2>&1)
SHORTCODE_COUNT=$?
if [ $SHORTCODE_COUNT -eq 0 ]; then
  echo "✓ PASS: No pages using agent shortcodes found"
else
  echo "! INFO: Found $SHORTCODE_COUNT page(s) using agent shortcodes:"
  echo "$SHORTCODE_PAGES"
  echo "        These pages MUST render correctly after deactivation"
fi
echo ""

# 5. Check debug.log for existing errors
echo "[5/6] Checking debug.log for recent errors..."
DEBUG_LOG="$WECOZA_ROOT/wp-content/debug.log"
if [ -f "$DEBUG_LOG" ]; then
  # Check last 50 lines for agent-related errors
  RECENT_ERRORS=$(tail -50 "$DEBUG_LOG" | grep -i "agent" | grep -iE "error|warning|fatal" || true)
  if [ -z "$RECENT_ERRORS" ]; then
    echo "✓ PASS: No recent agent-related errors in debug.log"
  else
    echo "⚠ WARNING: Found recent agent-related errors:"
    echo "$RECENT_ERRORS"
    echo "           Resolve these before deactivation"
  fi
else
  echo "⚠ WARNING: debug.log not found at $DEBUG_LOG"
  echo "           Cannot check for existing errors"
fi
echo ""

# 6. Database backup reminder
echo "[6/6] Database backup check..."
echo "! IMPORTANT: Ensure database backup exists before proceeding"
echo "             PostgreSQL dump command:"
echo "             pg_dump -h HOST -U USER -d DATABASE > backup_$(date +%Y%m%d_%H%M%S).sql"
read -p "             Database backup created? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "✗ ABORT: Create database backup before deactivation"
  exit 1
fi
echo "✓ CONFIRMED: Database backup exists"
echo ""

echo "====================================="
echo "Pre-Deactivation Checks: PASSED"
echo "====================================="
echo ""
echo "Next steps:"
echo "1. Go to WordPress admin → Plugins"
echo "2. Find 'WeCoza Agents Plugin' (standalone)"
echo "3. Click 'Deactivate'"
echo "4. Test agent pages immediately (forms, lists, single agent)"
echo "5. Check debug.log for new errors"
echo "6. Re-run feature parity test: php $CORE_ROOT/tests/integration/agents-feature-parity.php"
echo ""
```

### Dangling Reference Detection Script (Run Before Source Removal)

```bash
#!/bin/bash
# scripts/check-agents-references.sh
# Verify no code references standalone plugin before removing source

CORE_ROOT="/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core"
cd "$CORE_ROOT"

echo "====================================="
echo "Dangling Reference Detection"
echo "====================================="
echo ""

FAILED=0

# 1. Check for DatabaseService references (old standalone pattern)
echo "[1/5] Checking for DatabaseService references..."
if grep -r "WeCoza\\\\Agents\\\\Database\\\\DatabaseService" src/Agents/ views/agents/ --include="*.php" -q 2>/dev/null; then
  echo "✗ FAIL: Found DatabaseService references (old standalone pattern)"
  grep -rn "WeCoza\\\\Agents\\\\Database\\\\DatabaseService" src/Agents/ views/agents/ --include="*.php" --color=always
  FAILED=1
else
  echo "✓ PASS: No DatabaseService references"
fi
echo ""

# 2. Check for WECOZA_AGENTS_* constants
echo "[2/5] Checking for WECOZA_AGENTS_* constants..."
if grep -r "WECOZA_AGENTS_" src/Agents/ views/agents/ assets/js/agents/ --include="*.php" --include="*.js" -q 2>/dev/null; then
  echo "✗ FAIL: Found WECOZA_AGENTS_ constant references"
  grep -rn "WECOZA_AGENTS_" src/Agents/ views/agents/ assets/js/agents/ --include="*.php" --include="*.js" --color=always
  FAILED=1
else
  echo "✓ PASS: No WECOZA_AGENTS_ constants"
fi
echo ""

# 3. Check for .integrate/ path references in active code
echo "[3/5] Checking for .integrate/ path references..."
if grep -r "\.integrate/" src/ views/ assets/ config/ wecoza-core.php --include="*.php" --include="*.js" -q 2>/dev/null; then
  echo "✗ FAIL: Found .integrate/ path references"
  grep -rn "\.integrate/" src/ views/ assets/ config/ wecoza-core.php --include="*.php" --include="*.js" --color=always
  FAILED=1
else
  echo "✓ PASS: No .integrate/ path references"
fi
echo ""

# 4. Check for 'wecoza-agents-plugin' string in active code
echo "[4/5] Checking for 'wecoza-agents-plugin' string..."
if grep -r "wecoza-agents-plugin" src/ views/ assets/ config/ wecoza-core.php composer.json --include="*.php" --include="*.js" --include="*.json" -q 2>/dev/null; then
  echo "✗ FAIL: Found wecoza-agents-plugin references"
  grep -rn "wecoza-agents-plugin" src/ views/ assets/ config/ wecoza-core.php composer.json --include="*.php" --include="*.js" --include="*.json" --color=always
  FAILED=1
else
  echo "✓ PASS: No wecoza-agents-plugin references"
fi
echo ""

# 5. Verify source folder exists (should be present before removal)
echo "[5/5] Verifying source folder exists..."
if [ -d ".integrate/wecoza-agents-plugin" ]; then
  echo "✓ PASS: .integrate/wecoza-agents-plugin/ exists (ready for removal)"
  echo "        Folder size: $(du -sh .integrate/wecoza-agents-plugin/ | cut -f1)"
else
  echo "⚠ WARNING: .integrate/wecoza-agents-plugin/ not found"
  echo "           Already removed or never existed"
fi
echo ""

# Summary
echo "====================================="
if [ $FAILED -eq 0 ]; then
  echo "Status: ALL CHECKS PASSED"
  echo "====================================="
  echo ""
  echo "Safe to remove .integrate/wecoza-agents-plugin/"
  echo ""
  echo "Removal command:"
  echo "  cd $CORE_ROOT"
  echo "  rm -rf .integrate/wecoza-agents-plugin/"
  echo "  git add .integrate/"
  echo "  git status"
  exit 0
else
  echo "Status: FAILED"
  echo "====================================="
  echo ""
  echo "Found dangling references. Fix before removal."
  echo "Review output above for file locations."
  exit 1
fi
```

### Post-Cleanup Verification Script

```bash
#!/bin/bash
# scripts/verify-agents-cleanup.sh
# Run immediately after removing .integrate/wecoza-agents-plugin/

WECOZA_ROOT="/opt/lampp/htdocs/wecoza"
CORE_ROOT="$WECOZA_ROOT/wp-content/plugins/wecoza-core"

cd "$CORE_ROOT"

echo "====================================="
echo "Post-Cleanup Verification"
echo "====================================="
echo ""

FAILED=0

# 1. Verify folder removed
echo "[1/5] Verifying .integrate/wecoza-agents-plugin/ removed..."
if [ -d ".integrate/wecoza-agents-plugin" ]; then
  echo "✗ FAIL: Folder still exists at .integrate/wecoza-agents-plugin/"
  FAILED=1
else
  echo "✓ PASS: Folder successfully removed"
fi
echo ""

# 2. Verify integrated module still intact
echo "[2/5] Verifying src/Agents/ directory intact..."
if [ ! -d "src/Agents" ]; then
  echo "✗ FAIL: src/Agents/ directory MISSING"
  FAILED=1
elif [ ! "$(ls -A src/Agents/)" ]; then
  echo "✗ FAIL: src/Agents/ directory is EMPTY"
  FAILED=1
else
  FILE_COUNT=$(find src/Agents/ -type f -name "*.php" | wc -l)
  echo "✓ PASS: src/Agents/ intact ($FILE_COUNT PHP files)"
fi
echo ""

# 3. Run feature parity test
echo "[3/5] Running feature parity test post-cleanup..."
cd "$WECOZA_ROOT"
php "$CORE_ROOT/tests/integration/agents-feature-parity.php"
TEST_EXIT=$?
if [ $TEST_EXIT -eq 0 ] || [ $TEST_EXIT -eq 1 ]; then
  echo "✓ PASS: Feature parity test still passes post-cleanup"
else
  echo "✗ FAIL: Feature parity test FAILED after cleanup (exit code $TEST_EXIT)"
  FAILED=1
fi
echo ""

# 4. Verify controller still loadable
echo "[4/5] Smoke test: AgentsController loadable..."
cd "$WECOZA_ROOT"
if php -r "require 'wp-load.php'; exit(class_exists('\WeCoza\Agents\Controllers\AgentsController') ? 0 : 1);"; then
  echo "✓ PASS: AgentsController still loadable"
else
  echo "✗ FAIL: AgentsController NOT loadable"
  FAILED=1
fi
echo ""

# 5. Check git status
echo "[5/5] Git status check..."
cd "$CORE_ROOT"
GIT_STATUS=$(git status --short .integrate/ 2>&1)
if [ -n "$GIT_STATUS" ]; then
  echo "! INFO: Git changes detected in .integrate/:"
  echo "$GIT_STATUS"
  echo "        Remember to commit these changes"
else
  echo "✓ INFO: No git changes in .integrate/ (already committed or no changes)"
fi
echo ""

# Summary
echo "====================================="
if [ $FAILED -eq 0 ]; then
  echo "Status: CLEANUP VERIFIED"
  echo "====================================="
  echo ""
  echo "✓ Standalone plugin source removed"
  echo "✓ Integrated module still functional"
  echo "✓ Feature parity maintained"
  echo ""
  echo "Next steps:"
  echo "1. Commit removal: git add .integrate/ && git commit -m 'chore(v3.0): remove standalone wecoza-agents-plugin source'"
  echo "2. Update STATE.md to mark standalone as removed"
  echo "3. Create Phase 30 VERIFICATION.md"
  exit 0
else
  echo "Status: VERIFICATION FAILED"
  echo "====================================="
  echo ""
  echo "Cleanup verification failed. Review output above."
  echo "Consider restoring from backup if issues persist."
  exit 1
fi
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual testing only | CLI test scripts + manual checklists | Clients v2.0 (Phase 25, 2026-02) | Faster verification, repeatable process, confidence in deactivation |
| Keep standalone indefinitely | Verify parity → deactivate → remove source | Phase 25 pattern | Cleaner repository, single source of truth, reduced maintenance |
| No reference detection | Automated grep scripts before removal | Phase 25 pattern | Prevents broken imports, catches dangling constants |
| Immediate source removal | Two-step: deactivate first, remove later | Phase 25 pattern | Allows rollback if issues found, safer migration |
| Plugin deactivation without testing | Pre-deactivation safety checks | Phase 25 pattern | Prevents production outages, documents verification steps |

**Deprecated/outdated:**
- **Standalone wecoza-agents-plugin:** Being removed in Phase 30 after v3.0 integration complete (Phases 26-29)
- **WeCoza\Agents\Database\DatabaseService:** Replaced by wecoza_db() singleton in integrated version
- **WECOZA_AGENTS_* constants:** No longer needed—integrated module uses wecoza-core infrastructure
- **Keep .integrate/ folder indefinitely:** Phase 30 removes after verification—Git history preserves reference implementation

## Open Questions

1. **Are there production pages currently using agent shortcodes?**
   - What we know: Phase 29 verified shortcodes work, standalone plugin has been inactive since Phase 26
   - What's unclear: Which WordPress pages in production render [wecoza_capture_agents], [wecoza_display_agents], [wecoza_single_agent]
   - Recommendation: Pre-deactivation script searches wp_posts for shortcodes, documents page IDs for manual verification

2. **Should .integrate/ folder be completely removed or moved to .integrate/done/?**
   - What we know: Requirements CLN-02 says "removed from repository", Phase 25 (Clients) moved to done/ first
   - What's unclear: Project preference—complete removal vs archival in done/
   - Recommendation: Complete removal (rm -rf)—Git history preserves source, no need for duplicate archival. Simpler git status.

3. **Does wecoza-events-plugin depend on agents functionality?**
   - What we know: Phase 18 created Events notification system, Agents module separate
   - What's unclear: If Events module references agent data or triggers agent-related hooks
   - Recommendation: Grep Events code for agent references before deactivation: `grep -r "agent" src/Events/ --include="*.php" -i`

4. **Performance baseline after cleanup?**
   - What we know: Phase 29 verified no information_schema queries (Bug #16 fixed)
   - What's unclear: Expected query count on agent pages after standalone removed
   - Recommendation: Use Query Monitor to establish baseline, verify no regression post-cleanup (should be identical)

## Sources

### Primary (HIGH confidence)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.planning/phases/25-integration-testing-cleanup/` - Phase 25 research/plans (v2.0 Clients integration reference)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.planning/phases/29-feature-verification/29-VERIFICATION.md` - Phase 29 verification report (22/24 must-haves verified)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/tests/integration/agents-feature-parity.php` - Existing 607-line test script
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/.integrate/wecoza-agents-plugin/` - Standalone plugin source (to be removed)
- `.planning/REQUIREMENTS.md` - Requirements CLN-01, CLN-02 defined
- `.planning/ROADMAP.md` - Phase 30 success criteria defined

### Secondary (MEDIUM confidence)
- [WordPress Plugin Testing Best Practices](https://melapress.com/wordpress-plugin-testing-best-practices/) - Testing methodology (2025)
- [Activation / Deactivation Hooks – Plugin Handbook](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) - Official WordPress deactivation documentation
- [The Complete Post-Migration WordPress Testing Checklist (2026)](https://duplicator.com/post-wordpress-migration-testing/) - Migration verification patterns
- [WordPress plugin best practices: How to clean Up, remove, and manage](https://www.liquidweb.com/wordpress/plugin/best-practices/) - Cleanup best practices
- [WordPress Post-Migration Checklist (2026): Fix Issues](https://www.cloudways.com/blog/wordpress-post-migration-checklist/) - Post-migration verification
- [The Ultimate WordPress Migration Checklist for 2026](https://www.cloudways.com/blog/wordpress-migration-checklist/) - Migration workflow

### Tertiary (LOW confidence)
- None—research based entirely on Phase 25 reference implementation, Phase 29 verification report, and official WordPress documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools already exist (test script, WordPress admin, bash scripts)
- Architecture: HIGH - Phase 25 provides proven pattern, Phase 29 provides comprehensive verification
- Pitfalls: HIGH - Phase 25 lessons learned apply directly to Phase 30 (same migration pattern)
- Cleanup verification: HIGH - Bash scripts proven effective in Phase 25, grep-based detection reliable

**Research date:** 2026-02-12
**Valid until:** 2026-03-12 (30 days—migration cleanup is one-time event, patterns stable)

**Phase dependencies:**
- Phase 26: Foundation architecture complete (src/Agents/ exists)
- Phase 27: Controllers/views/JS/AJAX migrated (all code in wecoza-core)
- Phase 28: Wiring verified (shortcodes/AJAX registered)
- Phase 29: Feature parity verified (test script exists, 22/24 checks pass)
- **Phase 30:** Final cleanup (deactivate standalone, remove source)

**Key findings:**
1. Phase 25 (Clients v2.0) provides exact pattern to follow—two plans: test + deactivate, then cleanup + remove
2. Feature parity test already exists (607 lines, comprehensive) from Phase 29
3. Standalone plugin has been idle since Phase 26—no code uses it
4. Requirements CLN-01, CLN-02 map directly to Phase 25 requirements
5. Zero blocker concerns—all migration bugs fixed in Phases 27-29
6. Reference detection scripts prevent broken imports before removal
7. Post-cleanup verification ensures no regression after source removed
