<?php
/**
 * NLQ Table Display View
 *
 * Renders a saved query result as a Phoenix-styled data table.
 * Used by the [wecoza_nlq_table] shortcode.
 *
 * Available Variables:
 *   - $columns:    Array of column names from query result
 *   - $rows:       Array of data rows
 *   - $row_count:  Total number of rows
 *   - $title:      Display title
 *   - $query_id:   Saved query ID
 *   - $unique_id:  Unique DOM ID for this table instance
 *   - $show_sql:   Boolean - show SQL accordion
 *   - $show_export: Boolean - show export button
 *   - $page_size:  DataTables page length
 *   - $sql_query:  The SQL query text (if show_sql is true)
 *   - $description: Query description
 *   - $category:   Query category
 *
 * @package WeCoza\NLQ
 */

defined('ABSPATH') || exit;

$columns     = $columns ?? [];
$rows        = $rows ?? [];
$row_count   = $row_count ?? 0;
$title       = $title ?? 'Query Results';
$subtitle    = $subtitle ?? '';
$query_id    = $query_id ?? 0;
$unique_id   = $unique_id ?? 'nlq-table-0';
$show_sql    = $show_sql ?? false;
$show_export = $show_export ?? true;
$page_size   = $page_size ?? 25;
$sql_query   = $sql_query ?? '';
$description = $description ?? '';
$category    = $category ?? '';
?>

<div class="wecoza-nlq-table-display" id="<?php echo esc_attr($unique_id); ?>-wrapper">

    <?php if (empty($rows)): ?>
        <!-- No Data -->
        <div class="alert alert-subtle-info d-flex align-items-center">
            <i class="bi bi-info-circle-fill me-3 fs-4"></i>
            <div>
                <h6 class="alert-heading mb-1">No Data Found</h6>
                <p class="mb-0">This query returned no results. The data may not exist or the query may need updating.</p>
            </div>
        </div>
    <?php else: ?>

        <!-- Phoenix Card Container -->
        <div class="card shadow-none border my-3" data-component-card="data-component-card">

            <!-- Card Header -->
            <div class="card-header p-3 border-bottom">
                <div class="row g-3 justify-content-between align-items-center mb-3">
                    <div class="col-12 col-md">
                        <h4 class="text-body mb-0" data-anchor="data-anchor">
                            <?php echo esc_html($title); ?>
                            <i class="bi bi-table ms-2 text-body-quaternary"></i>
                        </h4>
                        <?php if ($subtitle): ?>
                        <p class="text-body-tertiary fs-9 mb-0 mt-1">
                            <?php echo esc_html($subtitle); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="search-box col-auto">
                        <form class="position-relative">
                            <input class="form-control search-input search form-control-sm" type="search"
                                   placeholder="Search" aria-label="Search"
                                   id="<?php echo esc_attr($unique_id); ?>-search">
                            <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>
                        </form>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <?php if ($show_export): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm nlq-export-csv-btn" data-table="<?php echo esc_attr($unique_id); ?>">
                                Export
                                <i class="bi bi-download ms-1"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary Strip -->
                <div class="col-12">
                    <div class="scrollbar">
                        <div class="row g-0 flex-nowrap">
                            <div class="col-auto border-end pe-4">
                                <h6 class="text-body-tertiary">Total Records : <?php echo esc_html((string) $row_count); ?></h6>
                            </div>
                            <div class="col-auto px-4 border-end">
                                <h6 class="text-body-tertiary">Columns : <?php echo esc_html((string) count($columns)); ?></h6>
                            </div>
                            <div class="col-auto px-4 border-end">
                                <h6 class="text-body-tertiary">Query : #<?php echo esc_html((string) $query_id); ?></h6>
                            </div>
                            <?php if ($category): ?>
                            <div class="col-auto px-4">
                                <h6 class="text-body-tertiary">Category : <?php echo esc_html($category); ?></h6>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Body / Table -->
            <div class="card-body p-4 py-2">
                <div class="table-responsive">
                    <table id="<?php echo esc_attr($unique_id); ?>"
                           class="table table-hover table-sm fs-9 mb-0 overflow-hidden wecoza-nlq-datatable"
                           data-page-size="<?php echo esc_attr((string) $page_size); ?>">
                        <thead class="border-bottom">
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                <th scope="col" class="border-0">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $col))); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $idx => $col):
                                    $value = $row[$col] ?? '';
                                    $isFirstCol = ($idx === 0);
                                ?>
                                <td class="py-2 align-middle <?php echo $isFirstCol ? 'text-center' : ''; ?>">
                                    <?php if ($isFirstCol && is_numeric($value)): ?>
                                        <span class="badge fs-10 badge-phoenix badge-phoenix-secondary">
                                            #<?php echo esc_html((string) $value); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo esc_html((string) $value); ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($show_sql && $sql_query): ?>
        <!-- SQL Accordion -->
        <div class="card shadow-none border mb-3">
            <div class="card-header p-0 border-0">
                <button class="btn btn-link text-decoration-none text-body-tertiary w-100 text-start p-3 collapsed"
                        type="button" data-bs-toggle="collapse"
                        data-bs-target="#sql-body-<?php echo esc_attr($unique_id); ?>">
                    <i class="bi bi-code-slash me-2"></i>
                    <small>View SQL Query</small>
                </button>
            </div>
            <div id="sql-body-<?php echo esc_attr($unique_id); ?>" class="collapse">
                <div class="card-body pt-0">
                    <pre class="bg-body-tertiary p-3 rounded mb-0" style="font-size: 0.8rem;"><code><?php echo esc_html($sql_query); ?></code></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
