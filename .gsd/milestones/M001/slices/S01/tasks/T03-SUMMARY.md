---
id: T03
parent: S01
milestone: M001
provides:
  - ExamUploadService for SBA/certificate file uploads with MIME validation and security
key_files:
  - src/Learners/Services/ExamUploadService.php
key_decisions:
  - Return array always includes all four keys (success, file_path, file_name, error) with empty strings for unused fields, unlike PortfolioUploadService which omits keys on failure — consistent structure simplifies downstream consumption
patterns_established:
  - ExamUploadService follows PortfolioUploadService pattern with expanded MIME types for scanned documents (image/jpeg, image/png)
observability_surfaces:
  - error_log("WeCoza Exam: ExamUploadService::upload - ...") on validation failures and write errors with tracking_id and step context
  - Return array with success=false and specific error message for all failure modes
duration: 10m
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---

# T03: Create ExamUploadService for SBA/certificate files

**Built ExamUploadService with MIME validation (PDF/DOC/DOCX/JPG/PNG), finfo-based type checking, security files, and structured return arrays.**

## What Happened

Created `ExamUploadService` following the `PortfolioUploadService` pattern exactly. Key differences from portfolio service:
- Upload directory: `uploads/exam-documents/` (vs `uploads/portfolios/`)
- Expanded MIME types: added `image/jpeg` and `image/png` for scanned documents
- Expanded extensions: added `jpg`, `jpeg`, `png`
- Filename format: `{tracking_id}_{exam_step}_{uniqid}.{ext}` for traceability
- Method signature: `upload(array $file, int $trackingId, ExamStep $step)` — takes ExamStep enum directly
- No repository interaction — stores files only; DB record is ExamService's responsibility (T04)

## Verification

- `php -l src/Learners/Services/ExamUploadService.php` — no syntax errors
- Code inspection confirmed all must-haves:
  - Upload dir: `uploads/exam-documents/` ✓
  - MIME types include PDF, DOC, DOCX, JPEG, PNG ✓
  - Max file size: 10MB (10485760 bytes) ✓
  - MIME validation uses `finfo_open(FILEINFO_MIME_TYPE)` ✓
  - `.htaccess` and `index.php` security files created in `ensureUploadDirectory()` ✓
  - Stores relative paths (`exam-documents/filename`) ✓
  - Filenames include tracking_id and exam_step ✓
  - Returns structured array with success/file_path/file_name/error ✓
- Slice verification: `php tests/exam/verify-exam-schema.php` — 20/20 passed
- Slice verification: `php tests/exam/verify-exam-service.php` — not yet runnable (ExamService not created until T04)

## Diagnostics

- Check PHP error log for `"WeCoza Exam: ExamUploadService::upload"` entries for upload failures
- Upload directory existence: check `wp-content/uploads/exam-documents/`
- Security files: verify `.htaccess` and `index.php` exist in upload directory
- All failure modes return `['success' => false, 'error' => '<specific message>']`

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/Learners/Services/ExamUploadService.php` — File upload service with MIME validation, security files, and structured returns
