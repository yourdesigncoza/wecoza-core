# Phase 20: Material Tracking Urgency Indicators - Context

**Gathered:** 2026-02-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Add visual urgency indicators to the material delivery tracking table (`[wecoza_material_tracking]` shortcode). Left-border color coding on each row communicates whether a pending delivery is overdue, approaching, or on track. No new capabilities — this enhances existing rows with urgency signaling.

</domain>

<decisions>
## Implementation Decisions

### Urgency thresholds
- **Red** — delivery date is today OR has passed, AND status is still pending (today counts as expired)
- **Orange** — delivery date is 1-3 calendar days away (upcoming, not yet expired)
- **Green** — delivery date is more than 3 days away
- Red persists as long as the item remains pending — clears only when marked delivered/cancelled/etc.

### Border styling
- 3px solid left border on each `<tr>` row
- Color matches urgency tier: red / orange / green
- Use Phoenix theme color variables (not hardcoded hex)
- No background tint — border only
- Delivered/completed rows: no urgency border (only pending rows get borders)

### Delivery date text
- No color change on delivery date text — border alone communicates urgency
- No "X days overdue" subtext

### Status badge
- PENDING badge stays as-is regardless of urgency
- No OVERDUE badge — the left border is the sole urgency indicator

### Claude's Discretion
- Exact Phoenix color variable mapping for red/orange/green
- Whether urgency is computed in PHP (presenter) or JS (client-side)
- CSS class naming convention

</decisions>

<specifics>
## Specific Ideas

- User referenced the Phoenix color profile — must use existing Phoenix theme colors, not custom values
- The existing notification system already uses "orange" (7d) and "red" (5d) concepts for email alerts — urgency borders are a separate visual concern with different thresholds (3d/0d vs 7d/5d)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 20-material-tracking-urgency*
*Context gathered: 2026-02-10*
