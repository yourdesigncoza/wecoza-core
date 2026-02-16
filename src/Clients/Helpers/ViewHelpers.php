<?php

namespace WeCoza\Clients\Helpers;

/**
 * View Helpers for rendering form elements and UI components
 *
 * @package WeCoza\Clients
 * @since 1.0.0
 */
class ViewHelpers {

    /**
     * Render form field
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param string $label Field label
     * @param mixed $value Field value
     * @param array $options Additional options
     * @return string
     */
    public static function renderField($type, $name, $label, $value = '', $options = array()) {
        $defaults = array(
            'required' => false,
            'class' => 'form-control form-control-sm',
            'id' => $name,
            'placeholder' => '',
            'help_text' => '',
            'col_class' => 'col-md-6',
            'error' => '',
            'readonly' => false,
            'multiple' => false,
            'options' => array(),
        );

        $options = array_merge($defaults, $options);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($options['col_class']); ?>">
            <label for="<?php echo esc_attr($options['id']); ?>" class="form-label">
                <?php echo esc_html($label); ?>
                <?php if ($options['required']) : ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
            </label>

            <?php
            switch ($type) {
                case 'text':
                case 'email':
                case 'tel':
                case 'date':
                    self::renderInput($type, $name, $value, $options);
                    break;

                case 'textarea':
                    self::renderTextarea($name, $value, $options);
                    break;

                case 'select':
                    self::renderSelect($name, $value, $options);
                    break;

                case 'file':
                    self::renderFileInput($name, $value, $options);
                    break;
            }
            ?>

            <?php if ($options['error']) : ?>
                <div class="invalid-feedback d-block">
                    <?php echo esc_html($options['error']); ?>
                </div>
            <?php else : ?>
                <div class="invalid-feedback">
                    Please provide <?php echo esc_html(strtolower($label)); ?>.
                </div>
                <div class="valid-feedback">
                    Looks good!
                </div>
            <?php endif; ?>

            <?php if ($options['help_text']) : ?>
                <small class="form-text text-muted">
                    <?php echo esc_html($options['help_text']); ?>
                </small>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render input field
     */
    private static function renderInput($type, $name, $value, $options) {
        // Convert arrays to empty string to prevent "Array to string conversion" errors
        if (is_array($value)) {
            $value = '';
        }
        ?>
        <input
            type="<?php echo esc_attr($type); ?>"
            id="<?php echo esc_attr($options['id']); ?>"
            name="<?php echo esc_attr($name); ?>"
            class="<?php echo esc_attr($options['class']); ?>"
            value="<?php echo esc_attr($value); ?>"
            <?php if ($options['placeholder']) : ?>
                placeholder="<?php echo esc_attr($options['placeholder']); ?>"
            <?php endif; ?>
            <?php if ($options['required']) : ?>
                required
            <?php endif; ?>
            <?php if ($options['readonly']) : ?>
                readonly
            <?php endif; ?>
        >
        <?php
    }

    /**
     * Render textarea field
     */
    private static function renderTextarea($name, $value, $options) {
        // Convert arrays to empty string to prevent "Array to string conversion" errors
        if (is_array($value)) {
            $value = '';
        }
        ?>
        <textarea
            id="<?php echo esc_attr($options['id']); ?>"
            name="<?php echo esc_attr($name); ?>"
            class="<?php echo esc_attr($options['class']); ?>"
            rows="<?php echo isset($options['rows']) ? esc_attr($options['rows']) : '3'; ?>"
            <?php if ($options['placeholder']) : ?>
                placeholder="<?php echo esc_attr($options['placeholder']); ?>"
            <?php endif; ?>
            <?php if ($options['required']) : ?>
                required
            <?php endif; ?>
            <?php if ($options['readonly']) : ?>
                readonly
            <?php endif; ?>
        ><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    /**
     * Render select field
     */
    private static function renderSelect($name, $value, $options) {
        ?>
        <select
            id="<?php echo esc_attr($options['id']); ?>"
            name="<?php echo esc_attr($name); ?>"
            class="<?php echo esc_attr($options['class']); ?> form-select form-select-sm"
            <?php if ($options['required']) : ?>
                required
            <?php endif; ?>
            <?php if ($options['readonly']) : ?>
                disabled
            <?php endif; ?>
            <?php if ($options['multiple']) : ?>
                multiple
            <?php endif; ?>
        >
            <option value="">Select</option>
            <?php foreach ($options['options'] as $optValue => $optLabel) : ?>
                <?php if (is_array($optLabel)) : ?>
                    <option
                        value="<?php echo esc_attr($optValue); ?>"
                        <?php selected($value, $optValue); ?>
                        <?php if (isset($optLabel['data'])) : ?>
                            <?php foreach ($optLabel['data'] as $dataKey => $dataValue) : ?>
                                data-<?php echo esc_attr($dataKey); ?>="<?php echo esc_attr($dataValue); ?>"
                            <?php endforeach; ?>
                        <?php endif; ?>
                    >
                        <?php echo esc_html($optLabel['label'] ?? $optLabel); ?>
                    </option>
                <?php else : ?>
                    <option
                        value="<?php echo esc_attr($optValue); ?>"
                        <?php selected($value, $optValue); ?>
                    >
                        <?php echo esc_html($optLabel); ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render file input field
     */
    private static function renderFileInput($name, $value, $options) {
        ?>
        <input
            type="file"
            id="<?php echo esc_attr($options['id']); ?>"
            name="<?php echo esc_attr($name); ?><?php echo $options['multiple'] ? '[]' : ''; ?>"
            class="<?php echo esc_attr($options['class']); ?>"
            <?php if ($options['multiple']) : ?>
                multiple
            <?php endif; ?>
            <?php if (isset($options['accept'])) : ?>
                accept="<?php echo esc_attr($options['accept']); ?>"
            <?php endif; ?>
        >
        <?php if ($value) : ?>
            <p class="mt-1">
                Current file(s):
                <a href="<?php echo esc_url($value); ?>" target="_blank">View</a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render alert message
     *
     * @param string $message Message text
     * @param string $type Alert type (success, error, warning, info)
     * @param bool $dismissible Whether alert is dismissible
     * @return string
     */
    public static function renderAlert($message, $type = 'info', $dismissible = true) {
        $typeClasses = array(
            'success' => 'alert-subtle-success',
            'error' => 'alert-subtle-danger',
            'warning' => 'alert-subtle-warning',
            'info' => 'alert-subtle-info',
            'discovery' => 'alert-subtle-primary',
        );

        $typeIcons = array(
            'success' => 'fa-circle-check icon-success',
            'error' => 'fa-circle-exclamation icon-danger',
            'warning' => 'fa-triangle-exclamation icon-warning',
            'info' => 'fa-circle-info icon-info',
            'discovery' => 'fa-circle-question icon-primary',
        );

        $alertClass = isset($typeClasses[$type]) ? $typeClasses[$type] : 'alert-subtle-info';
        $iconClass = isset($typeIcons[$type]) ? $typeIcons[$type] : 'fa-circle-info icon-info';

        ob_start();
        ?>
        <div class="alert <?php echo esc_attr($alertClass); ?> <?php echo $dismissible ? 'alert-dismissible fade show' : ''; ?> ydcoza-notification" role="alert">
            <div class="d-flex gap-4">
                <span><i class="fa-solid <?php echo esc_attr($iconClass); ?>"></i></span>
                <div><?php echo wp_kses_post($message); ?></div>
            </div>
            <?php if ($dismissible) : ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pagination
     *
     * @param int $currentPage Current page number
     * @param int $totalPages Total number of pages
     * @param string $baseUrl Base URL for pagination links
     * @param array $queryArgs Additional query arguments
     * @return string
     */
    public static function renderPagination($currentPage, $totalPages, $baseUrl, $queryArgs = array()) {
        if ($totalPages <= 1) {
            return '';
        }

        ob_start();
        ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($currentPage > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($queryArgs, array('client_page' => $currentPage - 1)), $baseUrl)); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $currentPage - 2);
                $end = min($totalPages, $currentPage + 2);

                if ($start > 1) : ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($queryArgs, array('client_page' => 1)), $baseUrl)); ?>">1</a>
                    </li>
                    <?php if ($start > 2) : ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++) : ?>
                    <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($queryArgs, array('client_page' => $i)), $baseUrl)); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($end < $totalPages) : ?>
                    <?php if ($end < $totalPages - 1) : ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($queryArgs, array('client_page' => $totalPages)), $baseUrl)); ?>">
                            <?php echo $totalPages; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($currentPage < $totalPages) : ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo esc_url(add_query_arg(array_merge($queryArgs, array('client_page' => $currentPage + 1)), $baseUrl)); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php
        return ob_get_clean();
    }

    /**
     * Format date
     *
     * @param string $date Date string
     * @param string $format Date format
     * @return string
     */
    public static function formatDate($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp ? wp_date($format, $timestamp) : $date;
    }

    /**
     * Format phone number
     *
     * @param string $phone Phone number
     * @return string
     */
    public static function formatPhone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Format based on length
        if (strlen($phone) === 10) {
            // Format as (xxx) xxx-xxxx
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6)
            );
        }

        return $phone;
    }
}
