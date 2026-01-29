# Phase 1: Core Foundation TODO

**Started:** 2026-01-29
**Status:** COMPLETE

## Tasks

- [x] Create directory structure (core/, src/, views/, assets/, config/)
- [x] Create composer.json with PSR-4 autoloading
- [x] Create config/app.php
- [x] Create core/Helpers/functions.php (must be first - loaded by main plugin)
- [x] Create core/Database/PostgresConnection.php
- [x] Create core/Abstract/BaseModel.php
- [x] Create core/Abstract/BaseController.php
- [x] Create core/Abstract/BaseRepository.php
- [x] Create core/Helpers/AjaxSecurity.php
- [x] Create wecoza-core.php (main plugin file)
- [ ] Verify: Plugin activates without errors
- [ ] Verify: Database connection works

## Files Created

```
wecoza-core/
├── wecoza-core.php                          # Main plugin entry point
├── composer.json                            # PSR-4 autoloading config
├── config/
│   └── app.php                              # Application configuration
├── core/
│   ├── Abstract/
│   │   ├── BaseModel.php                    # Abstract model with hydrate/toArray
│   │   ├── BaseController.php               # Abstract controller with AJAX helpers
│   │   └── BaseRepository.php               # Abstract repository with CRUD
│   ├── Database/
│   │   └── PostgresConnection.php           # Singleton DB with lazy loading + SSL
│   └── Helpers/
│       ├── AjaxSecurity.php                 # Nonce/capability/sanitization helpers
│       └── functions.php                    # Global wecoza_* helper functions
├── src/
│   ├── Learners/                            # (Phase 2)
│   └── Classes/                             # (Phase 3)
├── views/
│   └── components/
└── assets/
    ├── css/
    └── js/
```

## Next Steps

1. Activate plugin in WordPress admin
2. Test database connection via WP-CLI: `wp wecoza test-db`
3. Proceed to Phase 2: Migrate Learners Module
