<?php
declare(strict_types=1);

namespace WeCoza\Events\Services\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use function preg_match;
use function preg_replace;
use function strlen;
use function str_repeat;
use function substr;
use function trim;

/**
 * Heuristic PII pattern detection for field values.
 *
 * Detects PII patterns in values regardless of field name:
 * - South African ID numbers (13 digits)
 * - Passport numbers (6-12 alphanumeric)
 * - Phone numbers (7-15 digits)
 */
trait PIIDetector
{
    /**
     * Detect if a value looks like a South African ID number.
     * SA ID format: 13 digits (YYMMDD + 4 digits + citizenship + gender digit + checksum)
     */
    private function looksLikeSouthAfricanID(string $value): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $value);
        return $cleaned !== null && strlen($cleaned) === 13;
    }

    /**
     * Detect if a value looks like a passport number.
     * International passports: 6-12 alphanumeric characters.
     */
    private function looksLikePassport(string $value): bool
    {
        // Only match if primarily alphanumeric and right length
        // Avoid matching common IDs, codes, etc.
        $value = trim($value);
        if (strlen($value) < 6 || strlen($value) > 12) {
            return false;
        }

        // Must be alphanumeric only
        return preg_match('/^[A-Z0-9]{6,12}$/i', $value) === 1;
    }

    /**
     * Detect if a value looks like a phone number.
     * Phone numbers: 7-15 digits (handles various formats with dashes, spaces, etc.)
     */
    private function looksLikePhoneNumber(string $value): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === null) {
            return false;
        }

        $length = strlen($digits);
        return $length >= 7 && $length <= 15;
    }

    /**
     * Detect PII pattern in a value, returning the pattern type or null.
     *
     * Order matters: more specific patterns first (SA ID before phone).
     *
     * @return string|null Pattern type: 'sa_id', 'passport', 'phone', or null
     */
    private function detectPIIPattern(string $value): ?string
    {
        // Skip very short values (likely codes, not PII)
        if (strlen(trim($value)) < 6) {
            return null;
        }

        // SA ID is most specific (exactly 13 digits)
        if ($this->looksLikeSouthAfricanID($value)) {
            return 'sa_id';
        }

        // Passport: alphanumeric 6-12 chars (check before phone to avoid overlap)
        // Only flag as passport if field context suggests it
        // (handled by caller with field name hints)

        // Phone: 7-15 digits (most common pattern)
        if ($this->looksLikePhoneNumber($value)) {
            return 'phone';
        }

        return null;
    }

    /**
     * Mask a South African ID number.
     * Shows last 2 digits for partial verification.
     */
    private function maskSouthAfricanID(string $value): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $value) ?? '';
        if (strlen($cleaned) !== 13) {
            return 'ID-XXXXXXXXXXXXX';
        }

        // Show last 2 digits for verification
        return 'ID-XXXXXXXXXXX' . substr($cleaned, -2);
    }

    /**
     * Mask a passport number.
     */
    private function maskPassport(string $value): string
    {
        $value = trim($value);
        if (strlen($value) < 6) {
            return 'PASSPORT-XXXX';
        }

        // Show last 2 characters for verification
        return 'PASSPORT-' . str_repeat('X', max(strlen($value) - 2, 2)) . substr($value, -2);
    }
}
