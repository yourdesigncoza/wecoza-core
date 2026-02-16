# Roadmap: WeCoza Core v4.0

**Milestone:** v4.0 Technical Debt — Architectural Improvements
**Created:** 2026-02-16
**Phases:** 36-41 (6 phases)
**Requirements:** 28 total

## Phase Overview

| # | Phase | Goal | Requirements | Success Criteria |
|---|-------|------|--------------|------------------|
| 36 | Service Layer Extraction | Complete    | 2026-02-16 | 4 |
| 37 | Model Architecture Unification | Complete    | 2026-02-16 | 4 |
| 38 | Address Storage Normalization | Agent addresses migrated to shared locations table with dual-write | ADDR-01..05 | 5 |
| 39 | Repository Pattern Enforcement | All repositories use BaseRepository methods — manual SQL only for complex queries | REPO-01..06 | 4 |
| 40 | Return Type Hints & Constants | All public methods typed, all magic numbers extracted to constants | TYPE-01..05, CONST-01..04 | 5 |
| 41 | Architectural Verification | End-to-end validation — all refactored layers work together, no regressions | All 28 | 5 |

## Dependency Graph

```
Phase 36 (SVC) ──> Phase 37 (MDL) ──> Phase 38 (ADDR)
      │                                      │
      └──> Phase 39 (REPO) ─────────────────┘
                                              │
Phase 40 (TYPE+CONST) ──────────────────────> Phase 41 (Verification)
```

- Phase 37 depends on 36 (services must exist before model refactor)
- Phase 38 depends on 37 (models must extend BaseModel before address migration)
- Phase 39 depends on 36 (services use repositories)
- Phase 40 can run after 37+39 (need stable method signatures)
- Phase 41 depends on all prior phases

## Phase Details

### Phase 36: Service Layer Extraction

**Goal:** Extract business logic from LearnerController, AgentsController, and ClientsController into dedicated service classes. Controllers should only handle input validation, service delegation, and response formatting.

**Plans:** 3/3 plans complete
- [ ] 36-01-PLAN.md — LearnerService extraction (SVC-01)
- [ ] 36-02-PLAN.md — AgentService extraction (SVC-02)
- [ ] 36-03-PLAN.md — ClientService extraction (SVC-03, biggest DRY win)

**Requirements:**
- SVC-01: Learner business logic extracted from LearnerController to LearnerService
- SVC-02: Agent business logic extracted from AgentsController to AgentService
- SVC-03: Client business logic extracted from ClientsController to ClientService
- SVC-04: Controllers contain only input validation, service calls, and response handling (<100 lines per method)

**Success Criteria:**
1. LearnerService.php exists with progression logic, validation orchestration, and learner CRUD operations
2. AgentService.php exists with agent creation workflow and working areas coordination
3. ClientService.php exists with client location linking and address normalization
4. No controller method exceeds 100 lines — each follows pattern: validate → service call → respond
5. All existing AJAX endpoints return identical responses (backward compatible)

---

### Phase 37: Model Architecture Unification

**Goal:** Make ClientsModel and AgentModel extend BaseModel, removing all duplicate get/set/toArray methods and using the shared validation framework.

**Plans:** 2/2 plans complete

Plans:
- [ ] 37-01-PLAN.md — ClientsModel extends BaseModel with ArrayAccess for backward-compatible array syntax (MDL-01)
- [ ] 37-02-PLAN.md — AgentModel extends BaseModel preserving data-bag pattern and validation (MDL-02, MDL-03, MDL-04)

**Requirements:**
- MDL-01: ClientsModel extends BaseModel with inherited get/set/toArray/validate
- MDL-02: AgentModel extends BaseModel with inherited get/set/toArray/validate
- MDL-03: No duplicate get/set/toArray methods in ClientsModel or AgentModel
- MDL-04: Validation framework consistent across all models using BaseModel::validate()

**Success Criteria:**
1. ClientsModel class declaration reads `class ClientsModel extends BaseModel`
2. AgentModel class declaration reads `class AgentModel extends BaseModel`
3. Zero duplicate get/set/toArray methods — grep confirms only BaseModel defines them
4. AgentModel::validate() and ClientsModel::validate() use BaseModel's validation patterns
5. All existing model consumers (controllers, repositories, views) work unchanged

---

### Phase 38: Address Storage Normalization

**Goal:** Migrate agent addresses from inline columns to the shared locations table, with dual-write for backward compatibility and zero data loss.

**Requirements:**
- ADDR-01: Migration script copies existing agent addresses to shared locations table
- ADDR-02: AgentRepository reads addresses from locations table (with fallback to old columns)
- ADDR-03: AgentRepository writes addresses to both locations table and old columns (dual-write)
- ADDR-04: AgentsController uses location linking for address management
- ADDR-05: All existing agent addresses preserved after migration (zero data loss)

**Success Criteria:**
1. Migration script successfully copies all agent addresses to wecoza_locations table with entity_type='agent'
2. AgentRepository::find() returns address data from locations table, falling back to old columns if not found
3. AgentRepository::save() writes to both locations table AND old agent columns (dual-write period)
4. Agent capture/edit forms work identically — users see no change in address workflow
5. Count of agent addresses before migration equals count of location records after migration

---

### Phase 39: Repository Pattern Enforcement

**Goal:** Replace manual SQL queries with BaseRepository findBy/updateBy/deleteBy methods where appropriate. Document justified bypasses for complex queries.

**Requirements:**
- REPO-01: Audit identifies all direct SQL queries bypassing BaseRepository methods
- REPO-02: LearnerRepository uses findBy/updateBy/deleteBy where appropriate
- REPO-03: AgentRepository uses findBy/updateBy/deleteBy where appropriate
- REPO-04: ClientRepository uses findBy/updateBy/deleteBy where appropriate
- REPO-05: All column names use quoteIdentifier() in repository queries
- REPO-06: Complex queries (joins, aggregations) documented as justified bypasses

**Success Criteria:**
1. Audit document lists every direct SQL query across all repositories with classification (replaceable vs justified)
2. 80%+ of simple queries replaced with BaseRepository findBy/updateBy/deleteBy
3. All remaining manual SQL uses quoteIdentifier() for column names
4. Justified bypass comments (// Complex query: ...) explain why BaseRepository methods insufficient

---

### Phase 40: Return Type Hints & Constants

**Goal:** Add return type hints to all public methods and extract all magic numbers to named constants.

**Requirements:**
- TYPE-01: All public controller methods have return type hints
- TYPE-02: All public model methods have return type hints
- TYPE-03: All public service methods have return type hints
- TYPE-04: All public repository methods have return type hints
- TYPE-05: Union types used appropriately (string|void, array|null) — no untyped mixed
- CONST-01: Pagination limits extracted to named constants (SCREAMING_SNAKE_CASE)
- CONST-02: Timeout values extracted to named constants
- CONST-03: Quantum/score limits extracted to named constants
- CONST-04: No magic numbers in business logic across all modules

**Success Criteria:**
1. grep for `public function` without `: ` return type finds zero results across all PHP files
2. Constants classes exist (e.g., LearnerConstants, AppConstants) with SCREAMING_SNAKE_CASE naming
3. grep for bare numeric literals (10, 20, 30, 50, 60, 100, 120) in business logic returns zero false positives
4. Union types used correctly — void for render methods, nullable for optional returns
5. All constants referenced from their class (e.g., LearnerConstants::DEFAULT_PAGE_SIZE)

---

### Phase 41: Architectural Verification

**Goal:** End-to-end validation that all 28 requirements are met, no regressions introduced, and architecture improvements are complete.

**Requirements:** Validates all 28 requirements (SVC-01..04, MDL-01..04, ADDR-01..05, REPO-01..06, TYPE-01..05, CONST-01..04)

**Success Criteria:**
1. All PHP files parse without errors (`php -l` passes on all modified files)
2. All AJAX endpoints respond correctly (learner, agent, client, event CRUD operations)
3. All shortcodes render without errors across all 5 modules
4. Address migration verified — agent addresses accessible via both old and new paths
5. Zero new PHP warnings/notices in WordPress debug log after full exercise

---

*Roadmap created: 2026-02-16*
*Phase numbering continues from v3.1 (phases 31-35)*
