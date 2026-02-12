<?php
/**
 * Agent Fields Partial Template
 *
 * This template contains reusable form field components for agent forms.
 *
 * @package WeCoza\Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Helper function to render a text input field
if (!function_exists('wecoza_agents_render_text_field')) {
    function wecoza_agents_render_text_field($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'value' => '',
            'required' => false,
            'placeholder' => '',
            'class' => 'form-control form-control-sm',
            'errors' => array(),
            'col_class' => 'col-md-3',
            'type' => 'text',
            'readonly' => false,
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $required_attr = $args['required'] ? ' required' : '';
        $readonly_attr = $args['readonly'] ? ' readonly' : '';
        $placeholder_attr = $args['placeholder'] ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<label for="' . esc_attr($args['name']) . '" class="form-label">';
        echo esc_html($args['label']);
        if ($args['required']) {
            echo ' <span class="text-danger">*</span>';
        }
        echo '</label>';

        echo '<input type="' . esc_attr($args['type']) . '" id="' . esc_attr($args['name']) . '" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class'] . $error_class) . '" ';
        echo 'value="' . esc_attr($args['value']) . '"' . $required_attr . $readonly_attr . $placeholder_attr . '>';

        if ($args['required']) {
            echo '<div class="invalid-feedback">Please provide ' . esc_html(strtolower($args['label'])) . '.</div>';
        }

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render a select field
if (!function_exists('wecoza_agents_render_select_field')) {
    function wecoza_agents_render_select_field($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'value' => '',
            'options' => array(),
            'required' => false,
            'class' => 'form-select form-select-sm',
            'errors' => array(),
            'col_class' => 'col-md-3',
            'placeholder' => 'Select',
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $required_attr = $args['required'] ? ' required' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<label for="' . esc_attr($args['name']) . '" class="form-label">';
        echo esc_html($args['label']);
        if ($args['required']) {
            echo ' <span class="text-danger">*</span>';
        }
        echo '</label>';

        echo '<select id="' . esc_attr($args['name']) . '" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class'] . $error_class) . '"' . $required_attr . '>';

        if ($args['placeholder']) {
            echo '<option value="">' . esc_html($args['placeholder']) . '</option>';
        }

        foreach ($args['options'] as $option_value => $option_label) {
            $selected = selected($args['value'], $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '"' . $selected . '>' . esc_html($option_label) . '</option>';
        }

        echo '</select>';

        if ($args['required']) {
            echo '<div class="invalid-feedback">Please select ' . esc_html(strtolower($args['label'])) . '.</div>';
        }

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render a textarea field
if (!function_exists('wecoza_agents_render_textarea_field')) {
    function wecoza_agents_render_textarea_field($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'value' => '',
            'required' => false,
            'placeholder' => '',
            'class' => 'form-control form-control-sm',
            'errors' => array(),
            'col_class' => 'col-md-6',
            'rows' => 3,
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $required_attr = $args['required'] ? ' required' : '';
        $placeholder_attr = $args['placeholder'] ? ' placeholder="' . esc_attr($args['placeholder']) . '"' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<label for="' . esc_attr($args['name']) . '" class="form-label">';
        echo esc_html($args['label']);
        if ($args['required']) {
            echo ' <span class="text-danger">*</span>';
        }
        echo '</label>';

        echo '<textarea id="' . esc_attr($args['name']) . '" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class'] . $error_class) . '" ';
        echo 'rows="' . esc_attr($args['rows']) . '"' . $required_attr . $placeholder_attr . '>';
        echo esc_textarea($args['value']);
        echo '</textarea>';

        if ($args['required']) {
            echo '<div class="invalid-feedback">Please provide ' . esc_html(strtolower($args['label'])) . '.</div>';
        }

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render a checkbox field
if (!function_exists('wecoza_agents_render_checkbox_field')) {
    function wecoza_agents_render_checkbox_field($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'value' => '',
            'checked' => false,
            'class' => 'form-check-input',
            'errors' => array(),
            'col_class' => 'col-md-3',
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $checked_attr = $args['checked'] ? ' checked' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<div class="form-check">';
        echo '<input type="checkbox" id="' . esc_attr($args['name']) . '" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class'] . $error_class) . '" ';
        echo 'value="' . esc_attr($args['value']) . '"' . $checked_attr . '>';
        echo '<label class="form-check-label" for="' . esc_attr($args['name']) . '">';
        echo esc_html($args['label']);
        echo '</label>';
        echo '</div>';

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render a file upload field
if (!function_exists('wecoza_agents_render_file_field')) {
    function wecoza_agents_render_file_field($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'required' => false,
            'accept' => '',
            'class' => 'form-control form-control-sm',
            'errors' => array(),
            'col_class' => 'col-md-3',
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $required_attr = $args['required'] ? ' required' : '';
        $accept_attr = $args['accept'] ? ' accept="' . esc_attr($args['accept']) . '"' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<label for="' . esc_attr($args['name']) . '" class="form-label">';
        echo esc_html($args['label']);
        if ($args['required']) {
            echo ' <span class="text-danger">*</span>';
        }
        echo '</label>';

        echo '<input type="file" id="' . esc_attr($args['name']) . '" name="' . esc_attr($args['name']) . '" ';
        echo 'class="' . esc_attr($args['class'] . $error_class) . '"' . $required_attr . $accept_attr . '>';

        if ($args['required']) {
            echo '<div class="invalid-feedback">Please select a file.</div>';
        }

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render radio button group
if (!function_exists('wecoza_agents_render_radio_group')) {
    function wecoza_agents_render_radio_group($args) {
        $defaults = array(
            'name' => '',
            'label' => '',
            'value' => '',
            'options' => array(),
            'required' => false,
            'class' => 'form-check-input',
            'errors' => array(),
            'col_class' => 'col-md-6',
            'inline' => false,
            'help_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $error_class = isset($args['errors'][$args['name']]) ? ' is-invalid' : '';
        $required_attr = $args['required'] ? ' required' : '';

        echo '<div class="' . esc_attr($args['col_class']) . '">';
        echo '<label class="form-label">';
        echo esc_html($args['label']);
        if ($args['required']) {
            echo ' <span class="text-danger">*</span>';
        }
        echo '</label>';

        foreach ($args['options'] as $option_value => $option_label) {
            $checked = checked($args['value'], $option_value, false);
            $radio_id = $args['name'] . '_' . $option_value;

            echo '<div class="form-check' . ($args['inline'] ? ' form-check-inline' : '') . '">';
            echo '<input type="radio" id="' . esc_attr($radio_id) . '" name="' . esc_attr($args['name']) . '" ';
            echo 'class="' . esc_attr($args['class'] . $error_class) . '" ';
            echo 'value="' . esc_attr($option_value) . '"' . $checked . $required_attr . '>';
            echo '<label class="form-check-label" for="' . esc_attr($radio_id) . '">';
            echo esc_html($option_label);
            echo '</label>';
            echo '</div>';
        }

        if (isset($args['errors'][$args['name']])) {
            echo '<div class="invalid-feedback">' . esc_html($args['errors'][$args['name']]) . '</div>';
        }

        if ($args['help_text']) {
            echo '<div class="form-text">' . esc_html($args['help_text']) . '</div>';
        }

        echo '</div>';
    }
}

// Helper function to render a date field
if (!function_exists('wecoza_agents_render_date_field')) {
    function wecoza_agents_render_date_field($args) {
        $args['type'] = 'date';
        $args['class'] = isset($args['class']) ? $args['class'] : 'form-control form-control-sm';
        wecoza_agents_render_text_field($args);
    }
}

// Helper function to render an email field
if (!function_exists('wecoza_agents_render_email_field')) {
    function wecoza_agents_render_email_field($args) {
        $args['type'] = 'email';
        $args['class'] = isset($args['class']) ? $args['class'] : 'form-control form-control-sm';
        wecoza_agents_render_text_field($args);
    }
}

// Helper function to render a phone field
if (!function_exists('wecoza_agents_render_phone_field')) {
    function wecoza_agents_render_phone_field($args) {
        $args['type'] = 'tel';
        $args['class'] = isset($args['class']) ? $args['class'] : 'form-control form-control-sm';
        wecoza_agents_render_text_field($args);
    }
}
