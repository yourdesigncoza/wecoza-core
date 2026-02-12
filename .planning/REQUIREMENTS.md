# v3.0 Agents Integration Requirements

## Architecture Requirements

### ARCH-01: PSR-4 Namespace Registration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] `WeCoza\Agents\` namespace registered in wecoza-core.php autoloader mapping to `src/Agents/`
- [ ] All migrated classes use `WeCoza\Agents\{SubNamespace}\` namespace
- [ ] Zero references to standalone plugin constants (WECOZA_AGENTS_*)

### ARCH-03: Database Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] All `DatabaseService::getInstance()` calls replaced with `wecoza_db()`
- [ ] All `DatabaseService` method calls adapted to PostgresConnection API signatures
- [ ] `update()` calls adapted: array WHERE → string WHERE + whereParams
- [ ] `delete()` calls adapted: array WHERE → string WHERE + params
- [ ] `insert()` calls use generic RETURNING (not hardcoded `RETURNING agent_id`)
- [ ] `query()` + `fetchAll()` chains replaced with `wecoza_db()->getAll()`
- [ ] `query()` + `fetch()` chains replaced with `wecoza_db()->getRow()`
- [ ] Zero `DatabaseService` references in migrated code
- [ ] Zero `DatabaseLogger` references in migrated code

### ARCH-04: Model Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] Agent model migrated as standalone class (NOT extending BaseModel)
- [ ] Model uses `wecoza_db()` for all database operations
- [ ] Model delegates queries to AgentRepository
- [ ] Validation logic preserved from source

### ARCH-05: Repository Creation
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] AgentRepository extends BaseRepository
- [ ] Column whitelisting via `getAllowedOrderColumns()`, `getAllowedFilterColumns()`, `getAllowedInsertColumns()`, `getAllowedUpdateColumns()`
- [ ] All AgentQueries methods migrated to repository pattern
- [ ] CRUD operations use wecoza_db() with correct PostgresConnection signatures
- [ ] Sanitization preserved from source AgentQueries

### ARCH-06: View Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] All 6 templates migrated to `views/agents/` using `.view.php` extension
- [ ] All template includes use `wecoza_view('agents/path', $data, true)` pattern
- [ ] All template includes use `wecoza_component('agents/component', $data, true)` for partials
- [ ] Template path references updated from WECOZA_AGENTS_TEMPLATES_DIR to wecoza_view()

### ARCH-07: Controller Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] AgentsController extends BaseController with `registerHooks()`
- [ ] All 3 shortcodes registered in controller
- [ ] Assets enqueued conditionally (only when shortcode present on page)
- [ ] Controller uses AgentRepository (not AgentQueries directly)
- [ ] AbstractShortcode functionality replaced by BaseController patterns

### ARCH-08: AJAX Handler Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] AgentsAjaxHandlers class created with AjaxSecurity pattern
- [ ] All AJAX handlers use `AjaxSecurity::requireNonce('agents_nonce_action')`
- [ ] All responses use `AjaxSecurity::sendSuccess()` / `AjaxSecurity::sendError()`
- [ ] Zero `wp_ajax_nopriv_*` handlers registered
- [ ] All AJAX action names have `wecoza_` prefix

### ARCH-09: JS Asset Migration
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] All 5 JS files moved to `assets/js/agents/`
- [ ] Localization object unified: `wecozaAgents` with camelCase keys
- [ ] All JS reads `response.data.*` not `response.*` directly
- [ ] All AJAX action references use `wecoza_` prefix
- [ ] All nonce references consistent with PHP-side nonce action name

### ARCH-10: Core Wiring
**Category:** ARCH
**Priority:** Must
**Acceptance Criteria:**
- [ ] Namespace registered in wecoza-core.php autoloader
- [ ] AgentsController initialized in wecoza-core.php module init section
- [ ] AgentsAjaxHandlers initialized in wecoza-core.php module init section
- [ ] Plugin loads without PHP errors

## Shortcode Requirements

### SC-01: wecoza_capture_agents Shortcode
**Category:** SC
**Priority:** Must
**Acceptance Criteria:**
- [ ] Shortcode renders agent capture form (add mode)
- [ ] Shortcode renders agent edit form (edit mode via URL params)
- [ ] Form submission creates/updates agent in database
- [ ] File uploads work for signed agreement and criminal record
- [ ] Validation errors display correctly
- [ ] SA ID and passport validation work
- [ ] Working areas dropdown populates correctly
- [ ] Google Maps address autocomplete loads (when API key configured)

### SC-02: wecoza_display_agents Shortcode
**Category:** SC
**Priority:** Must
**Acceptance Criteria:**
- [ ] Shortcode renders agent table with pagination
- [ ] AJAX pagination loads next/prev pages without full reload
- [ ] Search filters agents by name, email, phone, ID number
- [ ] Sort columns work (first name, surname, email, created date)
- [ ] Agent statistics badges display above table
- [ ] View/Edit/Delete action buttons functional
- [ ] Delete performs soft-delete via AJAX

### SC-03: wecoza_single_agent Shortcode
**Category:** SC
**Priority:** Must
**Acceptance Criteria:**
- [ ] Shortcode renders single agent detail view
- [ ] URL parameter `agent_id` loads correct agent
- [ ] All agent fields display correctly
- [ ] Back button returns to agent list
- [ ] Edit link navigates to capture form in edit mode

## Feature Requirements

### FEAT-01: Agent CRUD Operations
**Category:** FEAT
**Priority:** Must
**Acceptance Criteria:**
- [ ] Create agent with all fields persists to `agents` table
- [ ] Read agent by ID returns all fields
- [ ] Update agent modifies only changed fields + updated_at/updated_by
- [ ] Soft-delete sets status to 'deleted'
- [ ] Duplicate email check prevents duplicate agents
- [ ] Duplicate SA ID check prevents duplicate agents

### FEAT-02: Agent Metadata
**Category:** FEAT
**Priority:** Should
**Acceptance Criteria:**
- [ ] Agent meta CRUD operations (add/get/update/delete) work
- [ ] Agent notes CRUD operations work
- [ ] Agent absences CRUD operations work

### FEAT-03: File Upload Management
**Category:** FEAT
**Priority:** Must
**Acceptance Criteria:**
- [ ] Signed agreement file upload works
- [ ] Criminal record file upload works
- [ ] File type validation (PDF, DOC, DOCX only)
- [ ] Old files deleted when replaced
- [ ] Upload directory created with security (.htaccess)

### FEAT-04: Agent Statistics
**Category:** FEAT
**Priority:** Should
**Acceptance Criteria:**
- [ ] Total agents count displays
- [ ] Active agents count displays
- [ ] SACE registered count displays
- [ ] Quantum qualified count displays

### FEAT-05: Working Areas Service
**Category:** FEAT
**Priority:** Must
**Acceptance Criteria:**
- [ ] Working areas dropdown populates from database/config
- [ ] Up to 3 preferred areas selectable per agent
- [ ] NULL handling for empty area selections (foreign key safe)

## Cleanup Requirements

### CLN-01: Standalone Plugin Deactivation
**Category:** CLN
**Priority:** Must
**Acceptance Criteria:**
- [ ] wecoza-agents-plugin can be deactivated without breaking agents functionality
- [ ] All pages with agent shortcodes render correctly after deactivation
- [ ] All AJAX endpoints respond correctly after deactivation
- [ ] No PHP errors in debug.log after deactivation

### CLN-02: Source Directory Removal
**Category:** CLN
**Priority:** Must
**Acceptance Criteria:**
- [ ] `.integrate/wecoza-agents-plugin/` moved to `.integrate/done/wecoza-agents-plugin/`
- [ ] Zero `WeCoza\Agents\Database\DatabaseService` references in wecoza-core
- [ ] Zero `WECOZA_AGENTS_*` constant references in wecoza-core

## Traceability: Requirements > Phases

| Requirement | Phase |
|-------------|-------|
| ARCH-01, ARCH-03, ARCH-04, ARCH-05 | 26: Foundation Architecture |
| ARCH-06, ARCH-07, ARCH-08, ARCH-09, ARCH-10 | 27: Controllers, Views, JS, AJAX |
| SC-01, SC-02, SC-03 | 28: Wiring Verification & Fixes |
| FEAT-01, FEAT-02, FEAT-03, FEAT-04, FEAT-05 | 29: Feature Verification & Performance |
| CLN-01, CLN-02 | 30: Integration Testing & Cleanup |
