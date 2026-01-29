# Code Simplification TODO

## Phase 1: DRY Extraction

- [x] Add `wecoza_sanitize_value()` to functions.php
- [x] Refactor BaseController.php to use helper
- [x] Refactor AjaxSecurity.php to use helper
- [x] Refactor BaseModel.php to use helper

## Phase 2: Controller Refactoring

- [x] Convert QAController static→instance methods
- [x] Update hook registrations in QAController

## Phase 3: Complexity Reduction

- [ ] Extract FormDataProcessor methods (deferred - more complex)
- [ ] Add guard clauses to ScheduleService (deferred - more complex)

## Phase 4: Cleanup

- [x] Remove dead code from ClassModel
- [x] Fix CSV filename escaping

## Verification

- [x] PHP syntax check (all files pass)
- [ ] Test QA dashboard
- [ ] Test class CRUD
- [ ] Test schedule generation

## Summary of Changes

### Files Modified:
1. `core/Helpers/functions.php` - Added `wecoza_sanitize_value()` helper
2. `core/Abstract/BaseController.php` - Refactored to use helper (-30 lines)
3. `core/Helpers/AjaxSecurity.php` - Refactored to use helper (-25 lines)
4. `core/Abstract/BaseModel.php` - Refactored to use helper (-10 lines)
5. `src/Classes/Controllers/QAController.php` - Static→instance, parent helpers
6. `src/Classes/Models/ClassModel.php` - Removed dead code (-15 lines)
