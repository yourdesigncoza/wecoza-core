# Phase 2: Learners Module Migration TODO

**Started:** 2026-01-29
**Status:** COMPLETE

## Tasks

- [x] Create `src/Learners/` directory structure
- [x] Migrate `LearnerModel.php` → extend `BaseModel`
  - Source: `../wecoza-learners-plugin/models/LearnerModel.php`
  - Target: `src/Learners/Models/LearnerModel.php`
  - Namespace: `WeCoza\Learners\Models`
- [x] Migrate `LearnerProgressionModel.php` → extend `BaseModel`
  - Source: `../wecoza-learners-plugin/models/LearnerProgressionModel.php`
  - Target: `src/Learners/Models/LearnerProgressionModel.php`
- [x] Migrate `LearnerController.php` → extend `BaseController`
  - Source: `../wecoza-learners-plugin/controllers/LearnerController.php`
  - Target: `src/Learners/Controllers/LearnerController.php`
- [x] Migrate `LearnerProgressionRepository.php` → extend `BaseRepository`
  - Source: `../wecoza-learners-plugin/repositories/LearnerProgressionRepository.php`
  - Target: `src/Learners/Repositories/LearnerProgressionRepository.php`
- [x] Migrate `ProgressionService.php`
  - Source: `../wecoza-learners-plugin/services/ProgressionService.php`
  - Target: `src/Learners/Services/ProgressionService.php`
- [x] Migrate `PortfolioUploadService.php`
  - Source: `../wecoza-learners-plugin/services/PortfolioUploadService.php`
  - Target: `src/Learners/Services/PortfolioUploadService.php`
- [x] Create `LearnerRepository.php` (for learner CRUD)
  - New file: `src/Learners/Repositories/LearnerRepository.php`
  - Extracted from: `../wecoza-learners-plugin/database/learners-db.php`
- [x] Shortcodes registered in LearnerController
- [x] AJAX handlers registered in LearnerController
- [x] Updated wecoza-core.php to initialize module

## Files Created

```
src/Learners/
├── Controllers/
│   └── LearnerController.php       # MVC controller with AJAX handlers
├── Models/
│   ├── LearnerModel.php            # Learner data model
│   └── LearnerProgressionModel.php # LP tracking model
├── Repositories/
│   ├── LearnerRepository.php       # Learner CRUD + dropdown data
│   └── LearnerProgressionRepository.php # LP tracking repository
└── Services/
    ├── ProgressionService.php      # LP business logic
    └── PortfolioUploadService.php  # Portfolio file handling
```

## Verification (Pending)

- [ ] Create new learner
- [ ] Edit existing learner
- [ ] Delete learner
- [ ] View learner list
- [ ] Add/edit learner progressions
- [ ] Upload portfolio documents

## Next Steps

1. Activate plugin in WordPress admin
2. Test learner CRUD operations
3. Proceed to Phase 3: Migrate Classes Module
