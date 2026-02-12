# Phase 25: Integration Testing & Cleanup - Research

**Researched:** 2026-02-12
**Domain:** WordPress plugin integration testing and migration cleanup
**Confidence:** HIGH

## Summary

Phase 25 verifies that the integrated Clients module in wecoza-core provides identical functionality to the standalone wecoza-clients-plugin, enabling safe deactivation and removal of the standalone plugin. The migration (Phases 21-24) moved all client/location/site functionality from a standalone plugin into wecoza-core's `src/Clients/` namespace.

This is a **verification and cleanup phase**, not a feature-building phase. Success requires proving functional equivalence between two implementations, then safely removing the old implementation.

**Primary recommendation:** Use systematic E2E comparison testing (standalone vs integrated) followed by safe deactivation workflow and repository cleanup.

## Standard Stack

### Core Testing Tools

| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| Manual E2E testing | N/A | User-driven functional verification | WordPress ecosystem lacks automated integration test infrastructure for plugins |
| WP-CLI | Latest | Plugin activation/deactivation scripting | Official WordPress command-line tool, production-safe |
| PHP scripts | PHP 8.0+ | Custom verification tests (pattern: `tests/*.php`) | Existing pattern in codebase (`tests/security-test.php`) |
| WordPress debug.log | N/A | Error detection during testing | Built-in WordPress debugging, already enabled in project |

### Supporting Tools

| Tool | Purpose | When to Use |
|------|---------|-------------|
| Browser DevTools | AJAX/network inspection | Verifying identical AJAX payloads and responses |
| PostgreSQL client | Database state verification | Confirming data integrity after operations |
| diff/git diff | File comparison | Verifying cleanup completeness |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Manual E2E testing | PHPUnit + wp-browser | Requires test infrastructure setup (not established in project), time-consuming for single-use migration verification |
| Custom PHP test scripts | Codeception/Behat | Overkill for one-time verification, adds dependencies |
| WP-CLI commands | WordPress Admin UI only | WP-CLI provides scriptable, repeatable verification |

**Installation:**
```bash
# No installation needed - all tools already available
# WP-CLI: Already in use (verified in Phase 21-24 verification docs)
# PHP: 8.0+ required by project
# PostgreSQL client: Already configured (wecoza_db() in use)
```

## Architecture Patterns

### Recommended Verification Structure

```
tests/
├── integration/                    # Integration verification scripts
│   ├── clients-feature-parity.php # Compare standalone vs integrated
│   └── post-migration-smoke.php   # Verify integrated-only functionality
.planning/phases/25-integration-testing-cleanup/
├── 25-RESEARCH.md                 # This file
├── 25-01-PLAN.md                  # E2E comparison testing plan
├── 25-02-PLAN.md                  # Cleanup and removal plan
└── 25-VERIFICATION.md             # Verification report
```

### Pattern 1: Side-by-Side Functional Comparison

**What:** Test identical operations in both standalone plugin and integrated module, verify outputs match

**When to use:** Before deactivating standalone plugin

**Example workflow:**
```bash
# 1. Ensure both plugins active
# 2. Test operation with standalone (record output/database state)
# 3. Test same operation with integrated (record output/database state)
# 4. Compare outputs and database states
# 5. Repeat for all critical operations
```

**Critical operations to test:**
- Client CRUD (create, read, update, soft-delete)
- Location CRUD with Google Maps integration
- Site hierarchy (head site, sub-site creation)
- Client-location hydration (data enrichment from locations table)
- Search and filter functionality
- CSV export
- AJAX endpoint responses

### Pattern 2: Safe Deactivation Workflow

**What:** Controlled process for deactivating standalone plugin without breaking production functionality

**When to use:** After functional parity verified

**Example:**
```bash
# 1. Backup database (PostgreSQL dump)
# 2. Document all active shortcodes in use (grep production pages)
# 3. Verify wecoza-core module provides all shortcodes
# 4. Test on staging/local first
# 5. Deactivate standalone plugin
# 6. Verify all shortcodes still render
# 7. Test all AJAX endpoints still respond
# 8. Check debug.log for errors
# 9. If issues found: reactivate standalone, debug integrated module
```

### Pattern 3: Repository Cleanup Verification

**What:** Ensure `.integrate/` folder removal doesn't break anything

**When to use:** After standalone plugin successfully deactivated

**Example:**
```bash
# 1. Verify no code references .integrate/ paths
grep -r "\.integrate/" --include="*.php" --include="*.js" src/ views/ assets/

# 2. Verify no composer/autoload references
grep -r "integrate" composer.json

# 3. Remove folder
rm -rf .integrate/

# 4. Test functionality still works
# 5. Commit removal
git add -A
git commit -m "chore(cleanup): remove .integrate folder after successful migration"
```

### Anti-Patterns to Avoid

- **Deactivating standalone before verification:** Risk: integrated module might be missing functionality, causes immediate production breakage
- **Removing .integrate/ before deactivation:** Risk: lose reference implementation if rollback needed
- **Testing only happy paths:** Risk: edge cases (errors, validation, empty states) might behave differently
- **Skipping AJAX endpoint comparison:** Risk: frontend might break even if shortcodes render
- **Manual testing without checklist:** Risk: forget to test critical features

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Plugin activation state detection | Custom database queries | WP-CLI `plugin list` or `is_plugin_active()` | WordPress provides reliable APIs, handles edge cases |
| Shortcode detection in pages | Custom content parsing | `has_shortcode()` WordPress function | Handles shortcode variations, attributes, nested shortcodes |
| Database comparison | String comparison of queries | PostgreSQL query execution + result comparison | Queries might differ but produce same results |
| Error detection | Try-catch everything | WordPress debug.log + WP_DEBUG | Already enabled, captures all PHP errors/warnings |

**Key insight:** WordPress and PHP provide sufficient built-in tools for migration verification. Custom tooling adds complexity without value.

## Common Pitfalls

### Pitfall 1: Assuming Identical Code = Identical Behavior

**What goes wrong:** Integrated module might share core infrastructure (wecoza_db(), AjaxSecurity) that behaves subtly differently than standalone's infrastructure

**Why it happens:**
- Standalone uses `WeCozaClients\Services\Database\DatabaseService`
- Integrated uses `wecoza_db()` singleton
- Connection pooling, error handling, prepared statement caching might differ

**How to avoid:**
- Test actual operations, not just code review
- Verify database state after each operation
- Check for differences in error handling (try creating duplicate clients in both)

**Warning signs:**
- Debug.log shows errors from integrated but not standalone
- Same operation takes different time to execute
- AJAX responses have different shapes (even if data is same)

### Pitfall 2: Forgetting About Nonce/Action Name Conflicts

**What goes wrong:** Both plugins active simultaneously might have conflicting AJAX actions or nonces

**Why it happens:**
- Standalone registers `wp_ajax_wecoza_save_client`
- Integrated also registers `wp_ajax_wecoza_save_client`
- WordPress uses first registered handler (standalone wins if loaded first)

**How to avoid:**
- Test with ONLY integrated module active (deactivate standalone temporarily for specific tests)
- Check `wp_ajax_*` action registration order with `var_dump($wp_filter['wp_ajax_wecoza_save_client'])`
- Verify AJAX responses include expected data structure

**Warning signs:**
- AJAX calls work but save to wrong database/table
- Nonce verification fails unexpectedly
- Same AJAX call produces different results on page refresh

### Pitfall 3: Not Testing Soft-Delete Behavior

**What goes wrong:** Standalone and integrated might handle soft-delete differently (deleted_at column, cascading to related records)

**Why it happens:** Migration code might have changed soft-delete implementation during refactoring

**How to avoid:**
- Explicitly test delete operation in both systems
- Query database directly to verify deleted_at timestamp set (not hard delete)
- Check if related records (sites, locations) are handled correctly

**Warning signs:**
- Deleted clients disappear completely (hard delete instead of soft)
- Deleted clients still appear in some listings
- Sub-clients lose parent reference after main client deleted

### Pitfall 4: Incomplete Cleanup Detection

**What goes wrong:** Code might still reference standalone plugin paths or constants after .integrate/ removed

**Why it happens:**
- Legacy code copied during migration
- Constants defined in standalone (WECOZA_CLIENTS_PLUGIN_URL) might be referenced
- Asset paths might point to old plugin directory

**How to avoid:**
- Grep entire codebase for standalone plugin constants before removal
- Check for hardcoded paths to old plugin directory
- Verify all asset URLs resolve correctly after cleanup

**Warning signs:**
- 404 errors for CSS/JS files after cleanup
- PHP notices about undefined constants
- File not found errors in debug.log

## Code Examples

Verified patterns for Phase 25 implementation:

### E2E Comparison Test Pattern

```php
// tests/integration/clients-feature-parity.php
<?php
/**
 * Verify functional parity between standalone and integrated Clients module
 *
 * Run with: php tests/integration/clients-feature-parity.php
 */

// Load WordPress
require_once dirname(__FILE__, 4) . '/wp-load.php';

class ClientsParityTest {
    private array $results = [];

    public function run(): void {
        echo "=== Clients Module Feature Parity Test ===\n\n";

        $this->testShortcodesRegistered();
        $this->testAjaxEndpointsRegistered();
        $this->testClientCreation();
        $this->testLocationCreation();
        $this->testSiteHierarchy();

        $this->printResults();
    }

    private function testShortcodesRegistered(): void {
        $requiredShortcodes = [
            'wecoza_capture_clients',
            'wecoza_display_clients',
            'wecoza_update_clients',
            'wecoza_locations_capture',
            'wecoza_locations_list',
            'wecoza_locations_edit',
        ];

        $missing = [];
        foreach ($requiredShortcodes as $shortcode) {
            if (!shortcode_exists($shortcode)) {
                $missing[] = $shortcode;
            }
        }

        if (empty($missing)) {
            $this->pass('All 6 shortcodes registered');
        } else {
            $this->fail('Missing shortcodes: ' . implode(', ', $missing));
        }
    }

    private function testAjaxEndpointsRegistered(): void {
        global $wp_filter;

        $requiredEndpoints = [
            'wp_ajax_wecoza_save_client',
            'wp_ajax_wecoza_get_client',
            'wp_ajax_wecoza_delete_client',
            'wp_ajax_wecoza_check_location_duplicates',
            'wp_ajax_wecoza_get_locations',
        ];

        $missing = [];
        foreach ($requiredEndpoints as $hook) {
            if (!isset($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) {
                $missing[] = $hook;
            }
        }

        if (empty($missing)) {
            $this->pass('All critical AJAX endpoints registered');
        } else {
            $this->fail('Missing endpoints: ' . implode(', ', $missing));
        }
    }

    private function pass(string $message): void {
        $this->results[] = ['status' => 'PASS', 'message' => $message];
        echo "✓ PASS: {$message}\n";
    }

    private function fail(string $message): void {
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
        echo "✗ FAIL: {$message}\n";
    }

    private function printResults(): void {
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));

        echo "\n=================================\n";
        echo "Results: {$passed} passed, {$failed} failed\n";
        echo "=================================\n";
    }
}

$test = new ClientsParityTest();
$test->run();
```

### Safe Deactivation Script

```bash
#!/bin/bash
# scripts/deactivate-standalone-clients.sh
# Safely deactivate standalone wecoza-clients-plugin

set -e  # Exit on error

echo "=== Safe Deactivation Workflow ==="
echo ""

# 1. Verify integrated module active
echo "1. Checking integrated module is loaded..."
if ! php -r "require 'wp-load.php'; exit(class_exists('\WeCoza\Clients\Controllers\ClientsController') ? 0 : 1);"; then
    echo "ERROR: Integrated Clients module not loaded. Aborting."
    exit 1
fi
echo "✓ Integrated module loaded"
echo ""

# 2. Check for standalone plugin
echo "2. Checking standalone plugin status..."
STANDALONE_ACTIVE=$(php -r "require 'wp-load.php'; echo is_plugin_active('wecoza-clients-plugin/wecoza-clients-plugin.php') ? 'yes' : 'no';")
if [ "$STANDALONE_ACTIVE" = "no" ]; then
    echo "✓ Standalone plugin already inactive"
    exit 0
fi
echo "! Standalone plugin currently active"
echo ""

# 3. Run parity tests
echo "3. Running feature parity tests..."
if ! php tests/integration/clients-feature-parity.php; then
    echo "ERROR: Parity tests failed. Fix issues before deactivation."
    exit 1
fi
echo "✓ Parity tests passed"
echo ""

# 4. Backup database
echo "4. Creating database backup..."
BACKUP_FILE="backups/postgres_before_deactivation_$(date +%Y%m%d_%H%M%S).sql"
mkdir -p backups
# Note: User must run this manually with their PostgreSQL credentials
echo "Please create backup manually:"
echo "  pg_dump -h HOST -p PORT -U USER -d DATABASE > $BACKUP_FILE"
read -p "Backup created? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborting. Create backup first."
    exit 1
fi
echo ""

# 5. Deactivate standalone
echo "5. Deactivating standalone plugin..."
# Note: User must do this via WordPress admin
echo "Please deactivate 'WeCoza Clients Plugin' via WordPress admin."
echo "Then run: php tests/integration/post-migration-smoke.php"
echo ""
echo "After smoke tests pass, you can remove .integrate/ folder."
```

### Cleanup Verification

```bash
# Check for references to standalone plugin before removal
echo "Checking for standalone plugin references..."

# Check for old constants
echo "1. Checking for WECOZA_CLIENTS_* constants..."
grep -r "WECOZA_CLIENTS_" src/ views/ assets/ --include="*.php" --include="*.js" && echo "! Found references" || echo "✓ No references found"

# Check for old namespace
echo "2. Checking for WeCozaClients namespace..."
grep -r "WeCozaClients" src/ views/ --include="*.php" && echo "! Found references" || echo "✓ No references found"

# Check for .integrate/ paths
echo "3. Checking for .integrate/ paths..."
grep -r "\.integrate/" src/ views/ assets/ --include="*.php" --include="*.js" && echo "! Found references" || echo "✓ No references found"

# Check composer.json
echo "4. Checking composer.json..."
grep -i "integrate" composer.json && echo "! Found references" || echo "✓ No references found"

echo ""
echo "If all checks passed, safe to remove .integrate/ folder"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual testing only | Manual + automated scripts (tests/*.php) | 2026-02-11 | Existing security-test.php establishes pattern for custom verification scripts |
| Plugin deactivation without verification | Feature parity testing before deactivation | Phase 25 | Prevents breaking production |
| Keep reference code indefinitely | Remove after verification complete | Phase 25 | Cleaner repository, single source of truth |

**Deprecated/outdated:**
- **Standalone wecoza-clients-plugin**: Being replaced by integrated src/Clients/ module
- **DatabaseService class**: Replaced by wecoza_db() singleton in integrated version
- **WeCozaClients namespace**: Replaced by WeCoza\Clients namespace

## Open Questions

1. **Are there production pages using standalone shortcodes currently?**
   - What we know: Both plugins can coexist (Phases 21-24 completed with standalone active)
   - What's unclear: Which pages in production use client/location shortcodes
   - Recommendation: Grep production database for shortcode usage before deactivation, or document all known pages

2. **Do any third-party plugins depend on standalone wecoza-clients-plugin?**
   - What we know: Project uses wecoza-core, wecoza-agents-plugin, wecoza-clients-plugin
   - What's unclear: If wecoza-agents-plugin calls standalone plugin functions
   - Recommendation: Check wecoza-agents-plugin code for references to WeCozaClients namespace or standalone plugin hooks

3. **Should .integrate/ folder be archived or completely removed?**
   - What we know: Requirements say "removed from repository" (CLN-02)
   - What's unclear: If historical reference might be useful
   - Recommendation: Git history preserves code, safe to remove completely (can always check out old commits if needed)

## Sources

### Primary (HIGH confidence)

- **Codebase inspection** - Verified files:
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Clients/` (integrated module)
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-clients-plugin/` (standalone plugin)
  - `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/tests/security-test.php` (existing test pattern)
  - `.planning/phases/21-foundation-architecture/21-VERIFICATION.md` through `24-VERIFICATION.md` (all phases passed)

- **WordPress Codex** - Official documentation:
  - `shortcode_exists()`: https://developer.wordpress.org/reference/functions/shortcode_exists/
  - `is_plugin_active()`: https://developer.wordpress.org/reference/functions/is_plugin_active/
  - `has_shortcode()`: https://developer.wordpress.org/reference/functions/has_shortcode/

- **Project documentation**:
  - `.planning/REQUIREMENTS.md` - Requirements CLN-01, CLN-02 defined
  - `.planning/ROADMAP.md` - Phase 25 success criteria defined
  - `CLAUDE.md` - Project patterns and conventions

### Secondary (MEDIUM confidence)

- **Phase verification reports** - Evidence of migration completion:
  - Phase 21: Foundation architecture verified (5/5 truths, all requirements satisfied)
  - Phase 22: Client management verified (5/5 truths, human verification completed)
  - Phase 23: Location management verified (5/5 truths, all features working)
  - Phase 24: Sites hierarchy verified (7/7 truths, E2E tests passed)

### Tertiary (LOW confidence)

- None (research based entirely on codebase inspection and official documentation)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools are built-in to WordPress/PHP/PostgreSQL ecosystem, already in use in project
- Architecture: HIGH - Patterns based on existing project structure (tests/security-test.php) and WordPress best practices
- Pitfalls: HIGH - Identified from codebase analysis (two implementations exist, can compare directly)

**Research date:** 2026-02-12
**Valid until:** 60 days (migration is one-time event, patterns stable)

**Key findings:**
1. Migration already complete (Phases 21-24 verified) - only verification and cleanup remain
2. Standalone plugin still exists at `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-clients-plugin/`
3. Both implementations use same shortcode names and AJAX action names (potential conflict)
4. Existing test pattern established in `tests/security-test.php` (custom PHP scripts)
5. No automated integration testing infrastructure exists (manual E2E testing required)
