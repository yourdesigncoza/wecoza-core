# Phase 4: Consolidation TODO

**Started:** 2026-01-29
**Status:** IN PROGRESS

## Tasks

### 1. Check for Old Namespace References
- [x] Search for `WeCozaClasses\` in src/ and views/ - NONE FOUND
- [x] Search for `WeCozaLearners\` in src/ and views/ - NONE FOUND
- [x] Search for `WECOZA_CLASSES_` constants - NONE FOUND
- [x] Search for `WECOZA_LEARNERS_` constants - NONE FOUND
- [x] Fix any found references - N/A (no old references exist)

### 2. Verify wecoza-core Functionality
- [ ] Test database connection
- [ ] Test class capture form (create mode)
- [ ] Test class capture form (update mode)
- [ ] Test class list display
- [ ] Test single class display
- [ ] Test calendar events AJAX
- [ ] Test QA dashboard
- [ ] Test learner display shortcode
- [ ] Test learner capture shortcode

### 3. Deactivate Old Plugins
- [ ] Backup database before deactivation
- [ ] Deactivate wecoza-learners-plugin
- [ ] Deactivate wecoza-classes-plugin
- [ ] Test wecoza-core still works after deactivation

### 4. Cleanup
- [ ] Remove backup zip files from plugins directory
- [ ] Archive old plugin folders
- [ ] Clear orphaned wp_options entries
- [ ] Update documentation

## Notes

- Rollback plan: Re-activate old plugins if issues occur
- Check debug.log for errors after each step
