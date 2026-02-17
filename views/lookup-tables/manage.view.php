<?php
/**
 * Lookup Table Manager View
 *
 * Renders a Phoenix-styled CRUD card for managing a lookup table inline.
 * Data rows are loaded via AJAX on page ready.
 *
 * Variables provided by LookupTableController::renderManageTable():
 *   @var string $tableKey  Table key (e.g. 'qualifications', 'placement_levels')
 *   @var array  $config    Table config: table, pk, columns, labels, title, capability
 *   @var string $nonce     WordPress nonce for AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="card mb-3" id="lookup-table-<?= esc_attr($tableKey) ?>">

    <!-- Card Header -->
    <div class="card-header border-bottom border-translucent">
        <h5 class="mb-0"><?= esc_html($config['title']) ?></h5>
    </div>

    <!-- Alert container for success/error feedback (hidden by default) -->
    <div id="lookup-alert-<?= esc_attr($tableKey) ?>" class="mx-3 mt-3" style="display:none;"></div>

    <div class="card-body p-0">
        <table class="table table-sm fs-9 mb-0"
               data-table-key="<?= esc_attr($tableKey) ?>"
               data-pk="<?= esc_attr($config['pk']) ?>"
               id="lookup-table-body-<?= esc_attr($tableKey) ?>">
            <thead>
                <tr>
                    <th class="align-middle ps-3" style="width:50px">#</th>
                    <?php foreach ($config['labels'] as $label): ?>
                        <th class="align-middle"><?= esc_html($label) ?></th>
                    <?php endforeach; ?>
                    <th class="align-middle text-end pe-3" style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody id="lookup-rows-<?= esc_attr($tableKey) ?>">
                <!-- Inline "Add New" row -->
                <tr class="lookup-add-row" id="lookup-add-row-<?= esc_attr($tableKey) ?>">
                    <td class="align-middle ps-3">
                        <span class="badge badge-phoenix badge-phoenix-success fs-10">New</span>
                    </td>
                    <?php foreach ($config['columns'] as $col): ?>
                        <td class="align-middle">
                            <input type="text"
                                   class="form-control form-control-sm lookup-add-input"
                                   data-column="<?= esc_attr($col) ?>"
                                   placeholder="Enter <?= esc_attr($col) ?>">
                        </td>
                    <?php endforeach; ?>
                    <td class="align-middle text-end pe-3">
                        <button type="button"
                                class="btn btn-sm btn-phoenix-success lookup-btn-add"
                                data-table-key="<?= esc_attr($tableKey) ?>"
                                title="Add">
                            <span class="fas fa-plus"></span> Add
                        </button>
                    </td>
                </tr>
                <!-- Data rows are populated by lookup-table-manager.js on page load -->
            </tbody>
        </table>
    </div>

    <!-- Loading spinner shown during AJAX calls -->
    <div id="lookup-loading-<?= esc_attr($tableKey) ?>" class="text-center py-3" style="display:none;">
        <div class="spinner-border spinner-border-sm text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

</div>

<!-- Hidden JSON config consumed by lookup-table-manager.js -->
<script type="application/json" id="lookup-config-<?= esc_attr($tableKey) ?>">
<?= wp_json_encode([
    'tableKey' => $tableKey,
    'pk'       => $config['pk'],
    'columns'  => $config['columns'],
    'labels'   => $config['labels'],
]) ?>
</script>
