# Phase 8: Bug Fixes & Core Security - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix critical bugs in portfolio saves, learner queries, and database error handling. Harden file upload validation and sanitize exception logging. No new features - pure fixes and security hardening.

</domain>

<decisions>
## Implementation Decisions

### Validation Feedback (PDF Uploads)
- Generic error message only: "Invalid file type. Please upload a PDF document."
- Do NOT reveal detected MIME type (security consideration)
- Inline retry: Error shows next to file input, user can immediately select another file
- Error placement: Red text directly below the file upload field
- Validation timing: Both client-side (immediate UX feedback) and server-side (security backstop)

### Claude's Discretion
- Data recovery approach: Whether to detect/fix existing corrupted portfolios from overwrite bug or just fix going forward
- Admin error visibility: How administrators diagnose issues with sanitized exception logs
- Specific error message wording (within generic approach)
- Client-side validation implementation details
- Column name handling strategy for `sa_id_no` vs `sa_id_number` compatibility

</decisions>

<specifics>
## Specific Ideas

No specific requirements - open to standard approaches for the technical bug fixes.

</specifics>

<deferred>
## Deferred Ideas

None - discussion stayed within phase scope

</deferred>

---

*Phase: 08-bug-fixes-core-security*
*Context gathered: 2026-02-02*
