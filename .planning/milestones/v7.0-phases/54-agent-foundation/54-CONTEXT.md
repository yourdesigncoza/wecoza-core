# Phase 54: Agent Foundation - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning
**Source:** User-provided context (inline with plan-phase invocation)

<domain>
## Phase Boundary

Extend the existing `wp_agent` WordPress role with `capture_attendance` capability, add `wp_user_id` column to agents table, and enforce capability checks on attendance AJAX endpoints.

</domain>

<decisions>
## Implementation Decisions

### Role & Capability
- Extend existing `wp_agent` role (at line ~750 in wecoza-core.php) — do NOT create a new role
- Add `capture_attendance` capability to `wp_agent` role
- Add `capture_attendance` capability to `administrator` role too
- Existing `wp_agent` capabilities (read, edit_posts, upload_files) remain unchanged

### Update Survival
- Add `plugins_loaded` guard for capability updates — activation hook alone won't survive plugin updates
- Must work after both fresh activation AND plugin update (no manual reactivation)

### Schema
- Add `wp_user_id` INTEGER column on `agents` table

### AJAX Security
- All attendance capture AJAX endpoints must enforce `capture_attendance` capability
- All exception-marking AJAX endpoints must enforce `capture_attendance` capability
- Return permissions error for users without the capability

### Claude's Discretion
- Migration approach for adding `wp_user_id` column (ALTER TABLE vs migration system)
- Specific AJAX handler identification and guard implementation pattern
- Version tracking for capability updates (if needed)

</decisions>

<specifics>
## Specific Ideas

- Role name is `wp_agent` (NOT `wecoza_agent` as research may have assumed)
- Existing role definition is around line 750 in wecoza-core.php
- Current capabilities: read, edit_posts, upload_files

</specifics>

<deferred>
## Deferred Ideas

None — phase scope is well-defined.

</deferred>

---

*Phase: 54-agent-foundation*
*Context gathered: 2026-03-04 via user-provided inline context*
