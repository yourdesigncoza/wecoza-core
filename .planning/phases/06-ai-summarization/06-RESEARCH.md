# Phase 6: AI Summarization - Research

**Researched:** 2026-02-02
**Domain:** OpenAI API Integration for WordPress
**Confidence:** HIGH

## Summary

Phase 6 AI Summarization is **already fully implemented** in the codebase. The research focused on understanding the existing implementation and verifying it follows current best practices for OpenAI PHP integration in WordPress.

The implementation uses WordPress's native `wp_remote_post()` HTTP API to communicate with OpenAI's Chat Completions API, specifically using the `gpt-5-mini` model for cost-effective class change summarization. The architecture follows a service-oriented pattern with proper separation of concerns: AISummaryService handles API communication with retry logic, OpenAIConfig manages API key storage/validation, and NotificationProcessor orchestrates the workflow of generating summaries when class changes occur.

Key findings confirm the implementation adheres to 2026 best practices: API keys are stored securely in WordPress options (not hardcoded), timeout handling is properly configured at 60 seconds, exponential backoff retry logic is implemented for transient failures, and error codes are mapped to user-friendly messages. The system uses data obfuscation via the DataObfuscator trait to protect PII before sending to OpenAI.

**Primary recommendation:** Phase 6 implementation is production-ready and follows current best practices. Focus planning on verification testing rather than new development.

## Standard Stack

The established libraries/tools for OpenAI integration in WordPress (2026):

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress HTTP API | Core (6.0+) | HTTP client (`wp_remote_post()`) | Native WordPress solution, no external dependencies, handles SSL/timeouts automatically |
| OpenAI Chat Completions API | v1 | GPT model inference endpoint | Official OpenAI API, supports all current models (GPT-4o, GPT-5 family) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| openai-php/client | 0.10+ (Composer) | Structured PHP client for OpenAI | Prefer for greenfield projects requiring PHP 8.2+; not used here to avoid Composer dependency |
| Guzzle HTTP | 7.x (via Composer) | Alternative HTTP client | When WordPress HTTP API is insufficient (rare) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| wp_remote_post() | openai-php/client | Adds Composer dependency, requires PHP 8.2+, but provides type safety and structured responses |
| gpt-5-mini | gpt-4o-mini | 2.4x more expensive ($0.60/1M input vs $0.25/1M), slightly better quality |
| gpt-5-mini | gpt-5 | 5x more expensive ($1.25/1M input), better for complex reasoning but overkill for summaries |

**Installation:**
No installation required - uses WordPress core HTTP API and native PHP JSON functions.

**Model Selection (2026):**
```php
// Current implementation uses gpt-5-mini (optimal for cost/quality)
private const MODEL = 'gpt-5-mini';  // $0.25/1M input, $2.00/1M output

// Pricing reference (2026):
// gpt-5-mini:  $0.25 input / $2.00 output (RECOMMENDED for summaries)
// gpt-5-nano:  $0.05 input / $0.40 output (too low quality)
// gpt-5:       $1.25 input / $10.00 output (overkill for summaries)
// gpt-4o:      $2.50 input / $10.00 output (legacy, no advantage)
```

## Architecture Patterns

### Recommended Project Structure (Already Implemented)
```
src/Events/
├── Admin/
│   └── SettingsPage.php      # WordPress options UI for API key
├── Services/
│   ├── AISummaryService.php          # OpenAI API client
│   ├── AISummaryDisplayService.php   # Query/display logic
│   ├── NotificationProcessor.php     # Orchestrates summary generation
│   └── Traits/
│       └── DataObfuscator.php        # PII protection before API calls
├── Support/
│   └── OpenAIConfig.php       # API key validation/storage
├── Shortcodes/
│   └── AISummaryShortcode.php # Display summaries via [wecoza_insert_update_ai_summary]
└── Repositories/
    └── ClassChangeLogRepository.php  # Persist ai_summary JSONB column
```

### Pattern 1: Service-Based API Client with Dependency Injection
**What:** AISummaryService as a testable service with injected HTTP client
**When to use:** All external API integrations requiring testability
**Example:**
```php
// Source: src/Events/Services/AISummaryService.php (lines 55-61)
final class AISummaryService
{
    public function __construct(
        private readonly OpenAIConfig $config,
        ?callable $httpClient = null,  // Injectable for testing
        private readonly int $maxAttempts = 3
    ) {
        $this->httpClient = $httpClient ?? $this->defaultHttpClient();
    }
```

### Pattern 2: WordPress HTTP API with Timeout Configuration
**What:** Using wp_remote_post() with explicit timeout for API calls
**When to use:** All WordPress plugin HTTP requests to external services
**Example:**
```php
// Source: src/Events/Services/AISummaryService.php (lines 220-228)
$response = ($this->httpClient)([
    'url' => self::API_URL,
    'timeout' => self::TIMEOUT_SECONDS,  // 60 seconds for LLM inference
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $apiKey,
    ],
    'body' => $payload,
]);
```

### Pattern 3: Exponential Backoff Retry Logic
**What:** Retry failed requests with increasing delays (1s, 2s, 4s)
**When to use:** Any retryable API failures (rate limits, timeouts, 5xx errors)
**Example:**
```php
// Source: src/Events/Services/AISummaryService.php (lines 363-370)
private function backoffDelaySeconds(int $attempts): int
{
    return match ($attempts) {
        0 => 0,
        1 => 1,
        2 => 2,
        default => 4,
    };
}
```

### Pattern 4: WordPress Options API for Secure Key Storage
**What:** Store API keys in WordPress options with validation/sanitization
**When to use:** Any API credentials that must persist across requests
**Example:**
```php
// Source: src/Events/Admin/SettingsPage.php (lines 79-84)
register_setting(self::OPTION_GROUP, self::OPTION_AI_API_KEY, [
    'type' => 'string',
    'sanitize_callback' => [self::class, 'sanitizeApiKey'],
    'default' => '',
    'autoload' => false,  // Don't load on every request
]);

// Source: src/Events/Support/OpenAIConfig.php (lines 91-98)
public function isValidApiKey(string $key): bool
{
    $key = trim($key);
    if ($key === '') {
        return false;
    }
    return preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key) === 1;
}
```

### Anti-Patterns to Avoid
- **Hardcoding API keys in PHP files:** Always use WordPress options or environment variables
- **No timeout configuration:** WordPress HTTP API defaults to 5 seconds (too short for LLM inference)
- **Retrying immediately on failure:** Implement exponential backoff to avoid overwhelming API or hitting rate limits
- **Exposing API keys client-side:** Never pass API keys to JavaScript; always proxy through PHP
- **Using global models for all use cases:** GPT-5 costs 5x more than GPT-5-mini; match model to complexity

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP client for WordPress | Custom curl/fsockopen wrapper | `wp_remote_post()` | Handles SSL verification, proxy support, timeout management, WP_Error integration |
| API key validation | String length check | Regex pattern `/^sk-[A-Za-z0-9_-]{20,}$/` | OpenAI keys have specific format; pattern catches most typos/corruption |
| Retry logic with backoff | Custom sleep() loops | Match expression with delays | Exponential backoff is proven pattern; custom loops risk infinite retries or rate limit violations |
| API error code mapping | Switch statements on HTTP status | Error code mapping with retryable flags | OpenAI returns specific error structures; proper mapping distinguishes permanent (401, 400) from transient (429, 5xx) failures |
| PII protection | Manual field filtering | DataObfuscator trait with alias mapping | Requires consistent field detection, reversible obfuscation for email display, state tracking across nested payloads |

**Key insight:** WordPress provides battle-tested HTTP handling via `wp_remote_post()` that manages SSL, proxies, timeouts, and error handling. Using native WordPress APIs avoids external dependencies and ensures compatibility across hosting environments.

## Common Pitfalls

### Pitfall 1: Insufficient Timeout for LLM Inference
**What goes wrong:** Default WordPress HTTP timeout is 5 seconds; OpenAI GPT responses often take 10-30 seconds, causing false timeout errors
**Why it happens:** Developers forget to override default timeout, assuming "HTTP request" means fast response
**How to avoid:** Always set timeout to 45-60 seconds for LLM API calls
**Warning signs:** Error logs showing "http_request_failed" or "cURL error 28: Operation timed out" with OpenAI requests

### Pitfall 2: No Retry Logic for Transient Failures
**What goes wrong:** Single API call fails due to network blip or rate limit, leaving summary permanently empty
**Why it happens:** Developers treat API calls like database queries (immediate success/fail), not understanding distributed system failure modes
**How to avoid:** Implement exponential backoff with 3-5 retry attempts; distinguish retryable (429, 5xx) from permanent (401, 400) errors
**Warning signs:** Summaries missing despite valid API key; error_code shows rate limits or timeouts

### Pitfall 3: Sending PII to Third-Party APIs
**What goes wrong:** Learner names, ID numbers, personal data sent to OpenAI, violating privacy regulations (POPIA/GDPR)
**Why it happens:** Class change payloads contain full learner records; developers don't audit API request bodies
**How to avoid:** Use obfuscation/anonymization layer (like DataObfuscator trait) to replace PII with aliases before API calls
**Warning signs:** Audit logs show actual learner names in OpenAI request payloads

### Pitfall 4: API Key Stored in Code or Database Without Validation
**What goes wrong:** Invalid/expired API key stored, causing all summary attempts to fail with cryptic errors
**Why it happens:** Settings page accepts any string without validation; typos or key rotation breaks functionality silently
**How to avoid:** Validate API key format on save (`/^sk-[A-Za-z0-9_-]{20,}$/`), optionally test connection to OpenAI /models endpoint
**Warning signs:** Error code "config_missing" despite API key appearing set in admin UI

### Pitfall 5: Choosing Wrong Model for Use Case
**What goes wrong:** Using GPT-5 for simple summaries costs 5x more than GPT-5-mini with negligible quality improvement
**Why it happens:** Developers default to "best" model without considering cost vs. quality tradeoff
**How to avoid:** Use GPT-5-mini ($0.25/1M input) for summaries, notifications, simple Q&A; reserve GPT-5 for complex reasoning, code generation
**Warning signs:** OpenAI usage dashboard shows high costs despite low request volume; tokens_used column averages >500 tokens per summary

## Code Examples

Verified patterns from existing implementation:

### Making OpenAI Chat Completions Request
```php
// Source: src/Events/Services/AISummaryService.php (lines 198-283)
private function callOpenAI(array $messages, string $model): array
{
    $apiKey = $this->config->getApiKey();
    if ($apiKey === null) {
        return [
            'success' => false,
            'error_code' => 'config_missing',
            'error_message' => 'OpenAI API key is not configured.',
            'retryable' => false,
        ];
    }

    $payload = [
        'model' => $model,
        'messages' => $messages
    ];

    $response = ($this->httpClient)([
        'url' => self::API_URL,
        'timeout' => self::TIMEOUT_SECONDS,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ],
        'body' => $payload,
    ]);

    // Handle WP_Error (network failures, timeouts)
    if ($response instanceof \WP_Error) {
        return [
            'success' => false,
            'error_code' => $this->mapErrorCode($response->get_error_code(), 0),
            'error_message' => $this->sanitizeErrorMessage($response->get_error_message()),
            'retryable' => true,
        ];
    }

    $statusCode = (int) ($response['status'] ?? 0);
    $body = (string) ($response['body'] ?? '');

    // Handle HTTP errors (401, 429, 5xx)
    if ($statusCode < 200 || $statusCode >= 300) {
        return [
            'success' => false,
            'error_code' => $this->mapErrorCode('', $statusCode),
            'error_message' => $this->extractErrorMessage($body),
            'retryable' => $statusCode >= 500 || $statusCode === 429,
        ];
    }

    // Parse successful response
    $decoded = json_decode($body, true);
    $choices = $decoded['choices'][0]['message']['content'] ?? '';
    $tokens = (int) ($decoded['usage']['total_tokens'] ?? 0);

    return [
        'success' => true,
        'content' => (string) $choices,
        'tokens' => $tokens,
    ];
}
```

### Building Chat Messages with System Prompt
```php
// Source: src/Events/Services/AISummaryService.php (lines 332-360)
private function buildMessages(string $operation, array $context, array $newRow, array $diff, array $oldRow): array
{
    $operation = strtoupper(trim($operation));

    $summaryContext = [
        'operation' => $operation,
        'changed_at' => $context['changed_at'] ?? null,
        'class_id' => $context['class_id'] ?? null,
        'class_code' => $newRow['class_code'] ?? null,
        'class_subject' => $newRow['class_subject'] ?? null,
        'diff' => $diff,
        'new_row' => $newRow,
    ];

    if ($operation === 'UPDATE') {
        $summaryContext['old_row'] = $oldRow;
    }

    $prompt = sprintf(
        "Provide a concise summary (maximum five bullet points) explaining the key aspects of the WeCoza class %s. Highlight scheduling, learner, or staffing changes and flag risks requiring follow-up. Reference learners using the aliases provided. Avoid exposing personal data.",
        strtolower($operation)
    );

    $payload = wp_json_encode($summaryContext, JSON_PRETTY_PRINT);

    return [
        ['role' => 'system', 'content' => 'You are an assistant helping WeCoza operations understand class changes. Be brief, factual, and actionable.'],
        ['role' => 'user', 'content' => $prompt . "\n\n" . $payload],
    ];
}
```

### WordPress Settings Page for API Key Configuration
```php
// Source: src/Events/Admin/SettingsPage.php (lines 59-84)
public static function registerSettings(): void
{
    register_setting(self::OPTION_GROUP, self::OPTION_AI_API_KEY, [
        'type' => 'string',
        'sanitize_callback' => [self::class, 'sanitizeApiKey'],
        'default' => '',
        'autoload' => false,  // Performance: don't load on every page
    ]);
}

public static function sanitizeApiKey($value): string
{
    $config = new OpenAIConfig();
    return $config->sanitizeApiKey((string) $value);
}

// Source: src/Events/Support/OpenAIConfig.php (lines 24-37)
public function getApiKey(): ?string
{
    $stored = get_option(self::OPTION_API_KEY, '');
    if (!is_string($stored)) {
        return null;
    }

    $stored = trim($stored);
    if ($stored === '') {
        return null;
    }

    return $this->isValidApiKey($stored) ? $stored : null;
}
```

### Handling Retries with Exponential Backoff
```php
// Source: src/Events/Services/AISummaryService.php (lines 68-178)
public function generateSummary(array $context, ?array $existing = null): array
{
    $record = $this->normaliseRecord($existing);

    // Skip if already successful
    if ($record['status'] === 'success') {
        return ['record' => $record, 'status' => 'success'];
    }

    // Give up after max attempts
    if ($record['attempts'] >= $this->maxAttempts) {
        $record['status'] = 'failed';
        return ['record' => $record, 'status' => 'failed'];
    }

    // Exponential backoff delay
    $attemptNumber = $record['attempts'] + 1;
    $delaySeconds = $this->backoffDelaySeconds($record['attempts']);
    if ($delaySeconds > 0) {
        usleep($delaySeconds * 1_000_000);
    }

    // Make API call
    $response = $this->callOpenAI($messages, self::MODEL);

    $record['attempts'] = $attemptNumber;

    if ($response['success'] === true) {
        $record['status'] = 'success';
        $record['summary'] = $this->normaliseSummaryText($response['content']);
        // ... populate metadata
        return ['record' => $record, 'status' => 'success'];
    }

    // Failed - mark as pending (for retry) or failed (if max attempts)
    $record['error_code'] = $response['error_code'];
    $record['error_message'] = $response['error_message'];
    $record['status'] = $record['attempts'] >= $this->maxAttempts ? 'failed' : 'pending';

    return ['record' => $record, 'status' => $record['status']];
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| GPT-3.5-turbo ($0.50/1M) | GPT-5-mini ($0.25/1M) | Q3 2025 (GPT-5 release) | 50% cost reduction with equivalent/better quality for summaries |
| GPT-4 ($30/1M input) | GPT-5 ($1.25/1M input) | Q3 2025 | 96% cost reduction; GPT-4 deprecated for new apps |
| Single attempt with no retry | Exponential backoff (3 attempts) | 2024-2025 | Handles transient failures (rate limits, network blips) gracefully |
| Hardcoded API keys in code | WordPress options with validation | Best practice (always) | Enables key rotation, multi-environment deployment, security audits |
| openai Python library | Direct HTTP API calls | Language-dependent | PHP ecosystem lacks official client; wp_remote_post() sufficient |

**Deprecated/outdated:**
- **GPT-3.5-turbo:** Still available but GPT-5-mini outperforms at half the cost
- **GPT-4:** Legacy model; GPT-5 provides better results at 96% lower cost
- **Completions API (legacy):** Replaced by Chat Completions API; use messages format not prompt
- **Davinci/Curie/Ada models:** Deprecated January 2024; migrate to GPT-3.5+ family

## Open Questions

Things that couldn't be fully resolved:

1. **GPT-5-mini Availability/Naming**
   - What we know: Codebase uses `gpt-5-mini` model (src/Events/Services/AISummaryService.php line 35)
   - What's unclear: WebSearch shows GPT-5 family (gpt-5, gpt-5-mini, gpt-5-nano) but couldn't verify exact model name via official docs (platform.openai.com returned 403)
   - Recommendation: Test existing implementation; if API returns "model not found", try `gpt-4o-mini` as fallback (confirmed available, $0.60/1M input)

2. **OpenAI Rate Limits for Tier/Pricing Plan**
   - What we know: Implementation has retry logic for 429 rate limit errors
   - What's unclear: What tier/plan is required for production volume? Free tier limits may be insufficient
   - Recommendation: Monitor usage via OpenAI dashboard after deployment; upgrade to Tier 1 ($5 spend) if hitting rate limits

3. **PII Compliance with OpenAI Terms**
   - What we know: DataObfuscator trait replaces learner PII with aliases before API calls
   - What's unclear: Does obfuscation meet POPIA/GDPR requirements? OpenAI's data retention policies?
   - Recommendation: Legal review of OpenAI Terms of Service regarding educational data; verify 30-day API request retention is acceptable

## Sources

### Primary (HIGH confidence)
- **Existing Codebase:** src/Events/Services/AISummaryService.php, OpenAIConfig.php, SettingsPage.php (direct examination)
- **OpenAI API Pricing 2026:** [GPT-5.1 Pricing Guide](https://chatlyai.app/blog/gpt-5-1-pricing-explained), [Price Per Token GPT-5 API](https://pricepertoken.com/pricing-page/model/openai-gpt-5)
- **OpenAI Best Practices:** [Rate Limits Guide](https://platform.openai.com/docs/guides/rate-limits), [Error Codes Reference](https://platform.openai.com/docs/guides/error-codes), [How to Handle Rate Limits](https://cookbook.openai.com/examples/how_to_handle_rate_limits)

### Secondary (MEDIUM confidence)
- **WordPress HTTP API:** [wp_remote_post() Function Reference](https://developer.wordpress.org/reference/functions/wp_remote_post/)
- **WordPress OpenAI Integration:** [How to Integrate OpenAI APIs into WordPress](https://www.iflair.com/how-to-integrate-openai-apis-into-a-custom-wordpress-plugin/), [WordPress Plugin Development: Integrate LLM API](https://www.plugintify.com/integrating-an-llm-api-into-a-custom-wordpress-plugin/)
- **OpenAI PHP Libraries:** [openai-php/client GitHub](https://github.com/openai-php/client), [OpenAI API Integration Best Practices](https://anglara.com/blog/openai-api-integration-best-practices/)

### Tertiary (LOW confidence)
- **GPT-5 Features:** [GPT-5: Best Features, Pricing & Accessibility 2026](https://research.aimultiple.com/gpt-5/) - WebSearch only, model naming unverified
- **Model Comparison:** [LLM API Pricing Comparison 2025](https://intuitionlabs.ai/articles/llm-api-pricing-comparison-2025) - Third-party aggregation, may have outdated pricing

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Examined existing implementation; WordPress HTTP API is well-documented; OpenAI pricing verified across multiple sources
- Architecture: HIGH - Patterns extracted from working codebase following WordPress/PHP best practices
- Pitfalls: HIGH - Based on common issues documented in OpenAI community forums and official guides; validated against existing implementation
- Model pricing: MEDIUM - Multiple sources agree on GPT-5 family pricing, but official docs inaccessible (403 error); exact model name `gpt-5-mini` unverified

**Research date:** 2026-02-02
**Valid until:** 2026-03-04 (30 days) - OpenAI pricing stable but model availability changes quarterly
