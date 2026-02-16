<?php
declare(strict_types=1);

namespace WeCoza\Clients\Controllers;

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Core\Abstract\BaseController;
use WeCoza\Clients\Models\LocationsModel;

class LocationsController extends BaseController {

    protected $model;

    /**
     * Register hooks (required by BaseController)
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    protected function getModel() {
        if (!$this->model) {
            $this->model = new LocationsModel();
        }

        return $this->model;
    }

    public function registerShortcodes(): void {
        add_shortcode('wecoza_locations_capture', array($this, 'captureLocationShortcode'));
        add_shortcode('wecoza_locations_list', array($this, 'listLocationsShortcode'));
        add_shortcode('wecoza_locations_edit', array($this, 'editLocationShortcode'));
    }

    public function enqueueAssets(): void {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $hasCapture = has_shortcode($post->post_content, 'wecoza_locations_capture');
        $hasList = has_shortcode($post->post_content, 'wecoza_locations_list');
        $hasEdit = has_shortcode($post->post_content, 'wecoza_locations_edit');

        if (!$hasCapture && !$hasList && !$hasEdit) {
            return;
        }

        $googleMapsKey = $this->getGoogleMapsApiKey();
        $googleHandle = 'google-maps-api';
        $config = wecoza_config('clients');
        $provinceOptions = array_values($config['province_options'] ?? array());

        // Enqueue for capture/edit
        if ($hasCapture || $hasEdit) {
            $dependencies = array('jquery');
            if ($googleMapsKey) {
                if (!wp_script_is($googleHandle, 'enqueued')) {
                    wp_enqueue_script(
                        $googleHandle,
                        'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($googleMapsKey) . '&libraries=places&loading=async&v=weekly',
                        array(),
                        null,
                        true
                    );
                }
                $dependencies[] = $googleHandle;
            }

            wp_enqueue_script(
                'wecoza-location-capture',
                wecoza_js_url('clients/location-capture.js'),
                $dependencies,
                WECOZA_CORE_VERSION,
                true
            );

            wp_localize_script(
                'wecoza-location-capture',
                'wecoza_locations',
                array(
                    'provinces' => $provinceOptions,
                    'googleMapsEnabled' => (bool) $googleMapsKey,
                    'messages' => array(
                        'autocompleteUnavailable' => __('Google Maps autocomplete is unavailable. You can still complete the form manually.', 'wecoza-core'),
                        'selectProvince' => __('Please choose a province.', 'wecoza-core'),
                        'requiredFields' => __('Please complete all required fields.', 'wecoza-core'),
                    ),
                )
            );

            wp_localize_script(
                'wecoza-location-capture',
                'wecoza_ajax',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                )
            );
        }

        // List page uses server-side ILIKE search — no Google Maps needed
    }

    public function captureLocationShortcode($atts): string {
        if (!current_user_can('manage_wecoza_clients')) {
            return '<p>' . esc_html__('You do not have permission to capture locations.', 'wecoza-core') . '</p>';
        }

        $config = wecoza_config('clients');
        $provinces = array_values($config['province_options'] ?? array());
        $errors = array();
        $success = false;
        $location = array(
            'street_address' => '',
            'suburb' => '',
            'town' => '',
            'province' => '',
            'postal_code' => '',
            'latitude' => '',
            'longitude' => '',
        );

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_locations_form_nonce'])) {
            if (!wp_verify_nonce($_POST['wecoza_locations_form_nonce'], 'submit_locations_form')) {
                $errors['general'] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->handleFormSubmission();
                if ($result['success']) {
                    $success = true;
                    $location = array(
                        'street_address' => '',
                        'suburb' => '',
                        'town' => '',
                        'province' => '',
                        'postal_code' => '',
                        'latitude' => '',
                        'longitude' => '',
                    );
                } else {
                    $errors = $result['errors'];
                    $location = $result['data'];
                }
            }
        }

        return wecoza_view('clients/components/location-capture-form', array(
            'errors' => $errors,
            'success' => $success,
            'location' => $location,
            'provinces' => $provinces,
            'google_maps_enabled' => (bool) $this->getGoogleMapsApiKey(),
        ), true);
    }

    protected function handleFormSubmission() {
        $data = $this->sanitizeFormData($_POST);
        $errors = $this->getModel()->validate($data);

        if (!empty($errors)) {
            return array(
                'success' => false,
                'errors' => $errors,
                'data' => $data,
            );
        }

        $created = $this->getModel()->create($data);

        if (!$created) {
            return array(
                'success' => false,
                'errors' => array('general' => __('Failed to save location. Please try again.', 'wecoza-core')),
                'data' => $data,
            );
        }

        return array(
            'success' => true,
            'errors' => array(),
            'data' => $data,
        );
    }

    protected function sanitizeFormData($data) {
        return array(
            'street_address' => isset($data['street_address']) ? sanitize_text_field($data['street_address']) : '',
            'suburb' => isset($data['suburb']) ? sanitize_text_field($data['suburb']) : '',
            'town' => isset($data['town']) ? sanitize_text_field($data['town']) : '',
            'province' => isset($data['province']) ? sanitize_text_field($data['province']) : '',
            'postal_code' => isset($data['postal_code']) ? sanitize_text_field($data['postal_code']) : '',
            'latitude' => isset($data['latitude']) ? sanitize_text_field(str_replace(',', '.', $data['latitude'])) : '',
            'longitude' => isset($data['longitude']) ? sanitize_text_field(str_replace(',', '.', $data['longitude'])) : '',
        );
    }

    protected function getGoogleMapsApiKey() {
        $optionKey = get_option('wecoza_google_maps_api_key');
        return !empty($optionKey) ? $optionKey : '';
    }

    public function editLocationShortcode($atts): string {
        if (!current_user_can('edit_wecoza_clients')) {
            return '<p>' . esc_html__('You do not have permission to edit locations.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts(array(
            'id' => 0,
            'redirect_url' => '',
        ), $atts);

        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : '';
        $id = isset($_GET['location_id']) ? (int) $_GET['location_id'] : (int) $atts['id'];
        if ($mode !== 'update' || $id <= 0) {
            return '<p>' . esc_html__('No location specified.', 'wecoza-core') . '</p>';
        }

        $config = wecoza_config('clients');
        $provinces = array_values($config['province_options'] ?? array());
        $errors = array();
        $success = false;
        $location = $this->getModel()->getById($id);
        if (!$location) {
            return '<p>' . esc_html__('Location not found.', 'wecoza-core') . '</p>';
        }

        // Build absolute redirect URL if provided
        $redirectAbs = '';
        if (!empty($atts['redirect_url'])) {
            $r = trim((string) $atts['redirect_url']);
            if (stripos($r, 'http://') === 0 || stripos($r, 'https://') === 0) {
                $redirectAbs = $r;
            } else {
                $r = '/' . ltrim($r, '/');
                $scheme = is_ssl() ? 'https' : 'http';
                $redirectAbs = site_url($r, $scheme);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_locations_form_nonce'])) {
            if (!wp_verify_nonce($_POST['wecoza_locations_form_nonce'], 'submit_locations_form')) {
                $errors['general'] = __('Security check failed. Please try again.', 'wecoza-core');
            } else {
                $result = $this->handleUpdateSubmission($id, $redirectAbs);
                if ($result['success']) {
                    $success = true;
                    $location = $result['location'];
                    if (!empty($result['redirect'])) {
                        // Headers already sent fallback: inject client-side redirect
                        $script = '<script>window.location.href=' . json_encode($result['redirect']) . ';</script>';
                        return $script;
                    }
                } else {
                    $errors = $result['errors'];
                    $location = $result['data'];
                    $location['location_id'] = $id;
                }
            }
        }

        return wecoza_view('clients/components/location-capture-form', array(
            'errors' => $errors,
            'success' => $success,
            'location' => $location,
            'provinces' => $provinces,
            'google_maps_enabled' => (bool) $this->getGoogleMapsApiKey(),
        ), true);
    }

    protected function handleUpdateSubmission($id, $redirectUrl = '') {
        $id = (int) $id;
        $data = $this->sanitizeFormData($_POST);
        $errors = $this->getModel()->validate($data, $id);

        if (!empty($errors)) {
            return array(
                'success' => false,
                'errors' => $errors,
                'data' => $data,
            );
        }

        $updated = $this->getModel()->updateById($id, $data);
        if (!$updated) {
            return array(
                'success' => false,
                'errors' => array('general' => __('Failed to update location. Please try again.', 'wecoza-core')),
                'data' => $data,
            );
        }

        if (!empty($redirectUrl)) {
            if (!headers_sent()) {
                wp_safe_redirect($redirectUrl);
                exit;
            }
            // Fallback: signal caller to perform client-side redirect
            return array(
                'success' => true,
                'errors' => array(),
                'location' => $this->getModel()->getById($id) ?: $data,
                'redirect' => $redirectUrl,
            );
        }

        return array(
            'success' => true,
            'errors' => array(),
            'location' => $this->getModel()->getById($id) ?: $data,
        );
    }

    public function listLocationsShortcode($atts): string {
        if (!current_user_can('view_wecoza_clients')) {
            return '<p>' . esc_html__('You do not have permission to view locations.', 'wecoza-core') . '</p>';
        }

        $atts = shortcode_atts(array(
            'per_page' => AppConstants::SEARCH_RESULT_LIMIT,
            'show_search' => true,
            'edit_url' => '/edit-locations',
        ), $atts);

        $page = isset($_GET['location_page']) ? max(1, (int) $_GET['location_page']) : 1;
        $search = isset($_GET['location_search']) ? sanitize_text_field(wp_unslash($_GET['location_search'])) : '';

        $params = array(
            'search' => $search,
            'limit' => (int) $atts['per_page'],
            'offset' => ((int) $page - 1) * (int) $atts['per_page'],
        );

        $locations = $this->getModel()->getAll($params);
        $total = (int) $this->getModel()->count($params);
        $totalPages = max(1, (int) ceil($total / max(1, (int) $atts['per_page'])));

        // Build absolute edit URL using site_url (HTTPS)
        $editPath = isset($atts['edit_url']) ? trim((string) $atts['edit_url']) : '/edit-locations';
        if (stripos($editPath, 'http://') === 0 || stripos($editPath, 'https://') === 0) {
            $editBase = $editPath;
        } else {
            $editPath = '/' . ltrim($editPath, '/');
            $scheme = is_ssl() ? 'https' : 'http';
            $editBase = site_url($editPath, $scheme);
        }

        return wecoza_view('clients/display/locations-list', array(
            'locations' => is_array($locations) ? $locations : array(),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'edit_url' => $editBase,
            'atts' => $atts,
        ), true);
    }
}
