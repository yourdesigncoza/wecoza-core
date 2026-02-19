<?php
declare(strict_types=1);

/**
 * Lookup Table Controller
 *
 * Registers shortcodes, enqueues assets, and holds the TABLES configuration
 * constant for the generic Lookup Table Manager module.
 *
 * Supports the following shortcodes:
 *   [wecoza_manage_qualifications]      — Phase 42
 *   [wecoza_manage_placement_levels]    — Phase 43
 *   [wecoza_manage_employers]           — Phase 44
 *
 * @package WeCoza\LookupTables\Controllers
 * @since 4.1.0
 */

namespace WeCoza\LookupTables\Controllers;

use WeCoza\Core\Abstract\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

class LookupTableController extends BaseController
{
    /**
     * Configuration for all supported lookup tables.
     *
     * Keys:
     *   - table      : Physical PostgreSQL table name
     *   - pk         : Primary key column name
     *   - columns    : Whitelisted columns allowed for INSERT/UPDATE
     *   - labels     : Human-readable column header labels (1:1 with columns)
     *   - title      : Page/card title
     *   - capability : WordPress capability required for write operations
     */
    private const TABLES = [
        'qualifications' => [
            'table'      => 'learner_qualifications',
            'pk'         => 'id',
            'columns'    => ['qualification'],
            'labels'     => ['Qualification'],
            'title'      => 'Manage Qualifications',
            'capability' => 'manage_options',
        ],
        'placement_levels' => [
            'table'      => 'learner_placement_level',
            'pk'         => 'placement_level_id',
            'columns'    => ['level', 'level_desc'],
            'labels'     => ['Level Code', 'Description'],
            'title'      => 'Manage Placement Levels',
            'capability' => 'manage_options',
        ],
        'employers' => [
            'table'      => 'employers',
            'pk'         => 'employer_id',
            'columns'    => ['employer_name'],
            'labels'     => ['Employer Name'],
            'title'      => 'Manage Employers',
            'capability' => 'manage_options',
        ],
        'class_subjects' => [
            'table'      => 'class_type_subjects',
            'pk'         => 'class_type_subject_id',
            'columns'    => ['class_type_id', 'subject_code', 'subject_name', 'subject_duration', 'display_order', 'is_active'],
            'labels'     => ['Class Type', 'Code', 'Subject Name', 'Duration (hrs)', 'Order', 'Active'],
            'column_types' => [
                'class_type_id'    => 'select',
                'subject_duration' => 'number',
                'display_order'    => 'number',
                'is_active'        => 'boolean',
            ],
            'column_options' => [
                'class_type_id' => [
                    'table' => 'class_types',
                    'pk'    => 'class_type_id',
                    'label' => 'class_type_name',
                    'order' => 'display_order ASC',
                ],
            ],
            'order_by'   => '(SELECT class_type_name FROM class_types WHERE class_types.class_type_id = class_type_subjects.class_type_id) ASC, subject_name ASC',
            'title'      => 'Manage Class Subjects',
            'capability' => 'manage_options',
        ],
        'class_types' => [
            'table'      => 'class_types',
            'pk'         => 'class_type_id',
            'columns'    => ['class_type_code', 'class_type_name', 'subject_selection_mode', 'progression_total_hours', 'display_order', 'is_active'],
            'labels'     => ['Code', 'Name', 'Subject Mode', 'Progression Hrs', 'Order', 'Active'],
            'column_types' => [
                'subject_selection_mode'  => 'select',
                'progression_total_hours' => 'number',
                'display_order'           => 'number',
                'is_active'               => 'boolean',
            ],
            'column_options' => [
                'subject_selection_mode' => [
                    'options' => [
                        ['value' => 'own',          'label' => 'Own Subjects'],
                        ['value' => 'all_subjects', 'label' => 'All Subjects'],
                        ['value' => 'progression',  'label' => 'Progression'],
                    ],
                ],
            ],
            'order_by'   => 'class_type_code ASC',
            'title'      => 'Manage Class Types',
            'capability' => 'manage_options',
        ],
    ];

    /**
     * Map shortcode tag => table_key for renderManageTable dispatch
     */
    private const SHORTCODE_MAP = [
        'wecoza_manage_qualifications'   => 'qualifications',
        'wecoza_manage_placement_levels' => 'placement_levels',
        'wecoza_manage_employers'        => 'employers',
        'wecoza_manage_class_subjects'   => 'class_subjects',
        'wecoza_manage_class_types'      => 'class_types',
    ];

    /*
    |--------------------------------------------------------------------------
    | Static Config Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Return the config array for a given table key, or null if not found
     *
     * Used by LookupTableAjaxHandler to resolve table config without
     * duplicating the TABLES constant in the AJAX handler.
     *
     * @param string $key Table key (e.g. 'qualifications', 'placement_levels')
     * @return array|null
     */
    public static function getTableConfig(string $key): ?array
    {
        return self::TABLES[$key] ?? null;
    }

    /**
     * Fetch select options — supports both FK table lookups and static option lists.
     *
     * FK table format:   ['table'=>..., 'pk'=>..., 'label'=>..., 'order'=>...]
     * Static format:     ['options'=> [['value'=>..., 'label'=>...], ...] ]
     *
     * @param array $optionConfig
     * @return array Array of ['value'=>..., 'label'=>...]
     */
    public static function getSelectOptions(array $optionConfig): array
    {
        // Static options — return directly
        if (isset($optionConfig['options'])) {
            return $optionConfig['options'];
        }

        // FK table lookup
        $db    = wecoza_db();
        $table = $optionConfig['table'];
        $pk    = $optionConfig['pk'];
        $label = $optionConfig['label'];
        $order = $optionConfig['order'] ?? $label . ' ASC';

        $sql  = "SELECT {$pk}, {$label} FROM {$table} ORDER BY {$order}";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'value' => $row[$pk],
                'label' => $row[$label],
            ];
        }
        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | WordPress Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Register all WordPress hooks
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register lookup table shortcodes
     *
     * @return void
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_manage_qualifications', [$this, 'renderManageTable']);
        add_shortcode('wecoza_manage_placement_levels', [$this, 'renderManageTable']);
        add_shortcode('wecoza_manage_employers', [$this, 'renderManageTable']);
        add_shortcode('wecoza_manage_class_subjects', [$this, 'renderManageTable']);
        add_shortcode('wecoza_manage_class_types', [$this, 'renderManageTable']);
    }

    /*
    |--------------------------------------------------------------------------
    | Shortcode Renderer
    |--------------------------------------------------------------------------
    */

    /**
     * Render the lookup table management card.
     * Determines which table to manage from the shortcode tag.
     *
     * @param array|string $atts    Shortcode attributes
     * @param string       $content Shortcode enclosed content (unused)
     * @param string       $tag     Shortcode tag name
     * @return string HTML output
     */
    public function renderManageTable(array|string $atts = [], string $content = '', string $tag = ''): string
    {
        // Determine table_key from shortcode tag
        $tableKey = self::SHORTCODE_MAP[$tag] ?? null;
        if ($tableKey === null) {
            return '<div class="alert alert-danger">Unknown lookup table shortcode.</div>';
        }

        // Retrieve config from TABLES constant
        $config = self::getTableConfig($tableKey);
        if ($config === null) {
            return '<div class="alert alert-danger">Lookup table configuration not found.</div>';
        }

        // Capability check
        if (!current_user_can($config['capability'])) {
            return '<div class="alert alert-warning">You do not have permission to manage this table.</div>';
        }

        // Resolve column_options into actual select option lists
        $selectOptions = [];
        if (!empty($config['column_options'])) {
            foreach ($config['column_options'] as $col => $optionConfig) {
                $selectOptions[$col] = self::getSelectOptions($optionConfig);
            }
        }

        return $this->render('lookup-tables/manage', [
            'tableKey'      => $tableKey,
            'config'        => $config,
            'selectOptions' => $selectOptions,
            'nonce'         => wp_create_nonce('lookup_table_nonce'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Asset Enqueuing
    |--------------------------------------------------------------------------
    */

    /**
     * Conditionally enqueue lookup table manager JS only on pages
     * that contain one of the lookup table shortcodes.
     *
     * @return void
     */
    public function enqueueAssets(): void
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $hasShortcode = has_shortcode($post->post_content, 'wecoza_manage_qualifications')
                     || has_shortcode($post->post_content, 'wecoza_manage_placement_levels')
                     || has_shortcode($post->post_content, 'wecoza_manage_employers')
                     || has_shortcode($post->post_content, 'wecoza_manage_class_subjects')
                     || has_shortcode($post->post_content, 'wecoza_manage_class_types');

        if (!$hasShortcode) {
            return;
        }

        wp_register_script(
            'wecoza-lookup-table-manager',
            WECOZA_CORE_URL . 'assets/js/lookup-tables/lookup-table-manager.js',
            ['jquery'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_enqueue_script('wecoza-lookup-table-manager');

        wp_localize_script('wecoza-lookup-table-manager', 'WeCozaLookupTables', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('lookup_table_nonce'),
        ]);
    }
}
