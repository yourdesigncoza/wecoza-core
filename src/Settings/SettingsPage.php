<?php

declare(strict_types=1);

namespace WeCoza\Settings;

final class SettingsPage
{
    private const PAGE_SLUG    = 'wecoza-settings';
    private const OPTION_GROUP = 'wecoza_settings_group';

    // PostgreSQL section
    private const PG_SECTION = 'wecoza_pg_section';
    private const PG_OPTIONS = [
        'wecoza_postgres_host',
        'wecoza_postgres_dbname',
        'wecoza_postgres_port',
        'wecoza_postgres_user',
        'wecoza_postgres_password',
    ];

    // API Keys section
    private const API_SECTION = 'wecoza_api_section';
    private const API_OPTIONS = [
        'wecoza_google_maps_api_key',
        'wecoza_openai_api_key',
        'wecoza_trello_api_key',
        'wecoza_trello_api_token',
        'wecoza_trello_board_id',
        'wecoza_trello_assign_member',
    ];

    // Notifications section
    private const NOTIFY_SECTION  = 'wecoza_notify_section';
    private const NOTIFY_OPTIONS  = [
        'wecoza_notification_class_created',
        'wecoza_notification_class_updated',
        'wecoza_notification_class_deleted',
        'wecoza_notification_material_delivery',
    ];

    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function addMenuPage(): void
    {
        add_options_page(
            esc_html__('WeCoza Settings', 'wecoza'),
            esc_html__('WeCoza Settings', 'wecoza'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        $page  = self::PAGE_SLUG;
        $group = self::OPTION_GROUP;

        // ── PostgreSQL ──────────────────────────────────────────────────
        add_settings_section(
            self::PG_SECTION,
            __('PostgreSQL Settings', 'wecoza'),
            static fn() => printf(
                '<p>%s</p>',
                esc_html__('Enter your PostgreSQL database credentials.', 'wecoza')
            ),
            $page
        );

        self::registerText($group, 'wecoza_postgres_host');
        self::registerText($group, 'wecoza_postgres_dbname');
        self::registerNumber($group, 'wecoza_postgres_port');
        self::registerText($group, 'wecoza_postgres_user');
        self::registerPasswordPreserve($group, 'wecoza_postgres_password');

        add_settings_field('wecoza_postgres_host',     __('Host', 'wecoza'),          [self::class, 'renderTextField'],     $page, self::PG_SECTION, ['name' => 'wecoza_postgres_host']);
        add_settings_field('wecoza_postgres_dbname',   __('Database Name', 'wecoza'), [self::class, 'renderTextField'],     $page, self::PG_SECTION, ['name' => 'wecoza_postgres_dbname']);
        add_settings_field('wecoza_postgres_port',     __('Port', 'wecoza'),          [self::class, 'renderNumberField'],   $page, self::PG_SECTION, ['name' => 'wecoza_postgres_port', 'placeholder' => '5432 / 25060']);
        add_settings_field('wecoza_postgres_user',     __('Username', 'wecoza'),      [self::class, 'renderTextField'],     $page, self::PG_SECTION, ['name' => 'wecoza_postgres_user']);
        add_settings_field('wecoza_postgres_password', __('Password', 'wecoza'),      [self::class, 'renderPasswordField'], $page, self::PG_SECTION, ['name' => 'wecoza_postgres_password']);

        // ── API Keys ────────────────────────────────────────────────────
        add_settings_section(
            self::API_SECTION,
            __('API Keys', 'wecoza'),
            static fn() => printf(
                '<p>%s</p>',
                esc_html__('Store API keys here. Leave password fields blank to keep the existing value.', 'wecoza')
            ),
            $page
        );

        self::registerPasswordPreserve($group, 'wecoza_google_maps_api_key');
        self::registerPasswordPreserve($group, 'wecoza_openai_api_key');
        self::registerPasswordPreserve($group, 'wecoza_trello_api_key');
        self::registerPasswordPreserve($group, 'wecoza_trello_api_token');
        self::registerText($group, 'wecoza_trello_board_id');
        self::registerText($group, 'wecoza_trello_assign_member');

        add_settings_field('wecoza_google_maps_api_key',   __('Google Maps API Key', 'wecoza'),   [self::class, 'renderPasswordField'], $page, self::API_SECTION, ['name' => 'wecoza_google_maps_api_key']);
        add_settings_field('wecoza_openai_api_key',        __('OpenAI API Key', 'wecoza'),        [self::class, 'renderPasswordField'], $page, self::API_SECTION, ['name' => 'wecoza_openai_api_key']);
        add_settings_field('wecoza_trello_api_key',        __('Trello API Key', 'wecoza'),        [self::class, 'renderPasswordField'], $page, self::API_SECTION, ['name' => 'wecoza_trello_api_key']);
        add_settings_field('wecoza_trello_api_token',      __('Trello API Token', 'wecoza'),      [self::class, 'renderPasswordField'], $page, self::API_SECTION, ['name' => 'wecoza_trello_api_token']);
        add_settings_field('wecoza_trello_board_id',       __('Trello Board ID', 'wecoza'),       [self::class, 'renderTextField'],     $page, self::API_SECTION, ['name' => 'wecoza_trello_board_id', 'placeholder' => 'Board ID from Trello URL']);
        add_settings_field('wecoza_trello_assign_member',  __('Trello Assign Member', 'wecoza'),  [self::class, 'renderTextField'],     $page, self::API_SECTION, ['name' => 'wecoza_trello_assign_member', 'placeholder' => '@username from Trello']);

        // ── Notifications ───────────────────────────────────────────────
        add_settings_section(
            self::NOTIFY_SECTION,
            __('Notifications', 'wecoza'),
            static fn() => printf(
                '<p>%s</p>',
                esc_html__('Email recipients for important events.', 'wecoza')
            ),
            $page
        );

        self::registerEmail($group, 'wecoza_notification_class_created');
        self::registerEmail($group, 'wecoza_notification_class_updated');
        self::registerEmail($group, 'wecoza_notification_class_deleted');
        self::registerEmail($group, 'wecoza_notification_material_delivery');

        add_settings_field('wecoza_notification_class_created',    __('New Class Created (notify email)', 'wecoza'),  [self::class, 'renderEmailField'], $page, self::NOTIFY_SECTION, ['name' => 'wecoza_notification_class_created',    'placeholder' => 'created@example.com']);
        add_settings_field('wecoza_notification_class_updated',    __('Class Updated (notify email)', 'wecoza'),      [self::class, 'renderEmailField'], $page, self::NOTIFY_SECTION, ['name' => 'wecoza_notification_class_updated',    'placeholder' => 'updated@example.com']);
        add_settings_field('wecoza_notification_class_deleted',     __('Class Deleted (notify email)', 'wecoza'),     [self::class, 'renderEmailField'], $page, self::NOTIFY_SECTION, ['name' => 'wecoza_notification_class_deleted',     'placeholder' => 'deleted@example.com']);
        add_settings_field('wecoza_notification_material_delivery', __('Material Delivery (notify email)', 'wecoza'), [self::class, 'renderEmailField'], $page, self::NOTIFY_SECTION, ['name' => 'wecoza_notification_material_delivery', 'placeholder' => 'materials@example.com']);
    }

    // ── Registration helpers ────────────────────────────────────────────

    private static function registerText(string $group, string $option): void
    {
        register_setting($group, $option, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }

    private static function registerNumber(string $group, string $option): void
    {
        register_setting($group, $option, [
            'type'              => 'string',
            'sanitize_callback' => static fn($v) => (string) intval($v),
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }

    private static function registerEmail(string $group, string $option): void
    {
        register_setting($group, $option, [
            'type'              => 'string',
            'sanitize_callback' => static function ($v) {
                $v = trim((string) $v);
                return ($v !== '' && is_email($v)) ? $v : '';
            },
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }

    private static function registerPasswordPreserve(string $group, string $option): void
    {
        register_setting($group, $option, [
            'type'              => 'string',
            'sanitize_callback' => static function ($v) use ($option) {
                if ($v === '' || $v === null) {
                    return get_option($option, '');
                }
                return sanitize_text_field($v);
            },
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }

    // ── Field renderers ─────────────────────────────────────────────────

    public static function renderTextField(array $args): void
    {
        $name  = $args['name'];
        $value = (string) get_option($name, '');
        $ph    = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';

        printf(
            '<input type="text" class="regular-text" name="%1$s" value="%2$s"%3$s />',
            esc_attr($name),
            esc_attr($value),
            $ph
        );
        self::renderUsageHint($name);
    }

    public static function renderNumberField(array $args): void
    {
        $name  = $args['name'];
        $value = (string) get_option($name, '');
        $ph    = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';

        printf(
            '<input type="number" class="small-text" name="%1$s" value="%2$s"%3$s />',
            esc_attr($name),
            esc_attr($value),
            $ph
        );
        self::renderUsageHint($name);
    }

    public static function renderPasswordField(array $args): void
    {
        $name  = $args['name'];
        $saved = (string) get_option($name, '') !== '';

        printf(
            '<input type="password" class="regular-text" name="%s" value="" placeholder="%s" autocomplete="new-password" />',
            esc_attr($name),
            esc_attr__('Enter new value (leave blank to keep existing)', 'wecoza')
        );

        echo $saved
            ? ' <code>&bull;&bull;&bull;&bull; ' . esc_html__('saved', 'wecoza') . '</code>'
            : ' <code>' . esc_html__('no value saved', 'wecoza') . '</code>';

        self::renderUsageHint($name);
    }

    public static function renderEmailField(array $args): void
    {
        $name  = $args['name'];
        $value = (string) get_option($name, '');
        $ph    = isset($args['placeholder']) ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';

        printf(
            '<input type="email" class="regular-text" name="%1$s" value="%2$s"%3$s />',
            esc_attr($name),
            esc_attr($value),
            $ph
        );
        self::renderUsageHint($name);
    }

    private static function renderUsageHint(string $optionName): void
    {
        printf(
            ' <span class="description" style="display:block;margin-top:4px;">%s</span>',
            sprintf(
                esc_html__('Use in code: %s', 'wecoza'),
                '<code>get_option(\'' . esc_html($optionName) . '\')</code>'
            )
        );
    }

    // ── Page renderer ───────────────────────────────────────────────────

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wecoza'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WeCoza Settings', 'wecoza'); ?></h1>
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Save Settings', 'wecoza'));
                ?>
            </form>
        </div>
        <?php
    }
}
