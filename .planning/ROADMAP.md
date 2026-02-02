# Roadmap: WeCoza Core

## Milestones

- v1.0 Events Integration - Phases 1-7 (shipped 2026-02-02)
- **v1.1 Quality & Performance** - Phases 8-12 (in progress)

## Phases

<details>
<summary>v1.0 Events Integration (Phases 1-7) - SHIPPED 2026-02-02</summary>

Migrated wecoza-events-plugin into wecoza-core. 7 phases, 13 plans, 24 requirements.

See: `.planning/milestones/v1-ROADMAP.md` for full details.

</details>

### v1.1 Quality & Performance (In Progress)

**Milestone Goal:** Production-ready polish addressing 21 issues from code analysis: bug fixes, security hardening, data privacy improvements, architecture refactoring, and performance optimization.

## Phase Details

### Phase 8: Bug Fixes & Core Security

**Goal**: Critical bugs fixed and core security vulnerabilities addressed
**Depends on**: Phase 7 (v1 complete)
**Requirements**: BUG-01, BUG-02, BUG-04, SEC-01, SEC-04, SEC-05
**Plans**: 3 plans (Wave 1 parallel)

**Success Criteria** (what must be TRUE):
1. Portfolio save operations append to existing portfolios instead of overwriting them
2. Learner queries work correctly regardless of column naming (`sa_id_no` vs `sa_id_number`)
3. Database catch blocks handle connection failures gracefully without throwing secondary errors
4. PDF uploads are validated by MIME type (not just extension) before processing
5. Exception logs contain sanitized messages without exposing schema details

Plans:
- [ ] 08-01-PLAN.md — Fix learner query bugs (BUG-01, BUG-04)
- [ ] 08-02-PLAN.md — Fix portfolio save and add MIME validation (BUG-02, SEC-04)
- [ ] 08-03-PLAN.md — Add security helpers and sanitize logging (SEC-01, SEC-05)

### Phase 9: Data Privacy Hardening

**Goal**: PII protection strengthened with no sensitive data leakage
**Depends on**: Phase 8
**Requirements**: SEC-02, SEC-03, SEC-06, PERF-05
**Plans**: TBD

**Success Criteria** (what must be TRUE):
1. DataObfuscator return values contain obfuscated data only (no PII mappings exposed)
2. Email addresses display as `****@domain.com` (domain visible, local part masked)
3. Custom fields containing PII patterns (ID numbers, phone numbers) are auto-detected and obfuscated
4. Long-running obfuscation operations release memory periodically (no memory leaks on large datasets)

Plans:
- [ ] 09-01: TBD

### Phase 10: Architecture & Type Safety

**Goal**: Codebase uses proper abstractions with type-safe data structures
**Depends on**: Phase 8
**Requirements**: ARCH-01, ARCH-02, QUAL-02, QUAL-03
**Plans**: TBD

**Success Criteria** (what must be TRUE):
1. `generateSummary()` delegates to focused single-purpose methods (prompt building, API calling, response parsing)
2. BaseRepository provides `count()` method usable by all repositories for pagination
3. `$record`, `$context`, and `$summary` arrays replaced with typed DTO classes
4. Status strings (`in_progress`, `completed`, etc.) use PHP 8.1 Enums with validation

Plans:
- [ ] 10-01: TBD
- [ ] 10-02: TBD

### Phase 11: AI Service Quality

**Goal**: AI service uses correct model and supports flexible deployment
**Depends on**: Phase 10 (uses refactored generateSummary)
**Requirements**: QUAL-01, QUAL-04
**Plans**: TBD

**Success Criteria** (what must be TRUE):
1. AI summaries generated using valid model name (`gpt-4o-mini` not `gpt-5-mini`)
2. OpenAI API endpoint configurable via WordPress options (supports Azure/proxy deployments)

Plans:
- [ ] 11-01: TBD

### Phase 12: Performance & Async Processing

**Goal**: Notification processing handles high volume without blocking
**Depends on**: Phase 8 (bug fixes needed for stable processing)
**Requirements**: PERF-01, PERF-02, PERF-03, PERF-04
**Plans**: TBD

**Success Criteria** (what must be TRUE):
1. NotificationProcessor processes 50+ notifications per batch without timeout
2. Email sending runs asynchronously via Action Scheduler (not blocking web requests)
3. AI enrichment and email sending run as separate scheduled jobs (independent failure)
4. Notification lock TTL prevents race conditions during high-volume processing

Plans:
- [ ] 12-01: TBD
- [ ] 12-02: TBD

## Progress

**Execution Order:** Phases execute in numeric order: 8 -> 9 -> 10 -> 11 -> 12

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1-7 | v1.0 | 13/13 | Complete | 2026-02-02 |
| 8. Bug Fixes & Core Security | v1.1 | 0/3 | Planned | - |
| 9. Data Privacy Hardening | v1.1 | 0/? | Not started | - |
| 10. Architecture & Type Safety | v1.1 | 0/? | Not started | - |
| 11. AI Service Quality | v1.1 | 0/? | Not started | - |
| 12. Performance & Async Processing | v1.1 | 0/? | Not started | - |

## Requirement Coverage

All 21 v1.1 requirements mapped to exactly one phase:

| Requirement | Phase | Description |
|-------------|-------|-------------|
| BUG-01 | 8 | Fix column name mismatch |
| BUG-02 | 8 | Fix savePortfolios() overwrite bug |
| BUG-03 | N/A | Already implemented (processPortfolioDetails exists) |
| BUG-04 | 8 | Fix unsafe $pdo access in catch block |
| SEC-01 | 8 | Add quoteIdentifier() helper |
| SEC-04 | 8 | Add MIME type validation on PDF uploads |
| SEC-05 | 8 | Reduce verbose exception logging |
| SEC-02 | 9 | Remove PII mappings from DataObfuscator return |
| SEC-03 | 9 | Strengthen email masking |
| SEC-06 | 9 | Add heuristic field detection for PII |
| PERF-05 | 9 | Add memory cleanup for DataObfuscator |
| ARCH-01 | 10 | Refactor generateSummary() for SRP |
| ARCH-02 | 10 | Add BaseRepository count() method |
| QUAL-02 | 10 | Extract DTOs for arrays |
| QUAL-03 | 10 | Implement PHP 8.1 Enums for status strings |
| QUAL-01 | 11 | Fix invalid model name |
| QUAL-04 | 11 | Make API URL configurable |
| PERF-01 | 12 | Increase NotificationProcessor BATCH_LIMIT |
| PERF-02 | 12 | Implement async email via Action Scheduler |
| PERF-03 | 12 | Separate AI enrichment from email sending |
| PERF-04 | 12 | Increase lock TTL |

**Coverage:** 21/21 requirements mapped

---

*Created: 2026-02-02 for v1.1 milestone*
*Last updated: 2026-02-02*
