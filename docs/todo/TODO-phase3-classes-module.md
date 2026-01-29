# Phase 3: Classes Module Migration TODO

**Started:** 2026-01-29
**Status:** COMPLETE

## Tasks

- [x] Create `src/Classes/` directory structure
- [x] Migrate `ClassModel.php` → extend `BaseModel`
  - Source: `../wecoza-classes-plugin/app/Models/ClassModel.php`
  - Target: `src/Classes/Models/ClassModel.php`
  - Namespace: `WeCoza\Classes\Models`
- [x] Migrate `QAModel.php` → extend `BaseModel`
  - Source: `../wecoza-classes-plugin/app/Models/QAModel.php`
  - Target: `src/Classes/Models/QAModel.php`
- [x] Migrate `QAVisitModel.php` → extend `BaseModel`
  - Source: `../wecoza-classes-plugin/app/Models/QAVisitModel.php`
  - Target: `src/Classes/Models/QAVisitModel.php`
- [x] Migrate `ClassRepository.php` → extend `BaseRepository`
  - Source: `../wecoza-classes-plugin/app/Repositories/ClassRepository.php`
  - Target: `src/Classes/Repositories/ClassRepository.php`
- [x] Migrate `ScheduleService.php`
  - Source: `../wecoza-classes-plugin/app/Services/ScheduleService.php`
  - Target: `src/Classes/Services/ScheduleService.php`
- [x] Migrate `FormDataProcessor.php`
  - Source: `../wecoza-classes-plugin/app/Services/FormDataProcessor.php`
  - Target: `src/Classes/Services/FormDataProcessor.php`
- [x] Migrate `ClassController.php` → extend `BaseController`
  - Source: `../wecoza-classes-plugin/app/Controllers/ClassController.php`
  - Target: `src/Classes/Controllers/ClassController.php`
- [x] Migrate `ClassAjaxController.php` → extend `BaseController`
  - Source: `../wecoza-classes-plugin/app/Controllers/ClassAjaxController.php`
  - Target: `src/Classes/Controllers/ClassAjaxController.php`
- [x] Migrate `ClassTypesController.php` → extend `BaseController`
  - Source: `../wecoza-classes-plugin/app/Controllers/ClassTypesController.php`
  - Target: `src/Classes/Controllers/ClassTypesController.php`
- [x] Migrate `PublicHolidaysController.php` → extend `BaseController`
  - Source: `../wecoza-classes-plugin/app/Controllers/PublicHolidaysController.php`
  - Target: `src/Classes/Controllers/PublicHolidaysController.php`
- [x] Migrate `QAController.php` → extend `BaseController`
  - Source: `../wecoza-classes-plugin/app/Controllers/QAController.php`
  - Target: `src/Classes/Controllers/QAController.php`
- [x] Copy views to `views/classes/`
- [x] Copy assets (CSS/JS) to `assets/`
- [x] Updated wecoza-core.php to initialize module
- [x] Syntax check all files

## Files Created

```
src/Classes/
├── Controllers/
│   ├── ClassController.php        # Shortcodes, page management
│   ├── ClassAjaxController.php    # All AJAX endpoints
│   ├── ClassTypesController.php   # Class type/subject lookup
│   ├── PublicHolidaysController.php # Holiday management
│   └── QAController.php           # QA analytics
├── Models/
│   ├── ClassModel.php             # Main class entity
│   ├── QAModel.php                # QA analytics model
│   └── QAVisitModel.php           # QA visit tracking
├── Repositories/
│   └── ClassRepository.php        # Data access layer
└── Services/
    ├── ScheduleService.php        # Schedule generation
    └── FormDataProcessor.php      # Form data processing

views/classes/
├── qa-analytics-dashboard.php
├── qa-dashboard-widget.php
└── components/
    ├── class-capture-form.view.php
    ├── classes-display.view.php
    ├── single-class-display.view.php
    ├── class-capture-partials/
    │   ├── create-class.php
    │   └── update-class.php
    └── single-class/
        ├── calendar.php
        ├── details-general.php
        ├── details-logistics.php
        ├── details-staff.php
        ├── header.php
        ├── modal-learners.php
        ├── notes.php
        ├── qa-reports.php
        ├── schedule-monthly.php
        ├── schedule-stats.php
        └── summary-cards.php

assets/js/classes/
├── class-capture.js
├── class-schedule-form.js
├── class-types.js
├── classes-table-search.js
├── learner-level-utils.js
├── learner-selection-table.js
├── qa-dashboard.js
├── single-class-display.js
├── wecoza-calendar.js
├── wecoza-classes-admin.js
└── utils/
    ├── ajax-utils.js
    ├── date-utils.js
    ├── escape.js
    └── table-manager.js
```

## Notes

- Skip ClassTypesController-bu.php (backup file)
- DatabaseService.php already exists as core/Database/PostgresConnection.php
- Views and assets can be copied later or during Phase 4 consolidation

## Shortcodes Registered

| Shortcode | Method |
|-----------|--------|
| wecoza_capture_class | captureClassShortcode |
| wecoza_display_classes | displayClassesShortcode |
| wecoza_display_single_class | displaySingleClassShortcode |
| qa_dashboard_widget | renderQADashboardWidget |
| qa_analytics_dashboard | renderQAAnalyticsDashboard |

## AJAX Handlers Registered

- save_class, delete_class, get_calendar_events
- get_class_subjects, get_class_notes, save_class_note, delete_class_note
- upload_attachment, get_public_holidays
- QA: get_qa_analytics, get_qa_summary, get_qa_visits, create_qa_visit
- QA: export_qa_reports, delete_qa_report, get_class_qa_data, submit_qa_question

## Verification (Pending)

- [ ] Test class capture form (create mode)
- [ ] Test class capture form (update mode)
- [ ] Test class list display
- [ ] Test single class display
- [ ] Test calendar events
- [ ] Test QA dashboard

## Next Steps

1. ~~Copy views to `views/classes/` directory~~ ✓
2. ~~Copy assets (CSS/JS) to `assets/` directory~~ ✓
3. Activate plugin and test functionality
4. Proceed to Phase 4: Consolidation
