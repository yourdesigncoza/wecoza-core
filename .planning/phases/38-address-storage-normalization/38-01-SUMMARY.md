---
phase: 38-address-storage-normalization
plan: 01
status: complete
started: 2026-02-16
completed: 2026-02-16
---

# Plan 38-01: Agent Address to Locations Migration

## What Was Built

Two migration files to normalize agent address storage by linking agents to the shared `public.locations` table:

1. **SQL DDL Migration** (`db/migrations/38-01-agent-address-to-locations.sql`) — Adds `location_id` column to agents table with FK constraint to `public.locations(location_id)` and index for lookups.

2. **PHP Data Migration** (`db/migrations/38-01-agent-address-to-locations.php`) — Copies agent addresses to locations table with duplicate detection, dry-run support, idempotency, and verification output.

## Migration Results

Migration executed by user on 2026-02-16:

- **Agents processed:** 19
- **Locations created:** 18
- **Locations reused:** 1 (duplicate detected)
- **Agents updated:** 19
- **Verification:** SUCCESS — all 19 agents with addresses now have location_id set

## Commits

| Commit | Description |
|--------|-------------|
| 3bac0d4 | feat(38-01): add migration scripts for agent address normalization |
| 267a8bb | fix(38-01): fix wp-load path and ClientsModel static conflict |

## Key Files

### Created
- `db/migrations/38-01-agent-address-to-locations.sql`
- `db/migrations/38-01-agent-address-to-locations.php`

## Deviations

1. **wp-load.php path fix:** Initial script used `dirname(__DIR__, 4)` — needed `dirname(__DIR__, 5)` to reach WordPress root from `db/migrations/` directory.
2. **ClientsModel static conflict:** `ClientsModel::getAll()` conflicted with `BaseModel::getAll()` (static vs non-static). Renamed to `getAllClients()` with all callers updated. Pre-existing issue from Phase 37 model unification.

## Self-Check: PASSED
- [x] Migration files created
- [x] SQL DDL is syntactically correct
- [x] PHP script passes syntax check
- [x] User executed migration successfully
- [x] All 19 agents have location_id set
