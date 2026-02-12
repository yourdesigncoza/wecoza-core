# Roadmap: WeCoza Core

## Milestones

- ✅ **v1.0 Events Integration** — Phases 1-7 (shipped 2026-02-02)
- ✅ **v1.1 Quality & Performance** — Phases 8-12 (shipped 2026-02-02)
- ✅ **v1.2 Event Tasks Refactor** — Phases 13-18 (shipped 2026-02-05)
- ✅ **v1.3 Fix Material Tracking Dashboard** — Phases 19-20 (shipped 2026-02-06)
- ✅ **v2.0 Clients Integration** — Phases 21-25 (shipped 2026-02-12)
- 🔄 **v3.0 Agents Integration** — Phases 26-30

## Phases

<details>
<summary>✅ v1.0 Events Integration (Phases 1-7) — SHIPPED 2026-02-02</summary>

- [x] Phase 1: Foundation Architecture (2/2 plans) — completed 2026-02-02
- [x] Phase 2: Task Management Core (3/3 plans) — completed 2026-02-02
- [x] Phase 3: Material Tracking (2/2 plans) — completed 2026-02-02
- [x] Phase 4: AI Summarization (2/2 plans) — completed 2026-02-02
- [x] Phase 5: Email Notifications (2/2 plans) — completed 2026-02-02
- [x] Phase 6: PostgreSQL Triggers (1/1 plan) — completed 2026-02-02
- [x] Phase 7: Testing & Verification (1/1 plan) — completed 2026-02-02

</details>

<details>
<summary>✅ v1.1 Quality & Performance (Phases 8-12) — SHIPPED 2026-02-02</summary>

- [x] Phase 8: Bug Fixes (2/2 plans) — completed 2026-02-02
- [x] Phase 9: Security Hardening (2/2 plans) — completed 2026-02-02
- [x] Phase 10: Performance Optimization (3/3 plans) — completed 2026-02-02
- [x] Phase 11: Data Privacy (3/3 plans) — completed 2026-02-02
- [x] Phase 12: Architecture Improvements (3/3 plans) — completed 2026-02-02

</details>

<details>
<summary>✅ v1.2 Event Tasks Refactor (Phases 13-18) — SHIPPED 2026-02-05</summary>

- [x] Phase 13: Event System Foundation (3/3 plans) — completed 2026-02-05
- [x] Phase 14: Task Derivation (3/3 plans) — completed 2026-02-05
- [x] Phase 15: Bidirectional Sync (3/3 plans) — completed 2026-02-05
- [x] Phase 16: Notification System (3/3 plans) — completed 2026-02-05
- [x] Phase 17: Code Cleanup (2/2 plans) — completed 2026-02-05
- [x] Phase 18: Multi-Recipient Config (2/2 plans) — completed 2026-02-05

</details>

<details>
<summary>✅ v1.3 Fix Material Tracking Dashboard (Phases 19-20) — SHIPPED 2026-02-06</summary>

- [x] Phase 19: Material Dashboard Rewrite (2/2 plans) — completed 2026-02-06
- [x] Phase 20: Dashboard Enhancements (1/1 plan) — completed 2026-02-06

</details>

<details>
<summary>✅ v2.0 Clients Integration (Phases 21-25) — SHIPPED 2026-02-12</summary>

- [x] Phase 21: Foundation Architecture (2/2 plans) — completed 2026-02-11
- [x] Phase 22: Client Management (2/2 plans) — completed 2026-02-11
- [x] Phase 23: Location Management (2/2 plans) — completed 2026-02-12
- [x] Phase 24: Sites Hierarchy (2/2 plans) — completed 2026-02-12
- [x] Phase 25: Integration Testing & Cleanup (2/2 plans) — completed 2026-02-12

</details>

### v3.0 Agents Integration (Phases 26-30)

**Source:** `.integrate/wecoza-agents-plugin/`
**Target:** wecoza-core `src/Agents/`
**Scope:** 13 classes, 6 templates, 5 JS files, 2 AJAX endpoints, 3 shortcodes, 4 DB tables (already exist)

- [ ] Phase 26: Foundation Architecture — namespace, DB migration, model, repository, helpers — **Plans:** 2 plans
  - [ ] 26-01-PLAN.md — Namespace registration, PostgresConnection fix, helpers migration
  - [ ] 26-02-PLAN.md — AgentRepository + AgentModel creation
- [ ] Phase 27: Controllers, Views, JS, AJAX — controller, AJAX handlers, views, JS, wiring
- [ ] Phase 28: Wiring Verification & Fixes — shortcode rendering, integration bugs
- [ ] Phase 29: Feature Verification & Performance — CRUD testing, file uploads, statistics
- [ ] Phase 30: Integration Testing & Cleanup — parity test, plugin deactivation, source removal

#### Phase 26: Foundation Architecture

**Goal:** Namespace registration, database migration (DatabaseService → wecoza_db()), model migration, repository creation with column whitelisting, helper migration.

**Requirements:** ARCH-01, ARCH-03, ARCH-04, ARCH-05

**Success Criteria:**
- [ ] `WeCoza\Agents\` namespace registered in wecoza-core.php autoloader
- [ ] Zero `DatabaseService` references in any migrated PHP file
- [ ] Zero `WECOZA_AGENTS_*` constant references in any migrated PHP file
- [ ] All models use `wecoza_db()` exclusively
- [ ] AgentRepository extends BaseRepository with column whitelisting
- [ ] All PHP files pass `php -l` syntax check
- [ ] Helpers migrated: ValidationHelper, FormHelpers, WorkingAreasService

**Migration Context:**

*Files to create:*
```
src/Agents/
  Models/AgentModel.php           (from src/Models/Agent.php — standalone, no BaseModel)
  Repositories/AgentRepository.php (from src/Database/AgentQueries.php — extend BaseRepository)
  Helpers/ValidationHelper.php    (from src/Helpers/ValidationHelper.php)
  Helpers/FormHelpers.php         (from src/Helpers/FormHelpers.php)
  Services/WorkingAreasService.php (from src/Services/WorkingAreasService.php)
```

*Transformation Rules:*
| Source | Target |
|--------|--------|
| `WeCoza\Agents\Database\` | `WeCoza\Agents\Repositories\` |
| `WeCoza\Agents\Shortcodes\` | `WeCoza\Agents\Controllers\` |
| `WeCoza\Agents\Includes\` | Not migrated (plugin infrastructure) |
| `DatabaseService::getInstance()` | `wecoza_db()` |
| `$this->db->query($sql, $params)->fetchAll()` | `wecoza_db()->getAll($sql, $params)` |
| `$this->db->query($sql, $params)->fetch()` | `wecoza_db()->getRow($sql, $params)` |
| `$this->db->insert($table, $data)` | `wecoza_db()->insert($table, $data)` |
| `$this->db->update($table, $data, ['agent_id' => $id])` | `wecoza_db()->update($table, $data, 'agent_id = :agent_id', [':agent_id' => $id])` |
| `$this->db->delete($table, ['agent_id' => $id])` | `wecoza_db()->delete($table, 'agent_id = :agent_id', [':agent_id' => $id])` |
| `wecoza_agents_log($msg, $level)` | `wecoza_log($msg, $level)` |
| `WECOZA_AGENTS_PLUGIN_DIR` | `wecoza_plugin_path()` |

*Bug Warnings:*

**Bug #1 — hydrate() on non-BaseModel classes**
- Agent model is standalone (good!) — do NOT extend BaseModel
- **Prevention:** Keep standalone. It has its own get/set/validate cycle.

**Bug #2 — PostgresConnection missing CRUD methods**
- **Status:** FIXED in wecoza-core.
- **CRITICAL:** wecoza_db()->update() and delete() have DIFFERENT signatures than source DatabaseService:
  - Source: `update($table, $data, $whereArray)` — WHERE is associative array
  - Target: `update($table, $data, $whereString, $whereParams)` — WHERE is SQL string + params
  - Source: `delete($table, $whereArray)` — WHERE is associative array
  - Target: `delete($table, $whereString, $params)` — WHERE is SQL string + params

**Bug #5 — Model method signatures**
- **Prevention:** Verify every repository method signature matches how controllers/AJAX call it.

**Bug #6 — Broken BaseModel extends**
- Agent model is standalone — do NOT change this.

*Verification Commands:*
```bash
grep -r "DatabaseService" src/Agents/ --include="*.php" | wc -l  # expect 0
grep -r "WECOZA_AGENTS_" src/Agents/ --include="*.php" | wc -l  # expect 0
grep -r "wecoza_agents_log" src/Agents/ --include="*.php" | wc -l  # expect 0
find src/Agents/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

---

#### Phase 27: Controllers, Views, JS, AJAX

**Goal:** Controller creation, AJAX handler extraction, view template migration, JS asset migration, wecoza-core.php wiring.

**Requirements:** ARCH-06, ARCH-07, ARCH-08, ARCH-09, ARCH-10, SC-01, SC-02, SC-03

**Success Criteria:**
- [ ] All 3 shortcodes registered via `add_shortcode()`
- [ ] All 2 AJAX endpoints registered via `wp_ajax_*` (NO nopriv)
- [ ] AgentsController extends BaseController with `registerHooks()`
- [ ] AgentsAjaxHandlers uses AjaxSecurity pattern
- [ ] All 6 views use `wecoza_view('agents/path', $data, true)` pattern
- [ ] Assets enqueued conditionally (only when shortcode present)
- [ ] Module initialized in wecoza-core.php
- [ ] Unified localization object `wecozaAgents` with camelCase keys

**Migration Context:**

*Files to create:*
```
src/Agents/Controllers/AgentsController.php
src/Agents/Ajax/AgentsAjaxHandlers.php
views/agents/components/agent-capture-form.view.php
views/agents/components/agent-fields.view.php
views/agents/display/agent-display-table.view.php
views/agents/display/agent-display-table-rows.view.php
views/agents/display/agent-pagination.view.php
views/agents/display/agent-single-display.view.php
assets/js/agents/agents-app.js
assets/js/agents/agent-form-validation.js
assets/js/agents/agents-ajax-pagination.js
assets/js/agents/agents-table-search.js
assets/js/agents/agent-delete.js
```

*Transformation Rules:*
| Source | Target |
|--------|--------|
| `$this->load_template('file.php', $data, 'display')` | `wecoza_view('agents/display/file', $data, true)` |
| `$this->load_template('file.php', $data, 'forms')` | `wecoza_view('agents/components/file', $data, true)` |
| Manual `wp_send_json_success()` | `AjaxSecurity::sendSuccess($data)` |
| Manual `wp_send_json_error()` | `AjaxSecurity::sendError($msg, $code)` |
| Manual nonce check | `AjaxSecurity::requireNonce('agents_nonce_action')` |
| 3 localization objects | 1 unified `wecozaAgents` with camelCase keys |

*Bug Warnings:*

**Bug #3** — Source has 3 localization objects with mixed casing. Unify into `wecozaAgents`.
**Bug #4** — All JS must access `response.data.*`, never `response.*` directly.
**Bug #10** — ALL AJAX actions need `wecoza_agents_` prefix. Standardize delete action.
**Bug #12** — NEVER register nopriv handlers. Remove both from source.

*Verification Commands:*
```bash
wp eval 'foreach(["wecoza_capture_agents","wecoza_display_agents","wecoza_single_agent"] as $s) echo shortcode_exists($s)?"OK: $s\n":"FAIL: $s\n";'
grep -r "wp_ajax_nopriv" src/Agents/ --include="*.php" | wc -l  # expect 0
grep -r "ajax_url\b" src/Agents/ --include="*.php"  # expect 0
grep -r "agents_nonce\.\|wecoza_agents_ajax\.\|wecoZaAgentsDelete\." assets/js/agents/ --include="*.js" | wc -l  # expect 0
```

---

#### Phase 28: Wiring Verification & Fixes

**Goal:** Verify all shortcodes render clean HTML, fix integration bugs found during rendering.

**Requirements:** All SC-xx requirements verified end-to-end

**Success Criteria:**
- [ ] All 3 shortcodes render clean HTML (no PHP errors)
- [ ] No PHP errors in debug.log
- [ ] No JS console errors on any page with shortcodes
- [ ] All DOM IDs in JS match view template IDs
- [ ] All AJAX action names in inline scripts have `wecoza_agents_` prefix
- [ ] All nonce names consistent between PHP and JS

*Bug Warnings:*
**Bug #11** — Check all `<script>` tags in views for hardcoded AJAX URLs
**Bug #13** — Check inline scripts for action names without `wecoza_agents_` prefix
**Bug #14** — Standardize on ONE nonce: `'agents_nonce_action'`

---

#### Phase 29: Feature Verification & Performance

**Goal:** CRUD testing, file uploads, statistics, working areas, performance checks.

**Requirements:** FEAT-01, FEAT-02, FEAT-03, FEAT-04, FEAT-05

**Success Criteria:**
- [ ] All AJAX endpoints respond correctly
- [ ] Agent create/update/delete operations persist correctly
- [ ] File uploads store files correctly
- [ ] Statistics badges show correct counts
- [ ] Working areas dropdown populates
- [ ] Feature parity with standalone plugin confirmed

*Bug Warnings:*
**Bug #15** — Check cache invalidation after writes
**Bug #16** — FIXED in core. Verify no redundant schema queries.

---

#### Phase 30: Integration Testing & Cleanup

**Goal:** Automated feature parity test, standalone plugin deactivation, source removal.

**Requirements:** CLN-01, CLN-02

**Success Criteria:**
- [ ] Automated feature parity test passes all checks
- [ ] Standalone plugin deactivated with zero breakage
- [ ] All pages render correctly after deactivation
- [ ] No standalone plugin references remain in wecoza-core

## Progress

**Execution Order:**
Phases execute in numeric order.

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation Architecture | v1.0 | 2/2 | Complete | 2026-02-02 |
| 2. Task Management Core | v1.0 | 3/3 | Complete | 2026-02-02 |
| 3. Material Tracking | v1.0 | 2/2 | Complete | 2026-02-02 |
| 4. AI Summarization | v1.0 | 2/2 | Complete | 2026-02-02 |
| 5. Email Notifications | v1.0 | 2/2 | Complete | 2026-02-02 |
| 6. PostgreSQL Triggers | v1.0 | 1/1 | Complete | 2026-02-02 |
| 7. Testing & Verification | v1.0 | 1/1 | Complete | 2026-02-02 |
| 8. Bug Fixes | v1.1 | 2/2 | Complete | 2026-02-02 |
| 9. Security Hardening | v1.1 | 2/2 | Complete | 2026-02-02 |
| 10. Performance Optimization | v1.1 | 3/3 | Complete | 2026-02-02 |
| 11. Data Privacy | v1.1 | 3/3 | Complete | 2026-02-02 |
| 12. Architecture Improvements | v1.1 | 3/3 | Complete | 2026-02-02 |
| 13. Event System Foundation | v1.2 | 3/3 | Complete | 2026-02-05 |
| 14. Task Derivation | v1.2 | 3/3 | Complete | 2026-02-05 |
| 15. Bidirectional Sync | v1.2 | 3/3 | Complete | 2026-02-05 |
| 16. Notification System | v1.2 | 3/3 | Complete | 2026-02-05 |
| 17. Code Cleanup | v1.2 | 2/2 | Complete | 2026-02-05 |
| 18. Multi-Recipient Config | v1.2 | 2/2 | Complete | 2026-02-05 |
| 19. Material Dashboard Rewrite | v1.3 | 2/2 | Complete | 2026-02-06 |
| 20. Dashboard Enhancements | v1.3 | 1/1 | Complete | 2026-02-06 |
| 21. Foundation Architecture | v2.0 | 2/2 | Complete | 2026-02-11 |
| 22. Client Management | v2.0 | 2/2 | Complete | 2026-02-11 |
| 23. Location Management | v2.0 | 2/2 | Complete | 2026-02-12 |
| 24. Sites Hierarchy | v2.0 | 2/2 | Complete | 2026-02-12 |
| 25. Integration Testing & Cleanup | v2.0 | 2/2 | Complete | 2026-02-12 |
| 26. Foundation Architecture | v3.0 | 0/? | Not started | — |
| 27. Controllers, Views, JS, AJAX | v3.0 | 0/? | Not started | — |
| 28. Wiring Verification & Fixes | v3.0 | 0/? | Not started | — |
| 29. Feature Verification & Performance | v3.0 | 0/? | Not started | — |
| 30. Integration Testing & Cleanup | v3.0 | 0/? | Not started | — |
