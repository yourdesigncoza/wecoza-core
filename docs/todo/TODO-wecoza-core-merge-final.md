# WeCoza Core Plugin Merge - Final Implementation Plan

**Created:** 2026-01-29
**Status:** Phase 1 COMPLETE - Ready for Phase 2
**Plugin Name:** `wecoza-core`
**Scope:** Learners + Classes (extensible for future modules)

---

## Decisions Summary

| Decision | Choice |
|----------|--------|
| Plugin Name | `wecoza-core` |
| DB Normalization | Deferred to future sprint |
| Scope | Learners + Classes only |
| Architecture | Namespace-only separation (no interface overhead) |

---

## Source Document Comparison

This plan consolidates:
- `TODO-wecoza-core-merge.md` - Full merge with DB schema changes
- `TODO-plugin-merge-analysis.md` - Hybrid modular approach

**Selected approach:** Doc 1's simpler structure + Doc 2's phasing + deferred DB changes

---

## Target Architecture

```
wecoza-core/
├── wecoza-core.php                        # Main entry point
├── composer.json                          # PSR-4 autoloading
├── config/
│   └── app.php                            # Unified configuration
├── core/
│   ├── Database/
│   │   └── PostgresConnection.php         # Merged singleton (lazy + SSL)
│   ├── Abstract/
│   │   ├── BaseModel.php                  # Shared hydrate(), toArray(), CRUD
│   │   ├── BaseController.php
│   │   └── BaseRepository.php
│   └── Helpers/
│       ├── AjaxSecurity.php               # Nonce/capability patterns
│       └── functions.php                  # view(), component() helpers
├── src/
│   ├── Learners/                          # WeCoza\Learners\*
│   │   ├── Models/
│   │   │   ├── LearnerModel.php
│   │   │   └── LearnerProgressionModel.php
│   │   ├── Controllers/
│   │   │   └── LearnerController.php
│   │   ├── Repositories/
│   │   │   └── LearnerProgressionRepository.php
│   │   └── Services/
│   │       ├── ProgressionService.php
│   │       └── PortfolioUploadService.php
│   └── Classes/                           # WeCoza\Classes\*
│       ├── Models/
│       │   ├── ClassModel.php
│       │   ├── QAModel.php
│       │   └── QAVisitModel.php
│       ├── Controllers/
│       │   ├── ClassController.php
│       │   ├── ClassAjaxController.php
│       │   └── QAController.php
│       ├── Repositories/
│       │   └── ClassRepository.php
│       └── Services/
│           ├── ScheduleService.php
│           ├── FormDataProcessor.php
│           └── UploadService.php
├── views/
│   ├── learners/
│   └── classes/
└── assets/
    ├── css/
    └── js/
```

---

## Implementation Checklist

### Phase 1: Core Foundation ✅ COMPLETE

- [x] Create `wecoza-core` directory structure under `wp-content/plugins/`
- [x] Create `composer.json` with PSR-4 autoloading:
  - `WeCoza\\Core\\` → `core/`
  - `WeCoza\\Learners\\` → `src/Learners/`
  - `WeCoza\\Classes\\` → `src/Classes/`
- [x] Create `wecoza-core.php` main plugin file with WordPress headers
- [x] Merge database services:
  - Source: `wecoza-learners-plugin/database/WeCozaLearnersDB.php`
  - Source: `wecoza-classes-plugin/app/Services/Database/DatabaseService.php`
  - Target: `core/Database/PostgresConnection.php`
  - Keep: lazy loading, `sslmode=require` from Learners
- [x] Create `core/Abstract/BaseModel.php`:
  - Extract: `hydrate()`, `toArray()`, `save()`, `delete()` patterns
- [x] Create `core/Abstract/BaseController.php`
- [x] Create `core/Abstract/BaseRepository.php`
- [x] Create `core/Helpers/AjaxSecurity.php`:
  - Extract: nonce verification, capability checks
- [x] Create `core/Helpers/functions.php`:
  - Extract: `view()`, `component()` from Classes bootstrap
- [x] Create `config/app.php` with database credentials

### Phase 2: Migrate Learners Module

- [ ] Create `src/Learners/` directory structure
- [ ] Migrate `LearnerModel.php` → extend `BaseModel`
  - Source: `wecoza-learners-plugin/models/LearnerModel.php`
  - Update namespace: `WeCoza\Learners\Models`
- [ ] Migrate `LearnerProgressionModel.php` → extend `BaseModel`
  - Source: `wecoza-learners-plugin/models/LearnerProgressionModel.php`
- [ ] Migrate `LearnerController.php`
  - Source: `wecoza-learners-plugin/controllers/LearnerController.php`
- [ ] Migrate `LearnerProgressionRepository.php`
  - Source: `wecoza-learners-plugin/repositories/LearnerProgressionRepository.php`
- [ ] Migrate `ProgressionService.php`
  - Source: `wecoza-learners-plugin/services/ProgressionService.php`
- [ ] Migrate `PortfolioUploadService.php`
  - Source: `wecoza-learners-plugin/services/PortfolioUploadService.php`
- [ ] Migrate shortcodes from `learners-plugin.php`
- [ ] Migrate AJAX handlers from `ajax/learners-ajax-handlers.php`
- [ ] Copy views to `views/learners/`
- [ ] **VERIFY:** Learner CRUD operations work

### Phase 3: Migrate Classes Module

- [ ] Create `src/Classes/` directory structure
- [ ] Migrate `ClassModel.php` → extend `BaseModel`
  - Source: `wecoza-classes-plugin/app/Models/ClassModel.php`
  - Update namespace: `WeCoza\Classes\Models`
  - **Keep JSONB `learner_ids`** - do not refactor yet
- [ ] Migrate `QAModel.php`, `QAVisitModel.php`
- [ ] Migrate `ClassController.php`
  - Source: `wecoza-classes-plugin/app/Controllers/ClassController.php`
- [ ] Migrate `ClassAjaxController.php`
  - Source: `wecoza-classes-plugin/app/Controllers/ClassAjaxController.php`
- [ ] Migrate `QAController.php`
  - Source: `wecoza-classes-plugin/app/Controllers/QAController.php`
- [ ] Migrate `ClassRepository.php`
  - Source: `wecoza-classes-plugin/app/Repositories/ClassRepository.php`
- [ ] Migrate `ScheduleService.php`
  - Source: `wecoza-classes-plugin/app/Services/ScheduleService.php`
- [ ] Migrate `FormDataProcessor.php`
  - Source: `wecoza-classes-plugin/app/Services/FormDataProcessor.php`
- [ ] Migrate `UploadService.php`
- [ ] Copy views to `views/classes/`
- [ ] **VERIFY:** Class CRUD + learner assignment works

### Phase 4: Consolidation & Switchover

- [ ] Consolidate remaining helper functions → `core/Helpers/`
- [ ] Consolidate CSS assets → `assets/css/`
- [ ] Consolidate JS assets → `assets/js/`
- [ ] Unify AJAX routing (single handler registration)
- [ ] Create unified admin menu:
  - Main: "WeCoza"
  - Submenus: "Learners", "Classes", "QA Dashboard"
- [ ] **BACKUP:** PostgreSQL database
- [ ] **BACKUP:** Old plugin directories
- [ ] Deactivate `wecoza-learners-plugin`
- [ ] Deactivate `wecoza-classes-plugin`
- [ ] Activate `wecoza-core`
- [ ] Run regression tests

### Phase 5 (FUTURE): Database Normalization

- [ ] Create `class_enrollments` table:
```sql
CREATE TABLE class_enrollments (
    enrollment_id SERIAL PRIMARY KEY,
    class_id INTEGER NOT NULL REFERENCES classes(class_id),
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    enrolled_at TIMESTAMP DEFAULT NOW(),
    status VARCHAR(20) DEFAULT 'active',
    UNIQUE(class_id, learner_id)
);
```
- [ ] Migrate data from `classes.learner_ids` JSONB → `class_enrollments`
- [ ] Refactor `ClassModel` to use join table
- [ ] Create `class_exam_enrollments` table
- [ ] Remove deprecated JSONB columns (after verification)

---

## Verification Checklist

### After Phase 2 (Learners)
- [ ] Create new learner
- [ ] Edit existing learner
- [ ] Delete learner
- [ ] View learner list
- [ ] Add/edit learner progressions
- [ ] Upload portfolio documents

### After Phase 3 (Classes)
- [ ] Create new class
- [ ] Assign learners to class
- [ ] Edit class details
- [ ] Delete class
- [ ] View class list
- [ ] Class schedule generation

### After Phase 4 (Full)
- [ ] All shortcodes work:
  - `[wecoza_learners_form]`
  - `[wecoza_display_learners]`
  - `[wecoza_capture_class]`
  - `[wecoza_display_classes]`
- [ ] All AJAX endpoints respond correctly
- [ ] Admin menus accessible
- [ ] QA dashboard functional
- [ ] Calendar/schedule generation
- [ ] Hours logging & LP completion workflow
- [ ] Integration: create class → assign learners → track progression → upload portfolio

---

## Source Files Reference

### Learners Plugin
| File | Lines | Target |
|------|-------|--------|
| `learners-plugin.php` | 593 | `wecoza-core.php` |
| `models/LearnerModel.php` | 480 | `src/Learners/Models/` |
| `models/LearnerProgressionModel.php` | 473 | `src/Learners/Models/` |
| `controllers/LearnerController.php` | 452 | `src/Learners/Controllers/` |
| `repositories/LearnerProgressionRepository.php` | 578 | `src/Learners/Repositories/` |
| `services/ProgressionService.php` | 358 | `src/Learners/Services/` |
| `database/WeCozaLearnersDB.php` | 337 | `core/Database/PostgresConnection.php` |
| `database/learners-db.php` | 891 | (split across modules) |

### Classes Plugin
| File | Target |
|------|--------|
| `wecoza-classes-plugin.php` | `wecoza-core.php` |
| `app/Models/ClassModel.php` | `src/Classes/Models/` |
| `app/Controllers/ClassController.php` | `src/Classes/Controllers/` |
| `app/Controllers/ClassAjaxController.php` | `src/Classes/Controllers/` |
| `app/Controllers/QAController.php` | `src/Classes/Controllers/` |
| `app/Repositories/ClassRepository.php` | `src/Classes/Repositories/` |
| `app/Services/Database/DatabaseService.php` | `core/Database/PostgresConnection.php` |
| `app/Services/ScheduleService.php` | `src/Classes/Services/` |
| `app/Services/FormDataProcessor.php` | `src/Classes/Services/` |
| `app/bootstrap.php` | `core/Helpers/functions.php` |

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking changes during migration | Phase-by-phase approach, backup before each phase |
| Data loss | PostgreSQL backup before Phase 4 switchover |
| Namespace conflicts | Clear separation: `WeCoza\Learners\*`, `WeCoza\Classes\*` |
| Rollback needed | Keep old plugins intact until verification complete |
| DB migration issues (Phase 5) | Deferred - JSONB works, normalize when stable |

---

## Notes

- Both plugins access same PostgreSQL database
- Database service duplication verified by direct file comparison (95% identical)
- Shared reference tables: `agents`, `products`, `sites`, `clients`, `locations`, `employers`
- Existing FK relationships already correct in `learner_lp_tracking`
