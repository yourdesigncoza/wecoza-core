# WeCoza Core - Form Fields Reference

Complete inventory of all form fields across every module in the WeCoza Core plugin.

**Last updated:** 2026-02-12

---

## Table of Contents

- [Agents Module](#agents-module)
- [Clients Module](#clients-module)
- [Classes Module](#classes-module)
- [Learners Module](#learners-module)
- [Events Module](#events-module)
- [Summary](#summary)

---

## Agents Module

**Forms:** Capture (add new agent), Edit (update existing agent)
**Main view:** `views/agents/components/agent-capture-form.view.php`
**Model:** `src/Agents/Models/AgentModel.php`
**Repository:** `src/Agents/Repositories/AgentRepository.php`

### Personal Information

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `title` | title | select | Title | No | - |
| `first_name` | first_name | text | First Name | Yes | - |
| `second_name` | second_name | text | Second Name | No | - |
| `surname` | surname | text | Surname | Yes | - |
| `initials` | initials | text | Initials (Auto) | No | Auto-generated, readonly |
| `gender` | gender | select | Gender | Yes | M/F |
| `race` | race | select | Race | Yes | African, Coloured, White, Indian |

### Identification & Contact

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `id_type` | id_type | radio | Identification Type | Yes | sa_id or passport |
| `sa_id_no` | sa_id_no | text | SA ID Number | Conditional | 13 digits, Luhn checksum. Required if id_type=sa_id |
| `passport_number` | passport_number | text | Passport Number | Conditional | Min 6 chars. Required if id_type=passport |
| `tel_number` | tel_number | text | Telephone Number | Yes | Min 10 digits |
| `email_address` | email_address | email | Email | Yes | Valid email, uniqueness check |

### Address

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `google_address_search` | - | text | Search Address | No | Google Places autocomplete helper |
| `address_line_1` | residential_address_line | text | Street Address | Yes | - |
| `address_line_2` | address_line_2 | text | Unit/Complex | No | - |
| `residential_suburb` | residential_suburb | text | Suburb | No | - |
| `city_town` | city | text | City/Town | Yes | - |
| `province_region` | province | select | Province/Region | Yes | 9 SA provinces |
| `postal_code` | residential_postal_code | text | Postal Code | Yes | Numeric |

### SACE Registration

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `sace_number` | sace_number | text | SACE Registration Number | No | - |
| `sace_registration_date` | sace_registration_date | date | SACE Registration Date | No | Valid date |
| `sace_expiry_date` | sace_expiry_date | date | SACE Expiry Date | No | Valid date |

### Preferred Working Areas

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `preferred_working_area_1` | preferred_working_area_1 | select | Preferred Working Area 1 | Yes | At least one required |
| `preferred_working_area_2` | preferred_working_area_2 | select | Preferred Working Area 2 | No | - |
| `preferred_working_area_3` | preferred_working_area_3 | select | Preferred Working Area 3 | No | - |

### Phase & Subjects

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `phase_registered` | phase_registered | select | Phase Registered | No | Foundation, Intermediate, Senior, FET |
| `subjects_registered` | subjects_registered | text | Subjects Registered | Yes | - |
| `highest_qualification` | highest_qualification | text | Highest Qualification | Yes | - |
| `agent_training_date` | agent_training_date | date | Agent Training Date | Yes | Valid date |

### Quantum Assessments

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `quantum_assessment` | quantum_assessment | number | Quantum Assessment % | Yes | 0-100 |
| `quantum_maths_score` | quantum_maths_score | number | Quantum Maths Score % | Yes | 0-100 |
| `quantum_science_score` | quantum_science_score | number | Quantum Science Score % | Yes | 0-100 |

### Legal & Compliance

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `criminal_record_date` | criminal_record_date | date | Criminal Record Check Date | No | Valid date |
| `criminal_record_file` | criminal_record_file | file | Upload Criminal Record | No | PDF, DOC, DOCX |
| `signed_agreement_date` | signed_agreement_date | date | Agreement Signed Date | Yes | Valid date |
| `signed_agreement_file` | signed_agreement_file | file | Upload Signed Agreement | Conditional | Required on add, optional on edit if exists |

### Banking Details

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `bank_name` | bank_name | text | Bank Name | Yes | - |
| `account_holder` | account_holder | text | Account Holder Name | Yes | - |
| `account_number` | bank_account_number | text | Account Number | Yes | Numeric |
| `branch_code` | bank_branch_code | text | Branch Code | Yes | Numeric |
| `account_type` | account_type | select | Account Type | Yes | Savings, Current, Transmission |

### Hidden/System Fields

| Field Name | Type | Purpose |
|---|---|---|
| `editing_agent_id` | hidden | Agent ID (edit mode only) |
| `wecoza_agents_form_nonce` | hidden | CSRF nonce |

**Agents total: 45 fields** (13 text, 7 date, 3 number, 1 email, 9 select, 1 radio, 2 file, 2 hidden)

---

## Clients Module

**Forms:** Client Capture, Client Update, Location Capture, Location Edit
**Main views:** `views/clients/components/client-capture-form.view.php`, `views/clients/components/location-capture-form.view.php`
**Models:** `src/Clients/Models/ClientsModel.php`, `src/Clients/Models/LocationsModel.php`
**Repository:** `src/Clients/Repositories/ClientRepository.php`, `src/Clients/Repositories/LocationRepository.php`

### Client Capture/Update Form

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `id` | id | hidden | - | No (edit: yes) | Integer |
| `head_site_id` | head_site_id | hidden | - | No | Integer |
| `client_name` | client_name | text | Client Name | Yes | String |
| `company_registration_nr` | company_registration_nr | text | Company Registration Nr | Yes | String |
| `site_name` | site_name | text | Site Name | Yes | String |
| `is_sub_client` | - | checkbox | Is SubClient | No | Boolean (toggles main_client_id) |
| `main_client_id` | main_client_id | select | Main Client | Conditional | Required if is_sub_client checked |
| `client_province` | - | select | Province | Yes | 9 SA provinces, cascades to town |
| `client_town` | - | select | Town | Yes | Cascades from province |
| `client_town_id` | client_town_id | select | Suburb | Yes | FK to locations table |
| `client_postal_code` | - | text | Client Postal Code | Yes | Readonly, auto-populated |
| `client_suburb` | - | hidden | - | No | Auto-populated |
| `client_town_name` | - | hidden | - | No | Auto-populated |
| `client_street_address` | - | text | Client Street Address | Yes | Readonly if location selected |
| `contact_person` | contact_person | text | Contact Person | Yes | String |
| `contact_person_email` | contact_person_email | email | Contact Person Email | Yes | Valid email |
| `contact_person_cellphone` | contact_person_cellphone | tel | Contact Person Cellphone | Yes | Phone format |
| `contact_person_tel` | contact_person_tel | tel | Contact Person Tel Number | No | Phone format |
| `contact_person_position` | contact_person_position | text | Contact Person Position | No | String |
| `seta` | seta | select | SETA | Yes | Config SETA list |
| `client_status` | client_status | select | Client Status | Yes | Config status list |
| `financial_year_end` | financial_year_end | date | Financial Year End | Yes | Valid date |
| `bbbee_verification_date` | bbbee_verification_date | date | BBBEE Verification Date | Yes | Valid date |

### Location Capture/Edit Form

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `location_id` | location_id | hidden | - | No (edit: yes) | Integer |
| `wecoza_clients_google_address_search` | - | text | Search Address | No | Google Maps autocomplete |
| `street_address` | street_address | text | Street Address | Yes | Max 200 chars |
| `suburb` | suburb | text | Suburb | Yes | Max 50 chars |
| `town` | town | text | Town / City | Yes | Max 50 chars |
| `province` | province | select | Province | Yes | 9 SA provinces |
| `postal_code` | postal_code | text | Postal Code | Yes | Max 10 chars |
| `latitude` | latitude | text | Latitude | Yes | Decimal, -90 to 90 |
| `longitude` | longitude | text | Longitude | Yes | Decimal, -180 to 180 |
| `wecoza_locations_form_nonce` | - | hidden | - | Yes | CSRF nonce |

**Clients total: 33 fields** (23 client form + 10 location form)

---

## Classes Module

**Forms:** Class Capture (create), Class Update (edit)
**Main views:** `views/classes/components/class-capture-partials/create-class.php`, `update-class.php`
**Model:** `src/Classes/Models/ClassModel.php`
**Service:** `src/Classes/Services/FormDataProcessor.php`

### Basic Class Details

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `class_id` | class_id | hidden | Auto-Generated Class ID | No | Value 'auto-generated' for new |
| `client_id` | client_id | select | Client Name (ID) | Yes | FK to clients |
| `site_id` | site_id | select | Class/Site Name | Yes | Grouped by client |
| `site_address` | class_address_line | text (readonly) | Address | No | Auto-populated from site |
| `class_type` | class_type | select | Class Type | Yes | Populates class_subject options |
| `class_subject` | class_subject | select | Class Subject | Yes | Disabled until class_type selected |
| `class_duration` | class_duration | number (readonly) | Duration (Hours) | No | Auto-calculated |
| `class_code` | class_code | text (readonly) | Class Code | No | Auto-generated [Abbr][mm][dd][hh] |
| `class_start_date` | original_start_date | date | Class Start Date | Yes | Triggers auto-population |
| `nonce` | - | hidden | Security Token | Yes | CSRF nonce |
| `redirect_url` | - | hidden | Redirect URL | No | Post-submit redirect |

### Schedule

| Field Name | DB Column (JSON path) | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `schedule_pattern` | schedule_data.pattern | select | Schedule Pattern | Yes | weekly, biweekly, monthly, custom |
| `schedule_days[]` | schedule_data.selectedDays | checkbox array | Days of Week | Yes | At least one. Mon-Sun checkboxes |
| `schedule_day_of_month` | schedule_data.dayOfMonth | select | Day of Month | Conditional | 1-31 or 'last'. Shown if monthly |
| `schedule_start_date` | - | hidden date | Schedule Start Date | No | Populated from class_start_date |
| `schedule_end_date` | - | date (readonly) | Estimated End Date | Yes | Calculated via JS |
| `schedule_total_hours` | - | text (readonly) | Total Hours | No | Auto-calculated |
| `day-start-time` (per day) | schedule_data.perDayTimes.startTime | select | Start Time | Yes | 6:00 AM - 8:00 PM, 30-min steps |
| `day-end-time` (per day) | schedule_data.perDayTimes.endTime | select | End Time | Yes | 6:30 AM - 8:30 PM, 30-min steps |

### Exception Dates (Dynamic Array)

| Field Name | DB Column (JSON path) | Type | Label | Required |
|---|---|---|---|---|
| `exception_dates[]` | schedule_data.exceptionDates | date | Date | No |
| `exception_reasons[]` | schedule_data.exceptionReasons | select | Reason | No | Options: Client Cancelled, Agent Absent, Public Holiday, Other |

### Stop/Restart Dates (Dynamic Array)

| Field Name | DB Column (JSON path) | Type | Label | Required |
|---|---|---|---|---|
| `stop_dates[]` | stop_restart_dates[].stopDate | date | Stop Date | No |
| `restart_dates[]` | stop_restart_dates[].restartDate | date | Restart Date | No |

### Event Dates (Dynamic Array)

| Field Name | DB Column (JSON path) | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `event_types[]` | event_dates[].type | select | Event Type | No | Deliveries, Collections, Exams, Mock Exams, SBA Collection, Learner Packs, QA Visit, SETA Exit |
| `event_descriptions[]` | event_dates[].description | text | Description | No | Max 255 chars |
| `event_dates_input[]` | event_dates[].date | date | Date | No | Valid date |
| `event_statuses[]` | event_dates[].status | select | Status | No | Pending, Completed, Cancelled |
| `event_notes[]` | event_dates[].notes | text | Notes | No | Max 500 chars |

### Funding & Exam

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `seta_funded` | seta_funded | select | SETA Funded? | Yes | Yes/No. Toggles seta_id |
| `seta_id` | seta | select | SETA | Conditional | Shown if seta_funded=Yes |
| `exam_class` | exam_class | select | Exam Class | Yes | Yes/No. Toggles exam_type |
| `exam_type` | exam_type | text | Exam Type | Conditional | Shown if exam_class=Yes |

### Learners (Dynamic)

| Field Name | DB Column | Type | Label | Required |
|---|---|---|---|---|
| `learner-search-input` | - | text | Search learners... | No |
| Learner checkboxes (dynamic) | - | checkbox array | Learner Selection | No |
| `class_learners_data` | learner_ids | hidden | Learner data JSON | No |
| `exam_learner_select[]` | exam_learners | multi-select | Select Exam Learners | Conditional |
| `exam_learners` | exam_learners | hidden | Exam learner data JSON | Conditional |

### Agents & Assignments

| Field Name | DB Column | Type | Label | Required |
|---|---|---|---|---|
| `initial_class_agent` | initial_class_agent | select | Initial Class Agent | Yes |
| `initial_agent_start_date` | initial_agent_start_date | date | Start Date | Yes |
| `project_supervisor` | project_supervisor_id | select | Project Supervisor | Yes |
| `backup_agent_ids[]` | backup_agent_ids (JSON) | select (dynamic array) | Backup Agent | No |
| `backup_agent_dates[]` | backup_agent_ids[].date (JSON) | date (dynamic array) | Backup Date | No |
| `replacement_agent_ids[]` | agent_replacements (JSON) | select (dynamic, update only) | Replacement Agent | No |
| `replacement_agent_dates[]` | agent_replacements[].date (JSON) | date (dynamic, update only) | Takeover Date | No |

### Notes & QA (Update Mode Only)

| Field Name | DB Column | Type | Label | Required |
|---|---|---|---|---|
| `note-content` (modal) | class_notes_data (JSON) | textarea | Class Note | No |
| `note-priority` (modal) | class_notes_data (JSON) | select | Priority | No | high/medium/low |
| `note-category` (modal) | class_notes_data (JSON) | select | Category | No |
| `qa_visit_dates[]` | - | date (dynamic) | QA Visit Date | No |
| `qa_visit_types[]` | - | select (dynamic) | QA Type | No | Initial, Follow-up, Compliance, Final |
| `qa_officers[]` | - | text (dynamic) | QA Officer | No |
| `qa_reports[]` | - | file (dynamic) | QA Report | No | PDF only |

**Classes total: 50+ fields** (many are dynamic arrays with unlimited rows)

---

## Learners Module

**Forms:** Registration (add), Update (edit), LP Progression, Portfolio Upload
**Main views:** `views/learners/learner-form.view.php`, `views/learners/components/learner-progressions.php`
**Model:** `src/Learners/Models/LearnerModel.php`
**Repository:** `src/Learners/Repositories/LearnerRepository.php`

### Personal Information

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `title` | title | select | Title | No | Mr., Mrs., Dr., etc. |
| `first_name` | first_name | text | First Name | Yes | - |
| `second_name` | second_name | text | Second Name | No | - |
| `initials` | initials | text | Initials | No | Auto-generated, readonly |
| `surname` | surname | text | Surname | Yes | - |
| `gender` | gender | select | Gender | No | - |
| `race` | race | select | Race | No | - |

### Identification & Contact

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `id_type` | - | radio | Identification Type | Yes | sa_id or passport |
| `sa_id_no` | sa_id_no | text | SA ID Number | Conditional | 13 digits, YYMMDD format, Luhn checksum. Required if id_type=sa_id |
| `passport_number` | passport_number | text | Passport Number | Conditional | 6-12 alphanumeric. Required if id_type=passport |
| `tel_number` | tel_number | tel | Telephone Number | No | - |
| `alternative_tel_number` | alternative_tel_number | tel | Alternative Tel Number | No | - |
| `email_address` | email_address | email | Email Address | No | Valid email |
| `date_of_birth` | date_of_birth | date | Date of Birth | No | - |

### Address

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `address_line_1` | address_line_1 | text | Address Line 1 | No | - |
| `address_line_2` | address_line_2 | text | Address Line 2 | No | - |
| `suburb` | suburb | text | Suburb | No | - |
| `city_town_id` | city_town_id | select | City/Town | No | FK to locations |
| `province_region_id` | province_region_id | select | Province/Region | No | FK to locations |
| `postal_code` | postal_code | text | Postal Code | No | - |

### Qualifications & Assessment

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `highest_qualification` | highest_qualification | select | Highest Qualification | No | FK to learner_qualifications |
| `assessment_status` | assessment_status | select | Assessment Status | No | "Assessed" / "Not Assessed" |
| `placement_assessment_date` | placement_assessment_date | date | Placement Assessment Date | Conditional | Shown if assessment_status=Assessed |
| `numeracy_level` | numeracy_level | select | Numeracy Level | Conditional | FK to learner_placement_level. Shown if Assessed |
| `communication_level` | communication_level | select | Communication Level | Conditional | FK to learner_placement_level. Shown if Assessed |

### Employment

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `employment_status` | employment_status | select | Employment Status | No | Employed / Unemployed |
| `employer_id` | employer_id | select | Employer | Conditional | FK to employers. Shown if Employed |
| `disability_status` | disability_status | select | Disability Status | No | Boolean |

### LP Progression (Managed by ProgressionService)

| Field Name | DB Column | Type | Label | Context |
|---|---|---|---|---|
| `learner_id` | learner_id | integer | Learner | Auto-assigned |
| `product_id` | product_id | integer | LP/Product | Class assignment |
| `class_id` | class_id | integer | Class | Optional FK |
| `hours_trained` | hours_trained | float | Total Trained Hours | Auto-calculated |
| `hours_present` | hours_present | float | Attendance Hours | Auto-calculated |
| `hours_absent` | hours_absent | float | Absence Hours | Auto-calculated |
| `status` | status | enum | Status | in_progress, completed, on_hold |
| `start_date` | start_date | date | LP Start Date | Auto: current date |
| `completion_date` | completion_date | date | LP Completion Date | Auto: on mark complete |
| `notes` | notes | text | LP Notes | Optional |

### Portfolio Upload

| Field Name | DB Column | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `tracking_id` | tracking_id | hidden | LP Tracking ID | Yes | Auto-generated |
| `portfolio_file` | portfolio_file_path | file | Portfolio Upload | No | PDF, DOC, DOCX; max 10MB |
| `nonce` | - | hidden | CSRF Nonce | Yes | learners_nonce |

### Hidden/System Fields

| Field Name | Type | Purpose |
|---|---|---|
| `wecoza_learner_nonce` | hidden | CSRF nonce (form POST) |
| `nonce` | hidden | CSRF nonce (AJAX) |

**Learners total: 41 fields** (27 base learner + 11 progression + 3 portfolio)

---

## Events Module

**Forms:** Event Tasks, Material Tracking, AI Summary Notifications, Admin Settings
**Main views:** `views/events/event-tasks/`, `views/events/material-tracking/`, `views/events/ai-summary/`, `views/events/admin/`
**Controllers:** `src/Events/Controllers/TaskController.php`, `src/Events/Controllers/MaterialTrackingController.php`

### Event Tasks Form (`[wecoza_event_tasks]`)

| Field Name | Type | Label | Required | Validation |
|---|---|---|---|---|
| Search input (`data-role="tasks-search"`) | text | Search clients, classes, or agents | No | Client-side filter |
| Filter select (`data-role="open-task-filter"`) | select | Filter by open task | No | Dynamic options |
| `wecoza-note-{classId}-{taskId}` | text | Task note | Conditional | Required when `data-note-required="1"` |
| `class_id` | hidden | Class ID | Yes | AJAX param |
| `task_id` | hidden | Task ID | Yes | AJAX param |
| `task_action` | hidden | Action | Yes | "complete" or "reopen" |
| `nonce` | hidden | CSRF | Yes | wecoza_events_tasks |

### Material Tracking Form (`[wecoza_material_tracking]`)

| Field Name | Type | Label | Required | Validation |
|---|---|---|---|---|
| Search input (`id="material-tracking-search"`) | search | Search by class code, subject, or client | No | Client-side filter |
| Status filter (`id="status-filter"`) | select | Status | No | all, pending, completed |
| Mark delivered checkbox | checkbox | Mark Delivered | No | `data-class-id`, `data-event-index` |
| `class_id` | hidden | Class ID | Yes | AJAX param |
| `event_index` | hidden | Event Index | Yes | 0-based index into event_dates JSONB |
| `nonce` | hidden | CSRF | Yes | wecoza_material_tracking_action |

### AI Summary Notifications (`[wecoza_insert_update_ai_summary]`)

| Field Name | Type | Label | Required | Validation |
|---|---|---|---|---|
| Search input (`data-role="ai-search"`) | search | Search by class code, subject, or summary | No | Client-side filter |
| Operation filter (`data-role="operation-filter"`) | select | Event type | No | INSERT, UPDATE, DELETE |
| Unread filter (`data-role="unread-filter"`) | checkbox | Unread only | No | Toggle |
| Acknowledge button | button | Acknowledge | No | `data-event-id` |
| `event_id` | hidden | Event ID | Yes | AJAX param |
| `nonce` | hidden | CSRF | Yes | wecoza_notification_nonce |

**Shortcode attributes:**

| Attribute | Type | Default | Description |
|---|---|---|---|
| `limit` | integer | 20 | Number of notifications |
| `layout` | string | card | "card" or "timeline" |
| `class_id` | integer | - | Filter by class |
| `operation` | string | - | INSERT, UPDATE, DELETE |
| `unread_only` | boolean | false | Show unread only |
| `show_filters` | boolean | true | Show filter controls |

### Admin Notification Settings (WP Admin)

| Field Name | DB Option | Type | Label | Required | Validation |
|---|---|---|---|---|---|
| `wecoza_ai_summary_enabled` | wecoza_ai_summary_enabled | checkbox | Enable AI summaries | No | Boolean with hidden default=0 |
| `wecoza_ai_summary_api_key` | wecoza_ai_summary_api_key | password | OpenAI API Key | No | sk-... format |
| `wecoza_notification_recipients[CLASS_INSERT]` | wecoza_notification_recipients | textarea | Recipients for new classes | No | Comma-separated emails |
| `wecoza_notification_recipients[CLASS_UPDATE]` | wecoza_notification_recipients | textarea | Recipients for class updates | No | Comma-separated emails |
| `wecoza_notification_recipients[LEARNER_ADD]` | wecoza_notification_recipients | textarea | Recipients for learner additions | No | Comma-separated emails |
| `wecoza_notification_recipients[LEARNER_REMOVE]` | wecoza_notification_recipients | textarea | Recipients for learner removals | No | Comma-separated emails |
| `wecoza_notification_recipients[STATUS_CHANGE]` | wecoza_notification_recipients | textarea | Recipients for status changes | No | Comma-separated emails |

**Events total: ~25 fields** (across 4 form contexts)

---

## Summary

### Field Counts by Module

| Module | Total Fields | Forms |
|---|---|---|
| Agents | 45 | Capture, Edit |
| Clients | 33 | Client Capture/Update, Location Capture/Edit |
| Classes | 50+ | Class Capture, Class Update (with dynamic arrays) |
| Learners | 41 | Registration, Update, LP Progression, Portfolio Upload |
| Events | ~25 | Event Tasks, Material Tracking, AI Summary, Admin Settings |
| **Total** | **~194** | **13 form contexts** |

### Field Types Across All Modules

| Type | Count | Notes |
|---|---|---|
| text | ~45 | Standard text inputs |
| select | ~40 | Dropdowns, some cascading |
| date | ~25 | Date pickers |
| hidden | ~15 | IDs, nonces, JSON data |
| email | 3 | Email validation |
| tel | 4 | Phone number inputs |
| file | 5 | PDF/DOC uploads |
| checkbox | ~8 | Boolean toggles, arrays |
| radio | 2 | ID type selection |
| textarea | ~6 | Notes, recipients |
| number | 4 | Scores, percentages |
| search | 3 | Client-side filter inputs |
| password | 1 | API key |
| multi-select | 1 | Exam learner selection |

### Conditional Fields (Show/Hide Logic)

| Trigger Field | Condition | Shows |
|---|---|---|
| `id_type` = sa_id | Agents, Learners | `sa_id_no` |
| `id_type` = passport | Agents, Learners | `passport_number` |
| `is_sub_client` = checked | Clients | `main_client_id` |
| `client_province` selected | Clients | `client_town` options |
| `client_town` selected | Clients | `client_town_id` (suburb) options |
| `class_type` selected | Classes | `class_subject` options |
| `seta_funded` = Yes | Classes | `seta_id` |
| `exam_class` = Yes | Classes | `exam_type`, exam learner selection |
| `schedule_pattern` = monthly | Classes | `schedule_day_of_month` |
| `assessment_status` = Assessed | Learners | `placement_assessment_date`, `numeracy_level`, `communication_level` |
| `employment_status` = Employed | Learners | `employer_id` |

### JSONB-Stored Fields (PostgreSQL)

These fields are stored as JSON objects/arrays in single database columns:

| DB Column | Module | Contains |
|---|---|---|
| `schedule_data` | Classes | selectedDays, perDayTimes, exceptionDates, startDate, endDate |
| `stop_restart_dates` | Classes | Array of {stopDate, restartDate} |
| `event_dates` | Classes | Array of {type, description, date, status, notes} |
| `learner_ids` | Classes | Array of learner objects |
| `exam_learners` | Classes | Array of exam learner objects |
| `backup_agent_ids` | Classes | Array of {agent_id, date} |
| `agent_replacements` | Classes | Array of {agent_id, date, reason} |
| `class_notes_data` | Classes | Array of note objects |
| `event_data` | Events | new_row, old_row, diff, metadata |
| `ai_summary` | Events | AI-generated summary result |

### Form-to-DB Column Name Mappings

Some form field names differ from their database column names:

| Form Field | DB Column | Module |
|---|---|---|
| `address_line_1` | `residential_address_line` | Agents |
| `city_town` | `city` | Agents |
| `province_region` | `province` | Agents |
| `postal_code` | `residential_postal_code` | Agents |
| `account_number` | `bank_account_number` | Agents |
| `branch_code` | `bank_branch_code` | Agents |
| `class_start_date` | `original_start_date` | Classes |
| `site_address` | `class_address_line` | Classes |
| `project_supervisor` | `project_supervisor_id` | Classes |
| `last_name` / `surname` | `surname` | Learners |
