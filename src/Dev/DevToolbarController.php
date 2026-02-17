<?php

declare(strict_types=1);

namespace WeCoza\Dev;

/**
 * Dev Toolbar Controller
 *
 * Enqueues the dev toolbar scripts only when WP_DEBUG is enabled.
 * Provides a floating toolbar for auto-filling forms with test data.
 */
class DevToolbarController
{
    public function register(): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts'], 999);

        $wipeHandler = new WipeDataHandler();
        $wipeHandler->register();
    }

    public function enqueueScripts(): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        $baseUrl = wecoza_js_url('dev/');
        $basePath = wecoza_plugin_path('assets/js/dev/');
        $version = filemtime($basePath . 'dev-toolbar.js') ?: '1.0.0';

        // Data pools (no dependencies)
        wp_enqueue_script(
            'wecoza-dev-data-pools',
            $baseUrl . 'form-fillers/data-pools.js',
            [],
            $version,
            true
        );

        // Generators (depends on data pools)
        wp_enqueue_script(
            'wecoza-dev-generators',
            $baseUrl . 'form-fillers/generators.js',
            ['wecoza-dev-data-pools'],
            $version,
            true
        );

        // Form fillers (depend on generators)
        $fillers = [
            'location-filler',
            'client-filler',
            'learner-filler',
            'agent-filler',
            'class-filler',
        ];

        foreach ($fillers as $filler) {
            $fillerPath = $basePath . 'form-fillers/' . $filler . '.js';
            if (file_exists($fillerPath)) {
                wp_enqueue_script(
                    'wecoza-dev-' . $filler,
                    $baseUrl . 'form-fillers/' . $filler . '.js',
                    ['wecoza-dev-generators'],
                    filemtime($fillerPath) ?: $version,
                    true
                );
            }
        }

        // Main toolbar (depends on all fillers)
        wp_enqueue_script(
            'wecoza-dev-toolbar',
            $baseUrl . 'dev-toolbar.js',
            ['jquery', 'wecoza-dev-generators'],
            $version,
            true
        );

        wp_localize_script('wecoza-dev-toolbar', 'wecoza_dev_toolbar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wecoza_dev_wipe_data'),
        ]);
    }
}
