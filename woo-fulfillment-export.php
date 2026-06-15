<?php
/**
 * Plugin Name: Woo Fulfillment Export
 * Description: Export WooCommerce fulfillment orders to CSV/XLSX templates with flexible mappings, filters, variable products, and WCPA order meta.
 * Version: 1.1.0
 * Author: Admin
 * Requires Plugins: woocommerce
 * Text Domain: woo-fulfillment-export
 */

defined('ABSPATH') || exit;

define('WFE_VERSION', '1.1.0');
define('WFE_FILE', __FILE__);
define('WFE_PATH', plugin_dir_path(__FILE__));
define('WFE_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    if (strpos($class, 'WFE_') !== 0) {
        return;
    }

    $map = [
        'WFE_Admin_Menu' => 'includes/Admin/Menu.php',
        'WFE_Order_Query' => 'includes/Export/OrderQuery.php',
        'WFE_Order_Formatter' => 'includes/Export/OrderFormatter.php',
        'WFE_Csv_Template_Exporter' => 'includes/Export/CsvTemplateExporter.php',
        'WFE_Xlsx_Template_Exporter' => 'includes/Export/XlsxTemplateExporter.php',
        'WFE_Template_Resolver' => 'includes/Export/TemplateResolver.php',
        'WFE_Placeholder_Resolver' => 'includes/Export/PlaceholderResolver.php',
        'WFE_Template_Inspector' => 'includes/Export/TemplateInspector.php',
        'WFE_Settings' => 'includes/Data/Settings.php',
        'WFE_Template_Repository' => 'includes/Data/TemplateRepository.php',
        'WFE_Mapping_Repository' => 'includes/Data/MappingRepository.php',
        'WFE_Product_Helper' => 'includes/Helpers/ProductHelper.php',
        'WFE_WCPA_Helper' => 'includes/Helpers/WcpaHelper.php',
    ];

    if (!empty($map[$class])) {
        require_once WFE_PATH . $map[$class];
    }
});

final class WFE_Plugin
{
    public function boot(): void
    {
        add_action('admin_menu', ['WFE_Admin_Menu', 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('admin_post_wfe_upload_template', [$this, 'handle_upload_template']);
        add_action('admin_post_wfe_save_manual_template', [$this, 'handle_save_manual_template']);
        add_action('admin_post_wfe_delete_template', [$this, 'handle_delete_template']);
        add_action('admin_post_wfe_save_mapping', [$this, 'handle_save_mapping']);
        add_action('admin_post_wfe_export_orders', [$this, 'handle_export_orders']);
        add_action('admin_post_wfe_save_settings', [$this, 'handle_save_settings']);
    }

    public function enqueue_admin_assets($hook): void
    {
        if (strpos((string) $hook, 'wfe-') === false && strpos((string) wp_unslash($_GET['page'] ?? ''), 'wfe-') === false) {
            return;
        }

        wp_enqueue_style('wfe-admin', WFE_URL . 'assets/admin.css', [], WFE_VERSION);
    }

    private function assert_access(string $nonce_action): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'woo-fulfillment-export'));
        }

        check_admin_referer($nonce_action);
    }

    public function handle_upload_template(): void
    {
        $this->assert_access('wfe_upload_template');

        $name = sanitize_text_field(wp_unslash($_POST['template_name'] ?? ''));
        if ($name === '') {
            $name = 'Template ' . current_time('Y-m-d H:i:s');
        }

        if (empty($_FILES['template_file']['tmp_name']) || !is_uploaded_file($_FILES['template_file']['tmp_name'])) {
            $this->redirect_templates('missing_file');
            exit;
        }

        $file = $_FILES['template_file'];
        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect_templates('upload_error');
            exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true) || !$this->is_valid_template_upload($file, $ext)) {
            $this->redirect_templates('invalid_file');
            exit;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $target_dir = WFE_Template_Repository::templates_dir();
        if (!wp_mkdir_p($target_dir)) {
            $this->redirect_templates('mkdir_failed');
            exit;
        }

        $safe_filename = wp_unique_filename($target_dir, sanitize_file_name($file['name']));
        $target = trailingslashit($target_dir) . $safe_filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->redirect_templates('upload_failed');
            exit;
        }

        $repo = new WFE_Template_Repository();
        $repo->create_uploaded($name, $target, $safe_filename, $ext);

        wp_safe_redirect(admin_url('admin.php?page=wfe-templates&wfe_success=uploaded'));
        exit;
    }

    public function handle_save_manual_template(): void
    {
        $this->assert_access('wfe_save_manual_template');

        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['manual_template_name'] ?? ''));
        $file_type = sanitize_key(wp_unslash($_POST['manual_template_type'] ?? 'csv'));

        if ($name === '' || !in_array($file_type, ['csv', 'xlsx'], true)) {
            $this->redirect_templates('invalid_manual_template');
            exit;
        }

        $columns = $this->sanitize_manual_columns($_POST['manual_columns'] ?? []);
        if (!$columns) {
            $this->redirect_templates('empty_manual_columns');
            exit;
        }

        $template_repo = new WFE_Template_Repository();
        $mapping_repo = new WFE_Mapping_Repository();
        $saved_id = $template_repo->save_manual($template_id, $name, $file_type, $columns);

        $existing_mapping = $mapping_repo->find($saved_id) ?: [];
        $mapping_repo->save($saved_id, array_merge($existing_mapping, [
            'sheet_index' => 0,
            'header_row' => 1,
            'start_row' => 2,
            'row_mode' => $existing_mapping['row_mode'] ?? WFE_Settings::get('row_mode', 'item_per_row'),
            'one_row_per' => ($existing_mapping['row_mode'] ?? WFE_Settings::get('row_mode', 'item_per_row')) === 'order_per_row' ? 'order' : 'item',
            'columns' => WFE_Mapping_Repository::columns_from_manual_template($columns),
            'headers' => WFE_Mapping_Repository::headers_from_manual_template($columns),
            'defaults' => WFE_Mapping_Repository::defaults_from_manual_template($columns),
            'updated_at' => current_time('mysql'),
        ]));

        wp_safe_redirect(admin_url('admin.php?page=wfe-templates&edit_template_id=' . rawurlencode($saved_id) . '&wfe_success=manual_saved'));
        exit;
    }

    public function handle_delete_template(): void
    {
        $this->assert_access('wfe_delete_template');

        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
        $repo = new WFE_Template_Repository();
        $repo->delete($template_id);

        wp_safe_redirect(admin_url('admin.php?page=wfe-templates&wfe_success=deleted'));
        exit;
    }

    public function handle_save_mapping(): void
    {
        $this->assert_access('wfe_save_mapping');

        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));
        $template = (new WFE_Template_Repository())->find($template_id);
        if (!$template) {
            wp_die(esc_html__('Template not found.', 'woo-fulfillment-export'));
        }

        $sheet_index = max(0, absint($_POST['sheet_index'] ?? 0));
        $header_row = max(1, absint($_POST['header_row'] ?? 1));
        $start_row = max(1, absint($_POST['start_row'] ?? 2));
        $row_mode = sanitize_key(wp_unslash($_POST['row_mode'] ?? ($_POST['one_row_per'] ?? 'item_per_row')));
        if ($row_mode === 'item') {
            $row_mode = 'item_per_row';
        }
        if ($row_mode === 'order') {
            $row_mode = 'order_per_row';
        }
        if (!in_array($row_mode, ['item_per_row', 'order_per_row'], true)) {
            $row_mode = 'item_per_row';
        }

        $posted_columns = isset($_POST['column_mapping']) ? wp_unslash($_POST['column_mapping']) : [];
        $posted_headers = isset($_POST['column_header']) ? wp_unslash($_POST['column_header']) : [];
        $posted_defaults = isset($_POST['column_default']) ? wp_unslash($_POST['column_default']) : [];

        $columns = WFE_Mapping_Repository::sanitize_columns_array(is_array($posted_columns) ? $posted_columns : []);
        $headers = WFE_Mapping_Repository::sanitize_labels_array(is_array($posted_headers) ? $posted_headers : []);
        $defaults = WFE_Mapping_Repository::sanitize_columns_array(is_array($posted_defaults) ? $posted_defaults : []);

        $raw = '';
        if (!$columns && isset($_POST['columns_mapping'])) {
            $raw = sanitize_textarea_field(wp_unslash($_POST['columns_mapping']));
            $columns = WFE_Mapping_Repository::parse_columns_text($raw);
        } else {
            $raw = WFE_Mapping_Repository::columns_to_text($columns);
        }

        $repo = new WFE_Mapping_Repository();
        $repo->save($template_id, [
            'sheet_index' => $sheet_index,
            'header_row' => $header_row,
            'start_row' => $start_row,
            'row_mode' => $row_mode,
            'one_row_per' => $row_mode === 'order_per_row' ? 'order' : 'item',
            'columns' => $columns,
            'headers' => $headers,
            'defaults' => $defaults,
            'raw' => $raw,
            'updated_at' => current_time('mysql'),
        ]);

        $preview_order = absint($_POST['preview_order_id'] ?? 0);
        $redirect = admin_url('admin.php?page=wfe-mapping&template_id=' . rawurlencode($template_id) . '&sheet_index=' . $sheet_index . '&header_row=' . $header_row . '&wfe_success=mapping_saved');
        if ($preview_order > 0) {
            $redirect = add_query_arg('preview_order_id', $preview_order, $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_export_orders(): void
    {
        $this->assert_access('wfe_export_orders');

        $order_ids = array_map('absint', $_POST['order_ids'] ?? []);
        $order_ids = array_values(array_filter(array_unique($order_ids)));
        $template_id = sanitize_text_field(wp_unslash($_POST['template_id'] ?? ''));

        if ($template_id === '') {
            wp_die(esc_html__('Please select a template.', 'woo-fulfillment-export'));
        }

        $template_repo = new WFE_Template_Repository();
        $mapping_repo = new WFE_Mapping_Repository();

        $template = $template_repo->find($template_id);
        $mapping = $mapping_repo->find($template_id);

        if (!$template) {
            wp_die(esc_html__('Template not found.', 'woo-fulfillment-export'));
        }

        if (($template['source'] ?? 'upload') === 'upload' && (empty($template['file_path']) || !WFE_Template_Repository::is_template_path($template['file_path']) || !file_exists($template['file_path']))) {
            wp_die(esc_html__('Template file not found.', 'woo-fulfillment-export'));
        }

        if (!$mapping && ($template['source'] ?? '') === 'manual') {
            $mapping = WFE_Mapping_Repository::mapping_from_manual_template($template);
        }

        if (!$mapping || !WFE_Mapping_Repository::export_columns($template, $mapping)) {
            wp_die(esc_html__('Template mapping is empty. Please configure mapping first.', 'woo-fulfillment-export'));
        }

        if (!$order_ids) {
            $settings = WFE_Settings::all();
            $query = new WFE_Order_Query();
            $collection = $query->collect_order_ids_for_export($this->sanitize_export_filters($_POST), absint($settings['export_limit'] ?? 500));
            $order_ids = $collection['order_ids'];
        }

        if (!$order_ids) {
            wp_die(esc_html__('No orders matched the export filters.', 'woo-fulfillment-export'));
        }

        $formatter = new WFE_Order_Formatter();
        $rows = $formatter->format_orders($order_ids, $mapping['row_mode'] ?? ($mapping['one_row_per'] ?? 'item_per_row'));

        if (($template['file_type'] ?? 'xlsx') === 'csv') {
            (new WFE_Csv_Template_Exporter())->download($template, $mapping, $rows);
        }

        (new WFE_Xlsx_Template_Exporter())->download($template, $mapping, $rows);
    }

    public function handle_save_settings(): void
    {
        $this->assert_access('wfe_save_settings');

        $statuses = isset($_POST['default_statuses']) ? (array) wp_unslash($_POST['default_statuses']) : [];
        $settings = [
            'default_statuses' => WFE_Order_Query::sanitize_statuses($statuses),
            'row_mode' => sanitize_key(wp_unslash($_POST['row_mode'] ?? 'item_per_row')),
            'export_limit' => max(1, min(5000, absint($_POST['export_limit'] ?? 500))),
            'scan_limit' => max(100, min(10000, absint($_POST['scan_limit'] ?? 2000))),
        ];

        if (!in_array($settings['row_mode'], ['item_per_row', 'order_per_row'], true)) {
            $settings['row_mode'] = 'item_per_row';
        }

        WFE_Settings::save($settings);

        wp_safe_redirect(admin_url('admin.php?page=wfe-settings&wfe_success=saved'));
        exit;
    }

    private function redirect_templates(string $error): void
    {
        wp_safe_redirect(admin_url('admin.php?page=wfe-templates&wfe_error=' . rawurlencode($error)));
    }

    private function is_valid_template_upload(array $file, string $ext): bool
    {
        $allowed_mimes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (($checked['ext'] ?? '') !== $ext) {
            return false;
        }

        $detected = '';
        if (function_exists('mime_content_type')) {
            $detected = (string) mime_content_type($file['tmp_name']);
        }

        if ($ext === 'csv') {
            return in_array($detected, ['', 'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)
                || in_array(($checked['type'] ?? ''), ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true);
        }

        return in_array($detected, ['', 'application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)
            || ($checked['type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    private function sanitize_manual_columns($raw_columns): array
    {
        $raw_columns = is_array($raw_columns) ? wp_unslash($raw_columns) : [];
        $headers = isset($raw_columns['header']) && is_array($raw_columns['header']) ? $raw_columns['header'] : [];
        $mappings = isset($raw_columns['mapping']) && is_array($raw_columns['mapping']) ? $raw_columns['mapping'] : [];
        $defaults = isset($raw_columns['default']) && is_array($raw_columns['default']) ? $raw_columns['default'] : [];
        $columns = [];
        $position = 0;

        foreach ($headers as $index => $header) {
            $header = sanitize_text_field($header);
            $mapping = sanitize_text_field($mappings[$index] ?? '');
            $default = sanitize_text_field($defaults[$index] ?? '');

            if ($header === '' && $mapping === '' && $default === '') {
                continue;
            }

            if ($header === '') {
                $header = sprintf(__('Column %s', 'woo-fulfillment-export'), WFE_Mapping_Repository::column_letter($position + 1));
            }

            $columns[] = [
                'column' => WFE_Mapping_Repository::column_letter($position + 1),
                'header' => $header,
                'mapping' => $mapping,
                'default' => $default,
            ];
            $position++;
        }

        return $columns;
    }

    private function sanitize_export_filters(array $source): array
    {
        return [
            'status' => WFE_Order_Query::sanitize_statuses(isset($source['status']) ? (array) wp_unslash($source['status']) : []),
            'date_from' => sanitize_text_field(wp_unslash($source['date_from'] ?? '')),
            'date_to' => sanitize_text_field(wp_unslash($source['date_to'] ?? '')),
            'customer' => sanitize_text_field(wp_unslash($source['customer'] ?? '')),
            'product' => sanitize_text_field(wp_unslash($source['product'] ?? '')),
            'sku' => sanitize_text_field(wp_unslash($source['sku'] ?? '')),
            'category' => sanitize_text_field(wp_unslash($source['category'] ?? '')),
        ];
    }
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>Woo Fulfillment Export requires WooCommerce.</p></div>';
        });
        return;
    }

    (new WFE_Plugin())->boot();
});
