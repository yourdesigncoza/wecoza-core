# Error Handling Contract

**Version**: 1.1
**Date**: 2026-02-16
**Scope**: All WeCoza Core plugin PHP code

---

## Overview

WeCoza Core uses three error handling patterns, each suited to different contexts. All code must follow these patterns consistently.

| Pattern | Use When | Return Type | Consumer |
|---------|----------|-------------|----------|
| **Exceptions** | Unexpected/exceptional failures | `throw \Exception` | Service callers, Controllers |
| **Structured Arrays** | Expected/recoverable failures | `['success' => bool, ...]` | Controllers, AJAX handlers |
| **WP_Error** | WordPress integration points | `\WP_Error` | WordPress core, hooks |

---

## Pattern 1: Exceptions

**Use for**: Business logic violations, unexpected runtime errors, infrastructure failures (database, filesystem), and programmer errors.

**When to use**:
- A precondition is violated (e.g., learner already has an active LP)
- A database operation fails unexpectedly
- Required external resources are unavailable
- The operation cannot reasonably continue

### Service Layer Example

```php
class ProgressionService
{
    /**
     * Start a new LP for a learner
     *
     * @throws \Exception If learner already has an in-progress LP and $forceOverride is false
     * @throws \Exception If the progression record fails to save
     */
    public function startLearnerProgression(
        int $learnerId,
        int $productId,
        ?int $classId = null,
        ?string $notes = null,
        bool $forceOverride = false
    ): LearnerProgressionModel {
        $current = LearnerProgressionModel::getCurrentForLearner($learnerId);

        if ($current && !$forceOverride) {
            throw new \Exception("Learner already has an in-progress LP: " . $current->getProductName());
        }

        $progression = new LearnerProgressionModel([...]);

        if (!$progression->save()) {
            throw new \Exception("Failed to create progression record");
        }

        return $progression;
    }
}
```

### Controller Handling

Controllers **must** catch exceptions from services and convert them to JSON error responses. Exceptions must never reach the frontend raw.

```php
public function handleUpdate(): void
{
    try {
        $tasks = $this->manager->markTaskCompleted($classId, $taskId, ...);
        $this->sendSuccess(['tasks' => $tasks]);
    } catch (\Throwable $exception) {
        $this->sendError($exception->getMessage(), 500);
    }
}
```

### Rules

1. Always document exceptions with `@throws` PHPDoc tags
2. Use `\RuntimeException` for runtime failures, `\InvalidArgumentException` for bad input
3. Include actionable context in exception messages
4. Never expose internal details (SQL, stack traces) in exception messages sent to users
5. Log the full exception with `error_log()` or `wecoza_log()` before converting to user-facing message

---

## Pattern 2: Structured Arrays

**Use for**: Expected failures that are part of normal application flow -- validation errors, API errors with retry logic, operations where the caller needs granular failure details.

**When to use**:
- Validation failures (form data, business rules)
- External API calls that may fail (OpenAI, email)
- Operations where the caller decides what to do with the failure
- Multi-step processes where partial success is possible

### Standard Structure

```php
// Success
return [
    'success' => true,
    'message' => 'Operation completed successfully',
    'data'    => [...],  // Optional: operation-specific data
];

// Failure
return [
    'success'       => false,
    'error_code'    => 'validation_failed',  // Machine-readable code
    'error_message' => 'Email is required',  // Human-readable message
    'errors'        => ['email' => 'This field is required'],  // Optional: field-level errors
];
```

### Service Layer Example (AISummaryService pattern)

```php
private function callOpenAI(array $messages): array
{
    $apiKey = $this->config->getApiKey();
    if ($apiKey === null) {
        return [
            'success'       => false,
            'content'       => '',
            'error_code'    => 'config_missing',
            'error_message' => 'OpenAI API key is not configured.',
            'retryable'     => false,
        ];
    }

    // ... API call ...

    if ($statusCode >= 400) {
        return [
            'success'       => false,
            'content'       => '',
            'error_code'    => $this->mapErrorCode('', $statusCode),
            'error_message' => $errorMessage,
            'retryable'     => $statusCode >= 500 || $statusCode === 429,
        ];
    }

    return [
        'success' => true,
        'content' => $choices,
        'tokens'  => $tokens,
    ];
}
```

### Service Layer Example (NotificationEmailer pattern)

```php
/**
 * Send email for a notification event
 *
 * @return array{success: bool, error_code?: string, error_message?: string}
 */
public function send(int $eventId, string $recipient, array $emailContext = []): array
{
    $event = $this->eventRepository->findByEventId($eventId);
    if ($event === null) {
        return [
            'success'       => false,
            'error_code'    => 'event_not_found',
            'error_message' => "Event not found for event_id {$eventId}",
        ];
    }

    if (!is_email($recipient)) {
        return [
            'success'       => false,
            'error_code'    => 'invalid_recipient',
            'error_message' => "Invalid recipient email for event {$eventId}",
        ];
    }

    // ... send email ...

    if (!$sent) {
        return [
            'success'       => false,
            'error_code'    => 'send_failed',
            'error_message' => "Email failed for event {$eventId}",
        ];
    }

    return ['success' => true];
}
```

### Rules

1. Always include `'success' => bool` as the first key
2. On failure, include `error_code` (machine-readable) and `error_message` (human-readable)
3. Field-level validation errors go in an `errors` associative array
4. Never mix exceptions and structured arrays in the same method -- pick one
5. Document the return structure with `@return` PHPDoc

---

## Pattern 3: WP_Error

**Use for**: Interfacing with WordPress core functions that expect or return `WP_Error` objects.

**When to use**:
- WordPress hook/filter callbacks that follow WP conventions
- Wrapping `wp_mail()`, `wp_insert_post()`, `wp_upload_bits()` etc.
- Any context where WordPress core checks `is_wp_error()`

### Example

```php
// Creating a WP_Error
if (!$user_id) {
    return new \WP_Error('user_not_found', 'User does not exist', ['status' => 404]);
}

// Checking for WP_Error from a WordPress function
$response = wp_remote_post($url, $args);
if (is_wp_error($response)) {
    wecoza_log('API call failed: ' . $response->get_error_message(), 'error');
    return [
        'success'       => false,
        'error_code'    => $response->get_error_code(),
        'error_message' => $response->get_error_message(),
    ];
}
```

### Rules

1. Only use `WP_Error` at WordPress integration boundaries
2. Convert `WP_Error` to structured arrays or exceptions at the service boundary
3. Never pass `WP_Error` objects up through the service layer to controllers

---

## Controller Error Response Contract

All AJAX controllers must return consistent JSON error responses using the WordPress `wp_send_json_error()` format.

### Standard JSON Error Response

```json
{
    "success": false,
    "data": {
        "message": "Human-readable error message"
    }
}
```

### Using BaseController

Controllers extending `BaseController` use the built-in helpers:

```php
// Simple error
$this->sendError('Invalid learner ID');

// Error with HTTP status code
$this->sendError('Insufficient permissions.', 403);

// Error with additional data
$this->sendError('Missing required fields', 400, ['missing_fields' => ['name', 'email']]);
```

### Using JsonResponder (Events module)

```php
$this->responder->error('Invalid task request.', 400);
$this->responder->success(['tasks' => $tasks]);
```

### Using AjaxSecurity (standalone AJAX handlers)

```php
AjaxSecurity::sendError('Permission denied.', 403);
AjaxSecurity::sendSuccess($data, 'Operation completed');
```

### Using wp_send_json_error directly (legacy/inline handlers)

```php
wp_send_json_error(['message' => 'Invalid ID'], 400);
```

### Which AJAX helper to use

| Context | Helper | Example |
|---------|--------|---------|
| Class extending `BaseController` | `$this->sendError()` | Controllers, QA module |
| Class with `AjaxSecurity` | `AjaxSecurity::sendError()` | Agents, Clients AJAX handlers |
| Events module controllers | `$this->responder->error()` | TaskController, MaterialTrackingController |
| Legacy procedural handlers | `wp_send_json_error()` | LearnerAjaxHandlers |
| Inline closures in bootstrap | `wp_send_json_error()` | wecoza-core.php notification handlers |

### Controller Error Handling Pattern

Every AJAX handler must follow this structure:

```php
public function ajaxHandler(): void
{
    // 1. Security checks (nonce + capability)
    $this->requireNonce('action_nonce');

    // 2. Input validation
    $id = $this->input('id', 'int');
    if (!$id) {
        $this->sendError('Invalid ID');
        return;
    }

    // 3. Business logic with exception handling
    try {
        $result = $this->service->doSomething($id);

        // 4a. Handle structured array responses
        if (isset($result['success']) && !$result['success']) {
            $this->sendError($result['error_message'] ?? 'Operation failed');
            return;
        }

        $this->sendSuccess($result);
    } catch (\Throwable $e) {
        // 4b. Catch exceptions and convert to JSON
        error_log('WeCoza: ' . $e->getMessage());
        $this->sendError($e->getMessage(), 500);
    }
}
```

---

## Error Codes Reference

Standard error codes used across the codebase:

| Code | Meaning | HTTP Status |
|------|---------|-------------|
| `validation_failed` | Input validation failed | 400 |
| `not_found` | Resource not found | 404 |
| `unauthorized` | Not authenticated | 401 |
| `forbidden` | Insufficient permissions | 403 |
| `conflict` | Resource state conflict | 409 |
| `config_missing` | Required configuration absent | 500 |
| `send_failed` | Email/notification send failed | 500 |
| `openai_timeout` | OpenAI API timeout | 504 |
| `quota_exceeded` | API rate limit hit | 429 |
| `unknown_error` | Unclassified error | 500 |

---

## Decision Matrix

Use this to choose the right pattern:

| Scenario | Pattern |
|----------|---------|
| Database save fails unexpectedly | Exception |
| User submits invalid form data | Structured Array |
| Learner already has active LP | Exception (precondition violated) |
| Email send fails | Structured Array (expected, retryable) |
| OpenAI API returns error | Structured Array (expected, retryable) |
| `wp_remote_post()` returns WP_Error | Convert WP_Error to Structured Array |
| WordPress hook callback | WP_Error |
| Controller receives exception from service | Catch and convert to `sendError()` JSON |
| Missing required POST field | `sendError()` or `requireFields()` |

---

## Anti-patterns

### Do NOT

1. **Return bare `false` or `null` for failures** -- always use structured arrays with error details
2. **Let exceptions bubble to the frontend** -- controllers must catch all exceptions
3. **Mix patterns in a single method** -- a method either throws or returns structured arrays
4. **Use `die()` or `exit()` after `wp_send_json_*`** -- WordPress handles exit internally via `wp_die()`
5. **Expose SQL or stack traces to users** -- log internally, send generic messages externally
6. **Return `WP_Error` from services** -- convert at the boundary
7. **Throw exceptions for validation failures in AJAX handlers** -- use early `return` with `wp_send_json_error()` or `sendError()` instead when the handler has no try/catch, or use the centralized try/catch pattern when one exists

### Before (anti-pattern)

```php
// Returns bool -- caller has no idea why it failed
public function send(int $eventId, string $recipient): bool
{
    $event = $this->eventRepository->findByEventId($eventId);
    if ($event === null) {
        return false;  // No context for the caller
    }
    // ...
    return $sent;
}
```

### After (correct)

```php
// Returns structured array -- caller knows exactly what went wrong
public function send(int $eventId, string $recipient): array
{
    $event = $this->eventRepository->findByEventId($eventId);
    if ($event === null) {
        return [
            'success'       => false,
            'error_code'    => 'event_not_found',
            'error_message' => "Event not found for event_id {$eventId}",
        ];
    }
    // ...
    return ['success' => true];
}
```
