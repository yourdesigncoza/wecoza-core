<?php
/**
 * WeCoza Core - Application Configuration
 *
 * @package WeCoza\Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    'name' => 'WeCoza Core',
    'version' => defined('WECOZA_CORE_VERSION') ? WECOZA_CORE_VERSION : '1.0.0',
    'text_domain' => 'wecoza-core',

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'use_postgresql' => true,
        'sslmode' => 'prefer', // Use 'require' for SSL-only connections
        'defaults' => [
            'host' => '102.141.145.117',
            'port' => '5432',
            'dbname' => 'wecoza_db',
            'user' => 'John',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'default_expiration' => 3600,
        'groups' => [
            'db_queries' => 1800,
            'config' => 7200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'views' => defined('WECOZA_CORE_PATH') ? WECOZA_CORE_PATH . 'views/' : '',
        'components' => defined('WECOZA_CORE_PATH') ? WECOZA_CORE_PATH . 'views/components/' : '',
        'assets' => defined('WECOZA_CORE_URL') ? WECOZA_CORE_URL . 'assets/' : '',
    ],

    /*
    |--------------------------------------------------------------------------
    | AJAX Configuration
    |--------------------------------------------------------------------------
    */
    'ajax' => [
        'nonce_action' => 'wecoza_core_nonce',
        'default_capability' => 'manage_options',
    ],
];
