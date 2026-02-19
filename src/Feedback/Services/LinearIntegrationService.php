<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Services;

use WeCoza\Feedback\Support\SchemaContext;

final class LinearIntegrationService
{
    private const API_URL = 'https://api.linear.app/graphql';
    private const TIMEOUT = 20;
    private const LABEL_CACHE_KEY = 'wecoza_linear_feedback_labels';
    private const LABEL_CACHE_TTL = 86400; // 24 hours

    private string $apiKey;
    private string $teamId;

    public function __construct(?string $apiKey = null, ?string $teamId = null)
    {
        $this->apiKey = $apiKey ?? (string) get_option('wecoza_linear_api_key', '');
        $this->teamId = $teamId ?? (string) get_option('wecoza_linear_team_id', '');
    }

    /**
     * Create a Linear issue from a feedback record.
     *
     * @return array{success: bool, issue_id?: string, issue_url?: string, error?: string, retryable?: bool}
     */
    public function createIssue(array $record): array
    {
        $preflight = $this->preflightCheck();
        if ($preflight !== null) {
            return $preflight;
        }

        // Build issue content
        $title = $record['ai_generated_title'] ?: substr($record['feedback_text'], 0, 80);
        $priority = $this->mapPriority($record['ai_suggested_priority'] ?? 'Medium');

        $body = $this->buildIssueBody($record);

        // Get or create labels
        $labelIds = $this->resolveLabelIds($record['category'], $record['shortcode'] ?? null, $record['page_url'] ?? null);

        // Create the issue
        $mutation = <<<'GRAPHQL'
mutation IssueCreate($input: IssueCreateInput!) {
    issueCreate(input: $input) {
        success
        issue {
            id
            identifier
            url
        }
    }
}
GRAPHQL;

        $input = [
            'teamId'      => $this->teamId,
            'title'       => $title,
            'description' => $body,
            'priority'    => $priority,
        ];

        if (!empty($labelIds)) {
            $input['labelIds'] = $labelIds;
        }

        $result = $this->graphql($mutation, ['input' => $input]);
        if ($result === null) {
            return ['success' => false, 'error' => 'Linear API request failed', 'retryable' => true];
        }

        if (!empty($result['errors'])) {
            $errorMsg = $result['errors'][0]['message'] ?? 'Unknown GraphQL error';
            $retryable = !str_contains(strtolower($errorMsg), 'invalid') && !str_contains(strtolower($errorMsg), 'not found');
            return ['success' => false, 'error' => $errorMsg, 'retryable' => $retryable];
        }

        $issueData = $result['data']['issueCreate'] ?? null;
        if (!$issueData || !$issueData['success']) {
            return ['success' => false, 'error' => 'Issue creation returned unsuccessful', 'retryable' => true];
        }

        $issue = $issueData['issue'];

        // Attach screenshot if available
        if (!empty($record['screenshot_path'])) {
            $this->attachScreenshot($issue['id'], $record['screenshot_path']);
        }

        return [
            'success'   => true,
            'issue_id'  => $issue['identifier'],
            'issue_url' => $issue['url'],
        ];
    }

    /**
     * @return array{success: false, error: string, retryable: false}|null
     */
    private function preflightCheck(): ?array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Linear API key not configured', 'retryable' => false];
        }
        if (empty($this->teamId)) {
            return ['success' => false, 'error' => 'Linear team ID not configured', 'retryable' => false];
        }
        return null;
    }

    private function buildIssueBody(array $record): string
    {
        $categoryLabel = match ($record['category']) {
            'bug_report'      => 'Bug Report',
            'feature_request' => 'Feature Request',
            default           => 'Comment',
        };

        $body = "## Feedback\n\n{$record['feedback_text']}\n\n";

        // Include AI conversation if present
        $conversation = json_decode($record['ai_conversation'] ?? '[]', true);
        if (!empty($conversation)) {
            $body .= "### Follow-up Conversation\n\n";
            foreach ($conversation as $entry) {
                if (isset($entry['question'])) {
                    $body .= "**Q:** {$entry['question']}\n";
                }
                if (isset($entry['answer'])) {
                    $body .= "**A:** {$entry['answer']}\n\n";
                }
            }
        }

        $body .= "## Category\n\n{$categoryLabel}\n\n";
        $body .= "## Page Context\n\n";
        $body .= "- **Page:** " . ($record['page_title'] ?? 'N/A') . "\n";
        $body .= "- **URL:** " . ($record['page_url'] ?? 'N/A') . "\n";

        if (!empty($record['shortcode'])) {
            $body .= "- **Shortcode:** {$record['shortcode']}\n";
        }

        $body .= "- **User:** {$record['user_email']}\n";
        $body .= "- **Browser:** " . ($record['browser_info'] ?? 'N/A') . "\n";
        $body .= "- **Viewport:** " . ($record['viewport'] ?? 'N/A') . "\n";
        $body .= "- **Timestamp:** {$record['created_at']}\n";

        if (!empty($record['ai_suggested_priority'])) {
            $body .= "\n## AI Analysis\n\n";
            $body .= "- **Priority suggestion:** {$record['ai_suggested_priority']}\n";

            $module = SchemaContext::detectModule($record['shortcode'] ?? null, $record['page_url'] ?? null);
            if ($module) {
                $body .= "- **Module:** " . ucfirst($module) . "\n";
            }
        }

        return $body;
    }

    /**
     * Map priority string to Linear priority int (0=none, 1=urgent, 2=high, 3=medium, 4=low)
     */
    private function mapPriority(?string $priority): int
    {
        return match ($priority) {
            'Urgent' => 1,
            'High'   => 2,
            'Medium' => 3,
            'Low'    => 4,
            default  => 3,
        };
    }

    /**
     * Resolve label IDs, creating missing labels as needed. Cached for 24h.
     *
     * @return string[]
     */
    private function resolveLabelIds(string $category, ?string $shortcode, ?string $pageUrl): array
    {
        $cached = get_transient(self::LABEL_CACHE_KEY);
        $labelMap = is_array($cached) ? $cached : [];

        $desiredLabels = ['UAT Feedback'];

        // Category label
        $categoryLabel = match ($category) {
            'bug_report'      => 'Bug',
            'feature_request' => 'Feature Request',
            default           => 'Comment',
        };
        $desiredLabels[] = $categoryLabel;

        // Module label
        $module = SchemaContext::detectModule($shortcode, $pageUrl);
        if ($module) {
            $desiredLabels[] = ucfirst($module);
        }

        // Check which labels we need to fetch/create
        $missingLabels = array_filter($desiredLabels, fn($name) => !isset($labelMap[$name]));

        if (!empty($missingLabels)) {
            $labelMap = $this->syncLabels($labelMap, $missingLabels);
            set_transient(self::LABEL_CACHE_KEY, $labelMap, self::LABEL_CACHE_TTL);
        }

        $ids = [];
        foreach ($desiredLabels as $name) {
            if (isset($labelMap[$name])) {
                $ids[] = $labelMap[$name];
            }
        }

        return $ids;
    }

    /**
     * Fetch existing labels and create missing ones.
     *
     * @return array<string, string> name => id map
     */
    private function syncLabels(array $existingMap, array $missingNames): array
    {
        // Fetch all team labels
        $query = <<<'GRAPHQL'
query TeamLabels($teamId: String!) {
    team(id: $teamId) {
        labels {
            nodes {
                id
                name
            }
        }
    }
}
GRAPHQL;

        $result = $this->graphql($query, ['teamId' => $this->teamId]);
        $nodes = $result['data']['team']['labels']['nodes'] ?? [];

        foreach ($nodes as $node) {
            $existingMap[$node['name']] = $node['id'];
        }

        // Create any still-missing labels
        foreach ($missingNames as $name) {
            if (isset($existingMap[$name])) {
                continue;
            }

            $createMutation = <<<'GRAPHQL'
mutation CreateLabel($input: IssueLabelCreateInput!) {
    issueLabelCreate(input: $input) {
        success
        issueLabel {
            id
            name
        }
    }
}
GRAPHQL;

            $createResult = $this->graphql($createMutation, [
                'input' => [
                    'teamId' => $this->teamId,
                    'name'   => $name,
                ],
            ]);

            $labelData = $createResult['data']['issueLabelCreate'] ?? null;
            if ($labelData && $labelData['success']) {
                $existingMap[$labelData['issueLabel']['name']] = $labelData['issueLabel']['id'];
            }
        }

        return $existingMap;
    }

    /**
     * Attach a screenshot to a Linear issue via attachment URL.
     */
    private function attachScreenshot(string $issueId, string $screenshotPath): void
    {
        // Convert server path to URL
        $uploadsDir = wp_upload_dir();
        $screenshotUrl = str_replace(
            $uploadsDir['basedir'],
            $uploadsDir['baseurl'],
            $screenshotPath
        );

        // If path doesn't start with uploads dir, try constructing URL directly
        if (!str_starts_with($screenshotPath, $uploadsDir['basedir'])) {
            wecoza_log("LinearIntegrationService: Screenshot path not in uploads dir: {$screenshotPath}");
            return;
        }

        $mutation = <<<'GRAPHQL'
mutation AttachScreenshot($input: AttachmentCreateInput!) {
    attachmentCreate(input: $input) {
        success
    }
}
GRAPHQL;

        $this->graphql($mutation, [
            'input' => [
                'issueId' => $issueId,
                'title'   => 'Screenshot',
                'url'     => $screenshotUrl,
            ],
        ]);
    }

    /**
     * Execute a GraphQL query/mutation against Linear API.
     */
    private function graphql(string $query, array $variables = []): ?array
    {
        $body = wp_json_encode([
            'query'     => $query,
            'variables' => $variables,
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
            wecoza_log('LinearIntegrationService: API call failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            wecoza_log("LinearIntegrationService: API returned HTTP {$statusCode}", 'error');
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
