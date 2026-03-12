# M003: Excessive Hours Report

**Vision:** Live dashboard surfacing learners with excessive training hours, with resolution tracking workflow.

## Success Criteria

- Admin can view all learners whose hours_trained > subject_duration for applicable programmes
- Admin can resolve flagged items with action taken + notes
- Resolved items resurface after 30 days if still over hours
- SystemPulse shows excessive hours count in attention items
- Data loads via AJAX (fast initial page render)

## Key Risks / Unknowns

- Query performance with LATERAL JOIN — low risk, bounded dataset

## Proof Strategy

- Performance → retire in S01 by proving the live query executes in under 100ms on real data

## Verification Classes

- Contract verification: PHP test script verifying repository queries return expected shape
- Integration verification: Browser verification of full AJAX flow (load, filter, resolve)
- Operational verification: None (no cron/background)
- UAT / human verification: Visual check of dashboard with real data

## Milestone Definition of Done

This milestone is complete only when all are true:

- Schema migration provided and executed
- Dashboard shortcode loads real data via AJAX
- Resolution workflow works end-to-end (resolve, verify persistence, verify resurface logic)
- SystemPulse shows attention item count
- Browser-verified with real production data

## Slices

- [x] **S01: Data Layer + AJAX API** `risk:medium` `depends:[]`
  > After this: AJAX endpoint returns correct excessive-hours data from real DB, resolutions can be created/queried
- [x] **S02: Dashboard UI + SystemPulse Integration** `risk:low` `depends:[S01]`
  > After this: Full working dashboard page with DataTable, filters, inline resolve, and SystemPulse count

## Boundary Map

### S01 → S02

Produces:
- `ExcessiveHoursRepository` with `findFlagged()`, `createResolution()`, `countOpen()`
- `ExcessiveHoursService` with `getFlaggedLearners()`, `resolveFlag()`
- AJAX endpoints: `wecoza_get_excessive_hours`, `wecoza_resolve_excessive_hours`
- `excessive_hours_resolutions` DB table

Consumes:
- nothing (first slice)
