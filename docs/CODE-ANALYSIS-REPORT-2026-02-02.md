# Full Code Analysis Report — WeCoza Core Plugin

*Analyzed by Gemini Pro on 2026-02-02*

---

## Executive Summary

| File | Focus | Grade | Critical Issues |
|------|-------|-------|-----------------|
| `core/Abstract/BaseRepository.php` | Security | **A-** | Missing `count()` method (fixed), identifier quoting |
| `src/Events/Services/AISummaryService.php` | Quality | **B+** | Invalid model name, array-heavy design |
| `src/Events/Services/Traits/DataObfuscator.php` | Security | **B-** | Mapping exposure reverses obfuscation |
| `src/Events/Services/NotificationProcessor.php` | Performance | **B** | Synchronous blocking, batch limit=1 |
| `src/Learners/Repositories/LearnerRepository.php` | Bugs | **B-** | Column name mismatches, file overwrite bug |

---

## 1. BaseRepository.php — Security Analysis

### Strengths

- ✅ SQL injection prevention via prepared statements
- ✅ Column whitelisting for ORDER BY, WHERE, INSERT, UPDATE
- ✅ Mass assignment protection via `filterAllowedColumns()`
- ✅ Strict type casting

### Issues Found

| Severity | Issue | Location |
|----------|-------|----------|
| 🔴 High | Missing `count()` method causes DoS via pagination | `paginate()` |
| 🟡 Medium | Unquoted identifiers may fail on reserved words | `insert()`, `update()` |
| 🟢 Low | Verbose exception logging may leak schema details | All catch blocks |

### Recommendation

Add `quoteIdentifier()` helper for PostgreSQL reserved word safety:

```php
protected function quoteIdentifier(string $identifier): string
{
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    return '"' . $clean . '"';
}
```

---

## 2. AISummaryService.php — Quality Analysis

### Strengths

- ✅ Dependency injection for testability
- ✅ PII protection via DataObfuscator trait
- ✅ API key redaction in error messages
- ✅ Exponential backoff retry logic
- ✅ Strict typing (PHP 8.1+)

### Issues Found

| Severity | Issue | Location |
|----------|-------|----------|
| 🔴 Critical | Invalid model `gpt-5-mini` (doesn't exist) | `const MODEL` |
| 🟡 Medium | SRP violation - method does too much | `generateSummary()` |
| 🟡 Medium | Heavy array usage instead of DTOs | Throughout |
| 🟢 Low | Hardcoded API URL prevents Azure/proxy use | `const API_URL` |

### Recommendation

Move model name to `OpenAIConfig`, use PHP 8.1 Enums for status strings:

```php
// In OpenAIConfig
public function getModel(): string { return 'gpt-4o-mini'; }

// PHP 8.1 Enum
enum SummaryStatus: string {
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
```

---

## 3. DataObfuscator.php — Security Analysis

### Strengths

- ✅ Consistent alias generation for learner names
- ✅ Email/phone masking implemented
- ✅ State preservation across multiple payloads

### Issues Found

| Severity | Issue | Location |
|----------|-------|----------|
| 🔴 Critical | **Mapping exposure reverses obfuscation** | Return value includes `mappings` array |
| 🟡 High | Heuristic-based detection misses custom fields | `shouldAliasName()` |
| 🟡 Medium | Weak email masking enables inference attacks | `maskEmail()` |
| 🟢 Low | Memory growth in long-running processes | `$state['aliases']` |

### Recommendation

Remove `mappings` from return value unless strictly needed internally. Never log the mapping.

```php
// Change return statement - remove mappings from external output
return [
    'payload' => $obfuscated,
    // 'mappings' => $state['aliases'], // REMOVE - exposes PII reversal
];
```

Strengthen email masking:

```php
private function maskEmail(string $value): string
{
    if (!str_contains($value, '@')) {
        return '[INVALID_EMAIL]';
    }
    $parts = explode('@', $value);
    return '*****@' . $parts[1]; // Only show domain
}
```

---

## 4. NotificationProcessor.php — Performance Analysis

### Strengths

- ✅ Transient-based locking prevents concurrent runs
- ✅ Runtime budget tracking via `shouldStop()`
- ✅ Graceful degradation when AI is unavailable
- ✅ WordPress action hook for metrics

### Issues Found

| Severity | Issue | Impact |
|----------|-------|--------|
| 🔴 High | `BATCH_LIMIT = 1` is extremely inefficient | 1 email per cron run |
| 🔴 High | Synchronous `wp_mail()` blocks execution | 0.5-5s per email |
| 🟡 Medium | AI generation blocks email pipeline | 2-10s per summary |
| 🟡 Medium | Lock TTL (30s) ≈ max runtime (20s) risks overlap | Race condition |

### Recommendation

1. Increase batch limit to 50+
2. Queue emails via Action Scheduler
3. Separate AI enrichment from notification sending

```php
// Immediate improvements
private const BATCH_LIMIT = 50;
private const LOCK_TTL = 120; // Increase TTL to accommodate larger batches

// Queue emails instead of blocking
private function queueEmail(string $recipient, array $mailData): void
{
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time(), 'wecoza_send_notification_email', [
            'to' => $recipient,
            'subject' => $mailData['subject'],
            'body' => $mailData['body'],
            'headers' => $mailData['headers']
        ]);
    } else {
        wp_mail($recipient, $mailData['subject'], $mailData['body'], $mailData['headers']);
    }
}
```

### Architectural Recommendation

Split into two distinct jobs:

1. **Job A (AI Enricher):** Scans logs where `ai_summary` is null/pending, calls OpenAI, updates DB
2. **Job B (Email Sender):** Checks if summary is ready, generates email context, queues email

This prevents the email queue from stalling when OpenAI is slow.

---

## 5. LearnerRepository.php — Bug Analysis

### Strengths

- ✅ Complex CTE queries for progression context
- ✅ Transactional portfolio uploads
- ✅ WordPress transient caching (12h)
- ✅ Foreign key validation on insert

### Issues Found

| Severity | Issue | Location |
|----------|-------|----------|
| 🔴 Critical | Column name mismatch: `sa_id_no` vs `sa_id_number` | `getAllowedInsertColumns()` vs `getLearnersWithProgressionContext()` |
| 🔴 High | `savePortfolios()` overwrites existing paths | Line ~678 |
| 🟡 Medium | Missing `processPortfolioDetails()` method | Called in `findAllWithMappings()` |
| 🟡 Medium | Unsafe `$pdo` access in catch block | `savePortfolios()` |
| 🟢 Low | No MIME type validation on PDF uploads | Security risk |

### Recommendation

**Fix column name consistency:**

```php
// Standardize to match database schema
protected function getAllowedInsertColumns(): array
{
    return [
        // Use consistent names matching the actual DB columns
        'sa_id_no',      // or 'sa_id_number' - pick one
        'tel_number',    // or 'cell_phone' - pick one
        // ...
    ];
}
```

**Fix portfolio overwrite bug:**

```php
public function savePortfolios(int $learnerId, array $files): array
{
    $pdo = null; // Initialize to prevent catch block crash
    try {
        $pdo = $this->db->getPdo();

        // Get existing paths first to append, not overwrite
        $stmt = $pdo->prepare("SELECT scanned_portfolio FROM learners WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $learnerId]);
        $existingStr = $stmt->fetchColumn();
        $currentPaths = $existingStr ? array_map('trim', explode(',', $existingStr)) : [];

        // ... upload logic ...

        // Merge existing and new paths
        $allPaths = array_merge($currentPaths, $newPaths);
        $uniquePaths = array_unique(array_filter($allPaths));
        $portfolioList = implode(', ', $uniquePaths);

        // ...
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) { // Safe null check
            $pdo->rollBack();
        }
        // ...
    }
}
```

**Add MIME type validation:**

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmpName);
finfo_close($finfo);

if ($fileExt === 'pdf' && $mime === 'application/pdf') {
    // Safe to process
}
```

---

## Architecture Recommendations

1. **Use DTOs** instead of associative arrays for `$record`, `$context`, `$summary`
2. **Separate concerns**: AI enrichment should be a separate background job from email sending
3. **Add Action Scheduler** for async email delivery
4. **Implement Enums** (PHP 8.1+) for status strings (`success`, `failed`, `pending`)
5. **Add identifier quoting** helper for PostgreSQL reserved word safety

---

## Summary Metrics

| Category | Issues Found |
|----------|--------------|
| 🔴 Critical/High | 7 |
| 🟡 Medium | 9 |
| 🟢 Low | 5 |
| **Total** | **21** |

---

## Overall Assessment

The codebase demonstrates solid security fundamentals:
- Prepared statements for SQL injection prevention
- Column whitelisting for mass assignment protection
- PII protection via DataObfuscator trait

However, there is architectural debt in:
- **Performance**: Synchronous operations, batch size of 1
- **Data handling**: Array-heavy design, column name inconsistencies

**Recommendation:** The v1 milestone delivered working functionality. A v1.1 polish pass should address these findings before scaling to production load.

---

*Generated by Claude Code with Gemini Pro analysis*
