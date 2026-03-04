# Requirements: WeCoza Core

**Defined:** 2026-03-04
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure

## v7.0 Requirements

Requirements for v7.0 Agent Attendance Access. Each maps to roadmap phases.

### Exception UX

- [ ] **EXC-01**: Agent can see a clearly labelled "Report Exception" button (not icon-only) in the attendance session list
- [ ] **EXC-02**: Exception button styling uses Phoenix badge/button patterns for visibility

### Stopped-Class Capture

- [ ] **STP-01**: Agent can capture attendance on stopped classes for session dates up to and including the stop date
- [ ] **STP-02**: Server-side AJAX guard allows capture on stopped classes when session date ≤ stop date
- [ ] **STP-03**: Sessions after stop date remain locked (no capture allowed)

### Agent Access

- [ ] **AGT-01**: Plugin registers `wecoza_agent` WordPress role with `capture_attendance` capability on activation and update
- [ ] **AGT-02**: Administrator role also receives `capture_attendance` capability
- [ ] **AGT-03**: `agents` table has `wp_user_id` column linking to WordPress user accounts
- [ ] **AGT-04**: AJAX attendance handlers check `capture_attendance` capability (not just logged-in)
- [ ] **AGT-05**: Agent sees only their assigned classes (primary + backup) on dedicated attendance page
- [ ] **AGT-06**: Agent-dedicated attendance shortcode renders minimal page with existing attendance capture UI
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
| EXC-01 | — | Pending |
| EXC-02 | — | Pending |
| STP-01 | — | Pending |
| STP-02 | — | Pending |
| STP-03 | — | Pending |
| AGT-01 | — | Pending |
| AGT-02 | — | Pending |
| AGT-03 | — | Pending |
| AGT-04 | — | Pending |
| AGT-05 | — | Pending |
| AGT-06 | — | Pending |
| AGT-07 | — | Pending |
| AGT-08 | — | Pending |

**Coverage:**
- v7.0 requirements: 13 total
- Mapped to phases: 0
- Unmapped: 13 ⚠️

---
*Requirements defined: 2026-03-04*
*Last updated: 2026-03-04 after initial definition*
