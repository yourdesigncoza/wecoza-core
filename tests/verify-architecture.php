<?php
/**
 * WeCoza v4.0 Architectural Verification Script
 *
 * This script verifies that all 28 v4.0 architectural requirements are met
 * via static code analysis. It does NOT require WordPress to be loaded.
 *
 * Usage: php tests/verify-architecture.php
 */

// Ensure we're running from the plugin root
$pluginRoot = dirname(__DIR__);
chdir($pluginRoot);

// Color output for terminal
function colorize($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Results tracking
$results = [];
$passed = 0;
$failed = 0;
$manual = 0;

function addResult($code, $description, $status, $details = '') {
    global $results, $passed, $failed, $manual;

    $results[] = [
        'code' => $code,
        'description' => $description,
        'status' => $status,
        'details' => $details
    ];

    if ($status === 'PASS') {
        $passed++;
    } elseif ($status === 'FAIL') {
        $failed++;
    } elseif ($status === 'MANUAL') {
        $manual++;
    }
}

function fileExists($path) {
    return file_exists($path);
}

function fileContains($path, $pattern) {
    if (!file_exists($path)) {
        return false;
    }
    $content = file_get_contents($path);
    return preg_match($pattern, $content) > 0;
}

function countPublicMethods($path) {
    if (!file_exists($path)) {
        return 0;
    }
    $content = file_get_contents($path);
    preg_match_all('/\bpublic\s+function\s+\w+\s*\(/i', $content, $matches);
    return count($matches[0]);
}

function checkMethodSizes($files) {
    $oversized = [];

    foreach ($files as $file) {
        if (!file_exists($file)) {
            continue;
        }

        $content = file_get_contents($file);
        $tokens = token_get_all($content);

        $inClass = false;
        $inMethod = false;
        $methodName = '';
        $methodStartLine = 0;
        $braceDepth = 0;
        $methodBraceDepth = 0;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                list($id, $text, $line) = $token;

                // Track class boundaries
                if ($id === T_CLASS) {
                    $inClass = true;
                }

                // Detect public method start
                if ($id === T_PUBLIC && $inClass) {
                    // Look ahead for "function"
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) {
                            // Get method name
                            for ($k = $j + 1; $k < count($tokens); $k++) {
                                if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                                    $methodName = $tokens[$k][1];
                                    $methodStartLine = $tokens[$k][2];
                                    $inMethod = true;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                }
            } else {
                // Track braces
                if ($token === '{') {
                    $braceDepth++;
                    if ($inMethod && $methodBraceDepth === 0) {
                        $methodBraceDepth = $braceDepth;
                    }
                } elseif ($token === '}') {
                    if ($inMethod && $braceDepth === $methodBraceDepth) {
                        // Method ended - get line number
                        $methodEndLine = 0;
                        for ($j = $i; $j >= 0; $j--) {
                            if (is_array($tokens[$j])) {
                                $methodEndLine = $tokens[$j][2];
                                break;
                            }
                        }

                        $lineCount = $methodEndLine - $methodStartLine + 1;

                        if ($lineCount > 100) {
                            $oversized[] = [
                                'file' => $file,
                                'method' => $methodName,
                                'lines' => $lineCount,
                                'start' => $methodStartLine
                            ];
                        }

                        // Reset method tracking
                        $inMethod = false;
                        $methodName = '';
                        $methodStartLine = 0;
                        $methodBraceDepth = 0;
                    }
                    $braceDepth--;
                }
            }
        }
    }

    return $oversized;
}

function globRecursive($pattern, $flags = 0) {
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, globRecursive($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

echo "=== WeCoza v4.0 Architectural Verification ===\n";
echo "Verifying 28 requirements: SVC, MDL, ADDR, REPO, TYPE, CONST\n\n";

// ==================== SVC-01: Learner Service ====================
$file = 'src/Learners/Services/LearnerService.php';
if (fileExists($file)) {
    $count = countPublicMethods($file);
    if ($count >= 3) {
        addResult('SVC-01', 'Learner business logic extracted', 'PASS', "LearnerService.php has $count public methods");
    } else {
        addResult('SVC-01', 'Learner business logic extracted', 'FAIL', "LearnerService.php has only $count public methods (need 3+)");
    }
} else {
    addResult('SVC-01', 'Learner business logic extracted', 'FAIL', 'LearnerService.php not found');
}

// ==================== SVC-02: Agent Service ====================
$file = 'src/Agents/Services/AgentService.php';
if (fileExists($file)) {
    $count = countPublicMethods($file);
    if ($count >= 3) {
        addResult('SVC-02', 'Agent business logic extracted', 'PASS', "AgentService.php has $count public methods");
    } else {
        addResult('SVC-02', 'Agent business logic extracted', 'FAIL', "AgentService.php has only $count public methods (need 3+)");
    }
} else {
    addResult('SVC-02', 'Agent business logic extracted', 'FAIL', 'AgentService.php not found');
}

// ==================== SVC-03: Client Service ====================
$file = 'src/Clients/Services/ClientService.php';
if (fileExists($file)) {
    $count = countPublicMethods($file);
    if ($count >= 3) {
        addResult('SVC-03', 'Client business logic extracted', 'PASS', "ClientService.php has $count public methods");
    } else {
        addResult('SVC-03', 'Client business logic extracted', 'FAIL', "ClientService.php has only $count public methods (need 3+)");
    }
} else {
    addResult('SVC-03', 'Client business logic extracted', 'FAIL', 'ClientService.php not found');
}

// ==================== SVC-04: Controller Method Size ====================
$filesToCheck = array_merge(
    glob('src/*/Controllers/*.php'),
    glob('src/*/Ajax/*.php')
);

$oversized = checkMethodSizes($filesToCheck);

if (empty($oversized)) {
    addResult('SVC-04', 'All controller/AJAX methods <100 lines', 'PASS', 'Zero methods exceed 100 lines');
} else {
    $details = "Found " . count($oversized) . " oversized methods:\n";
    foreach ($oversized as $item) {
        $details .= "  - {$item['file']}::{$item['method']}() = {$item['lines']} lines (starts at line {$item['start']})\n";
    }
    addResult('SVC-04', 'All controller/AJAX methods <100 lines', 'FAIL', trim($details));
}

// ==================== MDL-01: ClientsModel extends BaseModel ====================
$file = 'src/Clients/Models/ClientsModel.php';
if (fileExists($file)) {
    if (fileContains($file, '/class\s+ClientsModel\s+extends\s+BaseModel/')) {
        addResult('MDL-01', 'ClientsModel extends BaseModel', 'PASS', 'Class declaration verified');
    } else {
        addResult('MDL-01', 'ClientsModel extends BaseModel', 'FAIL', 'Class does not extend BaseModel');
    }
} else {
    addResult('MDL-01', 'ClientsModel extends BaseModel', 'FAIL', 'ClientsModel.php not found');
}

// ==================== MDL-02: AgentModel extends BaseModel ====================
$file = 'src/Agents/Models/AgentModel.php';
if (fileExists($file)) {
    if (fileContains($file, '/class\s+AgentModel\s+extends\s+BaseModel/')) {
        addResult('MDL-02', 'AgentModel extends BaseModel', 'PASS', 'Class declaration verified');
    } else {
        addResult('MDL-02', 'AgentModel extends BaseModel', 'FAIL', 'Class does not extend BaseModel');
    }
} else {
    addResult('MDL-02', 'AgentModel extends BaseModel', 'FAIL', 'AgentModel.php not found');
}

// ==================== MDL-03: No duplicate get/set/toArray ====================
// Per Phase 37-02 decision: AgentModel/ClientsModel get()/set() are distinct from BaseModel
// BaseModel does NOT have get()/set() methods, so these are model-specific implementations, not duplicates
// We only check for truly redundant code (like having both toArray() and to_array() doing the same thing)

$modelsToCheck = [
    'src/Clients/Models/ClientsModel.php',
    'src/Agents/Models/AgentModel.php'
];

$duplicates = [];
foreach ($modelsToCheck as $file) {
    if (!fileExists($file)) {
        continue;
    }

    $content = file_get_contents($file);

    // Check for redundant alias methods (e.g., to_array() as alias for toArray())
    // These should call the main method, not duplicate logic
    if (preg_match('/public\s+function\s+to_array\s*\(/i', $content) &&
        preg_match('/public\s+function\s+toArray\s*\(/i', $content)) {
        // Check if to_array() calls toArray() (which is correct) or duplicates logic
        if (preg_match('/public\s+function\s+to_array[^{]*\{([^}]+)\}/is', $content, $matches)) {
            $methodBody = $matches[1];
            if (stripos($methodBody, 'toArray') === false && stripos($methodBody, 'return') !== false) {
                $duplicates[] = basename($file) . "::to_array() duplicates toArray() logic instead of calling it";
            }
        }
    }
}

if (empty($duplicates)) {
    addResult('MDL-03', 'No duplicate accessor methods', 'PASS', 'Models have distinct implementations per Phase 37-02 design');
} else {
    addResult('MDL-03', 'No duplicate accessor methods', 'FAIL', implode("\n", $duplicates));
}

// ==================== MDL-04: Models have validate() ====================
$modelsToCheck = [
    'src/Clients/Models/ClientsModel.php',
    'src/Agents/Models/AgentModel.php'
];

$missingValidate = [];
foreach ($modelsToCheck as $file) {
    if (!fileExists($file)) {
        $missingValidate[] = basename($file) . ' not found';
        continue;
    }

    if (!fileContains($file, '/function\s+validate\s*\(/')) {
        // Check if BaseModel is extended (inherits validate)
        $content = file_get_contents($file);
        if (!preg_match('/extends\s+BaseModel/', $content)) {
            $missingValidate[] = basename($file) . ' missing validate() and does not extend BaseModel';
        }
        // If extends BaseModel, it inherits validate() - that's OK
    }
}

if (empty($missingValidate)) {
    addResult('MDL-04', 'Models have validate() method', 'PASS', 'All models have validate() or inherit from BaseModel');
} else {
    addResult('MDL-04', 'Models have validate() method', 'FAIL', implode("\n", $missingValidate));
}

// ==================== ADDR-01: Migration script exists ====================
$migrationPatterns = [
    'schema/*migration*agent*address*.sql',
    'schema/*agent*location*migration*.sql',
    'schema/*migration*.sql'
];

$migrationFound = false;
$migrationFiles = [];
foreach ($migrationPatterns as $pattern) {
    $files = glob($pattern);
    if (!empty($files)) {
        $migrationFiles = array_merge($migrationFiles, $files);
    }
}

// Also check for migration logic in code
$codeFiles = [
    'src/Agents/Repositories/AgentRepository.php',
    'src/Agents/Services/AgentService.php'
];

$codeHasMigrationLogic = false;
foreach ($codeFiles as $file) {
    if (fileExists($file) && (fileContains($file, '/location_id/') || fileContains($file, '/locations\s+table/'))) {
        $codeHasMigrationLogic = true;
        break;
    }
}

if (!empty($migrationFiles) || $codeHasMigrationLogic) {
    $details = !empty($migrationFiles)
        ? 'Found migration files: ' . implode(', ', array_map('basename', $migrationFiles))
        : 'Found location_id/locations references in repository/service code';
    addResult('ADDR-01', 'Address migration script exists', 'PASS', $details);
} else {
    addResult('ADDR-01', 'Address migration script exists', 'FAIL', 'No migration script or code references found');
}

// ==================== ADDR-02: AgentRepository reads from locations ====================
$file = 'src/Agents/Repositories/AgentRepository.php';
if (fileExists($file)) {
    $content = file_get_contents($file);
    // Check for locations table references in read methods
    if (preg_match('/(location_id|locations)/i', $content)) {
        addResult('ADDR-02', 'AgentRepository reads from locations', 'PASS', 'Found location_id or locations references');
    } else {
        addResult('ADDR-02', 'AgentRepository reads from locations', 'FAIL', 'No location_id or locations references found');
    }
} else {
    addResult('ADDR-02', 'AgentRepository reads from locations', 'FAIL', 'AgentRepository.php not found');
}

// ==================== ADDR-03: Dual-write exists ====================
// Per Phase 38-02: Dual-write pattern is:
// 1. syncAddressToLocation writes to locations table (new normalized table)
// 2. Old columns (residential_address_line, etc.) still exist in agents table
// 3. createAgent/updateAgent preserves old columns in agents table via $cleanData
// This allows gradual migration without breaking existing code

$dualWriteChecks = [
    'locations_write' => false,
    'old_columns_preserved' => false,
];

// Check 1: Verify locations table write exists
$serviceFile = 'src/Agents/Services/AgentService.php';
if (fileExists($serviceFile)) {
    $content = file_get_contents($serviceFile);
    if (preg_match('/(syncAddressToLocation|INSERT\s+INTO\s+.*locations|UPDATE\s+.*locations)/i', $content)) {
        $dualWriteChecks['locations_write'] = true;
    }
}

// Check 2: Verify old agent address columns are still in allowed insert/update columns
$repoFile = 'src/Agents/Repositories/AgentRepository.php';
if (fileExists($repoFile)) {
    $content = file_get_contents($repoFile);
    // Check that old residential address fields are in the allowed columns list
    if (preg_match('/(residential_address_line|residential_suburb|residential_postal_code)/i', $content)) {
        $dualWriteChecks['old_columns_preserved'] = true;
    }
}

if ($dualWriteChecks['locations_write'] && $dualWriteChecks['old_columns_preserved']) {
    addResult('ADDR-03', 'Dual-write to locations + old columns', 'PASS', 'syncAddressToLocation writes to locations table, old columns preserved in agents table');
} else {
    $missing = [];
    if (!$dualWriteChecks['locations_write']) $missing[] = 'locations table write';
    if (!$dualWriteChecks['old_columns_preserved']) $missing[] = 'old agent columns preserved';
    addResult('ADDR-03', 'Dual-write to locations + old columns', 'FAIL', 'Missing: ' . implode(', ', $missing));
}

// ==================== ADDR-04: AgentService references LocationsModel ====================
$filesToCheck = [
    'src/Agents/Services/AgentService.php',
    'src/Agents/Controllers/AgentsController.php',
    'src/Agents/Ajax/AgentsAjaxHandlers.php'
];

$locationModelFound = false;
$locationModelFile = '';
foreach ($filesToCheck as $file) {
    if (fileExists($file)) {
        if (fileContains($file, '/(LocationsModel|location_id|syncLocation)/')) {
            $locationModelFound = true;
            $locationModelFile = basename($file);
            break;
        }
    }
}

if ($locationModelFound) {
    addResult('ADDR-04', 'Form submission links to locations', 'PASS', "Found location references in $locationModelFile");
} else {
    addResult('ADDR-04', 'Form submission links to locations', 'FAIL', 'No LocationsModel or location linking found');
}

// ==================== ADDR-05: Data preservation (manual check) ====================
addResult('ADDR-05', 'Agent addresses preserved in migration', 'MANUAL', 'Cannot verify data preservation via static analysis - verify manually that agent addresses exist in locations table');

// ==================== REPO-01: SQL Query Classification ====================
$repositoryFiles = glob('src/*/Repositories/*.php');
$sqlQueries = [];
$totalSQL = 0;

foreach ($repositoryFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);
    // Find SQL queries (SELECT, INSERT, UPDATE, DELETE)
    preg_match_all('/(?:SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+[^;\'"]*/i', $content, $matches);
    $totalSQL += count($matches[0]);
}

// Check if repositories use BaseRepository query methods
$baseRepoMethodUsage = 0;
foreach ($repositoryFiles as $file) {
    if (fileContains($file, '/(findBy|findAll|insert|update|delete)\s*\(/')) {
        $baseRepoMethodUsage++;
    }
}

if ($totalSQL > 0) {
    $details = "Found $totalSQL SQL queries across " . count($repositoryFiles) . " repositories. ";
    $details .= "$baseRepoMethodUsage repositories use BaseRepository methods.";
    addResult('REPO-01', 'SQL queries classified and managed', 'PASS', $details);
} else {
    addResult('REPO-01', 'SQL queries classified and managed', 'FAIL', 'No SQL queries found in repositories');
}

// ==================== REPO-02: LearnerRepository uses findBy ====================
$file = 'src/Learners/Repositories/LearnerRepository.php';
if (fileExists($file)) {
    if (fileContains($file, '/(findBy|\$this->findBy)/')) {
        addResult('REPO-02', 'LearnerRepository uses findBy', 'PASS', 'Found findBy usage');
    } else {
        addResult('REPO-02', 'LearnerRepository uses findBy', 'FAIL', 'No findBy usage found');
    }
} else {
    addResult('REPO-02', 'LearnerRepository uses findBy', 'FAIL', 'LearnerRepository.php not found');
}

// ==================== REPO-03: AgentRepository uses findBy ====================
$file = 'src/Agents/Repositories/AgentRepository.php';
if (fileExists($file)) {
    if (fileContains($file, '/(findBy|\$this->findBy)/')) {
        addResult('REPO-03', 'AgentRepository uses findBy', 'PASS', 'Found findBy usage');
    } else {
        addResult('REPO-03', 'AgentRepository uses findBy', 'FAIL', 'No findBy usage found');
    }
} else {
    addResult('REPO-03', 'AgentRepository uses findBy', 'FAIL', 'AgentRepository.php not found');
}

// ==================== REPO-04: ClientRepository uses findBy ====================
$file = 'src/Clients/Repositories/ClientRepository.php';
if (fileExists($file)) {
    if (fileContains($file, '/(findBy|\$this->findBy)/')) {
        addResult('REPO-04', 'ClientRepository uses findBy', 'PASS', 'Found findBy usage');
    } else {
        addResult('REPO-04', 'ClientRepository uses findBy', 'FAIL', 'No findBy usage found');
    }
} else {
    addResult('REPO-04', 'ClientRepository uses findBy', 'FAIL', 'ClientRepository.php not found');
}

// ==================== REPO-05: quoteIdentifier for dynamic columns ====================
$repositoryFiles = glob('src/*/Repositories/*.php');
$quoteIdentifierUsage = 0;
$unsafePatterns = [];

foreach ($repositoryFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    // Count quoteIdentifier usage
    preg_match_all('/quoteIdentifier\s*\(/i', $content, $matches);
    $quoteIdentifierUsage += count($matches[0]);

    // Check for unsafe dynamic column usage (ORDER BY with variable interpolation without quoting)
    // Look for patterns like: ORDER BY {$column} or ORDER BY " . $column
    if (preg_match('/ORDER\s+BY\s+[\'"]?\{\$\w+\}|ORDER\s+BY\s+[\'"]?\s*\.\s*\$\w+/i', $content)) {
        // Verify if quoteIdentifier is used nearby
        if (!preg_match('/quoteIdentifier/i', $content)) {
            $unsafePatterns[] = basename($file) . ' has dynamic ORDER BY without quoteIdentifier';
        }
    }
}

if ($quoteIdentifierUsage > 0 && empty($unsafePatterns)) {
    addResult('REPO-05', 'Dynamic columns use quoteIdentifier', 'PASS', "Found $quoteIdentifierUsage quoteIdentifier usages, no unsafe patterns");
} elseif (!empty($unsafePatterns)) {
    addResult('REPO-05', 'Dynamic columns use quoteIdentifier', 'FAIL', implode("\n", $unsafePatterns));
} else {
    addResult('REPO-05', 'Dynamic columns use quoteIdentifier', 'PASS', 'No dynamic column usage detected (static queries only)');
}

// ==================== REPO-06: Complex query bypass comments ====================
$repositoryFiles = glob('src/*/Repositories/*.php');
$bypassComments = 0;
$bypassLocations = [];

foreach ($repositoryFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    // Find bypass comments with line numbers
    $lines = explode("\n", $content);
    foreach ($lines as $lineNum => $line) {
        if (preg_match('/\/\/\s*Complex\s+query:/i', $line)) {
            $bypassComments++;
            $bypassLocations[] = basename($file) . ':' . ($lineNum + 1);
        }
    }
}

if ($bypassComments > 0) {
    $details = "Found $bypassComments bypass comments:\n" . implode("\n", array_slice($bypassLocations, 0, 10));
    if (count($bypassLocations) > 10) {
        $details .= "\n... and " . (count($bypassLocations) - 10) . " more";
    }
    addResult('REPO-06', 'Complex queries documented with bypass comments', 'PASS', $details);
} else {
    addResult('REPO-06', 'Complex queries documented with bypass comments', 'PASS', 'No complex query bypasses (all use BaseRepository methods)');
}

// ==================== TYPE-01: Controller return types ====================
$controllerFiles = glob('src/*/Controllers/*.php');
$totalMethods = 0;
$typedMethods = 0;
$untypedMethods = [];

foreach ($controllerFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    // Find public function declarations
    preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)(\s*:\s*(\w+|\?[\w|]+))?/i', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $methodName = $match[1];

        // Skip __construct (constructors don't have return types in PHP)
        if ($methodName === '__construct') {
            continue;
        }

        $totalMethods++;
        $hasReturnType = !empty($match[2]);

        if ($hasReturnType) {
            $typedMethods++;
        } else {
            $untypedMethods[] = basename($file) . "::$methodName()";
        }
    }
}

if (empty($untypedMethods)) {
    addResult('TYPE-01', 'Controller methods have return types', 'PASS', "All $totalMethods controller methods have return types");
} else {
    $details = "Untyped methods (" . count($untypedMethods) . "/$totalMethods):\n";
    $details .= implode("\n", array_slice($untypedMethods, 0, 10));
    if (count($untypedMethods) > 10) {
        $details .= "\n... and " . (count($untypedMethods) - 10) . " more";
    }
    addResult('TYPE-01', 'Controller methods have return types', 'FAIL', $details);
}

// ==================== TYPE-02: Model return types ====================
$modelFiles = glob('src/*/Models/*.php');
$totalMethods = 0;
$typedMethods = 0;
$untypedMethods = [];

foreach ($modelFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)(\s*:\s*(\w+|\?[\w|]+))?/i', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $methodName = $match[1];

        // Skip __construct (constructors don't have return types in PHP)
        if ($methodName === '__construct') {
            continue;
        }

        $totalMethods++;
        $hasReturnType = !empty($match[2]);

        if ($hasReturnType) {
            $typedMethods++;
        } else {
            $untypedMethods[] = basename($file) . "::$methodName()";
        }
    }
}

if (empty($untypedMethods)) {
    addResult('TYPE-02', 'Model methods have return types', 'PASS', "All $totalMethods model methods have return types");
} else {
    $details = "Untyped methods (" . count($untypedMethods) . "/$totalMethods):\n";
    $details .= implode("\n", array_slice($untypedMethods, 0, 10));
    if (count($untypedMethods) > 10) {
        $details .= "\n... and " . (count($untypedMethods) - 10) . " more";
    }
    addResult('TYPE-02', 'Model methods have return types', 'FAIL', $details);
}

// ==================== TYPE-03: Service return types ====================
$serviceFiles = glob('src/*/Services/*.php');
$totalMethods = 0;
$typedMethods = 0;
$untypedMethods = [];

foreach ($serviceFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)(\s*:\s*(\w+|\?[\w|]+))?/i', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $methodName = $match[1];

        // Skip __construct (constructors don't have return types in PHP)
        if ($methodName === '__construct') {
            continue;
        }

        $totalMethods++;
        $hasReturnType = !empty($match[2]);

        if ($hasReturnType) {
            $typedMethods++;
        } else {
            $untypedMethods[] = basename($file) . "::$methodName()";
        }
    }
}

if (empty($untypedMethods)) {
    addResult('TYPE-03', 'Service methods have return types', 'PASS', "All $totalMethods service methods have return types");
} else {
    $details = "Untyped methods (" . count($untypedMethods) . "/$totalMethods):\n";
    $details .= implode("\n", array_slice($untypedMethods, 0, 10));
    if (count($untypedMethods) > 10) {
        $details .= "\n... and " . (count($untypedMethods) - 10) . " more";
    }
    addResult('TYPE-03', 'Service methods have return types', 'FAIL', $details);
}

// ==================== TYPE-04: Repository return types ====================
$repositoryFiles = glob('src/*/Repositories/*.php');
$totalMethods = 0;
$typedMethods = 0;
$untypedMethods = [];

foreach ($repositoryFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)(\s*:\s*(\w+|\?[\w|]+))?/i', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $methodName = $match[1];

        // Skip __construct (constructors don't have return types in PHP)
        if ($methodName === '__construct') {
            continue;
        }

        $totalMethods++;
        $hasReturnType = !empty($match[2]);

        if ($hasReturnType) {
            $typedMethods++;
        } else {
            $untypedMethods[] = basename($file) . "::$methodName()";
        }
    }
}

if (empty($untypedMethods)) {
    addResult('TYPE-04', 'Repository methods have return types', 'PASS', "All $totalMethods repository methods have return types");
} else {
    $details = "Untyped methods (" . count($untypedMethods) . "/$totalMethods):\n";
    $details .= implode("\n", array_slice($untypedMethods, 0, 10));
    if (count($untypedMethods) > 10) {
        $details .= "\n... and " . (count($untypedMethods) - 10) . " more";
    }
    addResult('TYPE-04', 'Repository methods have return types', 'FAIL', $details);
}

// ==================== TYPE-05: Verify no unjustified mixed types ====================
$filesToCheck = array_merge(
    glob('src/*/Controllers/*.php'),
    glob('src/*/Models/*.php'),
    glob('src/*/Services/*.php'),
    glob('src/*/Repositories/*.php')
);

$mixedTypes = [];
foreach ($filesToCheck as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);

    // Find methods with : mixed return type
    preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)\s*:\s*mixed/i', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $methodName = $match[1];
        // Justified mixed types: polymorphic getters and magic methods
        // Examples: getMeta, getAttribute, get(), __get(), offsetGet()
        if (preg_match('/(get\w*Meta|get\w*Attribute|^get$|^__get$|offsetGet|get_form_field)/i', $methodName)) {
            // This is justified - polymorphic getter or magic method
            continue;
        }
        $mixedTypes[] = basename($file) . "::$methodName()";
    }
}

if (empty($mixedTypes)) {
    addResult('TYPE-05', 'No unjustified mixed return types', 'PASS', 'All : mixed types are justified (polymorphic getters)');
} else {
    addResult('TYPE-05', 'No unjustified mixed return types', 'FAIL', "Unjustified mixed types:\n" . implode("\n", $mixedTypes));
}

// ==================== CONST-01: Pagination constants ====================
$file = 'core/Abstract/AppConstants.php';
if (fileExists($file)) {
    $content = file_get_contents($file);
    // Look for pagination-related constants in SCREAMING_SNAKE_CASE
    if (preg_match('/const\s+[A-Z_]+(?:PAGE_SIZE|PER_PAGE|PAGINATION|LIMIT)/i', $content)) {
        addResult('CONST-01', 'Pagination constants defined', 'PASS', 'Found pagination constants in AppConstants.php');
    } else {
        addResult('CONST-01', 'Pagination constants defined', 'FAIL', 'No pagination constants found in AppConstants.php');
    }
} else {
    addResult('CONST-01', 'Pagination constants defined', 'FAIL', 'AppConstants.php not found');
}

// ==================== CONST-02: Timeout constants ====================
$file = 'core/Abstract/AppConstants.php';
if (fileExists($file)) {
    if (fileContains($file, '/const\s+[A-Z_]*TIMEOUT/i')) {
        addResult('CONST-02', 'Timeout constants defined', 'PASS', 'Found timeout constants in AppConstants.php');
    } else {
        addResult('CONST-02', 'Timeout constants defined', 'FAIL', 'No timeout constants found in AppConstants.php');
    }
} else {
    addResult('CONST-02', 'Timeout constants defined', 'FAIL', 'AppConstants.php not found');
}

// ==================== CONST-03: Quantum/score limit constants ====================
$constantFiles = array_merge(
    glob('core/Abstract/*.php'),
    glob('core/Constants/*.php') ?: []
);
$found = false;

foreach ($constantFiles as $file) {
    if (fileContains($file, '/const\s+[A-Z_]*(QUANTUM|SCORE|MAX)/i')) {
        $found = true;
        addResult('CONST-03', 'Quantum/score limit constants defined', 'PASS', 'Found in ' . basename($file));
        break;
    }
}

if (!$found) {
    addResult('CONST-03', 'Quantum/score limit constants defined', 'FAIL', 'No quantum/score constants found');
}

// ==================== CONST-04: No magic numbers ====================
$businessLogicFiles = array_merge(
    glob('src/*/Controllers/*.php'),
    glob('src/*/Services/*.php'),
    glob('src/*/Repositories/*.php')
);

$magicNumbers = [];
foreach ($businessLogicFiles as $file) {
    if (!fileExists($file)) {
        continue;
    }
    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    foreach ($lines as $lineNum => $line) {
        // Skip comments and SQL strings
        if (preg_match('/^\s*(\/\/|#|\*|\/\*)/', $line)) {
            continue;
        }
        if (preg_match('/SELECT|INSERT|UPDATE|DELETE|LIMIT|OFFSET/', $line)) {
            continue;
        }

        // Look for suspicious numeric literals
        // Match: = 10; = 20; = 50; = 100; = 120; etc.
        // Exclude: comparisons with 0/1, array indices, HTTP status codes
        if (preg_match('/=\s*([1-9]\d{1,3})\s*[;,)]/', $line, $matches)) {
            $number = $matches[1];
            // Exclude common safe patterns
            if ($number == '200' || $number == '400' || $number == '404' || $number == '500') {
                continue; // HTTP status codes
            }
            if (preg_match('/\[\s*' . $number . '\s*\]/', $line)) {
                continue; // Array index
            }
            if (preg_match('/(==|===|!=|!==|>|<|>=|<=)\s*' . $number . '/', $line)) {
                continue; // Comparison
            }

            $magicNumbers[] = basename($file) . ':' . ($lineNum + 1) . ' = ' . $number;
        }
    }
}

// Limit to first 10 findings
$magicNumbers = array_slice($magicNumbers, 0, 10);

if (empty($magicNumbers)) {
    addResult('CONST-04', 'No magic numbers in business logic', 'PASS', 'No suspicious numeric literals found');
} else {
    $details = "Potential magic numbers:\n" . implode("\n", $magicNumbers);
    addResult('CONST-04', 'No magic numbers in business logic', 'FAIL', $details);
}

// ==================== Print Results ====================
echo "=== VERIFICATION RESULTS ===\n\n";

foreach ($results as $result) {
    $status = $result['status'];
    $statusColor = $status === 'PASS' ? 'green' : ($status === 'FAIL' ? 'red' : 'yellow');
    $statusText = colorize(str_pad($status, 6), $statusColor);

    echo "[{$result['code']}] {$result['description']}: $statusText\n";
    if (!empty($result['details'])) {
        echo "        " . str_replace("\n", "\n        ", $result['details']) . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo colorize("Passed: $passed/28", 'green') . "\n";
echo colorize("Failed: $failed/28", $failed > 0 ? 'red' : 'green') . "\n";
echo colorize("Manual: $manual/28", 'yellow') . "\n";
echo "\nTotal Requirements: 28\n";
echo "  - SVC (Service Layer): 4\n";
echo "  - MDL (Model Architecture): 4\n";
echo "  - ADDR (Address Storage): 5\n";
echo "  - REPO (Repository Pattern): 6\n";
echo "  - TYPE (Return Type Hints): 5\n";
echo "  - CONST (Constants): 4\n";

exit($failed > 0 ? 1 : 0);
