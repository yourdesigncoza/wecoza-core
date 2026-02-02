<?php
/**
 * AI Summarization Verification Tests
 *
 * Verifies all migrated AI summarization functionality works correctly
 * Run with: php wp-cli.phar eval-file wp-content/plugins/wecoza-core/tests/Events/AISummarizationTest.php --path=/opt/lampp/htdocs/wecoza
 *
 * Note: No declare(strict_types=1) to avoid conflicts with wp-cli eval-file
 */

// Bootstrap WordPress if not running via WP-CLI
if (!function_exists('shortcode_exists')) {
    require_once '/opt/lampp/htdocs/wecoza/wp-load.php';
}

use WeCoza\Events\Support\OpenAIConfig;
use WeCoza\Events\Admin\SettingsPage;
use WeCoza\Events\Services\AISummaryService;
use WeCoza\Events\Services\AISummaryDisplayService;
use WeCoza\Events\Shortcodes\AISummaryShortcode;
use WeCoza\Events\Views\Presenters\AISummaryPresenter;
use WeCoza\Events\Repositories\ClassChangeLogRepository;
use WeCoza\Events\Services\Traits\DataObfuscator;
use WeCoza\Events\Services\NotificationProcessor;
use WeCoza\Events\CLI\AISummaryStatusCommand;

/**
 * Simple test runner class to avoid global scope issues
 */
class AITestRunner
{
    private $total = 0;
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function test(string $name, bool $passed, string $message = ''): void
    {
        $this->total++;
        if ($passed) {
            $this->passed++;
            echo "✓ PASS: {$name}\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: {$name}";
            if ($message !== '') {
                echo " - {$message}";
            }
            echo "\n";
        }

        $this->tests[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message
        ];
    }

    public function getResults(): array
    {
        return [
            'total' => $this->total,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'tests' => $this->tests
        ];
    }
}

$runner = new AITestRunner();

echo "\n";
echo "====================================\n";
echo "AI SUMMARIZATION VERIFICATION TESTS\n";
echo "====================================\n";
echo "\n";

// =====================================================
// SECTION 1: AI-04 OpenAI API Key Configuration
// =====================================================

echo "SECTION 1: AI-04 OpenAI API Key Configuration\n";
echo "----------------------------------------------\n";

// Test 1.1: OpenAIConfig class exists and is instantiable
try {
    $config = new OpenAIConfig();
    $runner->test('OpenAIConfig class exists and is instantiable', true);
} catch (Throwable $e) {
    $runner->test('OpenAIConfig class exists and is instantiable', false, $e->getMessage());
    $config = null;
}

if ($config !== null) {
    // Test 1.2: getApiKey() returns null when no key configured
    delete_option(OpenAIConfig::OPTION_API_KEY);
    $apiKey = $config->getApiKey();
    $runner->test('getApiKey() returns null when no key configured', $apiKey === null);

    // Test 1.3: isValidApiKey() validates correct patterns
    // Valid key format
    $validKey = 'sk-abc123xyz456def789ghi012jkl345mn';
    $isValid = $config->isValidApiKey($validKey);
    $runner->test('isValidApiKey() accepts valid key format (sk- prefix + 20+ chars)', $isValid === true);

    // Invalid: no prefix
    $invalidNoPrefix = 'abc123';
    $isInvalid1 = $config->isValidApiKey($invalidNoPrefix);
    $runner->test('isValidApiKey() rejects key without sk- prefix', $isInvalid1 === false);

    // Invalid: too short
    $invalidShort = 'sk-short';
    $isInvalid2 = $config->isValidApiKey($invalidShort);
    $runner->test('isValidApiKey() rejects key too short', $isInvalid2 === false);

    // Invalid: empty string
    $isEmpty = $config->isValidApiKey('');
    $runner->test('isValidApiKey() rejects empty string', $isEmpty === false);

    // Test 1.4: sanitizeApiKey() strips invalid keys
    $sanitized = $config->sanitizeApiKey('invalid-key');
    $runner->test('sanitizeApiKey() returns empty string for invalid key', $sanitized === '');

    $sanitizedValid = $config->sanitizeApiKey($validKey);
    $runner->test('sanitizeApiKey() preserves valid key', $sanitizedValid === $validKey);

    // Test 1.5: maskApiKey() masks middle characters
    $masked = $config->maskApiKey($validKey);
    $expectedMasked = 'sk-a' . str_repeat('*', strlen($validKey) - 8) . '45mn';
    $runner->test('maskApiKey() shows first 4 and last 4 characters', $masked === $expectedMasked);

    // Test 1.6: maskApiKey() handles null
    $maskedNull = $config->maskApiKey(null);
    $runner->test('maskApiKey() returns null for null input', $maskedNull === null);

    // Test 1.7: maskApiKey() handles short keys
    $shortKey = 'sk-test';
    $maskedShort = $config->maskApiKey($shortKey);
    $runner->test('maskApiKey() masks short keys completely', $maskedShort === str_repeat('*', strlen($shortKey)));

    // Test 1.8: hasValidApiKey() returns boolean correctly
    $hasValid = $config->hasValidApiKey();
    $runner->test('hasValidApiKey() returns false when no key configured', $hasValid === false);

    // Set a valid key and test
    update_option(OpenAIConfig::OPTION_API_KEY, $validKey);
    $configWithKey = new OpenAIConfig();
    $hasValidNow = $configWithKey->hasValidApiKey();
    $runner->test('hasValidApiKey() returns true when valid key configured', $hasValidNow === true);

    // Clean up
    delete_option(OpenAIConfig::OPTION_API_KEY);

    // Test 1.9: isEnabled() returns boolean
    $isEnabled = $config->isEnabled();
    $runner->test('isEnabled() returns boolean', is_bool($isEnabled));

    // Test 1.10: assessEligibility() returns correct structure
    $eligibility = $config->assessEligibility(1);
    $hasEligibleKey = isset($eligibility['eligible']) && is_bool($eligibility['eligible']);
    $hasReasonKey = isset($eligibility['reason']);
    $runner->test('assessEligibility() returns array with eligible and reason keys', $hasEligibleKey && $hasReasonKey);

    // Test eligibility when no key configured
    $ineligible = $config->assessEligibility(1);
    $runner->test('assessEligibility() returns eligible=false when no API key', $ineligible['eligible'] === false && $ineligible['reason'] === 'config_missing');
}

echo "\n";

// =====================================================
// SECTION 2: AI-04 WordPress Settings Page Registration
// =====================================================

echo "SECTION 2: AI-04 WordPress Settings Page Registration\n";
echo "------------------------------------------------------\n";

// Test 2.1: SettingsPage class exists
try {
    $reflection = new ReflectionClass(SettingsPage::class);
    $runner->test('SettingsPage class exists', true);

    // Test 2.2: register() method exists
    $hasRegister = $reflection->hasMethod('register');
    $runner->test('SettingsPage::register() method exists', $hasRegister);

    // Test 2.3: registerSettings() method exists
    $hasRegisterSettings = $reflection->hasMethod('registerSettings');
    $runner->test('SettingsPage::registerSettings() method exists', $hasRegisterSettings);

} catch (ReflectionException $e) {
    $runner->test('SettingsPage class exists', false, $e->getMessage());
}

// Test 2.4: Trigger registration to check if settings are registered
// Note: Hook checking is complex in wp-cli context, so we verify by triggering registration
if (!did_action('admin_init')) {
    // Manually call registerSettings since we're in CLI context
    try {
        SettingsPage::registerSettings();
        $runner->test('SettingsPage::registerSettings() executes without errors', true);
    } catch (Throwable $e) {
        $runner->test('SettingsPage::registerSettings() executes without errors', false, $e->getMessage());
    }
}

// Test 2.5: Settings are registered with correct option names
global $wp_registered_settings;
$hasApiKeySetting = isset($wp_registered_settings[OpenAIConfig::OPTION_API_KEY]);
$runner->test('WordPress settings include wecoza_openai_api_key', $hasApiKeySetting);

// Test 2.6: Sanitize callback is registered for API key
if ($hasApiKeySetting) {
    $sanitizeCallback = $wp_registered_settings[OpenAIConfig::OPTION_API_KEY]['sanitize_callback'] ?? null;
    $hasCallback = is_array($sanitizeCallback) && $sanitizeCallback[0] === SettingsPage::class && $sanitizeCallback[1] === 'sanitizeApiKey';
    $runner->test('API key setting has sanitizeApiKey callback', $hasCallback);
}

echo "\n";

// =====================================================
// SECTION 3: AI-01 AISummaryService Infrastructure
// =====================================================

echo "SECTION 3: AI-01 AISummaryService Infrastructure\n";
echo "-------------------------------------------------\n";

// Test 3.1: AISummaryService class exists and is instantiable
try {
    $mockConfig = new OpenAIConfig();
    $service = new AISummaryService($mockConfig);
    $runner->test('AISummaryService class exists and is instantiable', true);
} catch (Throwable $e) {
    $runner->test('AISummaryService class exists and is instantiable', false, $e->getMessage());
    $service = null;
}

if ($service !== null) {
    // Test 3.2: generateSummary() method exists
    $reflection = new ReflectionClass(AISummaryService::class);
    $hasGenerateSummary = $reflection->hasMethod('generateSummary');
    $runner->test('AISummaryService::generateSummary() method exists', $hasGenerateSummary);

    // Test 3.3: getMetrics() returns expected structure
    $metrics = $service->getMetrics();
    $hasRequiredKeys = isset($metrics['attempts'], $metrics['success'], $metrics['failed'], $metrics['total_tokens'], $metrics['processing_time_ms']);
    $runner->test('getMetrics() returns array with required keys', $hasRequiredKeys);

    // Test 3.4: getMaxAttempts() returns 3 (default)
    $maxAttempts = $service->getMaxAttempts();
    $runner->test('getMaxAttempts() returns default value of 3', $maxAttempts === 3);

    // Test 3.5: Service uses gpt-5-mini model constant
    $constants = $reflection->getConstants();
    $hasModelConstant = isset($constants['MODEL']) && $constants['MODEL'] === 'gpt-5-mini';
    $runner->test('AISummaryService uses gpt-5-mini model constant', $hasModelConstant);

    // Test 3.6: Service uses DataObfuscator trait
    $traits = $reflection->getTraitNames();
    $usesDataObfuscator = in_array('WeCoza\\Events\\Services\\Traits\\DataObfuscator', $traits, true);
    $runner->test('AISummaryService uses DataObfuscator trait', $usesDataObfuscator);

    // Test 3.7: Constructor accepts OpenAIConfig
    $constructor = $reflection->getConstructor();
    $params = $constructor->getParameters();
    $firstParamType = $params[0]->getType();
    $acceptsOpenAIConfig = $firstParamType && $firstParamType->getName() === OpenAIConfig::class;
    $runner->test('AISummaryService constructor accepts OpenAIConfig', $acceptsOpenAIConfig);
}

echo "\n";

// =====================================================
// SECTION 4: AI-03 AISummaryShortcode Registration
// =====================================================

echo "SECTION 4: AI-03 AISummaryShortcode Registration\n";
echo "-------------------------------------------------\n";

// Test 4.1: Shortcode [wecoza_insert_update_ai_summary] exists
$shortcodeExists = shortcode_exists('wecoza_insert_update_ai_summary');
$runner->test('Shortcode [wecoza_insert_update_ai_summary] is registered', $shortcodeExists);

// Test 4.2: Shortcode renders without PHP errors
if ($shortcodeExists) {
    try {
        ob_start();
        $output = do_shortcode('[wecoza_insert_update_ai_summary]');
        ob_end_clean();
        $runner->test('Shortcode renders without PHP errors', true);
    } catch (Throwable $e) {
        ob_end_clean();
        $runner->test('Shortcode renders without PHP errors', false, $e->getMessage());
        $output = null;
    }

    // Test 4.3: Output contains expected wrapper class
    if ($output !== null) {
        $hasWrapper = strpos($output, 'wecoza-ai-summary-wrapper') !== false;
        $runner->test('Shortcode output contains wecoza-ai-summary-wrapper class', $hasWrapper);
    }
}

// Test 4.4: AISummaryShortcode class exists
try {
    $shortcodeReflection = new ReflectionClass(AISummaryShortcode::class);
    $runner->test('AISummaryShortcode class exists', true);

    // Test 4.5: register() method exists
    $hasRegisterMethod = $shortcodeReflection->hasMethod('register');
    $runner->test('AISummaryShortcode::register() method exists', $hasRegisterMethod);
} catch (ReflectionException $e) {
    $runner->test('AISummaryShortcode class exists', false, $e->getMessage());
}

echo "\n";

// =====================================================
// SECTION 5: AI-03 AISummaryDisplayService
// =====================================================

echo "SECTION 5: AI-03 AISummaryDisplayService\n";
echo "-----------------------------------------\n";

// Test 5.1: AISummaryDisplayService class exists and is instantiable
try {
    $displayService = new AISummaryDisplayService();
    $runner->test('AISummaryDisplayService class exists and is instantiable', true);
} catch (Throwable $e) {
    $runner->test('AISummaryDisplayService class exists and is instantiable', false, $e->getMessage());
    $displayService = null;
}

if ($displayService !== null) {
    // Test 5.2: getSummaries() method exists
    $displayReflection = new ReflectionClass(AISummaryDisplayService::class);
    $hasGetSummaries = $displayReflection->hasMethod('getSummaries');
    $runner->test('AISummaryDisplayService::getSummaries() method exists', $hasGetSummaries);

    // Test 5.3: getSummaries() returns array without errors
    try {
        $summaries = $displayService->getSummaries(10, null, null);
        $isArray = is_array($summaries);
        $runner->test('getSummaries(10, null, null) returns array', $isArray);
    } catch (Throwable $e) {
        $runner->test('getSummaries(10, null, null) returns array', false, $e->getMessage());
    }

    // Test 5.4: Filtering by class_id works (no errors)
    try {
        $summariesFiltered = $displayService->getSummaries(10, 1, null);
        $runner->test('getSummaries() accepts class_id parameter', true);
    } catch (Throwable $e) {
        $runner->test('getSummaries() accepts class_id parameter', false, $e->getMessage());
    }

    // Test 5.5: Filtering by operation works (no errors)
    try {
        $summariesByOp = $displayService->getSummaries(10, null, 'INSERT');
        $runner->test('getSummaries() accepts operation parameter', true);
    } catch (Throwable $e) {
        $runner->test('getSummaries() accepts operation parameter', false, $e->getMessage());
    }
}

echo "\n";

// =====================================================
// SECTION 6: AI-03 AISummaryPresenter
// =====================================================

echo "SECTION 6: AI-03 AISummaryPresenter\n";
echo "------------------------------------\n";

// Test 6.1: AISummaryPresenter class exists and is instantiable
try {
    $presenter = new AISummaryPresenter();
    $runner->test('AISummaryPresenter class exists and is instantiable', true);
} catch (Throwable $e) {
    $runner->test('AISummaryPresenter class exists and is instantiable', false, $e->getMessage());
    $presenter = null;
}

if ($presenter !== null) {
    // Test 6.2: present() method exists
    $presenterReflection = new ReflectionClass(AISummaryPresenter::class);
    $hasPresent = $presenterReflection->hasMethod('present');
    $runner->test('AISummaryPresenter::present() method exists', $hasPresent);

    // Test 6.3: present() accepts array
    try {
        $testData = [
            'class_id' => 1,
            'operation' => 'INSERT',
            'summary' => 'Test summary'
        ];
        $presented = $presenter->present($testData);
        $isArray = is_array($presented);
        $runner->test('AISummaryPresenter::present() returns formatted array', $isArray);
    } catch (Throwable $e) {
        $runner->test('AISummaryPresenter::present() returns formatted array', false, $e->getMessage());
    }
}

echo "\n";

// =====================================================
// SECTION 7: View Template Verification
// =====================================================

echo "SECTION 7: View Template Verification\n";
echo "--------------------------------------\n";

$viewBasePath = wecoza_plugin_path('views/events/ai-summary/');

// Test 7.1: main.php exists
$mainViewPath = $viewBasePath . 'main.php';
$mainViewExists = file_exists($mainViewPath);
$runner->test('views/events/ai-summary/main.php exists', $mainViewExists);

// Test 7.2: card.php exists
$cardViewPath = $viewBasePath . 'card.php';
$cardViewExists = file_exists($cardViewPath);
$runner->test('views/events/ai-summary/card.php exists', $cardViewExists);

// Test 7.3: timeline.php exists
$timelineViewPath = $viewBasePath . 'timeline.php';
$timelineViewExists = file_exists($timelineViewPath);
$runner->test('views/events/ai-summary/timeline.php exists', $timelineViewExists);

echo "\n";

// =====================================================
// SECTION 8: Repository and Data Layer
// =====================================================

echo "SECTION 8: Repository and Data Layer\n";
echo "-------------------------------------\n";

// Test 8.1: ClassChangeLogRepository exists
try {
    $repoReflection = new ReflectionClass(ClassChangeLogRepository::class);
    $runner->test('ClassChangeLogRepository class exists', true);

    // Test 8.2: Extends BaseRepository
    $extendsBase = $repoReflection->getParentClass()->getName() === 'WeCoza\\Core\\Abstract\\BaseRepository';
    $runner->test('ClassChangeLogRepository extends BaseRepository', $extendsBase);

    // Test 8.3: getLogsWithAISummary() method exists
    $hasGetLogsWithSummary = $repoReflection->hasMethod('getLogsWithAISummary');
    $runner->test('ClassChangeLogRepository::getLogsWithAISummary() method exists', $hasGetLogsWithSummary);

    // Test 8.4: getLogsWithAISummary() returns array
    $repo = new ClassChangeLogRepository();
    try {
        $logs = $repo->getLogsWithAISummary(5, null, null);
        $isArray = is_array($logs);
        $runner->test('getLogsWithAISummary(5, null, null) returns array', $isArray);
    } catch (Throwable $e) {
        $runner->test('getLogsWithAISummary(5, null, null) returns array', false, $e->getMessage());
    }

    // Test 8.5: Filtering by class_id parameter
    try {
        $logsByClass = $repo->getLogsWithAISummary(5, 1, null);
        $runner->test('getLogsWithAISummary() accepts class_id parameter', true);
    } catch (Throwable $e) {
        $runner->test('getLogsWithAISummary() accepts class_id parameter', false, $e->getMessage());
    }

    // Test 8.6: Filtering by operation parameter
    try {
        $logsByOp = $repo->getLogsWithAISummary(5, null, 'INSERT');
        $runner->test('getLogsWithAISummary() accepts operation parameter', true);
    } catch (Throwable $e) {
        $runner->test('getLogsWithAISummary() accepts operation parameter', false, $e->getMessage());
    }

} catch (ReflectionException $e) {
    $runner->test('ClassChangeLogRepository class exists', false, $e->getMessage());
}

echo "\n";

// =====================================================
// SECTION 9: DataObfuscator Trait
// =====================================================

echo "SECTION 9: DataObfuscator Trait\n";
echo "--------------------------------\n";

// Test 9.1: DataObfuscator trait exists
try {
    $traitPath = wecoza_plugin_path('src/Events/Services/Traits/DataObfuscator.php');
    $traitExists = file_exists($traitPath);
    $runner->test('DataObfuscator trait file exists', $traitExists);
} catch (Throwable $e) {
    $runner->test('DataObfuscator trait file exists', false, $e->getMessage());
}

// Test 9.2: AISummaryService uses DataObfuscator trait (already tested in Section 3)
$runner->test('AISummaryService uses DataObfuscator trait (verified in Section 3)', $usesDataObfuscator ?? false);

echo "\n";

// =====================================================
// SECTION 10: NotificationProcessor Integration
// =====================================================

echo "SECTION 10: NotificationProcessor Integration\n";
echo "----------------------------------------------\n";

// Test 10.1: NotificationProcessor class exists
try {
    $notifReflection = new ReflectionClass(NotificationProcessor::class);
    $runner->test('NotificationProcessor class exists', true);

    // Test 10.2: boot() static factory method exists
    $hasBoot = $notifReflection->hasMethod('boot');
    $runner->test('NotificationProcessor::boot() method exists', $hasBoot);

    // Test 10.3: process() method exists
    $hasProcess = $notifReflection->hasMethod('process');
    $runner->test('NotificationProcessor::process() method exists', $hasProcess);

} catch (ReflectionException $e) {
    $runner->test('NotificationProcessor class exists', false, $e->getMessage());
}

echo "\n";

// =====================================================
// SECTION 11: CLI Command Registration
// =====================================================

echo "SECTION 11: CLI Command Registration\n";
echo "-------------------------------------\n";

// Test 11.1: AISummaryStatusCommand class exists
try {
    $cliReflection = new ReflectionClass(AISummaryStatusCommand::class);
    $runner->test('AISummaryStatusCommand class exists', true);
} catch (ReflectionException $e) {
    $runner->test('AISummaryStatusCommand class exists', false, $e->getMessage());
}

// Test 11.2: WP-CLI command registered (if WP_CLI available)
if (defined('WP_CLI') && WP_CLI) {
    try {
        $commandExists = WP_CLI::get_runner()->find_command_to_run(['wecoza-ai-summary', 'status']);
        $runner->test('WP-CLI command wecoza-ai-summary status is registered', is_array($commandExists));
    } catch (Throwable $e) {
        $runner->test('WP-CLI command wecoza-ai-summary status is registered', false, $e->getMessage());
    }
} else {
    $runner->test('WP-CLI command check skipped (not in CLI context)', true);
}

echo "\n";

// =====================================================
// FINAL SUMMARY
// =====================================================

$results = $runner->getResults();

echo "====================================\n";
echo "TEST SUMMARY\n";
echo "====================================\n";
echo "Total: {$results['total']}\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";
$passRate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;
echo "Pass Rate: {$passRate}%\n";
echo "\n";
echo "Requirements Verified:\n";
echo "- AI-01: OpenAI GPT integration (AISummaryService)\n";
echo "- AI-03: AI summary shortcode display\n";
echo "- AI-04: API key configuration via WordPress options\n";
echo "\n";

if ($results['failed'] > 0) {
    echo "FAILED TESTS:\n";
    foreach ($results['tests'] as $test) {
        if (!$test['passed']) {
            echo "- {$test['name']}";
            if ($test['message'] !== '') {
                echo ": {$test['message']}";
            }
            echo "\n";
        }
    }
    echo "\n";
}

exit($results['failed'] > 0 ? 1 : 0);
