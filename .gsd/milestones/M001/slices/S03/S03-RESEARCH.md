# S03: Exam Progress UI & AJAX — Research

**Date:** 2026-03-11

## Summary

S03 builds the AJAX endpoints and UI components that allow office staff to record exam results (mock 1–3 percentages, SBA marks + scan upload, final exam mark + certificate upload) through the learner progression view. The UI must conditionally show the exam flow for exam-class learners and the existing POE flow for non-exam learners — both on the same progression tab.

The codebase has a clear, well-established pattern: `ProgressionAjaxHandlers.php` (namespace-scoped procedural functions with `verify_learner_access()` + `wp_send_json_success/error`), `learner-progressions.php` view (Bootstrap cards with PHP server-side render), and `learner-progressions.js` (jQuery IIFE module with AJAX calls using `learnerSingleAjax` localized data). S03 follows these patterns exactly — no new frameworks or architectural changes needed.

The primary challenge is the conditional rendering: the `baseQuery()` in `LearnerProgressionRepository` already JOINs `classes c`, so adding `c.exam_class` to the SELECT is a trivial change. The `getCurrentLPDetails()` flow then naturally surfaces whether the current LP is on an exam-class, enabling the view to branch between exam progress and POE sections.

## Recommendation

**Follow the existing progression UI pattern exactly.** Create:

1. **ExamAjaxHandlers.php** — New file in `src/Learners/Ajax/` following `ProgressionAjaxHandlers.php` patterns. Three endpoints: `record_exam_result` (POST with percentage + optional file), `get_exam_progress` (GET by tracking_id), `delete_exam_result` (POST for re-recording). All use `verify_learner_access('learners_nonce')`.

2. **learner-exam-progress.php** — New component in `views/learners/components/` showing the 5-step exam progress card. Uses `ExamService::getExamProgress()` server-side for initial render. Conditionally included from `learner-progressions.php` when `$currentLP['is_exam_class']` is true.

3. **learner-exam-progress.js** — New JS file in `assets/js/learners/` following `learner-progressions.js` jQuery IIFE pattern. Handles percentage input, file upload with progress bar, and in-place card updates after each step.

4. **Modify `learner-progressions.php`** — Add conditional: if exam class, render exam progress component instead of POE-specific sections (mark complete + portfolio upload). The hours/progress card remains for both flows.

5. **Extend `getCurrentLPDetails()`** — Add `is_exam_class` and `exam_progress` keys to the return array.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| AJAX security (nonce + auth) | `verify_learner_access()` in LearnerAjaxHandlers.php | Already used by all 12 progression handlers; consistent nonce name `learners_nonce` |
| File upload with validation | `ExamUploadService::upload()` from S01 | Already handles MIME validation, directory creation, .htaccess security, tracking_id-based naming |
| Exam progress data | `ExamService::getExamProgress()` from S01 | Returns `{steps, completion_percentage, completed_count, total_steps}` — render directly |
| Step metadata (labels, badges, requiresFile) | `ExamStep` enum methods | `label()`, `badgeClass()`, `requiresFile()` eliminate hardcoded step names in UI |
| Exam result persistence | `ExamService::recordExamResult()` from S01 | Handles validation, upload delegation, upsert — returns `['success', 'data', 'error']` |
| Upload progress bar UI | Pattern from `learner-progressions.js` | `showProgressBar()`/`resetProgressBar()` helpers with Bootstrap progress bars |
| WP script localization | `learnerSingleAjax` in shortcode | Already provides `ajaxurl`, `nonce`, `learnerId` — extend with exam-specific data if needed |

## Existing Code and Patterns

- `src/Learners/Ajax/ProgressionAjaxHandlers.php` — **The AJAX template.** 12 handlers, all following: `verify_learner_access()` → validate input → call service → `wp_send_json_success/error`. New exam handlers follow this exactly. Registered via `add_action('init', ...)` at file bottom.
- `views/learners/components/learner-progressions.php` — **The view template.** Server-side renders current LP card + history. Uses `ProgressionService` directly. Exam section should be a conditional block here or a separate component `include`d within.
- `assets/js/learners/learner-progressions.js` — **The JS template.** jQuery IIFE, event delegation on document, uses `learnerSingleAjax` for AJAX config. New exam JS follows this module pattern.
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` (line ~62) — **Script enqueue pattern.** Uses `wp_enqueue_script` + `wp_localize_script('learner-single-display', 'learnerSingleAjax', ...)`. New exam JS should be enqueued here.
- `src/Learners/Repositories/LearnerProgressionRepository.php` (line 42, `baseQuery()`) — **Already JOINs `classes c`**. Adding `c.exam_class` to the SELECT is a one-line change. This makes `is_exam_class` available on every progression model instance.
- `src/Learners/Services/ProgressionService.php` (line 319, `getCurrentLPDetails()`) — **Extend to include `is_exam_class`** and optionally pre-fetch exam progress when true.
- `src/Learners/Services/ExamService.php` — **S01 service layer.** `recordExamResult()` handles percentage validation + optional file upload + upsert. `getExamProgress()` returns structured progress data for all 5 steps.
- `src/Learners/Enums/ExamStep.php` — **5 enum cases** with `label()`, `badgeClass()`, `requiresFile()`. Use in both PHP view rendering and as data attributes for JS.
- `wecoza-core.php` (line 680–694) — **AJAX handler loading point.** New exam AJAX file gets a `require_once` here, following the same comment block pattern.
- `views/learners/components/learner-tabs.php` — Tab buttons (Learner Info, Placement Assessment, Current Status, Progressions). Exam progress lives within the existing "Progressions" tab — **no new tab needed**.

## Constraints

- **CSS must go in `ydcoza-styles.css`** — No inline styles or separate CSS files. Any exam-specific styling (step cards, progress indicators) appended to the global theme CSS file.
- **`learnerSingleAjax` is the single localized JS object** — All AJAX calls from the learner single display use this. The `nonce` field uses `learners_nonce` action, same as all other progression handlers.
- **File uploads limited to 10MB** — Both `ExamUploadService` (S01) and the existing `validate_portfolio_file()` enforce this. JS-side validation must match.
- **SBA/final require file upload; mocks do not** — `ExamStep::requiresFile()` returns true for `sba` and `final` only. UI must conditionally show file input.
- **`exam_class` column is `VARCHAR` stored as 'Yes'/'No'** — Not a boolean in Postgres. ClassModel converts via `setExamClass()`. When adding to `baseQuery()`, use `COALESCE(c.exam_class, 'No') = 'Yes'` or similar for the boolean check.
- **DB is read-only from agent** — All write operations (INSERT/UPDATE/DELETE) go through PHP code executed by WordPress, not directly by the agent.
- **ExamService returns consistent `['success', 'data', 'error']`** (D006) — AJAX handlers should map this directly to `wp_send_json_success`/`wp_send_json_error` responses.
- **`verify_learner_access()` is namespaced** — Defined in `WeCoza\Learners\Ajax` namespace. The new exam AJAX file must be in the same namespace or import the function.

## Common Pitfalls

- **Forgetting to load the new AJAX handler file** — Must add `require_once` in `wecoza-core.php` after the existing progression handler include (around line 694). Without this, WordPress won't register the AJAX actions and all exam AJAX calls will return 0.
- **Nonce mismatch** — The existing nonce is created as `wp_create_nonce('learners_nonce')` and verified via `verify_learner_access('learners_nonce')`. New handlers must use the same action name — do NOT create a separate `exam_nonce`.
- **File upload via FormData** — `$_FILES` only works when the AJAX request uses `FormData` with `processData: false, contentType: false`. The existing portfolio upload JS does this correctly — replicate that pattern for SBA/certificate uploads.
- **ExamStep::from() throws on invalid value** — Use `ExamStep::tryFrom()` or `ExamStep::tryFromString()` in AJAX handlers to safely convert user input, returning an error response for invalid steps instead of an uncaught ValueError.
- **Conditional UI regression** — The POE flow (mark complete + portfolio upload) must remain untouched for non-exam learners. Test both paths: exam class learner shows exam steps; non-exam learner shows original POE flow.
- **`exam_class` column value format** — The column is `exam_class` (verified in ClassRepository SQL). Stored as `'Yes'`/`'No'` VARCHAR, not a boolean. When checking in `baseQuery()`, compare as string: `c.exam_class = 'Yes'`.
- **Bootstrap version** — The existing UI uses Bootstrap 5 (BS5) with `data-bs-` attributes, Phoenix theme badge classes like `badge-phoenix badge-phoenix-primary`. Exam UI must match.

## Open Risks

- **Column name verified as `exam_class`** — ClassRepository SQL confirms the DB column is `exam_class` (not `n` — earlier search was a false positive from substring matches). Value is VARCHAR `'Yes'`/`'No'`, not boolean. Safe to add to `baseQuery()`.
- **Exam LP completion trigger** — When the final exam step is recorded with a certificate, should the LP status automatically change to `completed`? The milestone says "Exam LP completion triggers when final exam mark + certificate are recorded." This may need `ProgressionService::markLPComplete()` integration, but the existing method requires a portfolio file. Need to decide: does exam completion bypass the portfolio requirement? (Likely yes — the certificate IS the equivalent.)
- **Re-recording marks** — Users should be able to update an existing exam result (e.g., correct a wrong percentage). `ExamRepository::upsert()` supports this via INSERT ON CONFLICT, but the UI flow needs to clearly show whether a step has been previously recorded and allow editing.
- **Page reload vs in-place update** — After recording each exam step, should the entire progression section refresh via AJAX, or just the specific step card? The existing `onMarkCompleteSuccess()` does in-place DOM updates, but the exam UI has 5 steps to track. A full AJAX refresh of the exam progress section is simpler and less error-prone.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | — | No specific skill needed (standard WP AJAX patterns) |
| Bootstrap 5 | — | No specific skill needed (existing UI patterns) |
| jQuery | — | No specific skill needed (existing JS patterns) |
| PHP 8.1 enums | — | Already using ExamStep enum from S01 |

No external library skills needed — this is pure WordPress AJAX + Bootstrap UI work using existing codebase patterns.

## Sources

- `src/Learners/Ajax/ProgressionAjaxHandlers.php` — 12 existing AJAX handlers as the definitive pattern
- `views/learners/components/learner-progressions.php` — Current progression UI as the base template
- `assets/js/learners/learner-progressions.js` — jQuery AJAX + UI pattern for file uploads and in-place updates
- `src/Learners/Services/ExamService.php` — S01 service layer with recordExamResult and getExamProgress
- `.gsd/milestones/M001/slices/S01/S01-SUMMARY.md` — S01 forward intelligence on ExamStep::requiresFile() and getExamProgress() return shape
- `.gsd/milestones/M001/slices/S02/S02-SUMMARY.md` — S02 forward intelligence on ExamTaskProvider and task ID format
- `.gsd/DECISIONS.md` — D006 (consistent return format), D008 (dashboard records 100%, S03 records actual %)
