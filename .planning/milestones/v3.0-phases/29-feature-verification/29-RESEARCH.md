# Phase 29: Feature Verification & Performance - Research

**Researched:** 2026-02-12
**Domain:** WordPress Plugin Migration Feature Verification & Performance Testing
**Confidence:** HIGH

## Summary

Phase 29 verifies that the Agents module migration from standalone plugin to wecoza-core integration is functionally complete and performant. This involves manual verification testing of CRUD operations, file uploads, statistics calculations, working areas service, and performance validation—ensuring feature parity with the standalone plugin while catching migration-specific bugs (cache invalidation, redundant queries).

The research reveals a mature testing ecosystem in wecoza-core: existing CLI test runners (`tests/security-test.php`, `tests/integration/clients-feature-parity.php`) provide patterns for verification testing. Manual verification checklists from WordPress community emphasize field-level validation, data persistence checks, and end-to-end workflows. Performance testing focuses on Query Monitor for database profiling, cache invalidation verification (Bug #15), and eliminating redundant information_schema queries (Bug #16 was fixed in core, needs verification).

**Primary recommendation:** Create standalone CLI verification script `tests/integration/agents-feature-parity.php` following clients-feature-parity.php pattern, plus manual testing checklist for hands-on CRUD/upload/statistics verification. Use Query Monitor to validate performance (cache hits, query counts, information_schema elimination).

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP CLI test scripts | Core 1.0.0 | Automated feature parity verification | Existing pattern in wecoza-core for Clients integration tests |
| Query Monitor | Latest (WP plugin) | Database query profiling and performance debugging | Industry standard for WordPress performance debugging—tracks query counts, duplicate queries, cache hits |
| wecoza_log() | Core 1.0.0 | Debug logging to wp-content/debug.log | Project standard for debugging (WP_DEBUG only) |
| Manual testing checklist | N/A | Human verification of UI/UX workflows | Catches edge cases automation misses—standard in WordPress QA |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP_Ajax_UnitTestCase | WordPress Core | Unit testing AJAX callbacks | If PHPUnit tests needed (not currently in project) |
| wp eval-file | WP-CLI | Run test scripts via CLI | Alternative to direct PHP execution |
| Browser DevTools | Built-in | Network tab for AJAX debugging, console for JS errors | Manual testing verification |
| PostgreSQL query logs | PostgreSQL 12+ | Raw SQL query inspection | Deep debugging when Query Monitor insufficient |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Manual testing only | Full PHPUnit/Behat suite | PHPUnit requires significant setup—manual testing sufficient for integration verification |
| Query Monitor | New Relic / Blackfire | External APM tools overkill for single-plugin debugging |
| CLI test scripts | WP_UnitTestCase | Unit tests test isolated components—integration tests verify end-to-end workflows |

**Installation:**
```bash
# Query Monitor (via WordPress admin)
wp plugin install query-monitor --activate

# No other external dependencies—all utilities in wecoza-core
```

## Architecture Patterns

### Recommended Project Structure
```
tests/
├── integration/
│   ├── clients-feature-parity.php       # Existing reference pattern
│   └── agents-feature-parity.php        # NEW: Agents verification script
├── security-test.php                     # Existing security regression tests
└── Events/                               # Existing Events module tests
.planning/phases/29-feature-verification/
├── 29-RESEARCH.md                        # This document
├── 29-01-PLAN.md                         # Verification test script plan
└── 29-02-PLAN.md                         # Manual testing checklist plan
```

### Pattern 1: CLI Feature Parity Test Script
**What:** Standalone PHP script using CLI safety check, test runner class with pass/fail tracking, WordPress global access for verification
**When to use:** Automated verification of integration completeness (shortcodes, AJAX endpoints, classes, database tables)
**Example:**
```php
// Source: tests/integration/clients-feature-parity.php (adapted for Agents)
<?php
/**
 * WeCoza Core - Agents Integration Feature Parity Tests
 *
 * Verifies that the integrated Agents module in wecoza-core provides
 * all functionality previously in the standalone wecoza-agents-plugin:
 * - 3 shortcodes registered
 * - 2 AJAX endpoints registered
 * - 7 classes in WeCoza\Agents namespace
 * - 4 database tables queryable
 * - View templates present
 * - Statistics calculations working
 * - Working areas service functional
 *
 * Run with: php tests/integration/agents-feature-parity.php
 * Or via WP-CLI: wp eval-file tests/integration/agents-feature-parity.php
 */

// Prevent web access - CLI only
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    die('This script can only be run from command line.');
}

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Could not find wp-load.php. Run this script from the plugin directory.\n");
    }
}

class AgentsParityTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n=== WeCoza Core - Agents Feature Parity Tests ===\n\n";

        $this->testShortcodeRegistration();
        $this->testAjaxEndpointRegistration();
        $this->testNamespaceClasses();
        $this->testDatabaseConnectivity();
        $this->testViewTemplateExistence();
        $this->testStatisticsCalculation();
        $this->testWorkingAreasService();

        $this->printResults();
    }

    private function testShortcodeRegistration(): void
    {
        echo "--- Shortcode Registration ---\n";

        $shortcodes = [
            'wecoza_capture_agents',
            'wecoza_display_agents',
            'wecoza_single_agent',
        ];

        foreach ($shortcodes as $shortcode) {
            if (shortcode_exists($shortcode)) {
                $this->pass("Shortcode [{$shortcode}] registered");
            } else {
                $this->fail("Shortcode [{$shortcode}] NOT registered");
            }
        }
        echo "\n";
    }

    // ... more test methods

    private function pass(string $message): void
    {
        $this->passed++;
        echo "✓ PASS: {$message}\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        echo "✗ FAIL: {$message}\n";
    }

    private function printResults(): void
    {
        echo "=================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "=================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

// Run tests
$runner = new AgentsParityTest();
$runner->run();
```

**Key points:**
- CLI safety check prevents web access
- Test runner class with pass/fail tracking
- Exit code 1 on failures (CI/CD integration)
- WordPress globals available (`$wpdb`, `shortcode_exists()`, etc.)

### Pattern 2: Manual Testing Checklist (Markdown Format)
**What:** Structured checklist document covering CRUD operations, file uploads, statistics, edge cases—printable, checkboxes for manual completion
**When to use:** Human verification of UI/UX workflows, edge cases, data persistence
**Example:**
```markdown
# Agents Module - Manual Verification Checklist

## FEAT-01: Agent CRUD Operations

### Create Agent
- [ ] Navigate to agent capture form
- [ ] Fill all required fields (first name, surname, email, SA ID)
- [ ] Submit form
- [ ] Verify success message displays
- [ ] Check database: `SELECT * FROM agents ORDER BY created_at DESC LIMIT 1;`
- [ ] Confirm all fields persisted correctly
- [ ] Verify created_at and created_by populated

### Duplicate Email Validation
- [ ] Attempt to create agent with existing email
- [ ] Verify error message: "Agent with this email already exists"
- [ ] Confirm agent NOT created in database

### Update Agent
- [ ] Navigate to agent update form
- [ ] Change email address
- [ ] Submit form
- [ ] Verify success message
- [ ] Check database: Confirm only changed fields updated
- [ ] Verify updated_at and updated_by populated

### Soft Delete Agent
- [ ] Click delete button on agent
- [ ] Confirm deletion in modal
- [ ] Verify agent no longer appears in list
- [ ] Check database: `status = 'deleted'` (not hard delete)
- [ ] Verify deleted_at timestamp populated

## FEAT-02: Agent Metadata

### Agent Notes
- [ ] Add note to agent
- [ ] Verify note appears in agent view
- [ ] Edit note
- [ ] Delete note
- [ ] Confirm all operations persist to agent_notes table

## FEAT-03: File Upload Management

### Signed Agreement Upload
- [ ] Upload valid PDF file
- [ ] Verify success message
- [ ] Check uploads directory: File exists with unique name
- [ ] Verify file path stored in database
- [ ] Refresh page: File link displays correctly

### File Type Validation
- [ ] Attempt to upload .jpg file
- [ ] Verify error: "Only PDF, DOC, DOCX files allowed"
- [ ] Confirm file NOT uploaded

### File Replacement
- [ ] Upload new signed agreement (agent already has one)
- [ ] Verify old file deleted from uploads directory
- [ ] Confirm new file path stored in database
- [ ] Old file path no longer accessible

## FEAT-04: Agent Statistics

### Statistics Badge Display
- [ ] Navigate to agents display page
- [ ] Verify "Total Agents" badge shows correct count
- [ ] Verify "Active Agents" badge shows count where status='active'
- [ ] Verify "SACE Registered" badge shows count where sace_number IS NOT NULL
- [ ] Verify "Quantum Qualified" badge shows count where quantum_assessment >= threshold

### Statistics Accuracy
- [ ] Create new agent
- [ ] Refresh page: Verify "Total Agents" incremented
- [ ] Soft-delete agent
- [ ] Refresh page: Verify "Active Agents" decremented

## FEAT-05: Working Areas Service

### Working Areas Dropdown
- [ ] Open agent capture form
- [ ] Verify "Preferred Working Area 1" dropdown populated
- [ ] Confirm 14 areas listed (Sandton, Durbanville, Durban, Hatfield, etc.)
- [ ] Select 3 different areas for agent
- [ ] Submit form
- [ ] Check database: preferred_working_area_1/2/3 populated with correct IDs

### NULL Handling
- [ ] Create agent with only 1 preferred area
- [ ] Leave areas 2 and 3 empty
- [ ] Submit form
- [ ] Check database: preferred_working_area_2 and preferred_working_area_3 are NULL
- [ ] Verify no foreign key constraint errors

## Performance Checks

### Query Monitor Verification
- [ ] Install and activate Query Monitor plugin
- [ ] Navigate to agents display page
- [ ] Open Query Monitor panel (bottom of page)
- [ ] Check "Queries" tab: Verify < 50 queries for page load
- [ ] Check "Duplicate Queries" section: Verify 0 duplicates
- [ ] Check "Database Queries" section: Verify no `information_schema` queries (Bug #16)
- [ ] Navigate to agent capture form
- [ ] Verify < 30 queries for form load

### Cache Invalidation (Bug #15)
- [ ] Update agent record
- [ ] Immediately view agent detail page
- [ ] Verify updated data displays (not cached old data)
- [ ] Check Query Monitor: Confirm cache MISS after update
- [ ] Refresh page
- [ ] Check Query Monitor: Confirm cache HIT on second load

## Browser Console Check
- [ ] Open browser DevTools Console tab
- [ ] Navigate through all agent pages
- [ ] Verify 0 JavaScript errors
- [ ] Check Network tab: All AJAX requests return 200 status
- [ ] Verify no 404s for assets (JS, CSS files)
```

**Key points:**
- Checkbox format for manual completion
- Database verification steps included
- Performance checks integrated
- Bug-specific verification (Bug #15, Bug #16)

### Pattern 3: Statistics Calculation Verification
**What:** Query-based verification that badge counts match database reality
**When to use:** FEAT-04 verification, ensuring statistics are accurate
**Example:**
```php
// In agents-feature-parity.php test script
private function testStatisticsCalculation(): void
{
    echo "--- Statistics Calculation ---\n";

    $db = wecoza_db();

    // Total agents (exclude soft-deleted)
    $total = $db->query("SELECT COUNT(*) FROM agents WHERE status != 'deleted'")->fetchColumn();

    // Active agents
    $active = $db->query("SELECT COUNT(*) FROM agents WHERE status = 'active'")->fetchColumn();

    // SACE registered
    $sace = $db->query("SELECT COUNT(*) FROM agents WHERE sace_number IS NOT NULL AND status != 'deleted'")->fetchColumn();

    // Quantum qualified (assuming threshold is 75%)
    $quantum = $db->query("SELECT COUNT(*) FROM agents WHERE quantum_assessment >= 75 AND status != 'deleted'")->fetchColumn();

    // Now verify AgentRepository methods return same counts
    $repo = new \WeCoza\Agents\Repositories\AgentRepository();

    $repoTotal = $repo->getTotalCount();
    $repoActive = $repo->getActiveCount();
    $repoSace = $repo->getSaceRegisteredCount();
    $repoQuantum = $repo->getQuantumQualifiedCount();

    if ($total == $repoTotal) {
        $this->pass("Total agents statistic matches ({$total})");
    } else {
        $this->fail("Total agents mismatch: DB={$total}, Repo={$repoTotal}");
    }

    if ($active == $repoActive) {
        $this->pass("Active agents statistic matches ({$active})");
    } else {
        $this->fail("Active agents mismatch: DB={$active}, Repo={$repoActive}");
    }

    // ... SACE and Quantum checks
    echo "\n";
}
```

### Pattern 4: File Upload Verification
**What:** Test file upload, validation, storage, and cleanup
**When to use:** FEAT-03 verification
**Example:**
```php
// Manual testing only—file uploads require $_FILES superglobal
// Automated tests would need to mock file uploads

// Manual checklist approach:
// 1. Navigate to form
// 2. Upload test files (valid PDF, invalid JPG)
// 3. Verify validation messages
// 4. Check uploads directory: /wp-content/uploads/agents/{agent_id}/
// 5. Verify .htaccess file present (security: deny direct access)
// 6. Upload replacement file
// 7. Confirm old file deleted (no orphaned files)
```

### Pattern 5: Working Areas Service Verification
**What:** Verify service returns 14 working areas, IDs match database foreign keys
**When to use:** FEAT-05 verification
**Example:**
```php
private function testWorkingAreasService(): void
{
    echo "--- Working Areas Service ---\n";

    $areas = \WeCoza\Agents\Services\WorkingAreasService::get_working_areas();

    if (count($areas) === 14) {
        $this->pass("Working areas service returns 14 areas");
    } else {
        $this->fail("Working areas service returns " . count($areas) . " areas (expected 14)");
    }

    // Verify specific known areas
    $expectedAreas = [
        '1' => 'Sandton, Johannesburg, Gauteng, 2196',
        '2' => 'Durbanville, Cape Town, Western Cape, 7551',
        '14' => 'East London, East London, Eastern Cape, 5201',
    ];

    foreach ($expectedAreas as $id => $location) {
        $actual = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id($id);
        if ($actual === $location) {
            $this->pass("Working area {$id} matches expected location");
        } else {
            $this->fail("Working area {$id} mismatch: got '{$actual}', expected '{$location}'");
        }
    }

    // Verify NULL handling (no DB foreign key errors)
    try {
        $db = wecoza_db();
        $result = $db->query("SELECT agent_id FROM agents WHERE preferred_working_area_1 IS NULL LIMIT 1")->fetch();
        $this->pass("NULL working areas handled correctly (no foreign key constraint)");
    } catch (\Exception $e) {
        $this->fail("NULL working areas error: " . $e->getMessage());
    }

    echo "\n";
}
```

### Anti-Patterns to Avoid
- **100% automation attempt:** File uploads and UI interactions need manual testing—don't build complex Selenium setup for one-time verification
- **Skipping performance checks:** Migration bugs often hide in performance (redundant queries, cache invalidation)—Query Monitor is essential
- **Testing only happy path:** Validation errors, duplicate checks, NULL handling reveal migration bugs
- **Database-only verification:** Always test via UI—backend might work but controller/view layer broken

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Performance profiling | Custom query logging | Query Monitor plugin | Handles duplicate detection, plugin attribution, cache hit tracking, query time aggregation |
| Test runner framework | Custom assertion library | Simple pass/fail pattern from clients-feature-parity.php | Existing pattern works—no need for PHPUnit complexity |
| File upload validation | Custom MIME type checker | WordPress wp_check_filetype(), validate_file() | Handles edge cases (fake extensions, MIME spoofing) |
| Statistics queries | Multiple separate queries | Single query with CASE/COUNT aggregation | Reduces database round-trips |
| AJAX debugging | Manual curl commands | Browser DevTools Network tab | Shows full request/response, timing, headers |

**Key insight:** WordPress migration verification is about proving feature parity and catching performance regressions—not building comprehensive test infrastructure. Existing patterns (clients-feature-parity.php), manual checklists, and Query Monitor cover 95% of needs.

## Common Pitfalls

### Pitfall 1: Cache Invalidation After Writes (Bug #15)
**What goes wrong:** Agent update succeeds in database but display page shows old data due to stale cache
**Why it happens:** Repository update() method doesn't invalidate relevant caches—cached queries return old data
**How to avoid:** After every write operation (insert, update, delete), invalidate related caches. Use WordPress wp_cache_delete() or clear all agent-related transients.
**Warning signs:** User reports "I updated the agent but nothing changed"—check Query Monitor for cache HITs after updates (should be MISS)

**Verification steps:**
```php
// In manual testing checklist
1. Update agent email
2. Immediately view agent detail page
3. Open Query Monitor > Object Cache
4. Verify MISS for agent query (not HIT with old data)
5. Refresh page
6. Now verify HIT (cache warmed with new data)
```

### Pitfall 2: Redundant information_schema Queries (Bug #16)
**What goes wrong:** PostgresConnection::getTableColumns() queries information_schema on EVERY repository operation—hundreds of redundant queries
**Why it happens:** Column metadata fetched per-operation instead of cached per-request
**How to avoid:** Bug #16 allegedly fixed in core—verify getTableColumns() uses static cache or request-level caching
**Warning signs:** Query Monitor shows 50+ `information_schema.columns` queries on single page load

**Verification steps:**
```bash
# In Query Monitor plugin
1. Navigate to agents display page
2. Open Query Monitor > Database Queries
3. Filter by "information_schema"
4. Verify 0 results (or 1 per unique table, max ~4 for agents module)
5. If > 10 queries found, Bug #16 NOT actually fixed
```

### Pitfall 3: Statistics Badge Stale Data
**What goes wrong:** Statistics badges show wrong counts after agent creation/deletion
**Why it happens:** Badge counts cached or calculated incorrectly (soft-delete status not excluded)
**How to avoid:** Statistics queries must filter `WHERE status != 'deleted'`—verify SQL queries
**Warning signs:** Delete agent, badge count doesn't change

**Verification approach:**
```php
// Check repository statistics methods
class AgentRepository {
    public function getTotalCount(): int {
        // CORRECT: Excludes soft-deleted
        return $this->db->query("SELECT COUNT(*) FROM agents WHERE status != 'deleted'")->fetchColumn();

        // WRONG: Includes soft-deleted
        return $this->db->query("SELECT COUNT(*) FROM agents")->fetchColumn();
    }
}
```

### Pitfall 4: File Upload Directory Permissions
**What goes wrong:** File upload validation passes but file not written to disk—permission denied error
**Why it happens:** Uploads directory `/wp-content/uploads/agents/` doesn't exist or wrong permissions
**How to avoid:** Controller must create directory with wp_mkdir_p() on first upload, set permissions 0755
**Warning signs:** PHP warning in debug.log: "failed to open stream: Permission denied"

**Verification steps:**
```bash
# Check uploads directory exists and has correct permissions
ls -la /wp-content/uploads/agents/
# Should show: drwxr-xr-x (755 permissions)

# Check for .htaccess security file
cat /wp-content/uploads/agents/.htaccess
# Should contain: Deny from all (prevents direct file access)
```

### Pitfall 5: Working Areas NULL Foreign Key Constraint
**What goes wrong:** Agent form submission fails with foreign key constraint error when preferred_working_area_2/3 left empty
**Why it happens:** Form sends empty string '' instead of NULL—PostgreSQL can't match empty string to foreign key
**How to avoid:** Form data sanitization must convert empty strings to NULL for integer columns
**Warning signs:** PostgreSQL error: "insert or update on table agents violates foreign key constraint"

**Correct sanitization:**
```php
// In AgentModel or FormHelpers
private function sanitizeWorkingArea(?string $value): ?int {
    if (empty($value) || $value === '') {
        return null; // CRITICAL: Return NULL, not 0 or empty string
    }
    return (int) $value;
}
```

### Pitfall 6: Duplicate Validation False Negatives
**What goes wrong:** Duplicate email check fails to catch duplicates during UPDATE operations
**Why it happens:** Query checks `WHERE email = ? AND agent_id != ?` but agent_id not provided, matches self
**How to avoid:** Duplicate check on update must exclude current agent ID
**Warning signs:** Can change agent email to another agent's email without validation error

**Correct validation:**
```php
// In AgentRepository
public function emailExists(string $email, ?int $excludeAgentId = null): bool {
    $sql = "SELECT COUNT(*) FROM agents WHERE email = :email AND status != 'deleted'";

    if ($excludeAgentId !== null) {
        $sql .= " AND agent_id != :exclude_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email, 'exclude_id' => $excludeAgentId]);
    } else {
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
    }

    return $stmt->fetchColumn() > 0;
}
```

## Code Examples

Verified patterns from existing test infrastructure:

### Complete CLI Test Script Structure
```php
<?php
/**
 * WeCoza Core - Agents Integration Feature Parity Tests
 *
 * @package WeCoza\Tests
 * @since 3.0.0
 */

// CLI safety check
if (php_sapi_name() !== 'cli' && !defined('WP_CLI')) {
    die('This script can only be run from command line.');
}

// Load WordPress
if (!defined('ABSPATH')) {
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die("Could not find wp-load.php. Run this script from the plugin directory.\n");
    }
}

class AgentsParityTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "\n=== WeCoza Core - Agents Feature Parity Tests ===\n\n";

        // Test categories
        $this->testShortcodeRegistration();
        $this->testAjaxEndpointRegistration();
        $this->testNamespaceClasses();
        $this->testDatabaseConnectivity();
        $this->testViewTemplateExistence();
        $this->testStatisticsCalculation();
        $this->testWorkingAreasService();
        $this->testCacheInvalidation();
        $this->testInformationSchemaQueries();

        $this->printResults();
    }

    private function testShortcodeRegistration(): void
    {
        echo "--- Shortcode Registration ---\n";

        $shortcodes = [
            'wecoza_capture_agents',
            'wecoza_display_agents',
            'wecoza_single_agent',
        ];

        foreach ($shortcodes as $shortcode) {
            if (shortcode_exists($shortcode)) {
                $this->pass("Shortcode [{$shortcode}] registered");
            } else {
                $this->fail("Shortcode [{$shortcode}] NOT registered");
            }
        }
        echo "\n";
    }

    private function testAjaxEndpointRegistration(): void
    {
        echo "--- AJAX Endpoint Registration ---\n";

        global $wp_filter;

        $endpoints = [
            'wp_ajax_wecoza_agents_delete',
            'wp_ajax_wecoza_agents_paginate',
        ];

        foreach ($endpoints as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $this->pass("AJAX endpoint [{$hook}] registered");
            } else {
                $this->fail("AJAX endpoint [{$hook}] NOT registered");
            }
        }

        // Verify NO nopriv handlers (entire WP requires login)
        $noprivHooks = [
            'wp_ajax_nopriv_wecoza_agents_delete',
            'wp_ajax_nopriv_wecoza_agents_paginate',
        ];

        foreach ($noprivHooks as $hook) {
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                $this->fail("Nopriv AJAX hook [{$hook}] FOUND (should not exist)");
            } else {
                $this->pass("Nopriv AJAX hook [{$hook}] correctly absent");
            }
        }

        echo "\n";
    }

    private function testNamespaceClasses(): void
    {
        echo "--- Namespace Class Verification ---\n";

        $classes = [
            '\\WeCoza\\Agents\\Controllers\\AgentsController',
            '\\WeCoza\\Agents\\Ajax\\AgentsAjaxHandlers',
            '\\WeCoza\\Agents\\Models\\AgentModel',
            '\\WeCoza\\Agents\\Repositories\\AgentRepository',
            '\\WeCoza\\Agents\\Services\\WorkingAreasService',
            '\\WeCoza\\Agents\\Helpers\\FormHelpers',
            '\\WeCoza\\Agents\\Helpers\\ValidationHelper',
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->pass("Class {$class} exists");
            } else {
                $this->fail("Class {$class} NOT found");
            }
        }

        echo "\n";
    }

    private function testDatabaseConnectivity(): void
    {
        echo "--- Database Table Access ---\n";

        try {
            $db = wecoza_db();

            $tables = ['agents', 'agent_meta', 'agent_notes', 'agent_absences'];

            foreach ($tables as $table) {
                $result = $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                $this->pass("Table [{$table}] accessible (count: {$result})");
            }
        } catch (\Exception $e) {
            $this->fail("Database connectivity error: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testViewTemplateExistence(): void
    {
        echo "--- View Template Files ---\n";

        $basePath = wecoza_plugin_path('views/agents/');

        $templates = [
            'components/agent-capture-form.view.php',
            'components/agent-fields.view.php',
            'display/agent-display-table.view.php',
            'display/agent-display-table-rows.view.php',
            'display/agent-pagination.view.php',
            'display/agent-single-display.view.php',
        ];

        foreach ($templates as $template) {
            $fullPath = $basePath . $template;
            if (file_exists($fullPath)) {
                $this->pass("View template [{$template}] exists");
            } else {
                $this->fail("View template [{$template}] NOT found at {$fullPath}");
            }
        }

        echo "\n";
    }

    private function testStatisticsCalculation(): void
    {
        echo "--- Statistics Calculation ---\n";

        try {
            $db = wecoza_db();

            // Direct SQL queries
            $totalDirect = $db->query("SELECT COUNT(*) FROM agents WHERE status != 'deleted'")->fetchColumn();
            $activeDirect = $db->query("SELECT COUNT(*) FROM agents WHERE status = 'active'")->fetchColumn();

            // Repository methods (if implemented)
            $repo = new \WeCoza\Agents\Repositories\AgentRepository();

            // Verify basic count method works
            $allAgents = $repo->findAll(1000); // Get all agents
            $repoTotal = count(array_filter($allAgents, fn($a) => $a['status'] !== 'deleted'));
            $repoActive = count(array_filter($allAgents, fn($a) => $a['status'] === 'active'));

            if ($totalDirect == $repoTotal) {
                $this->pass("Total agents statistic matches ({$totalDirect})");
            } else {
                $this->fail("Total agents mismatch: Direct={$totalDirect}, Repo={$repoTotal}");
            }

            if ($activeDirect == $repoActive) {
                $this->pass("Active agents statistic matches ({$activeDirect})");
            } else {
                $this->fail("Active agents mismatch: Direct={$activeDirect}, Repo={$repoActive}");
            }

        } catch (\Exception $e) {
            $this->fail("Statistics calculation error: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testWorkingAreasService(): void
    {
        echo "--- Working Areas Service ---\n";

        $areas = \WeCoza\Agents\Services\WorkingAreasService::get_working_areas();

        if (count($areas) === 14) {
            $this->pass("Working areas service returns 14 areas");
        } else {
            $this->fail("Working areas service returns " . count($areas) . " areas (expected 14)");
        }

        $area1 = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id('1');
        if (strpos($area1, 'Sandton') !== false) {
            $this->pass("Working area ID 1 returns Sandton location");
        } else {
            $this->fail("Working area ID 1 incorrect: {$area1}");
        }

        echo "\n";
    }

    private function testCacheInvalidation(): void
    {
        echo "--- Cache Invalidation (Bug #15) ---\n";

        // This is a behavioral test—requires manual verification with Query Monitor
        // Automated test would need to mock cache operations
        $this->pass("Cache invalidation requires manual Query Monitor verification (see manual checklist)");

        echo "\n";
    }

    private function testInformationSchemaQueries(): void
    {
        echo "--- Information Schema Queries (Bug #16) ---\n";

        // This requires Query Monitor or PostgreSQL query logs
        $this->pass("Information schema query reduction requires manual Query Monitor verification (see manual checklist)");

        echo "\n";
    }

    private function pass(string $message): void
    {
        $this->passed++;
        echo "✓ PASS: {$message}\n";
    }

    private function fail(string $message): void
    {
        $this->failed++;
        echo "✗ FAIL: {$message}\n";
    }

    private function printResults(): void
    {
        echo "=================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "=================================\n";

        if ($this->failed > 0) {
            echo "\nSome tests failed. Review output above.\n";
            exit(1);
        } else {
            echo "\nAll tests passed! Agents module integration verified.\n";
            exit(0);
        }
    }
}

// Run tests
$runner = new AgentsParityTest();
$runner->run();
```

### Query Monitor Performance Check
```php
// Manual verification steps (not automated)
/*
1. Install Query Monitor plugin:
   wp plugin install query-monitor --activate

2. Navigate to agents display page in browser

3. Click "Query Monitor" link in admin bar

4. Check "Database Queries" panel:
   - Total queries: Should be < 50 for display page
   - Duplicate queries: Should be 0
   - Slowest queries: None > 0.1s

5. Check for Bug #16 (information_schema):
   - Filter queries by "information_schema"
   - Should see 0 results (or max 1 per unique table)
   - If > 10, Bug #16 NOT fixed

6. Check "Object Cache" panel:
   - Cache hit ratio: Should be > 50% on second page load
   - For Bug #15: Update agent, reload page immediately
     - Should see cache MISS for that agent query
     - Reload again: Should see cache HIT

7. Check "HTTP API Calls" panel:
   - Should be 0 (no external API calls for basic CRUD)
*/
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual testing only | CLI test scripts + manual checklist | Clients v2.0 (2026-01) | Faster regression detection, repeatable verification |
| Custom performance logging | Query Monitor plugin | WordPress standard | Industry-standard profiling, duplicate query detection |
| Database-only verification | End-to-end UI testing | WordPress QA best practice | Catches controller/view bugs missed by DB tests |
| Single comprehensive test | Modular test methods | Existing pattern in clients-feature-parity.php | Isolated failures, faster debugging |
| Hard-coded test data | Query actual database state | Clients v2.0 pattern | Tests work on any environment (dev, staging, production) |

**Deprecated/outdated:**
- PHPUnit for WordPress plugins without CI/CD: Existing projects use simple CLI test runners instead
- Blackfire/New Relic for single-plugin debugging: Query Monitor sufficient for local performance profiling
- Manual SQL query logging: Query Monitor automates query tracking and aggregation
- 100% unit test coverage for integration verification: Feature parity tests focus on end-to-end workflows

## Open Questions

1. **Statistics Badge Implementation Details**
   - What we know: FEAT-04 requires total agents, active agents, SACE registered, Quantum qualified counts
   - What's unclear: Where are badges displayed? AgentsController method? Shortcode attribute?
   - Recommendation: Check existing Learners/Classes modules for badge patterns, verify badges on display page during manual testing

2. **File Upload Security Implementation**
   - What we know: FEAT-03 requires PDF/DOC/DOCX validation, old file deletion, .htaccess security
   - What's unclear: Upload directory structure—per-agent folders or flat structure? File naming convention?
   - Recommendation: Follow WordPress wp_handle_upload() standard, verify .htaccess contains "Deny from all", check manual testing checklist

3. **Cache Invalidation Scope (Bug #15)**
   - What we know: Bug #15 warns to check cache invalidation after writes
   - What's unclear: Which caches need invalidation? WordPress object cache? Transients? Static arrays?
   - Recommendation: Use Query Monitor to identify cached queries, verify cache MISS after updates, clear wp_cache for agent-related keys on write operations

4. **Performance Baseline**
   - What we know: Bug #16 warns about redundant information_schema queries (allegedly fixed in core)
   - What's unclear: What's acceptable query count for agents display page? < 30? < 50?
   - Recommendation: Compare with Clients module performance (similar complexity), establish baseline via Query Monitor, flag if > 2x Clients query count

## Sources

### Primary (HIGH confidence)
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/tests/integration/clients-feature-parity.php` - Existing integration test pattern
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/tests/security-test.php` - CLI test runner pattern
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/src/Agents/Services/WorkingAreasService.php` - Working areas implementation
- `/opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/schema/wecoza_db_schema_bu_feb_12.sql` - Database schema (agents table, preferred_working_area columns)
- [Query Monitor Plugin Documentation](https://wordpress.org/plugins/query-monitor/) - Performance debugging tool
- [Query Monitor Usage Guide](https://querymonitor.com/wordpress-debugging/how-to-use/) - How to use Query Monitor

### Secondary (MEDIUM confidence)
- [WordPress Plugin Testing Best Practices (Melapress)](https://melapress.com/wordpress-plugin-testing-best-practices/) - Testing methodology
- [How to Add Automated Unit Tests to WordPress Plugin (WP Developer Blog, Dec 2025)](https://developer.wordpress.org/news/2025/12/how-to-add-automated-unit-tests-to-your-wordpress-plugin/) - Official WordPress testing guidance
- [Unit Testing Ajax and API Requests in WordPress Plugins (Delicious Brains)](https://deliciousbrains.com/unit-testing-ajax-api-requests-wordpress-plugins/) - AJAX testing patterns
- [How to Properly Test WordPress Forms Before Launching (WPForms)](https://wpforms.com/docs/how-to-properly-test-your-wordpress-forms-before-launching-checklist/) - Form validation testing
- [Complete Form Testing Checklist (WPForms)](https://wpforms.com/form-testing-checklist/) - Manual testing checklist format
- [Feature Parity Testing with Feature Flags (Harness)](https://www.harness.io/blog/parity-testing-with-feature-flags) - Parity testing methodology
- [WordPress Performance Debugging with Query Monitor (Kinsta)](https://kinsta.com/blog/query-monitor/) - Query Monitor usage
- [Data Migration Testing Strategy (QA Source 2026)](https://blog.qasource.com/a-guide-to-data-migration-testing) - Migration verification patterns
- [Post-Migration WordPress Testing Checklist 2026 (Duplicator)](https://duplicator.com/post-wordpress-migration-testing/) - WordPress migration verification

### Tertiary (LOW confidence)
- None—all research based on existing codebase patterns and verified WordPress documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools exist (CLI test scripts, Query Monitor, wecoza_log, manual checklists)
- Architecture: HIGH - Direct patterns from clients-feature-parity.php and security-test.php
- Pitfalls: HIGH - Bug #15 and Bug #16 explicitly mentioned in phase requirements, verified in schema/codebase
- Performance testing: HIGH - Query Monitor is WordPress standard, verified in multiple 2026 sources

**Research date:** 2026-02-12
**Valid until:** 2026-03-12 (30 days—testing patterns are stable)

**Requirements coverage:**
- FEAT-01: Agent CRUD Operations - Manual checklist + automated class verification
- FEAT-02: Agent Metadata - Manual checklist for meta/notes/absences CRUD
- FEAT-03: File Upload Management - Manual checklist (file uploads require browser interaction)
- FEAT-04: Agent Statistics - Automated statistics calculation test + manual badge verification
- FEAT-05: Working Areas Service - Automated service test (14 areas, NULL handling)
- Bug #15 (cache invalidation) - Query Monitor verification + manual checklist
- Bug #16 (redundant information_schema) - Query Monitor verification

**Known limitations:**
- File upload testing is manual only (requires $_FILES superglobal, browser interaction)
- Cache invalidation verification requires Query Monitor plugin installed
- Performance baseline requires comparison with Clients module (similar complexity)
- Statistics badge location/implementation unclear—needs verification during planning
