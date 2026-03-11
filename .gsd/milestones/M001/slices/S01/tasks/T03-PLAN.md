---
estimated_steps: 4
estimated_files: 2
---

# T03: Create ExamUploadService for SBA/certificate files

**Slice:** S01 — Exam Data Layer & Service
**Milestone:** M001

## Description

Create `ExamUploadService` handling file uploads for SBA scans and final exam certificates. Follows `PortfolioUploadService` pattern exactly — separate upload directory, MIME validation, extension whitelist, size limit, and security files (.htaccess, index.php). Expands allowed types to include images (JPG, PNG) for scanned documents alongside PDF/DOC/DOCX.

## Steps

1. Read `src/Learners/Services/PortfolioUploadService.php` to capture exact upload pattern, security file creation, MIME detection approach, and return format
2. Write `src/Learners/Services/ExamUploadService.php` with: upload dir `uploads/exam-documents/`, allowed MIME types (application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, image/jpeg, image/png), max 10MB, `upload($file, $trackingId, ExamStep $step)` method returning `['success' => bool, 'file_path' => string, 'file_name' => string, 'error' => string]`, security file creation on first upload, filename sanitization with tracking_id and step prefix
3. Verify class loads and static analysis confirms MIME types and method signatures
4. Confirm security measures: `.htaccess` denies direct access, `index.php` is empty, filenames are sanitized

## Must-Haves

- [ ] Upload directory: `uploads/exam-documents/` inside WP uploads
- [ ] Allowed MIME types include PDF, DOC, DOCX, JPEG, PNG
- [ ] Max file size: 10MB
- [ ] MIME validation uses `finfo_open()` (not just extension check)
- [ ] Creates `.htaccess` and `index.php` security files on first upload
- [ ] Stores relative paths (not absolute) for portability
- [ ] Filenames include tracking_id and exam_step for uniqueness and traceability
- [ ] Returns structured array with success/file_path/file_name/error

## Verification

- `php -l src/Learners/Services/ExamUploadService.php` — no syntax errors
- Code inspection confirms MIME types array includes `image/jpeg` and `image/png`
- Code inspection confirms `.htaccess` and `index.php` creation logic present

## Observability Impact

- Signals added/changed: Error logging via `error_log("WeCoza Exam: ExamUploadService::upload - ...")` on validation failures and IO errors
- How a future agent inspects this: Check upload directory existence, security files presence, PHP error log for upload failures
- Failure state exposed: Return array with `'success' => false` and specific error message (file too large, invalid type, write failed)

## Inputs

- `src/Learners/Services/PortfolioUploadService.php` — exact pattern to follow
- `src/Learners/Enums/ExamStep.php` — ExamStep enum from T01

## Expected Output

- `src/Learners/Services/ExamUploadService.php` — file upload service with validation, security, and structured returns
