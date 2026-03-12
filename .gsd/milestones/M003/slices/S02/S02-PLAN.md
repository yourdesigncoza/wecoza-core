# S02: Dashboard UI + SystemPulse Integration

**Goal:** Build the dashboard view, JS DataTable, and SystemPulse attention item
**Demo:** Full working dashboard page with live data, filters, inline resolve, and SystemPulse count

## Must-Haves

- Shortcode `[wecoza_excessive_hours_report]` rendering dashboard view
- DataTable with AJAX loading, pagination, sorting, filtering
- Inline resolve UI: action dropdown + notes + resolve button
- Summary cards: Open count, Resolved (last 30 days), Total flagged
- Default filter to Open items
- SystemPulse attention item with lightweight count query
- CSS styles in ydcoza-styles.css

## Proof Level

- This slice proves: integration
- Real runtime required: yes
- Human/UAT required: yes (visual verification in browser)

## Verification

- Browser: navigate to dashboard page, verify data loads
- Browser: resolve an item, verify it shows as resolved
- Browser: verify SystemPulse shows excessive hours count
- Browser: verify filters work (Open / Resolved / All)

## Observability / Diagnostics

- Runtime signals: JS console errors, AJAX network responses
- Inspection surfaces: browser DevTools network tab, DataTable state
- Failure visibility: AJAX error messages displayed in UI toast/alert
- Redaction constraints: none

## Integration Closure

- Upstream surfaces consumed: S01 AJAX endpoints, S01 ExcessiveHoursService
- New wiring introduced: shortcode registration, SystemPulse modification
- What remains: nothing — milestone complete after this slice

## Tasks

- [ ] **T01: Shortcode + Dashboard view + CSS** `est:1h`
  - Why: The page shell, view template, and styles
  - Files: `src/Reports/ExcessiveHours/ExcessiveHoursShortcode.php`, `views/reports/excessive-hours/dashboard.php`, `ydcoza-styles.css`
  - Do: Register shortcode, build dashboard view with summary cards + DataTable container + filter controls. CSS for cards, status badges, resolve form
  - Verify: Browser — page loads with correct layout structure
  - Done when: Shortcode renders page shell with empty DataTable

- [ ] **T02: JavaScript DataTable + resolve workflow** `est:1h`
  - Why: The interactive data layer
  - Files: `assets/js/reports/excessive-hours-dashboard.js`
  - Do: Init DataTable with AJAX source, columns, sorting, default Open filter. Inline resolve form with action dropdown + notes. AJAX submit resolve. Success/error feedback
  - Verify: Browser — data loads, resolve works, status updates
  - Done when: Full CRUD cycle works in browser

- [ ] **T03: SystemPulse integration + final verification** `est:30m`
  - Why: Surface the count on the main dashboard
  - Files: `src/Events/Shortcodes/SystemPulseShortcode.php`
  - Do: Add lightweight COUNT query to gatherAttentionItems(). Show icon + count + label
  - Verify: Browser — SystemPulse card shows excessive hours count
  - Done when: Count appears correctly, links to dashboard page

## Files Likely Touched

- `src/Reports/ExcessiveHours/ExcessiveHoursShortcode.php`
- `views/reports/excessive-hours/dashboard.php`
- `assets/js/reports/excessive-hours-dashboard.js`
- `src/Events/Shortcodes/SystemPulseShortcode.php`
- `ydcoza-styles.css` (theme file)
