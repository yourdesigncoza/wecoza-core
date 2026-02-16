#!/usr/bin/env php
<?php
/**
 * Migration: 38-01 Agent Address to Locations
 *
 * Copies agent addresses from denormalized columns to the shared public.locations table
 * and sets the agents.location_id foreign key.
 *
 * Usage:
 *   php 38-01-agent-address-to-locations.php [--dry-run]
 *
 * Requirements:
 *   - 38-01-agent-address-to-locations.sql must be run first (adds location_id column)
 *   - WordPress environment must be available
 *
 * @package WeCoza\Core
 * @since 4.0.0
 */

declare(strict_types=1);

// Parse CLI arguments
$dryRun = in_array('--dry-run', $argv, true);

// Bootstrap WordPress
$wpLoadPath = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    echo "ERROR: WordPress wp-load.php not found at: {$wpLoadPath}\n";
    exit(1);
}
require_once $wpLoadPath;

// Check if wecoza_db() function exists
if (!function_exists('wecoza_db')) {
    echo "ERROR: wecoza_db() function not found. Is WeCoza Core plugin active?\n";
    exit(1);
}

// Get database connection
try {
    $db = wecoza_db();
} catch (Exception $e) {
    echo "ERROR: Failed to connect to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if location_id column exists
$columnCheckSql = "SELECT column_name FROM information_schema.columns
                   WHERE table_schema = 'public'
                   AND table_name = 'agents'
                   AND column_name = 'location_id'";
$columnExists = $db->getRow($columnCheckSql);

if (!$columnExists) {
    echo "ERROR: location_id column does not exist in agents table.\n";
    echo "Please run 38-01-agent-address-to-locations.sql first.\n";
    exit(1);
}

echo "===============================================\n";
echo "Agent Address to Locations Migration\n";
echo "===============================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "LIVE MIGRATION") . "\n";
echo "-----------------------------------------------\n\n";

// Count agents with non-empty addresses BEFORE migration
$countSql = "SELECT COUNT(*) as total
             FROM agents
             WHERE residential_address_line IS NOT NULL
             AND residential_address_line != ''";
$countResult = $db->getRow($countSql);
$totalAgentsWithAddresses = (int) ($countResult['total'] ?? 0);

echo "Agents with addresses: {$totalAgentsWithAddresses}\n\n";

if ($totalAgentsWithAddresses === 0) {
    echo "No agents with addresses found. Nothing to migrate.\n";
    exit(0);
}

// Fetch all agents with addresses that don't already have location_id set (idempotency)
$agentsSql = "SELECT agent_id, residential_address_line, address_line_2,
                     residential_suburb, city, province, residential_postal_code
              FROM agents
              WHERE residential_address_line IS NOT NULL
              AND residential_address_line != ''
              AND location_id IS NULL
              ORDER BY agent_id";
$agents = $db->getAll($agentsSql);

$agentsToProcess = count($agents);
echo "Agents to process (without location_id): {$agentsToProcess}\n\n";

if ($agentsToProcess === 0) {
    echo "All agents already have location_id set. Migration already complete.\n";
    exit(0);
}

// Start transaction (only in non-dry-run mode)
if (!$dryRun) {
    $db->query('BEGIN');
}

$locationsCreated = 0;
$locationsReused = 0;
$agentsUpdated = 0;
$errors = [];

foreach ($agents as $agent) {
    $agentId = (int) $agent['agent_id'];

    // Build street_address (combine residential_address_line and address_line_2)
    $streetAddress = trim($agent['residential_address_line']);
    if (!empty($agent['address_line_2'])) {
        $streetAddress .= ', ' . trim($agent['address_line_2']);
    }

    $suburb = trim($agent['residential_suburb'] ?? '');
    $town = trim($agent['city'] ?? '');
    $province = trim($agent['province'] ?? '');
    $postalCode = trim($agent['residential_postal_code'] ?? '');

    // Normalize empty strings to NULL
    $suburb = $suburb !== '' ? $suburb : null;
    $town = $town !== '' ? $town : null;
    $province = $province !== '' ? $province : null;
    $postalCode = $postalCode !== '' ? $postalCode : null;

    if ($dryRun) {
        echo "[DRY RUN] Agent {$agentId}:\n";
        echo "  Street: {$streetAddress}\n";
        echo "  Suburb: " . ($suburb ?? 'NULL') . "\n";
        echo "  Town: " . ($town ?? 'NULL') . "\n";
        echo "  Province: " . ($province ?? 'NULL') . "\n";
        echo "  Postal: " . ($postalCode ?? 'NULL') . "\n";
    }

    // Check if matching location already exists (case-insensitive match)
    $locationCheckSql = "SELECT location_id FROM public.locations
                         WHERE LOWER(street_address) = LOWER(:street_address)";
    $locationCheckParams = [':street_address' => $streetAddress];

    // Add additional filters if values are not NULL
    if ($suburb !== null) {
        $locationCheckSql .= " AND LOWER(suburb) = LOWER(:suburb)";
        $locationCheckParams[':suburb'] = $suburb;
    } else {
        $locationCheckSql .= " AND suburb IS NULL";
    }

    if ($town !== null) {
        $locationCheckSql .= " AND LOWER(town) = LOWER(:town)";
        $locationCheckParams[':town'] = $town;
    } else {
        $locationCheckSql .= " AND town IS NULL";
    }

    if ($province !== null) {
        $locationCheckSql .= " AND LOWER(province) = LOWER(:province)";
        $locationCheckParams[':province'] = $province;
    } else {
        $locationCheckSql .= " AND province IS NULL";
    }

    if ($postalCode !== null) {
        $locationCheckSql .= " AND postal_code = :postal_code";
        $locationCheckParams[':postal_code'] = $postalCode;
    } else {
        $locationCheckSql .= " AND postal_code IS NULL";
    }

    $locationCheckSql .= " LIMIT 1";

    $existingLocation = $db->getRow($locationCheckSql, $locationCheckParams);

    if ($existingLocation) {
        // Reuse existing location
        $locationId = (int) $existingLocation['location_id'];
        $locationsReused++;

        if ($dryRun) {
            echo "  -> Would reuse existing location_id: {$locationId}\n\n";
        } else {
            // Update agent with location_id
            $updateSql = "UPDATE agents SET location_id = :location_id WHERE agent_id = :agent_id";
            $updateParams = [
                ':location_id' => $locationId,
                ':agent_id' => $agentId
            ];
            $db->query($updateSql, $updateParams);
            $agentsUpdated++;
        }
    } else {
        // Create new location
        if ($dryRun) {
            echo "  -> Would create new location\n\n";
            $locationsCreated++;
        } else {
            try {
                $insertSql = "INSERT INTO public.locations
                             (street_address, suburb, town, province, postal_code,
                              longitude, latitude, created_at, updated_at)
                             VALUES
                             (:street_address, :suburb, :town, :province, :postal_code,
                              NULL, NULL, :created_at, :updated_at)
                             RETURNING location_id";

                $insertParams = [
                    ':street_address' => $streetAddress,
                    ':suburb' => $suburb,
                    ':town' => $town,
                    ':province' => $province,
                    ':postal_code' => $postalCode,
                    ':created_at' => current_time('mysql'),
                    ':updated_at' => current_time('mysql')
                ];

                $newLocation = $db->getRow($insertSql, $insertParams);
                $locationId = (int) $newLocation['location_id'];
                $locationsCreated++;

                // Update agent with new location_id
                $updateSql = "UPDATE agents SET location_id = :location_id WHERE agent_id = :agent_id";
                $updateParams = [
                    ':location_id' => $locationId,
                    ':agent_id' => $agentId
                ];
                $db->query($updateSql, $updateParams);
                $agentsUpdated++;
            } catch (Exception $e) {
                $errors[] = "Agent {$agentId}: " . $e->getMessage();
            }
        }
    }
}

// Commit transaction (only in non-dry-run mode)
if (!$dryRun) {
    if (empty($errors)) {
        $db->query('COMMIT');
    } else {
        $db->query('ROLLBACK');
        echo "\nERROR: Migration failed with " . count($errors) . " error(s):\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }
}

// Summary
echo "-----------------------------------------------\n";
echo "Migration Summary:\n";
echo "-----------------------------------------------\n";
echo "Agents to process: {$agentsToProcess}\n";
echo "Locations created: {$locationsCreated}\n";
echo "Locations reused: {$locationsReused}\n";

if (!$dryRun) {
    echo "Agents updated: {$agentsUpdated}\n\n";

    // Verification: Count agents with location_id set
    $verificationSql = "SELECT COUNT(*) as count
                        FROM agents
                        WHERE residential_address_line IS NOT NULL
                        AND residential_address_line != ''
                        AND location_id IS NOT NULL";
    $verificationResult = $db->getRow($verificationSql);
    $agentsWithLocationId = (int) ($verificationResult['count'] ?? 0);

    echo "-----------------------------------------------\n";
    echo "Verification:\n";
    echo "-----------------------------------------------\n";
    echo "Agents with addresses (before): {$totalAgentsWithAddresses}\n";
    echo "Agents with location_id (after): {$agentsWithLocationId}\n";

    if ($agentsWithLocationId === $totalAgentsWithAddresses) {
        echo "\n✓ SUCCESS: All agents with addresses now have location_id set.\n";
    } else {
        echo "\n✗ WARNING: Mismatch detected. Some agents may not have been migrated.\n";
        exit(1);
    }
} else {
    echo "\n(Dry run - no changes made)\n";
}

echo "===============================================\n";
exit(0);
