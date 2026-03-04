# Requirements: WeCoza Core

**Defined:** 2026-03-04
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure

## v7.0 Requirements

Requirements for v7.0 Agent Attendance Access. Each maps to roadmap phases.

### Exception UX

- [x] **EXC-01**: Agent can see a clearly labelled "Report Exception" button (not icon-only) in the attendance session list
- [x] **EXC-02**: Exception button styling uses Phoenix badge/button patterns for visibility

### Stopped-Class Capture

- [x] **STP-01**: Agent can capture attendance on stopped classes for session dates up to and including the stop date
- [x] **STP-02**: Server-side AJAX guard allows capture on stopped classes when session date ≤ stop date
- [x] **STP-03**: Sessions after stop date remain locked (no capture allowed)

### Agent Access

- [x] **AGT-01**: Plugin registers `wp_agent` WordPress role with `capture_attendance` capability on activation and update
- [x] **AGT-02**: Administrator role also receives `capture_attendance` capability
- [x] **AGT-03**: `agents` table has `wp_user_id` column linking to WordPress user accounts
- [x] **AGT-04**: AJAX attendance handlers check `capture_attendance` capability (not just logged-in)
- [x] **AGT-05**: Agent sees only their assigned classes (primary + backup) on dedicated attendance page
- [x] **AGT-06**: Agent-dedicated attendance shortcode renders minimal page with existing attendance capture UI
- [ ] **AGT-07**: Agent is redirected away from WP admin and other WeCoza pages — can only access attendance page
- [ ] **AGT-08**: Agent login shows attendance page directly (no WP dashboard)

## Future Requirements

### Reporting

- **RPT-01**: Report generation with extractable field list (waiting on Mario's field list)

### Agent Management

- **AGT-09**: Manager UI for provisioning agent WordPress accounts
- **AGT-10**: Manager can generate/revoke agent access links

## Out of Scope

| Feature | Reason |
|---------|--------|
| Token-based auth (no WP login) | WP role approach is simpler, more secure, compatible with existing nonce validation |
| Agent self-registration | Agents provisioned by admins/managers only |
| Agent access to non-attendance pages | Mario explicitly requires agents see ONLY attendance |
| Report generation fields | Blocked on Mario's field list — defer to v7.1+ |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| EXC-01 | Phase 53 | Complete (quick-17, 2026-03-04) |
| EXC-02 | Phase 53 | Complete (quick-17, 2026-03-04) |
| STP-01 | Phase 53 | Complete (quick-17, 2026-03-04) |
| STP-02 | Phase 53 | Complete (quick-17, 2026-03-04) |
| STP-03 | Phase 53 | Complete (quick-17, 2026-03-04) |
| AGT-01 | Phase 54 | Complete |
| AGT-02 | Phase 54 | Complete |
| AGT-03 | Phase 54 | Complete |
| AGT-04 | Phase 54 | Complete |
| AGT-05 | Phase 55 | Complete |
| AGT-06 | Phase 55 | Complete |
| AGT-07 | Phase 55 | Pending |
| AGT-08 | Phase 55 | Pending |

**Coverage:**
- v7.0 requirements: 13 total
- Mapped to phases: 13 (100%) ✓
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-04*
*Last updated: 2026-03-04 — traceability complete after roadmap creation*
