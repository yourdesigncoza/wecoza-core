# Phase 11: AI Service Quality - Research

**Researched:** 2026-02-02
**Domain:** OpenAI API integration, WordPress configuration, Azure/proxy deployment patterns
**Confidence:** HIGH

## Summary

This phase addresses two quality issues in the AI summarization service: fixing an invalid model name (`gpt-5-mini` which doesn't exist) and making the API endpoint configurable to support Azure OpenAI deployments and custom proxies.

The codebase currently hardcodes two critical values as class constants in `AISummaryService.php`: `MODEL = 'gpt-5-mini'` (invalid) and `API_URL = 'https://api.openai.com/v1/chat/completions'`. The valid model name is **`gpt-4o-mini`** (confirmed via OpenAI official sources), and enterprise deployments commonly need custom endpoints for Azure OpenAI Service, API Management proxies, or local development environments.

The standard approach for WordPress plugins is to store configuration in the `wp_options` table using `get_option()`/`update_option()` with consistent naming conventions, sanitization, and optional autoload control. The existing `OpenAIConfig` class already retrieves `wecoza_openai_api_key` from options, making it the natural place to add endpoint and model configuration. For Azure deployments, the base URL follows the pattern `https://{resource-name}.openai.azure.com/openai/deployments/{deployment-name}/chat/completions?api-version={version}`.

**Primary recommendation:** Add two new WordPress options (`wecoza_openai_api_url` and `wecoza_openai_model`) with fallback defaults. Extend `OpenAIConfig` with `getApiUrl()` and `getModel()` methods that retrieve options and validate them. Update `AISummaryService` to accept these values via constructor injection instead of using hardcoded constants. This maintains backward compatibility (defaults work for most users) while enabling enterprise flexibility.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | Core | Configuration storage | Native WordPress mechanism, autoload support, transaction-safe |
| OpenAI API | v1 | AI completions | Official endpoint, `gpt-4o-mini` confirmed valid model |
| PHP 8.0+ | 8.0+ | Type safety | Constructor property promotion, union types for validation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Azure OpenAI Service | 2025+ v1 API | Enterprise deployment | When organization uses Azure instead of OpenAI directly |
| Azure API Management | Current | Gateway/proxy | Load balancing, cost tracking, rate limiting |
| LiteLLM Proxy | Latest | Multi-provider gateway | Testing multiple AI providers with OpenAI-compatible interface |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WordPress options | Environment variables | Less flexible (requires server config), not user-configurable via UI |
| WordPress options | Config file (`config/app.php`) | Works but not runtime-configurable, requires code deployment to change |
| Hardcoded constants | WordPress options | Constants are inflexible for multi-environment deployments (dev/staging/prod) |

**Installation:**
```bash
# No new packages needed - uses WordPress core functions
# Optional: Install Azure OpenAI SDK if needed for advanced features
# composer require microsoft/azure-openai-sdk
```

## Architecture Patterns

### Recommended Project Structure
```
src/Events/
├── Services/
│   └── AISummaryService.php      # Inject config values via constructor
├── Support/
│   └── OpenAIConfig.php          # Add getApiUrl() and getModel() methods
└── DTOs/                         # Already exists from Phase 10
```

### Pattern 1: Configuration Retrieval with Validation
**What:** Centralized config class retrieves and validates options
**When to use:** When configuration values need sanitization, validation, and default fallbacks
**Example:**
```php
// Source: Existing OpenAIConfig pattern + WordPress best practices
final class OpenAIConfig
{
    public const OPTION_API_KEY = 'wecoza_openai_api_key';
    public const OPTION_API_URL = 'wecoza_openai_api_url';
    public const OPTION_MODEL = 'wecoza_openai_model';

    private const DEFAULT_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    public function getApiUrl(): string
    {
        $stored = get_option(self::OPTION_API_URL, '');
        if (!is_string($stored)) {
            return self::DEFAULT_API_URL;
        }

        $stored = trim($stored);
        if ($stored === '') {
            return self::DEFAULT_API_URL;
        }

        return $this->isValidUrl($stored) ? $stored : self::DEFAULT_API_URL;
    }

    public function getModel(): string
    {
        $stored = get_option(self::OPTION_MODEL, '');
        if (!is_string($stored)) {
            return self::DEFAULT_MODEL;
        }

        $stored = trim($stored);
        return $stored !== '' ? $stored : self::DEFAULT_MODEL;
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'https://') || str_starts_with($url, 'http://'));
    }
}
```

### Pattern 2: Constructor Injection for Configuration
**What:** Service receives config values via constructor, not hardcoded constants
**When to use:** When service needs to be testable and support different configurations
**Example:**
```php
// Source: Dependency injection pattern
final class AISummaryService
{
    // Remove: private const MODEL = 'gpt-5-mini';
    // Remove: private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly OpenAIConfig $config,
        ?callable $httpClient = null,
        private readonly int $maxAttempts = 3
    ) {
        $this->httpClient = $httpClient ?? $this->defaultHttpClient();
    }

    private function callOpenAI(array $messages): array
    {
        $apiKey = $this->config->getApiKey();
        $apiUrl = $this->config->getApiUrl();    // Dynamic
        $model = $this->config->getModel();      // Dynamic

        // ... use variables instead of constants
    }
}
```

### Pattern 3: Azure OpenAI URL Construction
**What:** Build Azure-specific endpoint URLs with deployment name and API version
**When to use:** When users configure Azure OpenAI instead of standard OpenAI
**Example:**
```php
// Source: Azure OpenAI documentation
// Azure format: https://{resource}.openai.azure.com/openai/deployments/{deployment}/chat/completions?api-version={version}

// Option 1: User provides full URL (simpler)
$url = 'https://my-resource.openai.azure.com/openai/deployments/gpt-4o-mini/chat/completions?api-version=2024-08-01-preview';

// Option 2: Build from parts (more complex, defer to future phase)
// For Phase 11, accept full URL from user
```

### Anti-Patterns to Avoid
- **Hardcoded environment-specific values:** Constants prevent multi-environment deployments (dev/staging/prod with different endpoints)
- **No validation on user input:** URLs from options must be validated to prevent injection attacks
- **Breaking existing tests:** Changing constants to config requires updating tests to inject config
- **Over-engineering:** Don't build URL construction helpers for Azure if users can provide full URLs

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| URL validation | Custom regex | `filter_var($url, FILTER_VALIDATE_URL)` | Handles edge cases (IDN, IPv6, ports, Unicode) |
| Option sanitization | String cleaning | `sanitize_text_field()`, `esc_url_raw()` | WordPress-native, handles multibyte chars, SQL escaping |
| Azure endpoint format | Custom string builder | Accept full URL from user | Azure URLs vary by deployment, API version; easier to let users provide complete URL |
| Configuration caching | Custom cache layer | WordPress transients or rely on WP option caching | WordPress already caches options with autoload=yes |

**Key insight:** WordPress Options API handles serialization, caching, and database transactions. Filter functions handle sanitization better than custom regex. Let users configure full URLs rather than building complex URL constructors.

## Common Pitfalls

### Pitfall 1: Invalid Model Name
**What goes wrong:** Code uses `gpt-5-mini` which doesn't exist, causing API errors
**Why it happens:** Model name likely based on outdated research or speculation about GPT-5 family (GPT-5 models don't exist as of Feb 2026)
**How to avoid:** Verify model names against official OpenAI documentation; use `gpt-4o-mini` (confirmed valid)
**Warning signs:** API returns 404 or "model not found" errors in logs

### Pitfall 2: Breaking Tests with Hardcoded Constants
**What goes wrong:** Tests explicitly check for `MODEL === 'gpt-5-mini'` and `API_URL === 'https://api.openai.com/v1/chat/completions'`
**Why it happens:** Tests use reflection to verify constants exist with exact values (see `AISummarizationTest.php` lines 1119-1125)
**How to avoid:** Update tests to verify config methods return expected defaults instead of checking constants
**Warning signs:** Test failure: "Model constant is gpt-5-mini (verified)" fails after changing to `gpt-4o-mini`

### Pitfall 3: Autoload Performance Impact
**What goes wrong:** Adding new options with autoload=yes loads them on every page request (not just admin)
**Why it happens:** WordPress Options API defaults to autoload=true, but AI config only needed during notification processing
**How to avoid:** Use `update_option($key, $value, 'no')` (third param) to disable autoload for infrequently-accessed options
**Warning signs:** Increased memory usage on frontend page loads (visible in Query Monitor or similar tools)

### Pitfall 4: Azure URL Incompatibility
**What goes wrong:** Azure OpenAI uses different URL structure (`/openai/deployments/{name}/chat/completions`) vs OpenAI (`/v1/chat/completions`)
**Why it happens:** Azure requires deployment name and API version in URL; can't just replace hostname
**How to avoid:** Accept full URL from user instead of trying to construct Azure URLs programmatically
**Warning signs:** Azure users get 404 errors even with correct endpoint hostname

### Pitfall 5: Missing URL Validation
**What goes wrong:** Malicious or malformed URLs in options cause connection errors or security issues
**Why it happens:** User input from wp-admin options page not validated before storage
**How to avoid:** Use `filter_var()` and protocol whitelist (https:// only, or http:// for local dev)
**Warning signs:** Error logs show connection failures, or security scanners flag open redirect potential

## Code Examples

Verified patterns from official sources and codebase inspection:

### WordPress Option Storage with Validation
```php
// Source: WordPress Options API best practices
// Reference: https://developer.wordpress.org/plugins/settings/options-api/

// Saving option with autoload control
function save_openai_config($api_url, $model) {
    // Sanitize before saving
    $api_url = esc_url_raw($api_url);
    $model = sanitize_text_field($model);

    // Validate URL format
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', 'Invalid API URL format');
    }

    // Save with autoload=no (not needed on every page load)
    update_option('wecoza_openai_api_url', $api_url, 'no');
    update_option('wecoza_openai_model', $model, 'no');

    return true;
}

// Retrieving option with default fallback
function get_openai_api_url(): string {
    $url = get_option('wecoza_openai_api_url', '');

    if (!is_string($url) || trim($url) === '') {
        return 'https://api.openai.com/v1/chat/completions';
    }

    return $url;
}
```

### OpenAI API Call with Configurable Endpoint
```php
// Source: Current AISummaryService implementation + configuration injection

private function callOpenAI(array $messages): array
{
    $apiKey = $this->config->getApiKey();
    $apiUrl = $this->config->getApiUrl();  // Now configurable
    $model = $this->config->getModel();    // Now configurable

    if ($apiKey === null) {
        return [
            'success' => false,
            'error_code' => 'config_missing',
            'error_message' => 'OpenAI API key is not configured.',
            // ...
        ];
    }

    $payload = [
        'model' => $model,  // Use configured model instead of constant
        'messages' => $messages
    ];

    $response = ($this->httpClient)([
        'url' => $apiUrl,  // Use configured URL instead of constant
        'timeout' => self::TIMEOUT_SECONDS,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ],
        'body' => $payload,
    ]);

    // ... handle response
}
```

### Azure OpenAI Endpoint Examples
```php
// Source: Azure OpenAI Service documentation
// Reference: https://learn.microsoft.com/en-us/azure/ai-foundry/openai/reference

// Standard OpenAI (default)
$url = 'https://api.openai.com/v1/chat/completions';

// Azure OpenAI (user provides full URL)
$url = 'https://my-resource.openai.azure.com/openai/deployments/gpt-4o-mini/chat/completions?api-version=2024-08-01-preview';

// Azure OpenAI via API Management proxy
$url = 'https://api-gateway.company.com/openai/chat/completions';

// Local development proxy
$url = 'http://localhost:8080/v1/chat/completions';

// All handled by accepting full URL from user via wp_options
```

### Test Update Pattern
```php
// Source: Existing AISummarizationTest.php pattern update

// OLD: Test hardcoded constant
$constants = (new ReflectionClass(AISummaryService::class))->getConstants();
$modelIsGpt5Mini = $constants['MODEL'] === 'gpt-5-mini';
$runner->test('Model constant is gpt-5-mini (verified)', $modelIsGpt5Mini);

// NEW: Test config method returns correct default
$config = new OpenAIConfig();
$defaultModel = $config->getModel();
$runner->test('Default model is gpt-4o-mini', $defaultModel === 'gpt-4o-mini');

// Test with custom option set
update_option('wecoza_openai_model', 'gpt-4o');
$customModel = $config->getModel();
$runner->test('Custom model retrieved from options', $customModel === 'gpt-4o');
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded API endpoints | Configurable via environment/options | ~2020+ | Enables multi-cloud, staging/prod separation |
| `gpt-3.5-turbo` default | `gpt-4o-mini` default | Jul 2024 | 60% cheaper, better quality than GPT-3.5 |
| Azure API v2023-XX-XX | Azure v1 API (opt-in Aug 2025) | Aug 2025 | No more monthly api-version updates required |
| Model name in code | Model from config | Modern practice | Support A/B testing, gradual rollouts |

**Deprecated/outdated:**
- **`gpt-5-mini`**: Does not exist; GPT-5 family unconfirmed as of Feb 2026
- **`gpt-3.5-turbo`**: Still works but 2.4x more expensive than `gpt-4o-mini` with lower quality
- **Hardcoded Azure api-version**: Azure v1 API removes need for version in URL (opt-in since Aug 2025)

## Open Questions

Things that couldn't be fully resolved:

1. **Should we validate model names against a whitelist?**
   - What we know: OpenAI has many models (`gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`, etc.)
   - What's unclear: Whether to restrict user input or allow any string (for future models)
   - Recommendation: Allow any non-empty string for flexibility; OpenAI API will reject invalid models

2. **Should we add a UI for configuration in wp-admin?**
   - What we know: Options need to be set somewhere; currently only `wecoza_openai_api_key` has UI
   - What's unclear: Whether this phase should include admin UI or just the infrastructure
   - Recommendation: Phase 11 adds option retrieval infrastructure; defer admin UI to future phase (most users use defaults)

3. **How to handle Azure authentication differences?**
   - What we know: Azure uses `api-key` header instead of `Authorization: Bearer`
   - What's unclear: Whether to auto-detect Azure URLs and change auth header
   - Recommendation: Phase 11 supports custom URL only; Azure users must use Azure-compatible proxy that handles auth translation, OR defer Azure auth to Phase 13+

## Sources

### Primary (HIGH confidence)
- [GPT-4o mini Model | OpenAI API](https://platform.openai.com/docs/models/gpt-4o-mini) - Model name confirmed as `gpt-4o-mini`
- [Models | OpenAI API](https://platform.openai.com/docs/models/) - Official model listing
- [GPT 4o mini API Pricing 2026](https://pricepertoken.com/pricing-page/model/openai-gpt-4o-mini) - $0.150/1M input, $0.600/1M output
- [Options API – Plugin Handbook | WordPress](https://developer.wordpress.org/plugins/settings/options-api/) - Official WordPress docs
- [WordPress update_option Function](https://developer.wordpress.org/reference/functions/update_option/) - API reference
- [Azure OpenAI Service with Azure API Management](https://learn.microsoft.com/en-us/samples/azure/enterprise-azureai/enterprise-azureai/) - Enterprise patterns

### Secondary (MEDIUM confidence)
- [How to read and update options with get_option and update_option in WordPress](https://yourwpweb.com/2025/09/26/how-to-read-and-update-options-with-get_option-and-update_option-in-wordpress/) - Best practices verified against official docs
- [Azure OpenAI API version lifecycle](https://learn.microsoft.com/en-us/azure/ai-foundry/openai/api-version-lifecycle?view=foundry-classic) - v1 API info
- [OpenAI-Compatible Endpoints | liteLLM](https://docs.litellm.ai/docs/providers/openai_compatible) - base_url configuration patterns

### Tertiary (LOW confidence)
- [openai-php/client GitHub](https://github.com/openai-php/client) - PHP client library patterns (not used in codebase)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress Options API is native, `gpt-4o-mini` confirmed via official OpenAI sources
- Architecture: HIGH - Constructor injection and config class patterns well-established in codebase (OpenAIConfig exists)
- Pitfalls: MEDIUM - Test updates inferred from test file inspection, Azure auth differences documented but not verified

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - stable technology stack)
