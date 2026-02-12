<?php
/**
 * Agent Display Table Rows Template
 *
 * This template displays only the table rows for AJAX updates.
 *
 * @package WeCoza\Core
 * @since 1.0.0
 *
 * @var array $agents Array of agents to display
 * @var array $columns Columns to display
 * @var bool $can_manage Whether user can manage agents
 * @var bool $show_actions Whether to show actions column
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!empty($agents)) :
    foreach ($agents as $agent) : ?>
    <tr class="align-middle"
        data-name="<?php echo esc_attr($agent['first_name'] . ' ' . $agent['last_name']); ?>"
        data-email="<?php echo esc_attr($agent['email']); ?>"
        data-phone="<?php echo esc_attr($agent['phone']); ?>"
        data-city="<?php echo esc_attr($agent['city']); ?>"
        data-status="<?php echo esc_attr($agent['status']); ?>">
        <?php foreach ($columns as $column_key => $column_label) : ?>
        <td class="text-nowrap">
            <?php
            switch ($column_key) {
                case 'first_name':
                    echo esc_html($agent['first_name']);
                    break;
                case 'initials':
                    echo esc_html($agent['initials']);
                    break;
                case 'last_name':
                    echo esc_html($agent['last_name']);
                    break;
                case 'gender':
                    echo esc_html($agent['gender']);
                    break;
                case 'race':
                    echo esc_html($agent['race']);
                    break;
                case 'phone':
                    echo esc_html($agent['phone']);
                    break;
                case 'email':
                    echo '<a href="mailto:' . esc_attr($agent['email']) . '">' . esc_html($agent['email']) . '</a>';
                    break;
                case 'city':
                    echo esc_html($agent['city']);
                    break;
            }
            ?>
        </td>
        <?php endforeach; ?>
        <?php if ($show_actions) : ?>
        <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?php echo esc_url(add_query_arg('agent_id', $agent['id'], home_url('/app/agent-view/'))); ?>"
                   class="btn btn-sm btn-outline-secondary border-0"
                   title="<?php esc_attr_e('View', 'wecoza-core'); ?>">
                    <i class="bi bi-eye"></i>
                </a>
                <?php if ($can_manage) : ?>
                <a href="<?php echo esc_url(add_query_arg(['update' => '', 'agent_id' => $agent['id']], home_url('/new-agents/'))); ?>"
                   class="btn btn-sm btn-outline-secondary border-0"
                   title="<?php esc_attr_e('Edit', 'wecoza-core'); ?>">
                    <i class="bi bi-pencil"></i>
                </a>
                <button type="button"
                        class="btn btn-sm btn-outline-secondary border-0"
                        data-agent-id="<?php echo esc_attr($agent['id']); ?>"
                        title="<?php esc_attr_e('Delete', 'wecoza-core'); ?>">
                    <i class="bi bi-trash"></i>
                </button>
                <?php endif; ?>
            </div>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach;
else : ?>
    <tr>
        <td colspan="<?php echo count($columns) + ($show_actions ? 1 : 0); ?>" class="text-center text-muted">
            <?php esc_html_e('No agents found.', 'wecoza-core'); ?>
        </td>
    </tr>
<?php endif; ?>
