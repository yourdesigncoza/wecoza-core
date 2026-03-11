<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - SQL Generator Service
 *
 * Converts natural language questions into safe, read-only SQL queries
 * using OpenAI GPT. Reuses existing OpenAIConfig for API settings and
 * SchemaContext for database schema information.
 *
 * @package WeCoza\NLQ\Services
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Services;

use WeCoza\Events\Support\OpenAIConfig;

if (!defined('ABSPATH')) {
    exit;
}

final class SQLGeneratorService
{
    private const MODEL = 'gpt-4.1';
    private const TIMEOUT = 45;
    private const MAX_TOKENS = 2048;

    private OpenAIConfig $config;

    public function __construct()
    {
        $this->config = new OpenAIConfig();
    }

    /**
     * Generate SQL from a natural language question
     *
     * @param string      $question  The user's natural language question
     * @param string|null $module    Optional module hint (agents, learners, classes, clients)
     * @return array{success: bool, sql?: string, explanation?: string, module?: string, error?: string}
     */
    public function generate(string $question, ?string $module = null): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['success' => false, 'error' => 'Please enter a question.'];
        }

        $apiKey = $this->config->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'error' => 'OpenAI API key is not configured. Set it in WeCoza Settings.'];
        }

        // Auto-detect module from question if not provided
        if ($module === null) {
            $module = $this->detectModule($question);
        }

        // Build full schema context for the prompt
        $schemaContext = $this->buildSchemaContext($module);

        // Build messages
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($schemaContext)],
            ['role' => 'user',   'content' => $question],
        ];

        // Call OpenAI
        $response = $this->callOpenAI($apiKey, $messages);
        if ($response === null) {
            return ['success' => false, 'error' => 'Failed to get a response from the AI. Please try again.'];
        }

        // Parse the response
        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return ['success' => false, 'error' => 'The AI returned an unexpected response format. Please try rephrasing your question.'];
        }

        // Validate the generated SQL through the sandbox
        $validation = SQLSandbox::validate($parsed['sql']);
        if (!$validation['valid']) {
            wecoza_log('NLQ: AI generated unsafe SQL, blocked: ' . $parsed['sql'], 'warning');
            return ['success' => false, 'error' => 'The generated query did not pass safety validation. Please try rephrasing your question.'];
        }

        return [
            'success'             => true,
            'sql'                 => $validation['sanitized'],
            'reformulated_query'  => $parsed['reformulated_query'] ?? '',
            'explanation'         => $parsed['explanation'] ?? '',
            'module'              => $module,
        ];
    }

    /**
     * Refine an existing SQL query based on user feedback
     *
     * @param string $currentSql     The current SQL query
     * @param string $refinement     What the user wants changed
     * @param string $originalPrompt The original NL question
     * @return array{success: bool, sql?: string, explanation?: string, error?: string}
     */
    public function refine(string $currentSql, string $refinement, string $originalPrompt = ''): array
    {
        $apiKey = $this->config->getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'error' => 'OpenAI API key is not configured.'];
        }

        $module = $this->detectModule($originalPrompt . ' ' . $refinement);
        $schemaContext = $this->buildSchemaContext($module);

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($schemaContext)],
        ];

        if ($originalPrompt) {
            $messages[] = ['role' => 'user', 'content' => $originalPrompt];
            $messages[] = ['role' => 'assistant', 'content' => json_encode(['sql' => $currentSql, 'explanation' => 'Previous query'])];
        }

        $messages[] = ['role' => 'user', 'content' => "Please modify the query: {$refinement}\n\nCurrent SQL:\n{$currentSql}"];

        $response = $this->callOpenAI($apiKey, $messages);
        if ($response === null) {
            return ['success' => false, 'error' => 'Failed to get a response from the AI.'];
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return ['success' => false, 'error' => 'Unexpected response format.'];
        }

        $validation = SQLSandbox::validate($parsed['sql']);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => 'The refined query did not pass safety validation.'];
        }

        return [
            'success'             => true,
            'sql'                 => $validation['sanitized'],
            'reformulated_query'  => $parsed['reformulated_query'] ?? '',
            'explanation'         => $parsed['explanation'] ?? '',
        ];
    }

    /* ─── System Prompt ───────────────────────────────────── */

    private function buildSystemPrompt(string $schemaContext): string
    {
        $examples = $this->buildExamplesContext();

        return <<<PROMPT
You are a PostgreSQL SQL query generator for WeCoza, an internal training management system.

Your task is to:
1. Reformulate the user's natural language query to align precisely with the database schema, indicating which tables to use.
2. Generate the corresponding safe, read-only PostgreSQL SELECT query.

DATABASE SCHEMA (verified against live PostgreSQL database — these are the ONLY tables that exist):
{$schemaContext}

RULES:
1. Generate ONLY SELECT queries. Never generate INSERT, UPDATE, DELETE, DROP, ALTER, or any write operation.
2. CRITICAL: Use ONLY the exact table and column names from the schema above. Column names must match EXACTLY as listed — do NOT shorten, abbreviate, or rename them (e.g., use "class_subject" not "subject", use "class_code" not "code", use "client_name" not "name"). If a column is not listed, it does NOT exist.
3. Use proper PostgreSQL syntax (e.g., ILIKE for case-insensitive matching, ::date for date casting).
4. For "today" use CURRENT_DATE, for "now" use NOW().
5. For "active" status checks, use status = 'active' unless the column has different values.
6. Use reasonable LIMIT clauses (default 100) unless the user asks for all records.
7. Use meaningful column aliases with AS for calculated or prefixed fields (e.g., c.class_subject AS subject).
8. For JSONB columns (learner_ids, schedule_data, etc.), use appropriate JSONB operators.
9. Always qualify ambiguous column names with table aliases.
10. When using aggregate functions (COUNT, SUM, AVG, MIN, MAX), you MUST include a GROUP BY clause listing ALL non-aggregated columns in the SELECT.
11. For enum/status columns, match values exactly as documented (lowercase: 'active' not 'Active'). Use ILIKE only for free-text searches.
12. For division or ratio calculations, always wrap the divisor in NULLIF(col, 0) to prevent division-by-zero errors.
13. Double-check that every column reference in your SQL exists in the schema before responding.

EXAMPLES (follow these patterns for correct column names, JOINs, and table aliases):
{$examples}

RESPONSE FORMAT:
Return ONLY valid JSON (no markdown, no code blocks, no extra text):
{
  "reformulated_query": "Precise, schema-aligned version of the user's question, indicating which tables to use",
  "sql": "SELECT ... FROM ...",
  "explanation": "Brief explanation of what this query does"
}
PROMPT;
    }

    /* ─── Few-Shot Examples Builder ───────────────────────── */

    /**
     * Load curated NL→SQL examples from config/nlq-examples.php
     * These teach the AI exact column names, JOIN patterns, and conventions.
     */
    private function buildExamplesContext(): string
    {
        $examples = wecoza_config('nlq-examples');
        if (!$examples || !is_array($examples)) {
            return '';
        }

        $parts = [];
        foreach ($examples as $idx => $ex) {
            $num = $idx + 1;
            $parts[] = "Example {$num}:\n  Input: {$ex['input']}\n  Reformulated: {$ex['reformulated']}\n  SQL: {$ex['sql']}";
        }

        return implode("\n\n", $parts);
    }

    /* ─── Schema Context Builder ──────────────────────────── */

    /**
     * Build schema context from the hardcoded config/nlq-schema.php file.
     * This ensures the AI has rich semantic context (descriptions, enums,
     * relationships) and only references tables that actually exist.
     */
    private function buildSchemaContext(?string $module): string
    {
        $schema = wecoza_config('nlq-schema');
        if (!$schema || !is_array($schema)) {
            wecoza_log('NLQ: Failed to load config/nlq-schema.php', 'error');
            return 'Schema configuration not available.';
        }

        // Extract relationships (stored under special key)
        $relationships = $schema['_relationships'] ?? [];
        unset($schema['_relationships']);

        // Filter tables by module if specified
        $moduleTables = $this->getModuleTableNames($module);

        $parts = [];
        foreach ($schema as $tableName => $tableInfo) {
            // Skip if module filter is active and this table isn't in the module set
            if ($module && $moduleTables && !in_array($tableName, $moduleTables, true)) {
                continue;
            }

            $desc = $tableInfo['description'] ?? '';
            $pk = $tableInfo['primary_key'] ?? '';
            $columns = $tableInfo['columns'] ?? [];
            $enums = $tableInfo['enums'] ?? [];
            $notes = $tableInfo['notes'] ?? '';

            // Build column list with descriptions
            $colParts = [];
            foreach ($columns as $colName => $colDesc) {
                $enumStr = '';
                if (isset($enums[$colName])) {
                    $enumStr = ' [values: ' . implode(', ', $enums[$colName]) . ']';
                }
                $colParts[] = "    {$colName} — {$colDesc}{$enumStr}";
            }

            $tableBlock = "TABLE: {$tableName} (PK: {$pk})\n  {$desc}\n" . implode("\n", $colParts);
            if ($notes) {
                $tableBlock .= "\n  NOTE: {$notes}";
            }
            $parts[] = $tableBlock;
        }

        // Add relevant relationships
        $relParts = [];
        foreach ($relationships as $rel) {
            if (!$module || $this->relationshipRelevant($rel, $moduleTables)) {
                $relParts[] = "  {$rel}";
            }
        }

        $output = implode("\n\n", $parts);
        if ($relParts) {
            $output .= "\n\nRELATIONSHIPS (for JOINs):\n" . implode("\n", $relParts);
        }

        return $output;
    }

    /**
     * Get table names relevant to a module (including common lookup tables)
     */
    private function getModuleTableNames(?string $module): ?array
    {
        if (!$module) return null;

        // Common tables always included
        $common = ['clients', 'sites', 'locations', 'employers', 'class_types', 'class_type_subjects'];

        $moduleTables = match ($module) {
            'agents'   => ['agents', 'agent_orders', 'agent_monthly_invoices'],
            'learners' => ['learners', 'learner_lp_tracking', 'learner_hours_log', 'learner_qualifications',
                          'learner_sponsors', 'learner_portfolios', 'learner_progression_portfolios',
                          'learner_placement_level', 'employers'],
            'classes'  => ['classes', 'class_types', 'class_type_subjects', 'class_attendance_sessions',
                          'class_material_tracking', 'class_status_history', 'class_events', 'qa_visits'],
            'clients'  => ['clients', 'sites', 'locations', 'client_communications',
                          'v_client_head_sites', 'v_client_sub_sites'],
            default    => [],
        };

        return array_unique(array_merge($moduleTables, $common));
    }

    /**
     * Check if a relationship string is relevant to the given table set
     */
    private function relationshipRelevant(string $rel, ?array $tables): bool
    {
        if (!$tables) return true;
        foreach ($tables as $t) {
            if (str_contains($rel, $t . '.')) return true;
        }
        return false;
    }

    /* ─── Module Detection ────────────────────────────────── */

    /**
     * Auto-detect which module the question relates to
     */
    private function detectModule(string $question): ?string
    {
        $q = strtolower($question);

        $moduleKeywords = [
            'agents'   => ['agent', 'facilitator', 'trainer', 'instructor', 'sace', 'absence'],
            'learners' => ['learner', 'student', 'enrol', 'portfolio', 'progression', 'numeracy', 'placement', 'lp tracking'],
            'classes'  => ['class', 'schedule', 'session', 'training', 'exam', 'seta funded', 'qa visit', 'material'],
            'clients'  => ['client', 'company', 'organisation', 'organization', 'site', 'location', 'bbbee', 'communication'],
        ];

        $scores = [];
        foreach ($moduleKeywords as $mod => $keywords) {
            $scores[$mod] = 0;
            foreach ($keywords as $kw) {
                if (str_contains($q, $kw)) {
                    $scores[$mod]++;
                }
            }
        }

        arsort($scores);
        $top = key($scores);
        return $scores[$top] > 0 ? $top : null;
    }

    /* ─── OpenAI API Call ─────────────────────────────────── */

    /**
     * Call OpenAI API — reuses the same pattern as AIFeedbackService
     * but uses the shared OpenAIConfig for URL/model
     */
    private function callOpenAI(string $apiKey, array $messages): ?string
    {
        $apiUrl = $this->config->getApiUrl();

        $body = wp_json_encode([
            'model'       => self::MODEL,
            'messages'    => $messages,
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => 0.1, // Low temperature for precise SQL generation
        ]);

        $response = wp_remote_post($apiUrl, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            wecoza_log('NLQ SQLGenerator: API call failed: ' . $response->get_error_message(), 'error');
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $errBody = wp_remote_retrieve_body($response);
            wecoza_log("NLQ SQLGenerator: API returned HTTP {$statusCode}: {$errBody}", 'error');
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    /* ─── Response Parsing ────────────────────────────────── */

    /**
     * Parse the AI response JSON — handles markdown code blocks and extra text
     */
    private function parseResponse(string $response): ?array
    {
        // Try direct JSON parse
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['sql'])) {
            return $data;
        }

        // Try extracting JSON from markdown code blocks or surrounding text
        if (preg_match('/\{[^{}]*"sql"\s*:\s*"[^"]*"[^{}]*\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['sql'])) {
                return $data;
            }
        }

        // Try a more relaxed extraction for nested JSON
        if (preg_match('/\{.*?"sql".*?\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data) && isset($data['sql'])) {
                return $data;
            }
        }

        wecoza_log('NLQ SQLGenerator: Failed to parse response: ' . substr($response, 0, 500), 'warning');
        return null;
    }
}
