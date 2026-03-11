<?php
declare(strict_types=1);

/**
 * WeCoza NLQ - SQL Sandbox
 *
 * Validates and sanitizes SQL queries to ensure they are safe for execution.
 * Enforces READ-ONLY access — only SELECT and WITH statements are permitted.
 * Blocks all DDL, DML write operations, and dangerous patterns.
 *
 * @package WeCoza\NLQ\Services
 * @since 1.0.0
 */

namespace WeCoza\NLQ\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SQLSandbox
{
    /**
     * Statements that are allowed to execute
     */
    private const ALLOWED_STATEMENTS = ['SELECT', 'WITH'];

    /**
     * Keywords that indicate dangerous/write operations
     */
    private const BLOCKED_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE',
        'CREATE', 'REPLACE', 'GRANT', 'REVOKE', 'EXECUTE',
        'COPY', 'VACUUM', 'REINDEX', 'CLUSTER', 'COMMENT',
        'LOCK', 'NOTIFY', 'LISTEN', 'UNLISTEN',
    ];

    /**
     * Dangerous patterns (regex) beyond keyword matching
     */
    private const BLOCKED_PATTERNS = [
        '/;\s*(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE)/i',  // Multi-statement injection
        '/INTO\s+OUTFILE/i',                                         // File write
        '/INTO\s+DUMPFILE/i',                                        // File write
        '/LOAD_FILE\s*\(/i',                                         // File read
        '/pg_read_file\s*\(/i',                                      // PostgreSQL file read
        '/pg_write_file\s*\(/i',                                     // PostgreSQL file write
        '/lo_import\s*\(/i',                                         // Large object import
        '/lo_export\s*\(/i',                                         // Large object export
        '/pg_sleep\s*\(/i',                                          // DoS via sleep
        '/dblink\s*\(/i',                                            // External DB access
        '/;\s*$/',                                                    // Trailing semicolons (multi-statement)
        '/--.*$/m',                                                   // SQL comments (potential injection)
        '/\/\*.*?\*\//s',                                             // Block comments
    ];

    /**
     * Maximum query length to prevent abuse
     */
    private const MAX_QUERY_LENGTH = 10000;

    /**
     * Validate a SQL query for safe execution
     *
     * @param string $sql The SQL query to validate
     * @return array{valid: bool, errors: string[], sanitized: string}
     */
    public static function validate(string $sql): array
    {
        $errors = [];
        $sanitized = trim($sql);

        // Check empty
        if ($sanitized === '') {
            return ['valid' => false, 'errors' => ['Query cannot be empty.'], 'sanitized' => ''];
        }

        // Check length
        if (strlen($sanitized) > self::MAX_QUERY_LENGTH) {
            $errors[] = 'Query exceeds maximum allowed length of ' . self::MAX_QUERY_LENGTH . ' characters.';
        }

        // Remove trailing semicolons (we add our own execution boundary)
        $sanitized = rtrim($sanitized, "; \t\n\r");

        // Check for multiple statements (basic ; check after stripping trailing)
        if (str_contains($sanitized, ';')) {
            $errors[] = 'Multiple SQL statements are not allowed. Only single queries are permitted.';
        }

        // Determine the statement type
        $firstWord = strtoupper(strtok($sanitized, " \t\n\r("));
        if (!in_array($firstWord, self::ALLOWED_STATEMENTS, true)) {
            $errors[] = "Only SELECT queries are allowed. Found: {$firstWord}";
        }

        // Check for blocked keywords in the full query
        $upperSql = strtoupper($sanitized);
        foreach (self::BLOCKED_KEYWORDS as $keyword) {
            // Use word boundary check to avoid false positives (e.g., "UPDATED_AT" matching "UPDATE")
            if (preg_match('/\b' . $keyword . '\b/i', $sanitized)) {
                // Allow keywords that appear in column names or string literals
                if (self::isKeywordInDangerousContext($sanitized, $keyword)) {
                    $errors[] = "Blocked keyword detected: {$keyword}. Only read-only queries are allowed.";
                    break;
                }
            }
        }

        // Check for dangerous patterns
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                $errors[] = 'Query contains a blocked pattern that is not allowed for security reasons.';
                break;
            }
        }

        return [
            'valid'     => empty($errors),
            'errors'    => $errors,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * Quick boolean check — is this query safe to run?
     */
    public static function isSafe(string $sql): bool
    {
        return self::validate($sql)['valid'];
    }

    /**
     * Determine if a blocked keyword appears in a dangerous context
     * (vs. inside a column name like "updated_at" or a string literal)
     */
    private static function isKeywordInDangerousContext(string $sql, string $keyword): bool
    {
        // Pattern: keyword appears as a standalone statement word (not part of identifier)
        // e.g., "UPDATE table" is dangerous, but "updated_at" or "'UPDATE'" is fine
        $pattern = '/(?<![a-zA-Z0-9_])\b' . $keyword . '\b(?![a-zA-Z0-9_])/i';

        // Find all matches and check each one
        if (preg_match_all($pattern, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $offset = $match[1];

                // Check if this occurrence is inside a string literal
                if (!self::isInsideStringLiteral($sql, $offset)) {
                    return true; // It's in a dangerous context
                }
            }
        }

        return false;
    }

    /**
     * Check if a position in the SQL string is inside a quoted string literal
     */
    private static function isInsideStringLiteral(string $sql, int $position): bool
    {
        $inSingleQuote = false;

        for ($i = 0; $i < $position && $i < strlen($sql); $i++) {
            if ($sql[$i] === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inSingleQuote = !$inSingleQuote;
            }
        }

        return $inSingleQuote;
    }
}
