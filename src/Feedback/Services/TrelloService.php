<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Services;

final class TrelloService
{
    private const API_BASE = 'https://api.trello.com/1';
    private const TIMEOUT  = 15;

    private string $apiKey;
    private string $apiToken;
    private string $boardId;

    private ?array $cachedLists = null;
    private ?string $fullBoardId = null;
    private ?string $cachedMemberId = null;
    private bool $memberResolved = false;

    public function __construct(
        ?string $apiKey = null,
        ?string $apiToken = null,
        ?string $boardId = null
    ) {
        $this->apiKey   = $apiKey   ?? (string) get_option('wecoza_trello_api_key', '');
        $this->apiToken = $apiToken ?? (string) get_option('wecoza_trello_api_token', '');
        $this->boardId  = $boardId  ?? (string) get_option('wecoza_trello_board_id', '');
    }

    /**
     * Resolve short board ID (e.g. aCNdD5KG) to full 24-char hex ID.
     * Trello accepts short IDs for GET but requires full IDs for POST.
     */
    private function getFullBoardId(): ?string
    {
        if ($this->fullBoardId !== null) {
            return $this->fullBoardId;
        }

        // Already a full ID (24 hex chars)
        if (preg_match('/^[0-9a-f]{24}$/', $this->boardId)) {
            $this->fullBoardId = $this->boardId;
            return $this->fullBoardId;
        }

        $board = $this->get("/boards/{$this->boardId}", ['fields' => 'id']);
        if ($board !== null && isset($board['id'])) {
            $this->fullBoardId = $board['id'];
            return $this->fullBoardId;
        }

        wecoza_log('TrelloService: Could not resolve full board ID', 'error');
        return null;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiToken !== '' && $this->boardId !== '';
    }

    /**
     * Create a card in the "Open" list.
     *
     * @return array{id: string, url: string}|null
     */
    public function createCard(array $feedback): ?array
    {
        $listId = $this->findOrCreateList('Open');
        if ($listId === null) {
            return null;
        }

        $ref = $this->buildRef($feedback);
        $title = $feedback['ai_generated_title']
            ?: substr($feedback['feedback_text'] ?? '', 0, 60);
        $name = "[{$ref}] {$title}";

        $desc = $this->buildDescription($feedback);

        $cardData = [
            'idList' => $listId,
            'name'   => $name,
            'desc'   => $desc,
        ];

        $memberId = $this->resolveMemberId();
        if ($memberId !== null) {
            $cardData['idMembers'] = $memberId;
        }

        $response = $this->post('/cards', $cardData);

        if ($response === null) {
            return null;
        }

        $cardId = $response['id'] ?? null;
        $cardUrl = $response['shortUrl'] ?? $response['url'] ?? null;

        if ($cardId === null) {
            return null;
        }

        // Add labels (best-effort)
        $this->addCategoryLabel($cardId, $feedback['category'] ?? '');
        $this->addPriorityLabel($cardId, $feedback['ai_suggested_priority'] ?? 'Medium');

        // Upload screenshot (best-effort)
        if (!empty($feedback['screenshot_path']) && file_exists($feedback['screenshot_path'])) {
            $this->uploadAttachment($cardId, $feedback['screenshot_path']);
        }

        return ['id' => $cardId, 'url' => $cardUrl ?? ''];
    }

    /**
     * Move a card to a named list.
     */
    public function moveCardToList(string $cardId, string $listName): bool
    {
        $listId = $this->findOrCreateList($listName);
        if ($listId === null) {
            return false;
        }

        $response = $this->put("/cards/{$cardId}", ['idList' => $listId]);
        return $response !== null;
    }

    /**
     * Add a comment to a card.
     */
    public function addComment(string $cardId, string $comment): bool
    {
        $response = $this->post("/cards/{$cardId}/actions/comments", [
            'text' => $comment,
        ]);
        return $response !== null;
    }

    /**
     * Upload a file attachment to a card.
     */
    public function uploadAttachment(string $cardId, string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $url = self::API_BASE . "/cards/{$cardId}/attachments"
             . '?key=' . urlencode($this->apiKey)
             . '&token=' . urlencode($this->apiToken);

        $boundary = wp_generate_uuid4();
        $filename = basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileContents = file_get_contents($filePath);

        if ($fileContents === false) {
            return false;
        }

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContents . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post($url, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            wecoza_log('TrelloService: Attachment upload failed: ' . $response->get_error_message(), 'error');
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get all lists on the configured board (cached per request).
     */
    public function getBoardLists(): array
    {
        if ($this->cachedLists !== null) {
            return $this->cachedLists;
        }

        $response = $this->get("/boards/{$this->boardId}/lists");
        $this->cachedLists = is_array($response) ? $response : [];
        return $this->cachedLists;
    }

    /**
     * Find a list by name or create it on the board.
     */
    public function findOrCreateList(string $name): ?string
    {
        $lists = $this->getBoardLists();

        foreach ($lists as $list) {
            if (strcasecmp($list['name'] ?? '', $name) === 0) {
                return $list['id'];
            }
        }

        // Trello requires the full 24-char board ID for creating lists
        $fullId = $this->getFullBoardId();
        if ($fullId === null) {
            return null;
        }

        $response = $this->post('/lists', [
            'name'    => $name,
            'idBoard' => $fullId,
        ]);

        if ($response !== null && isset($response['id'])) {
            $this->cachedLists = null;
            return $response['id'];
        }

        return null;
    }

    /**
     * Resolve the configured Trello username to a member ID (cached per request).
     */
    private function resolveMemberId(): ?string
    {
        if ($this->memberResolved) {
            return $this->cachedMemberId;
        }

        $this->memberResolved = true;

        $username = trim((string) get_option('wecoza_trello_assign_member', ''));
        $username = ltrim($username, '@');

        if ($username === '') {
            return null;
        }

        $member = $this->get("/members/{$username}", ['fields' => 'id']);
        if ($member !== null && isset($member['id'])) {
            $this->cachedMemberId = $member['id'];
            return $this->cachedMemberId;
        }

        wecoza_log("TrelloService: Could not resolve member '{$username}'", 'error');
        return null;
    }

    // ── Private helpers ─────────────────────────────────────────────────

    private function addCategoryLabel(string $cardId, string $category): void
    {
        [$name, $color] = match ($category) {
            'bug_report'      => ['Bug', 'red'],
            'feature_request' => ['Feature', 'blue'],
            default           => ['Comment', 'black'],
        };

        $this->post("/cards/{$cardId}/labels", [
            'name'  => $name,
            'color' => $color,
        ]);
    }

    private function addPriorityLabel(string $cardId, string $priority): void
    {
        $color = match ($priority) {
            'Urgent' => 'red',
            'High'   => 'orange',
            'Medium' => 'yellow',
            'Low'    => 'green',
            default  => 'yellow',
        };

        $this->post("/cards/{$cardId}/labels", [
            'name'  => $priority,
            'color' => $color,
        ]);
    }

    private function buildRef(array $feedback): string
    {
        $prefix = match ($feedback['category'] ?? '') {
            'bug_report'      => 'BUG',
            'feature_request' => 'FEAT',
            default           => 'FB',
        };
        return $prefix . '-' . ($feedback['id'] ?? '0');
    }

    private function buildDescription(array $feedback): string
    {
        $parts = [];

        $parts[] = '## Feedback';
        $parts[] = '';
        $parts[] = $feedback['feedback_text'] ?? '';
        $parts[] = '';

        // AI conversation
        $conversation = $feedback['ai_conversation'] ?? '';
        if (is_string($conversation)) {
            $conversation = json_decode($conversation, true);
        }
        if (is_array($conversation) && !empty($conversation)) {
            $parts[] = '## AI Conversation';
            $parts[] = '';
            foreach ($conversation as $round) {
                if (!empty($round['question'])) {
                    $parts[] = "**Q:** {$round['question']}";
                }
                if (!empty($round['answer'])) {
                    $parts[] = "**A:** {$round['answer']}";
                }
                $parts[] = '';
            }
        }

        // Context
        $parts[] = '## Context';
        $parts[] = '';
        $parts[] = '- **Reporter:** ' . ($feedback['user_email'] ?? 'Unknown');
        if (!empty($feedback['page_title'])) {
            $parts[] = '- **Page:** ' . $feedback['page_title'];
        }
        if (!empty($feedback['page_url'])) {
            $parts[] = '- **URL:** ' . $feedback['page_url'];
        }
        if (!empty($feedback['shortcode'])) {
            $parts[] = '- **Shortcode:** `' . $feedback['shortcode'] . '`';
        }
        if (!empty($feedback['browser_info'])) {
            $parts[] = '- **Browser:** ' . $feedback['browser_info'];
        }
        if (!empty($feedback['viewport'])) {
            $parts[] = '- **Viewport:** ' . $feedback['viewport'];
        }

        return implode("\n", $parts);
    }

    /**
     * HTTP GET with auth query params.
     */
    private function get(string $endpoint, array $params = []): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = self::API_BASE . $endpoint
             . '?key=' . urlencode($this->apiKey)
             . '&token=' . urlencode($this->apiToken);

        foreach ($params as $k => $v) {
            $url .= '&' . urlencode($k) . '=' . urlencode((string) $v);
        }

        $response = wp_remote_get($url, ['timeout' => self::TIMEOUT]);

        if (is_wp_error($response)) {
            wecoza_log('TrelloService GET ' . $endpoint . ' failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wecoza_log("TrelloService GET {$endpoint} returned HTTP {$code}", 'error');
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : null;
    }

    /**
     * HTTP POST with auth query params and JSON body.
     */
    private function post(string $endpoint, array $data = []): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = self::API_BASE . $endpoint
             . '?key=' . urlencode($this->apiKey)
             . '&token=' . urlencode($this->apiToken);

        $response = wp_remote_post($url, [
            'timeout' => self::TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            wecoza_log('TrelloService POST ' . $endpoint . ' failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wecoza_log("TrelloService POST {$endpoint} returned HTTP {$code}", 'error');
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : null;
    }

    /**
     * HTTP PUT with auth query params and JSON body.
     */
    private function put(string $endpoint, array $data = []): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = self::API_BASE . $endpoint
             . '?key=' . urlencode($this->apiKey)
             . '&token=' . urlencode($this->apiToken);

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'timeout' => self::TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            wecoza_log('TrelloService PUT ' . $endpoint . ' failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wecoza_log("TrelloService PUT {$endpoint} returned HTTP {$code}", 'error');
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : null;
    }
}
