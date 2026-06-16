<?php

defined('ABSPATH') || exit;

final class WFE_Admin_Menu
{
    public static function register(): void
    {
        add_menu_page(
            __('Fulfillment Export', 'woo-fulfillment-export'),
            __('Fulfillment Export', 'woo-fulfillment-export'),
            'manage_woocommerce',
            'wfe-orders',
            [__CLASS__, 'orders_page'],
            'dashicons-media-spreadsheet',
            56
        );

        add_submenu_page('wfe-orders', __('Orders', 'woo-fulfillment-export'), __('Orders', 'woo-fulfillment-export'), 'manage_woocommerce', 'wfe-orders', [__CLASS__, 'orders_page']);
        add_submenu_page('wfe-orders', __('Templates', 'woo-fulfillment-export'), __('Templates', 'woo-fulfillment-export'), 'manage_woocommerce', 'wfe-templates', [__CLASS__, 'templates_page']);
        add_submenu_page('wfe-orders', __('Mapping', 'woo-fulfillment-export'), __('Mapping', 'woo-fulfillment-export'), 'manage_woocommerce', 'wfe-mapping', [__CLASS__, 'mapping_page']);
<<<<<<< HEAD
=======
        add_submenu_page('wfe-orders', __('API Connections', 'woo-fulfillment-export'), __('API Connections', 'woo-fulfillment-export'), 'manage_woocommerce', 'wfe-api-connections', [__CLASS__, 'api_connections_page']);
>>>>>>> 33573ee (first commit)
        add_submenu_page('wfe-orders', __('Settings', 'woo-fulfillment-export'), __('Settings', 'woo-fulfillment-export'), 'manage_woocommerce', 'wfe-settings', [__CLASS__, 'settings_page']);
    }

    public static function orders_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        $query = new WFE_Order_Query();
        $settings = WFE_Settings::all();
<<<<<<< HEAD
        $status = isset($_GET['status']) ? WFE_Order_Query::sanitize_statuses((array) wp_unslash($_GET['status'])) : $settings['default_statuses'];
        if (!$status) {
            $status = $settings['default_statuses'];
        }
        $page = max(1, absint($_GET['paged'] ?? 1));
=======
        $available_statuses = WFE_Order_Query::fulfillment_status_options();
        $allowed_statuses = array_map(static function ($key) { return str_replace('wc-', '', (string) $key); }, array_keys($available_statuses));
        $status = isset($_GET['status']) ? WFE_Order_Query::sanitize_statuses((array) wp_unslash($_GET['status'])) : $settings['default_statuses'];
        $status = array_values(array_intersect($status, $allowed_statuses));
        if (!$status) {
            $status = $allowed_statuses;
        }
        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = max(5, min(200, absint($_GET['per_page'] ?? ($settings['orders_per_page'] ?? 30))));
>>>>>>> 33573ee (first commit)
        $filters = [
            'status' => $status,
            'date_from' => sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')),
            'date_to' => sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')),
<<<<<<< HEAD
=======
            'order_query' => sanitize_text_field(wp_unslash($_GET['order_query'] ?? '')),
>>>>>>> 33573ee (first commit)
            'customer' => sanitize_text_field(wp_unslash($_GET['customer'] ?? '')),
            'product' => sanitize_text_field(wp_unslash($_GET['product'] ?? '')),
            'sku' => sanitize_text_field(wp_unslash($_GET['sku'] ?? '')),
            'category' => sanitize_text_field(wp_unslash($_GET['category'] ?? '')),
<<<<<<< HEAD
            'limit' => 30,
=======
            'limit' => $per_page,
>>>>>>> 33573ee (first commit)
            'page' => $page,
        ];
        $result = $query->get_orders([
            'status' => $filters['status'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
<<<<<<< HEAD
=======
            'order_query' => $filters['order_query'],
>>>>>>> 33573ee (first commit)
            'customer' => $filters['customer'],
            'product' => $filters['product'],
            'sku' => $filters['sku'],
            'category' => $filters['category'],
            'limit' => $filters['limit'],
            'page' => $filters['page'],
        ]);

        $templates = (new WFE_Template_Repository())->all();
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        $categories = is_wp_error($categories) ? [] : $categories;
        include WFE_PATH . 'includes/Admin/views/orders.php';
    }

    public static function templates_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        $templates = (new WFE_Template_Repository())->all();
        $edit_template_id = sanitize_text_field(wp_unslash($_GET['edit_template_id'] ?? ''));
        $edit_template = $edit_template_id !== '' ? (new WFE_Template_Repository())->find($edit_template_id) : null;
        if (!$edit_template || ($edit_template['source'] ?? '') !== 'manual') {
            $edit_template = null;
        }
        include WFE_PATH . 'includes/Admin/views/templates.php';
    }

    public static function mapping_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        $template_repo = new WFE_Template_Repository();
        $mapping_repo = new WFE_Mapping_Repository();
        $templates = $template_repo->all();
        $selected_template_id = sanitize_text_field(wp_unslash($_GET['template_id'] ?? ''));
        if ($selected_template_id === '' && $templates) {
            $keys = array_keys($templates);
            $selected_template_id = (string) $keys[0];
        }
        $selected_template = $selected_template_id !== '' ? $template_repo->find($selected_template_id) : null;
        $mapping = $selected_template_id !== '' ? $mapping_repo->find($selected_template_id) : null;
        if (!$mapping && $selected_template && ($selected_template['source'] ?? '') === 'manual') {
            $mapping = WFE_Mapping_Repository::mapping_from_manual_template($selected_template);
        }

        $sheet_index = max(0, absint($_GET['sheet_index'] ?? ($mapping['sheet_index'] ?? 0)));
        $header_row = max(1, absint($_GET['header_row'] ?? ($mapping['header_row'] ?? 1)));
        $inspection = $selected_template ? (new WFE_Template_Inspector())->inspect($selected_template, $sheet_index, $header_row) : ['sheets' => [], 'headers' => [], 'error' => ''];
        $placeholder_groups = WFE_Placeholder_Resolver::groups();
<<<<<<< HEAD
=======
        $api_connections = (new WFE_Api_Connection_Repository())->all();
>>>>>>> 33573ee (first commit)
        $preview_order_id = absint($_GET['preview_order_id'] ?? 0);
        $preview_row = null;
        $preview_wcpa_fields = [];
        if ($preview_order_id > 0 && $mapping) {
            $preview_row = (new WFE_Order_Formatter())->preview_order($preview_order_id, $mapping['row_mode'] ?? 'item_per_row');
            $preview_order = wc_get_order($preview_order_id);
            if ($preview_order instanceof WC_Order) {
                foreach ($preview_order->get_items('line_item') as $item) {
                    if ($item instanceof WC_Order_Item_Product) {
                        $preview_wcpa_fields = array_merge($preview_wcpa_fields, WFE_WCPA_Helper::extract_wcpa_fields($item));
                    }
                }
            }
        }

        include WFE_PATH . 'includes/Admin/views/mapping.php';
    }

<<<<<<< HEAD
=======
    public static function api_connections_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        $repo = new WFE_Api_Connection_Repository();
        $connections = $repo->all();
        $edit_key = WFE_Api_Connection_Repository::sanitize_key(wp_unslash($_GET['edit_key'] ?? ''));
        $edit_connection = $edit_key !== '' ? $repo->find($edit_key) : null;
        $test_result = get_transient('wfe_api_test_' . get_current_user_id());
        if (is_array($test_result)) {
            delete_transient('wfe_api_test_' . get_current_user_id());
            if (!empty($test_result['connection']) && is_array($test_result['connection'])) {
                $edit_connection = $test_result['connection'];
                $edit_key = (string) ($edit_connection['key'] ?? $edit_key);
            }
        }

        include WFE_PATH . 'includes/Admin/views/api-connections.php';
    }

>>>>>>> 33573ee (first commit)
    public static function settings_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        $settings = WFE_Settings::all();
        include WFE_PATH . 'includes/Admin/views/settings.php';
    }

    public static function tabs(string $active): void
    {
        $tabs = [
            'wfe-orders' => __('Orders', 'woo-fulfillment-export'),
            'wfe-templates' => __('Templates', 'woo-fulfillment-export'),
            'wfe-mapping' => __('Mapping', 'woo-fulfillment-export'),
<<<<<<< HEAD
=======
            'wfe-api-connections' => __('API Connections', 'woo-fulfillment-export'),
>>>>>>> 33573ee (first commit)
            'wfe-settings' => __('Settings', 'woo-fulfillment-export'),
        ];

        echo '<nav class="nav-tab-wrapper wfe-tabs">';
        foreach ($tabs as $page => $label) {
            $class = $active === $page ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(admin_url('admin.php?page=' . $page)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    public static function notice(): void
    {
        $success = sanitize_key(wp_unslash($_GET['wfe_success'] ?? ''));
        $error = sanitize_key(wp_unslash($_GET['wfe_error'] ?? ''));

        $success_messages = [
            'uploaded' => __('Template uploaded.', 'woo-fulfillment-export'),
            'manual_saved' => __('Manual template saved.', 'woo-fulfillment-export'),
            'deleted' => __('Template deleted.', 'woo-fulfillment-export'),
            'saved' => __('Settings saved.', 'woo-fulfillment-export'),
            'mapping_saved' => __('Mapping saved.', 'woo-fulfillment-export'),
<<<<<<< HEAD
=======
            'api_saved' => __('API connection saved.', 'woo-fulfillment-export'),
            'api_deleted' => __('API connection deleted.', 'woo-fulfillment-export'),
            'marked_fulfillment' => __('Order marked as Fulfillment.', 'woo-fulfillment-export'),
            'marked_processing' => __('Order moved back to Processing.', 'woo-fulfillment-export'),
>>>>>>> 33573ee (first commit)
        ];
        $error_messages = [
            'missing_file' => __('Please choose a CSV or XLSX file.', 'woo-fulfillment-export'),
            'upload_error' => __('The upload failed before WordPress could read the file.', 'woo-fulfillment-export'),
            'invalid_file' => __('Only valid CSV and XLSX files are allowed.', 'woo-fulfillment-export'),
            'mkdir_failed' => __('Could not create the template upload directory.', 'woo-fulfillment-export'),
            'upload_failed' => __('Could not save the uploaded template.', 'woo-fulfillment-export'),
            'invalid_manual_template' => __('Manual template name and type are required.', 'woo-fulfillment-export'),
            'empty_manual_columns' => __('Add at least one manual template column.', 'woo-fulfillment-export'),
<<<<<<< HEAD
=======
            'order_not_found' => __('Order not found.', 'woo-fulfillment-export'),
>>>>>>> 33573ee (first commit)
        ];

        if ($success !== '' && isset($success_messages[$success])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_messages[$success]) . '</p></div>';
        }
        if ($error !== '' && isset($error_messages[$error])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_messages[$error]) . '</p></div>';
        }
    }
}
