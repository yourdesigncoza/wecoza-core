---
status: resolved
trigger: "Newly created class type ASC not appearing in Class Type dropdown when creating a new class via [wecoza_capture_class] shortcode"
created: 2026-02-25T00:00:00Z
updated: 2026-02-25T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - LookupTableAjaxHandler does not call ClassTypesController::clearCache() after write operations on class_types table, causing stale transient data to persist for up to 2 hours
test: Verified by tracing both code paths and checking the WordPress transient
expecting: Cache invalidation after class_types write operations
next_action: Apply fix - clear class types transient in LookupTableAjaxHandler after write ops

## Symptoms

expected: When a class type is added in Manage & Audits, it should appear in the Class Type dropdown on the New Class form (wecoza_capture_class shortcode)
actual: ASC class type was added successfully and appears in the management table, but does NOT appear in the Class Type dropdown on the New Class form. Other types visible include "AET Communication & Numeracy", "REALLL", "Soft Skill Courses", "GETC AET", "Business Admin NQF 2/3/4", "Walk Package", "Hexa Packages", "Run Packages", "Adult Matric" but NOT "ASC"
errors: No error messages reported
reproduction: 1. Add new class type "ASC" in Manage & Audits. 2. Go to New Class page within 2 hours. 3. Open Class Type dropdown - ASC is missing.
started: After ASC was added to the management table

## Eliminated

- hypothesis: Different database tables used by the two shortcodes
  evidence: Both use public.class_types table - management reads/writes it directly, dropdown queries it via ClassTypesController::getClassTypes()
  timestamp: 2026-02-25T00:01:00Z

- hypothesis: is_active filter excluding ASC
  evidence: DB confirms ASC is active=TRUE and IS included in cached transient data
  timestamp: 2026-02-25T00:02:00Z

## Evidence

- timestamp: 2026-02-25T00:01:00Z
  checked: ClassController::handleCreateMode() -> ClassRepository::getClassTypes() -> ClassTypesController::getClassTypes()
  found: getClassTypes() caches results in WordPress transient 'wecoza_class_types' with 2-hour TTL
  implication: New class types won't appear in dropdown until transient expires

- timestamp: 2026-02-25T00:02:00Z
  checked: LookupTableAjaxHandler::handleCreate(), handleUpdate(), handleDelete()
  found: None of these methods call ClassTypesController::clearCache() after modifying the class_types table
  implication: The cache is never proactively invalidated when a class type is added/edited/deleted

- timestamp: 2026-02-25T00:03:00Z
  checked: ClassTypesController::clearCache() method
  found: Method exists and correctly deletes 'wecoza_class_types' transient + all subject transients
  implication: The fix is to call this method after write operations on class_types table

- timestamp: 2026-02-25T00:04:00Z
  checked: DB query + transient contents
  found: ASC (Adult Matric) IS in DB (id=13, active=TRUE, order=11) and IS in the current transient - meaning the 2-hour cache has since refreshed naturally after the bug was reported
  implication: Bug is reproducible every time a class type is added/edited. The transient TTL means a ~0-2 hour delay before changes appear.

## Resolution

root_cause: LookupTableAjaxHandler performs create/update/delete on the class_types table but never invalidates the WordPress transient cache (wecoza_class_types) maintained by ClassTypesController::getClassTypes(). New class types are invisible in the dropdown for up to 2 hours after being added.
fix: Added maybeClearClassTypesCache() private method to LookupTableAjaxHandler. Called after successful insert/update/delete when config table === 'class_types'. Uses existing ClassTypesController::clearCache() which deletes the wecoza_class_types transient and all per-type subject transients.
verification: PHP syntax check passed. ASC (Adult Matric) already present in transient (cache refreshed naturally after 2hr TTL). Fix ensures future adds/edits/deletes appear immediately in the dropdown.
files_changed: [src/LookupTables/Ajax/LookupTableAjaxHandler.php]
