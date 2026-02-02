# Requirements: WeCoza Core

**Defined:** 2026-02-02
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin architecture

## v1.1 Requirements

Requirements for quality/performance polish milestone. Derived from code analysis report.

### Security

- [x] **SEC-01**: Add `quoteIdentifier()` helper for PostgreSQL reserved words
- [x] **SEC-02**: Remove PII mappings from DataObfuscator return value
- [x] **SEC-03**: Strengthen email masking (show domain only)
- [x] **SEC-04**: Add MIME type validation on PDF uploads
- [x] **SEC-05**: Reduce verbose exception logging (schema leak risk)
- [x] **SEC-06**: Add heuristic field detection for custom PII fields

### Performance

- [ ] **PERF-01**: Increase NotificationProcessor BATCH_LIMIT to 50+
- [ ] **PERF-02**: Implement async email via Action Scheduler
- [ ] **PERF-03**: Separate AI enrichment job from email sending job
- [ ] **PERF-04**: Increase lock TTL to prevent race conditions
- [x] **PERF-05**: Add memory cleanup for long-running DataObfuscator

### Bugs

- [x] **BUG-01**: Fix column name mismatch (`sa_id_no` vs `sa_id_number`)
- [x] **BUG-02**: Fix savePortfolios() overwrite bug (append, don't replace)
- [x] **BUG-03**: Implement missing `processPortfolioDetails()` method (already existed)
- [x] **BUG-04**: Fix unsafe `$pdo` access in catch block

### Quality

- [x] **QUAL-01**: Fix invalid model name (`gpt-5-mini` → `gpt-4o-mini`)
- [x] **QUAL-02**: Extract DTOs for `$record`, `$context`, `$summary` arrays
- [x] **QUAL-03**: Implement PHP 8.1 Enums for status strings
- [x] **QUAL-04**: Make API URL configurable (support Azure/proxy)

### Architecture

- [x] **ARCH-01**: Refactor `generateSummary()` for Single Responsibility
- [x] **ARCH-02**: Add BaseRepository `count()` method for pagination (already existed)

## Future Requirements

Deferred to future milestones. Not in current roadmap.

### Reporting

- **RPT-01**: Dashboard with module statistics
- **RPT-02**: Export functionality for learner data
- **RPT-03**: Class attendance reports

### Features

- **FEAT-01**: Packages feature (learners on different subjects)
- **FEAT-02**: Bulk learner import from CSV
- **FEAT-03**: API endpoints for external integrations

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Mobile app | Not planned — web-first approach |
| OAuth/social login | Email/password sufficient for internal use |
| Real-time notifications | WebSockets complexity not justified for use case |
| Multi-tenant architecture | Single organization deployment |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| BUG-01 | Phase 8 | Complete |
| BUG-02 | Phase 8 | Complete |
| BUG-03 | Phase 8 | Complete |
| BUG-04 | Phase 8 | Complete |
| SEC-01 | Phase 8 | Complete |
| SEC-04 | Phase 8 | Complete |
| SEC-05 | Phase 8 | Complete |
| SEC-02 | Phase 9 | Complete |
| SEC-03 | Phase 9 | Complete |
| SEC-06 | Phase 9 | Complete |
| PERF-05 | Phase 9 | Complete |
| ARCH-01 | Phase 10 | Complete |
| ARCH-02 | Phase 10 | Complete |
| QUAL-02 | Phase 10 | Complete |
| QUAL-03 | Phase 10 | Complete |
| QUAL-01 | Phase 11 | Complete |
| QUAL-04 | Phase 11 | Complete |
| PERF-01 | Phase 12 | Pending |
| PERF-02 | Phase 12 | Pending |
| PERF-03 | Phase 12 | Pending |
| PERF-04 | Phase 12 | Pending |

**Coverage:**
- v1.1 requirements: 21 total
- Mapped to phases: 21
- Unmapped: 0

---
*Requirements defined: 2026-02-02*
*Last updated: 2026-02-02 — Phase 11 complete (17 requirements satisfied)*
