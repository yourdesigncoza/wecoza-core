# Requirements: WeCoza Core v3.1 — Form Field Wiring Fixes

**Defined:** 2026-02-13
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin architecture
**Source:** `docs/formfieldanalysis/*.md` (comprehensive form field wiring audits)

## v3.1 Requirements

### Learners Module (LRNR)

- [ ] **LRNR-01**: Fix `numeracy_level` missing from update shortcode POST processing — data silently lost on every learner update
- [ ] **LRNR-02**: Resolve `sponsors[]` orphaned field — either implement sponsor persistence or remove UI from both forms
- [ ] **LRNR-03**: Clean up phantom fields — remove `date_of_birth` and `suburb` from baseline doc, remove `suburb` from repo insert whitelist
- [ ] **LRNR-04**: Remove duplicate `placement_assessment_date` field from create form (lines 414-422)
- [ ] **LRNR-05**: Remove `nopriv` AJAX registrations for `fetch_learners_data` and `fetch_learners_dropdown_data`
- [ ] **LRNR-06**: Fix `employment_status` initial visibility bug in update form — employer div always visible regardless of status
- [ ] **LRNR-07**: Use `intval()` instead of `sanitize_text_field()` for `highest_qualification` FK ID in both shortcodes
- [ ] **LRNR-08**: Add date format validation for `placement_assessment_date` in both shortcodes
- [ ] **LRNR-09**: Fix template literal XSS risk — use `.text()` instead of `.html()` for dynamic content in `learners-app.js`
- [ ] **LRNR-10**: Clean up dead code — remove unused controller AJAX registrations, legacy `#editLearnerForm` handler, `populateEditForm()`, and unused `learner-form.view.php`

### Classes Module (CLS)

- [ ] **CLS-01**: Fix `order_nr` reverse path — add to `getSingleClass()` result array so value isn't overwritten with null on update
- [ ] **CLS-02**: Set `class_agent` from `initial_class_agent` on create so new classes don't start with null
- [ ] **CLS-03**: Remove `nopriv` from QA write endpoints (`create_qa_visit`, `delete_qa_report`, `export_qa_reports`, `submit_qa_question`)
- [ ] **CLS-04**: Sanitize `stop_dates[]`/`restart_dates[]` with `sanitizeText()` and date format validation in FormDataProcessor
- [ ] **CLS-05**: Type-cast `site_id` with `intval()` in FormDataProcessor
- [ ] **CLS-06**: Sanitize `learner_ids`/`exam_learners` per-entry after JSON decode — filter with `array_map('intval', ...)`
- [ ] **CLS-07**: Fix `initial_class_agent` pre-selection — use `initial_class_agent` value instead of `class_agent` in update view
- [ ] **CLS-08**: Migrate agents/supervisors from hardcoded static arrays to DB queries in ClassRepository
- [ ] **CLS-09**: Validate `backup_agent_dates[]` as valid date format in FormDataProcessor

### Agents Module (AGT)

- [ ] **AGT-01**: Fix `postal_code` → `residential_postal_code` mapping in `FormHelpers::$field_mapping` for edit mode pre-population
- [ ] **AGT-02**: Add server-side validation for 14 HTML-required fields missing from `validateFormData()`
- [ ] **AGT-03**: Sanitize `preferred_working_area_1/2/3` with `absint()` in controller `collectFormData()`
- [ ] **AGT-04**: Remove `agent_notes` from agents table insert/update whitelist (notes managed via separate table)
- [ ] **AGT-05**: Remove `residential_town_id` from insert whitelist (no form field populates it)
- [ ] **AGT-06**: Extract shared display methods (`getAgentStatistics`, `mapAgentFields`, `mapSortColumn`, `getDisplayColumns`) into shared service — DRY

### Clients Module (CLT)

- [ ] **CLT-01**: Remove inline submit handler from `client-update-form.view.php` — eliminates duplicate AJAX call on every update
- [ ] **CLT-02**: Add `wp_nonce_field()` to `client-capture-form.view.php` for non-AJAX fallback compatibility
- [ ] **CLT-03**: Remove `client_town_id` from `ClientRepository` insert/update whitelists (column doesn't exist, dead code)
- [ ] **CLT-04**: Unify nonce action strings across all client forms and controllers to `clients_nonce_action`
- [ ] **CLT-05**: Remove 7 unused AJAX endpoints from `ClientAjaxHandlers` (save_location, sub-site management, etc.)

### Events Module (EVT)

- [ ] **EVT-01**: Add late escaping for `summary_html` output in `card.php`, `timeline.php`, `item.php` views
- [ ] **EVT-02**: Add late escaping for `notification_badge_html`/`status_badge_html` in `list-item.php`
- [ ] **EVT-03**: Update `MaterialTrackingRepository::markDelivered()` to also set `materials_delivered_at` and `delivery_status = 'delivered'` on tracking table
- [ ] **EVT-04**: Remove duplicate test notification JS (consolidate from `SettingsPage.php` and `notification-settings.php`)

## Future Requirements

### Learners
- **LRNR-F01**: Implement sponsor persistence (new `learner_sponsors` table + POST processing) — if sponsors feature is desired
- **LRNR-F02**: Province → city → suburb cascade (like Clients module) — currently independent dropdowns

### Classes
- **CLS-F01**: Investigate `order_nr_metadata` orphaned JSONB column — drop or wire into form
- **CLS-F02**: Implement `class_agent` update mechanism when agent replacements are processed

## Out of Scope

| Feature | Reason |
|---------|--------|
| Info-level audit observations | Observations from audits that don't require code changes |
| New features or UI additions | This milestone is strictly bugfix/hardening |
| Database schema changes (DDL) | All fixes are application-level; no ALTER TABLE needed |
| Refactoring beyond identified issues | Only fix what audits identified |
| v3.0 requirements (ARCH/SC/FEAT/CLN) | Completed in previous milestone |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| LRNR-01 | Phase 31 | Pending |
| LRNR-02 | Phase 31 | Pending |
| LRNR-03 | Phase 31 | Pending |
| LRNR-04 | Phase 31 | Pending |
| LRNR-05 | Phase 31 | Pending |
| LRNR-06 | Phase 31 | Pending |
| LRNR-07 | Phase 31 | Pending |
| LRNR-08 | Phase 31 | Pending |
| LRNR-09 | Phase 31 | Pending |
| LRNR-10 | Phase 31 | Pending |
| CLS-01 | Phase 32 | Pending |
| CLS-02 | Phase 32 | Pending |
| CLS-03 | Phase 32 | Pending |
| CLS-04 | Phase 32 | Pending |
| CLS-05 | Phase 32 | Pending |
| CLS-06 | Phase 32 | Pending |
| CLS-07 | Phase 32 | Pending |
| CLS-08 | Phase 32 | Pending |
| CLS-09 | Phase 32 | Pending |
| AGT-01 | Phase 33 | Pending |
| AGT-02 | Phase 33 | Pending |
| AGT-03 | Phase 33 | Pending |
| AGT-04 | Phase 33 | Pending |
| AGT-05 | Phase 33 | Pending |
| AGT-06 | Phase 33 | Pending |
| CLT-01 | Phase 34 | Pending |
| CLT-02 | Phase 34 | Pending |
| CLT-03 | Phase 34 | Pending |
| CLT-04 | Phase 34 | Pending |
| CLT-05 | Phase 34 | Pending |
| EVT-01 | Phase 35 | Pending |
| EVT-02 | Phase 35 | Pending |
| EVT-03 | Phase 35 | Pending |
| EVT-04 | Phase 35 | Pending |

**Coverage:**
- v3.1 requirements: 34 total
- Mapped to phases: 34
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-13*
*Last updated: 2026-02-13 after initial definition*
