---
phase: 25
plan: 02
subsystem: integration-cleanup
tags: [cleanup, repository-hygiene, tech-debt]
dependency_graph:
  requires: ["25-01"]
  provides: ["clean-repository", "single-source-of-truth"]
  affects: [".integrate/", "repository-structure"]
tech_stack:
  added: []
  patterns: ["filesystem-cleanup", "verification-before-deletion"]
key_files:
  removed: [".integrate/done/wecoza-clients-plugin/", ".integrate/done/"]
decisions: []
metrics:
  duration_seconds: 120
  completed_date: "2026-02-12"
---

# Phase 25 Plan 02: Remove Archived Standalone Plugin

**One-liner:** Removed `.integrate/done/wecoza-clients-plugin/` directory after verifying no dangling references exist.

## Objective

Remove archived standalone wecoza-clients-plugin from `.integrate/done/` directory and verify no dangling references exist in the codebase. Satisfies CLN-02 requirement - establishes single source of truth in `src/Clients/`.

## Context

After successful integration of the Clients module into wecoza-core (Phases 21-24), the standalone plugin archive in `.integrate/done/wecoza-clients-plugin/` was no longer needed. Plan 25-01 created feature parity tests and verified the integrated module works identically to the standalone version.

## Tasks Completed

### Task 1: Verify no dangling references and remove archived plugin

**Status:** ✅ Complete

**Pre-removal verification scans (all passed):**

1. **WeCozaClients namespace search:**
   - Found only in `assets/js/clients/location-capture.js`
   - This is a JavaScript namespace pattern used by the integrated code (`window.WeCozaClients = {}`)
   - NOT a reference to the old standalone plugin - safe to proceed

2. **WECOZA_CLIENTS_ constants search:**
   - No matches found in active code ✓

3. **.integrate/ path references search:**
   - No PHP require/include statements found ✓

4. **wecoza-clients-plugin string search:**
   - No references found in active code ✓

5. **Agents plugin preservation check:**
   - `.integrate/wecoza-agents-plugin/` confirmed still exists ✓

**Removal executed:**
- Removed `.integrate/done/wecoza-clients-plugin/` directory
- Removed empty `.integrate/done/` parent directory

**Post-removal smoke test:**
- Feature parity test: 44/44 checks passed ✅
- All shortcodes, AJAX endpoints, classes, database connectivity, and views verified functional

**Files affected:**
- `.integrate/done/wecoza-clients-plugin/` (removed from filesystem)
- `.integrate/done/` (removed - was empty after plugin removal)

**Commit:**
- No git commit needed - `.integrate/` directory is in `.gitignore` (never tracked by git)

### Task 2: Update .gitignore and verify final repository state

**Status:** ✅ Complete

**Repository cleanliness verification:**

1. **Git status check:**
   - `.integrate/` not tracked (confirmed in `.gitignore` line 21)
   - No unexpected changes introduced
   - `.integrate/wecoza-agents-plugin/` unaffected

2. **Final integration checklist (all verified):**
   - ✅ `src/Clients/` directory exists with all module files (Ajax, Controllers, Helpers, Models, Repositories)
   - ✅ `views/clients/` directory exists with all templates (components, display)
   - ✅ `wecoza-core.php` references `WeCoza\Clients\` namespace (lines 233-240)
   - ✅ Feature parity test passes (44/44 checks)
   - ✅ `.integrate/done/wecoza-clients-plugin/` removed
   - ✅ No dangling references in codebase

**Files affected:**
- `.gitignore` - already correct (`.integrate/` listed), no changes needed

**Commit:**
- No changes to commit - `.gitignore` already optimal

## Deviations from Plan

None - plan executed exactly as written.

## Key Outcomes

### Repository Hygiene

1. **Single source of truth established:**
   - Clients module code lives only in `src/Clients/` and `views/clients/`
   - No duplicate/archived code in repository

2. **Clean .integrate/ structure:**
   - `.integrate/wecoza-agents-plugin/` preserved for future integration
   - No stale "done" directories cluttering workspace

3. **Git tracking optimized:**
   - `.integrate/` properly ignored (never tracked)
   - No unnecessary git history for temporary integration artifacts

### Verification Coverage

All CLN-02 requirements satisfied:
- ✅ `.integrate/wecoza-clients-plugin/` folder removed from repository
- ✅ No code references `.integrate/` paths
- ✅ No code references `WeCozaClients` namespace (except JavaScript namespace pattern in integrated code)
- ✅ No code references `WECOZA_CLIENTS_` constants
- ✅ Integrated Clients module still functions after cleanup (44/44 tests pass)

## Technical Notes

### JavaScript Namespace Pattern (Not a Problem)

The string `WeCozaClients` appears in `assets/js/clients/location-capture.js`:

```javascript
if (typeof window.WeCozaClients === 'undefined') {
    window.WeCozaClients = {};
}
```

This is a **JavaScript namespace pattern** used to avoid conflicts with other plugins. It's part of the integrated code, NOT a reference to the old standalone plugin. This is standard practice and should be preserved.

### Git Ignore Strategy

`.integrate/` is in `.gitignore` (line 21), meaning:
- Developers can test standalone plugins locally before integration
- Integration artifacts never pollute git history
- No need to track or commit integration workspace changes

This is optimal for the integration workflow.

## Testing Evidence

**Feature Parity Test Results (Post-Removal):**

```
=== WeCoza Core - Clients Feature Parity Tests ===
Results: 44 passed, 0 failed
```

All categories verified:
- Shortcode registration (6 shortcodes)
- AJAX endpoint registration (16 endpoints)
- Namespace class verification (8 classes)
- Database connectivity (3 tables)
- View template existence (8 templates/directories)
- No standalone plugin dependency (2 checks)

## Next Steps

Phase 25 complete. All cleanup objectives achieved:
- ✅ Feature parity test script created (25-01)
- ✅ Archived standalone plugin removed (25-02)
- ✅ Single source of truth established
- ✅ Repository hygiene restored

**Phase 26 candidate:** Agents Module Integration (`.integrate/wecoza-agents-plugin/` ready for same treatment)

## Self-Check: PASSED

**Created files exist:**
- ✅ `.planning/phases/25-integration-testing-cleanup/25-02-SUMMARY.md` (this file)

**Removed directories verified:**
- ✅ `.integrate/done/wecoza-clients-plugin/` - confirmed removed (ls returns "No such file or directory")
- ✅ `.integrate/done/` - confirmed removed (parent directory cleaned up)

**Preserved directories verified:**
- ✅ `.integrate/wecoza-agents-plugin/` - confirmed still exists

**Integration still functional:**
- ✅ Feature parity test passes - all 44 checks verified post-removal

All claims in summary verified against actual system state.
