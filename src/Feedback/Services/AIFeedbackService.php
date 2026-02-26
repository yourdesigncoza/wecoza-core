<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Services;

use WeCoza\Feedback\Support\SchemaContext;

final class AIFeedbackService
{
    private const MODEL = 'gpt-4.1';
    private const TIMEOUT = 30;
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (string) get_option('wecoza_openai_api_key', '');
    }

    /**
     * Check if feedback is clear and actionable.
     *
     * @return array{is_clear: bool, follow_up: ?string}
     */
    public function checkVagueness(
        string $feedbackText,
        string $category,
        ?string $shortcode = null,
        ?string $pageUrl = null,
        array $conversationHistory = []
    ): array {
        if (empty($this->apiKey)) {
            wecoza_log('AIFeedbackService: No OpenAI API key configured, skipping vagueness check');
            return ['is_clear' => true, 'follow_up' => null];
        }

        $module = SchemaContext::detectModule($shortcode, $pageUrl);
        $schemaContext = SchemaContext::getSchemaForModule($module);

        $categoryRules = match ($category) {
            'bug_report'      => 'Bug Report: The user must describe what happened AND what they expected to happen.',
            'feature_request' => 'Feature Request: The user must describe what they want AND why they need it.',
            default           => 'Comment: Must identify a specific topic with a concrete observation (not just "good" or "fine").',
        };

        $systemPrompt = <<<PROMPT
You are a feedback quality assistant for WeCoza, an internal training management system.
You help users write clear, actionable feedback.

Schema context:
{$schemaContext}

Evaluate the feedback and return JSON only (no other text):
- If feedback is clear and actionable: {"is_clear": true, "follow_up": null}
- If feedback is vague or unclear: {"is_clear": false, "follow_up": "Your specific question here"}

Rules:
- {$categoryRules}
- Under 10 characters or generic phrases like "fix this", "broken", "doesn't work" without context = vague
- Be concise in follow-up questions - one specific question only
- NEVER repeat or rephrase a question already asked in this conversation. Ask about something NEW.
- If the user has answered your previous questions, evaluate the COMBINED context to decide if feedback is now clear.
- When a shortcode or page URL is provided, you already know the page/screen context — do NOT ask which page or section the issue is on.

Handling dismissive or vague answers:
- If the user answers with dismissive phrases like "everything", "all of it", "nothing works", "I don't know", "it's all broken", or similarly unhelpful responses, do NOT rephrase the same question or ask the user to "elaborate" — that frustrates users.
- Instead, ask for ONE specific concrete example, e.g.: "Can you give me one specific example of what went wrong?"
- Always mark as vague and ask a follow-up when the answer is dismissive — the system will enforce the max round limit separately.

Example flow:
User: "it's broken"
You: {"is_clear": false, "follow_up": "What were you trying to do when it broke? For example, were you saving a form, loading a page, or clicking a button?"}
User: "everything"
You: {"is_clear": false, "follow_up": "Can you give me one specific example of something that went wrong?"}
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Include conversation history for multi-round follow-ups
        // AI asks questions (assistant role), user answers (user role)
        foreach ($conversationHistory as $entry) {
            if (isset($entry['question'])) {
                $messages[] = ['role' => 'assistant', 'content' => $entry['question']];
            }
            if (isset($entry['answer'])) {
                $messages[] = ['role' => 'user', 'content' => $entry['answer']];
            }
        }

        $contextParts = ["Category: {$category}"];
        if ($shortcode) {
            $contextParts[] = "Shortcode: {$shortcode}";
        }
        if ($pageUrl) {
            $contextParts[] = "Page URL: {$pageUrl}";
        }
        $contextParts[] = "Feedback: {$feedbackText}";
        $messages[] = ['role' => 'user', 'content' => implode("\n", $contextParts)];

        $response = $this->callOpenAI($messages);
        if ($response === null) {
            return ['is_clear' => true, 'follow_up' => null];
        }

        return $this->parseVaguenessResponse($response);
    }

    /**
     * Enrich feedback into a structured issue.
     *
     * @return array{title: string, priority: string, body: string}
     */
    public function enrich(
        string $feedbackText,
        string $category,
        ?string $shortcode = null,
        ?string $pageUrl = null,
        array $conversationHistory = []
    ): array {
        if (empty($this->apiKey)) {
            return $this->fallbackEnrichment($feedbackText, $category);
        }

        $module = SchemaContext::detectModule($shortcode, $pageUrl);
        $moduleName = $module ? ucfirst($module) : 'General';
        $schemaContext = SchemaContext::getSchemaForModule($module);

        $fullFeedback = $feedbackText;
        foreach ($conversationHistory as $entry) {
            if (isset($entry['answer'])) {
                $fullFeedback .= "\n\nFollow-up: " . $entry['question'];
                $fullFeedback .= "\nAnswer: " . $entry['answer'];
            }
        }

        $systemPrompt = <<<PROMPT
Generate a structured issue from this user feedback for an internal training management system.
Return JSON only (no other text):
{
  "title": "A concise, descriptive issue title (max 80 chars)",
  "priority": "Urgent|High|Medium|Low",
  "body": "Structured markdown body with ## sections"
}

Priority guidelines:
- Urgent: Data loss, system crash, security issue
- High: Feature broken, blocking user workflow
- Medium: Minor bug, UI issue, improvement needed
- Low: Cosmetic, nice-to-have, minor suggestion

Module: {$moduleName}
Category: {$category}

Schema context:
{$schemaContext}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $fullFeedback],
        ];

        $response = $this->callOpenAI($messages);
        if ($response === null) {
            return $this->fallbackEnrichment($feedbackText, $category);
        }

        return $this->parseEnrichmentResponse($response, $feedbackText, $category);
    }

    private function callOpenAI(array $messages): ?string
    {
        $body = wp_json_encode([
            'model'       => self::MODEL,
            'messages'    => $messages,
            'max_tokens' => 2048,
        ]);

        $response = wp_remote_post(self::API_URL, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            wecoza_log('AIFeedbackService: API call failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $errBody = wp_remote_retrieve_body($response);
            wecoza_log("AIFeedbackService: API returned HTTP {$statusCode}: {$errBody}", 'error');
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Parse vagueness check response with robust fallback
     *
     * @return array{is_clear: bool, follow_up: ?string}
     */
    private function parseVaguenessResponse(string $response): array
    {
        $data = $this->extractJson($response);

        if ($data !== null && isset($data['is_clear'])) {
            return [
                'is_clear'  => (bool) $data['is_clear'],
                'follow_up' => $data['follow_up'] ?? null,
            ];
        }

        wecoza_log('AIFeedbackService: Malformed vagueness response, defaulting to clear: ' . substr($response, 0, 200));
        return ['is_clear' => true, 'follow_up' => null];
    }

    /**
     * Parse enrichment response with robust fallback
     *
     * @return array{title: string, priority: string, body: string}
     */
    private function parseEnrichmentResponse(string $response, string $feedbackText, string $category): array
    {
        $data = $this->extractJson($response);

        if ($data !== null && isset($data['title'], $data['body'])) {
            return [
                'title'    => substr((string) $data['title'], 0, 500),
                'priority' => in_array($data['priority'] ?? '', ['Urgent', 'High', 'Medium', 'Low'], true)
                    ? $data['priority']
                    : 'Medium',
                'body'     => (string) $data['body'],
            ];
        }

        wecoza_log('AIFeedbackService: Malformed enrichment response, using fallback: ' . substr($response, 0, 200));
        return $this->fallbackEnrichment($feedbackText, $category);
    }

    /**
     * Try to extract JSON from a response that may contain extra text
     */
    private function extractJson(string $text): ?array
    {
        // Try direct parse first
        $data = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try extracting JSON from markdown code block or surrounding text
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * @return array{title: string, priority: string, body: string}
     */
    private function fallbackEnrichment(string $feedbackText, string $category): array
    {
        $categoryLabel = match ($category) {
            'bug_report'      => 'Bug Report',
            'feature_request' => 'Feature Request',
            default           => 'Comment',
        };

        $title = $categoryLabel . ': ' . substr($feedbackText, 0, 80);

        return [
            'title'    => $title,
            'priority' => 'Medium',
            'body'     => "## Feedback\n\n{$feedbackText}\n\n## Category\n\n{$categoryLabel}",
        ];
    }
}
