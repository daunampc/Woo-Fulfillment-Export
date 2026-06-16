<?php
/**
 * Plugin Name: Woo Fulfillment Export
 * Description: Export WooCommerce fulfillment orders to CSV/XLSX templates with flexible mappings, filters, variable products, and WCPA order meta.
 * Version: 1.3.3
 * Author: Admin
 * Requires Plugins: woocommerce
 * Text Domain: woo-fulfillment-export
 */

defined('ABSPATH') || exit;

define('WFE_VERSION', '1.3.3');
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
        'WFE_Dynamic_Api_Resolver' => 'includes/Export/DynamicApiResolver.php',
        'WFE_Api_Client' => 'includes/Api/ApiClient.php',
        'WFE_Api_Connection_Repository' => 'includes/Api/ApiConnectionRepository.php',
        'WFE_Array_Helper' => 'includes/Helpers/ArrayHelper.php',
        'WFE_Settings' => 'includes/Data/Settings.php',
        'WFE_Template_Repository' => 'includes/Data/TemplateRepository.php',
        'WFE_Mapping_Repository' => 'includes/Data/MappingRepository.php',
        'WFE_Product_Helper' => 'includes/Helpers/ProductHelper.php',
        'WFE_WCPA_Helper' => 'includes/Helpers/WcpaHelper.php',
        'WFE_GitHub_Updater' => 'includes/Updater/GitHubUpdater.php',
    ];

    if (!empty($map[$class])) {
        require_once WFE_PATH . $map[$class];
    }
});

final class WFE_Plugin
{
    public function boot(): void
    {
        add_action('init', [$this, 'register_fulfillment_order_status']);
        add_filter('wc_order_statuses', [$this, 'add_fulfillment_order_status']);
        add_action('admin_menu', ['WFE_Admin_Menu', 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        add_action('admin_post_wfe_upload_template', [$this, 'handle_upload_template']);
        add_action('admin_post_wfe_save_manual_template', [$this, 'handle_save_manual_template']);
        add_action('admin_post_wfe_delete_template', [$this, 'handle_delete_template']);
        add_action('admin_post_wfe_save_mapping', [$this, 'handle_save_mapping']);
        add_action('admin_post_wfe_export_orders', [$this, 'handle_export_orders']);
        add_action('admin_post_wfe_download_export', [$this, 'handle_download_export']);
        add_action('wp_ajax_wfe_start_export', [$this, 'ajax_start_export']);
        add_action('wp_ajax_wfe_process_export', [$this, 'ajax_process_export']);
        add_action('wp_ajax_wfe_finish_export', [$this, 'ajax_finish_export']);
        add_action('admin_post_wfe_mark_order_fulfillment', [$this, 'handle_mark_order_fulfillment']);
        add_action('admin_post_wfe_bulk_update_orders', [$this, 'handle_bulk_update_orders']);
        add_action('admin_post_wfe_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_wfe_save_api_connection', [$this, 'handle_save_api_connection']);
        add_action('admin_post_wfe_delete_api_connection', [$this, 'handle_delete_api_connection']);
        add_action('admin_post_wfe_test_api_connection', [$this, 'handle_test_api_connection']);

        if (is_admin()) {
            new WFE_GitHub_Updater();
        }
    }


    public function register_fulfillment_order_status(): void
    {
        register_post_status('wc-fulfillment', [
            'label' => _x('Fulfillment', 'Order status', 'woo-fulfillment-export'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Fulfillment <span class="count">(%s)</span>', 'Fulfillment <span class="count">(%s)</span>', 'woo-fulfillment-export'),
        ]);
    }

    public function add_fulfillment_order_status(array $statuses): array
    {
        $new_statuses = [];
        foreach ($statuses as $key => $label) {
            $new_statuses[$key] = $label;
            if ($key === 'wc-processing') {
                $new_statuses['wc-fulfillment'] = __('Fulfillment', 'woo-fulfillment-export');
            }
        }

        if (!isset($new_statuses['wc-fulfillment'])) {
            $new_statuses['wc-fulfillment'] = __('Fulfillment', 'woo-fulfillment-export');
        }

        return $new_statuses;
    }

    public function enqueue_admin_assets($hook): void
    {
        if (strpos((string) $hook, 'wfe-') === false && strpos((string) wp_unslash($_GET['page'] ?? ''), 'wfe-') === false) {
            return;
        }

        wp_enqueue_style('wfe-admin', WFE_URL . 'assets/admin.css', [], WFE_VERSION);
        wp_enqueue_script('wfe-admin', WFE_URL . 'assets/admin.js', [], WFE_VERSION, true);
        wp_localize_script('wfe-admin', 'WFE_EXPORT', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wfe_ajax_export'),
            'processingText' => __('Exporting...', 'woo-fulfillment-export'),
            'startingText' => __('Preparing export...', 'woo-fulfillment-export'),
            'doneText' => __('Export ready. Downloading...', 'woo-fulfillment-export'),
            'errorText' => __('Export failed. Please try again.', 'woo-fulfillment-export'),
            'bulkNoSelectionText' => __('Please select at least one order first.', 'woo-fulfillment-export'),
        ]);
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
        $this->mark_orders_fulfillment($order_ids);

        if (($template['file_type'] ?? 'xlsx') === 'csv') {
            (new WFE_Csv_Template_Exporter())->download($template, $mapping, $rows);
        }

        (new WFE_Xlsx_Template_Exporter())->download($template, $mapping, $rows);
    }

    public function ajax_start_export(): void
    {
        $this->assert_ajax_access();

        $payload = isset($_POST['payload']) && is_array($_POST['payload']) ? wp_unslash($_POST['payload']) : $_POST;
        $template_id = sanitize_text_field($payload['template_id'] ?? '');
        $order_ids = array_map('absint', isset($payload['order_ids']) && is_array($payload['order_ids']) ? $payload['order_ids'] : []);
        $order_ids = array_values(array_filter(array_unique($order_ids)));

        $template_repo = new WFE_Template_Repository();
        $mapping_repo = new WFE_Mapping_Repository();
        $template = $template_repo->find($template_id);
        $mapping = $mapping_repo->find($template_id);

        if (!$template) {
            wp_send_json_error(['message' => __('Template not found.', 'woo-fulfillment-export')], 400);
        }
        if (($template['source'] ?? 'upload') === 'upload' && (empty($template['file_path']) || !WFE_Template_Repository::is_template_path($template['file_path']) || !file_exists($template['file_path']))) {
            wp_send_json_error(['message' => __('Template file not found.', 'woo-fulfillment-export')], 400);
        }
        if (!$mapping && ($template['source'] ?? '') === 'manual') {
            $mapping = WFE_Mapping_Repository::mapping_from_manual_template($template);
        }
        if (!$mapping || !WFE_Mapping_Repository::export_columns($template, $mapping)) {
            wp_send_json_error(['message' => __('Template mapping is empty. Please configure mapping first.', 'woo-fulfillment-export')], 400);
        }

        if (!$order_ids) {
            $settings = WFE_Settings::all();
            $query = new WFE_Order_Query();
            $collection = $query->collect_order_ids_for_export($this->sanitize_export_filters($payload), absint($settings['export_limit'] ?? 500));
            $order_ids = $collection['order_ids'];
        }

        if (!$order_ids) {
            wp_send_json_error(['message' => __('No orders matched the export filters.', 'woo-fulfillment-export')], 400);
        }

        $job_id = wp_generate_uuid4();
        $job = [
            'id' => $job_id,
            'template_id' => $template_id,
            'order_ids' => $order_ids,
            'rows' => [],
            'processed' => 0,
            'total' => count($order_ids),
            'created_at' => time(),
            'file_path' => '',
            'file_name' => '',
        ];
        set_transient('wfe_export_job_' . $job_id, $job, HOUR_IN_SECONDS);

        wp_send_json_success([
            'job_id' => $job_id,
            'total' => count($order_ids),
            'chunk_size' => max(1, absint(WFE_Settings::get('ajax_chunk_size', 20))),
        ]);
    }

    public function ajax_process_export(): void
    {
        $this->assert_ajax_access();

        $job_id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''));
        $offset = max(0, absint($_POST['offset'] ?? 0));
        $chunk_size = max(1, min(100, absint(WFE_Settings::get('ajax_chunk_size', 20))));
        $job = $this->get_export_job($job_id);
        if (!$job) {
            wp_send_json_error(['message' => __('Export job expired. Please start again.', 'woo-fulfillment-export')], 404);
        }

        $template = (new WFE_Template_Repository())->find((string) $job['template_id']);
        $mapping = (new WFE_Mapping_Repository())->find((string) $job['template_id']);
        if (!$template) {
            wp_send_json_error(['message' => __('Template not found.', 'woo-fulfillment-export')], 400);
        }
        if (!$mapping && ($template['source'] ?? '') === 'manual') {
            $mapping = WFE_Mapping_Repository::mapping_from_manual_template($template);
        }

        $chunk_ids = array_slice((array) $job['order_ids'], $offset, $chunk_size);
        if ($chunk_ids) {
            $formatter = new WFE_Order_Formatter();
            $rows = $formatter->format_orders($chunk_ids, $mapping['row_mode'] ?? ($mapping['one_row_per'] ?? 'item_per_row'));
            $job['rows'] = array_merge((array) $job['rows'], $rows);
            $this->mark_orders_fulfillment($chunk_ids);
        }
        $processed = min((int) $job['total'], $offset + count($chunk_ids));
        $job['processed'] = $processed;
        set_transient('wfe_export_job_' . $job_id, $job, HOUR_IN_SECONDS);

        wp_send_json_success([
            'processed' => $processed,
            'total' => (int) $job['total'],
            'done' => $processed >= (int) $job['total'],
        ]);
    }

    public function ajax_finish_export(): void
    {
        $this->assert_ajax_access();

        $job_id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''));
        $job = $this->get_export_job($job_id);
        if (!$job) {
            wp_send_json_error(['message' => __('Export job expired. Please start again.', 'woo-fulfillment-export')], 404);
        }

        $template = (new WFE_Template_Repository())->find((string) $job['template_id']);
        $mapping = (new WFE_Mapping_Repository())->find((string) $job['template_id']);
        if (!$template) {
            wp_send_json_error(['message' => __('Template not found.', 'woo-fulfillment-export')], 400);
        }
        if (!$mapping && ($template['source'] ?? '') === 'manual') {
            $mapping = WFE_Mapping_Repository::mapping_from_manual_template($template);
        }

        $dir = trailingslashit(WFE_Template_Repository::exports_dir());
        if (!wp_mkdir_p($dir)) {
            wp_send_json_error(['message' => __('Could not create export directory.', 'woo-fulfillment-export')], 500);
        }
        $ext = ($template['file_type'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $filename = sanitize_file_name('fulfillment-orders-' . current_time('Y-m-d-His') . '-' . substr(md5($job_id), 0, 8) . '.' . $ext);
        $path = $dir . $filename;

        if ($ext === 'csv') {
            (new WFE_Csv_Template_Exporter())->save_file($template, $mapping, (array) $job['rows'], $path);
        } else {
            (new WFE_Xlsx_Template_Exporter())->save_file($template, $mapping, (array) $job['rows'], $path);
        }

        $job['file_path'] = $path;
        $job['file_name'] = $filename;
        set_transient('wfe_export_job_' . $job_id, $job, HOUR_IN_SECONDS);

        $download_url = wp_nonce_url(add_query_arg([
            'action' => 'wfe_download_export',
            'job_id' => $job_id,
        ], admin_url('admin-post.php')), 'wfe_download_export_' . $job_id);

        wp_send_json_success([
            'download_url' => $download_url,
            'file_name' => $filename,
        ]);
    }

    public function handle_download_export(): void
    {
        $job_id = sanitize_text_field(wp_unslash($_GET['job_id'] ?? ''));
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to download this export.', 'woo-fulfillment-export'));
        }
        check_admin_referer('wfe_download_export_' . $job_id);

        $job = $this->get_export_job($job_id);
        $path = is_array($job) ? (string) ($job['file_path'] ?? '') : '';
        $filename = is_array($job) ? (string) ($job['file_name'] ?? basename($path)) : '';
        if ($path === '' || !file_exists($path) || !WFE_Template_Repository::is_export_path($path)) {
            wp_die(esc_html__('Export file not found or expired.', 'woo-fulfillment-export'));
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        nocache_headers();
        header('Content-Type: ' . ($ext === 'csv' ? 'text/csv; charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        delete_transient('wfe_export_job_' . $job_id);
        exit;
    }


    public function handle_mark_order_fulfillment(): void
    {
        $this->assert_access('wfe_mark_order_fulfillment');

        $order_id = absint($_REQUEST['order_id'] ?? 0);
        $target_status = sanitize_key(wp_unslash($_REQUEST['target_status'] ?? 'fulfillment'));
        if (!in_array($target_status, ['fulfillment', 'processing'], true)) {
            $target_status = 'fulfillment';
        }

        $order = $order_id ? wc_get_order($order_id) : null;
        if (!$order instanceof WC_Order) {
            wp_safe_redirect(admin_url('admin.php?page=wfe-orders&wfe_error=order_not_found'));
            exit;
        }

        $note = $target_status === 'fulfillment'
            ? __('Marked as Fulfillment from Fulfillment Export.', 'woo-fulfillment-export')
            : __('Moved back to Processing from Fulfillment Export.', 'woo-fulfillment-export');

        $order->update_status($target_status, $note, true);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=wfe-orders');
        }
        $redirect = add_query_arg('wfe_success', $target_status === 'fulfillment' ? 'marked_fulfillment' : 'marked_processing', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_bulk_update_orders(): void
    {
        $this->assert_access('wfe_export_orders');

        $order_ids = array_map('absint', $_POST['order_ids'] ?? []);
        $order_ids = array_values(array_filter(array_unique($order_ids)));
        $target_status = sanitize_key(wp_unslash($_POST['target_status'] ?? 'fulfillment'));
        if (!in_array($target_status, ['fulfillment', 'processing'], true)) {
            $target_status = 'fulfillment';
        }

        if (!$order_ids) {
            $redirect = wp_get_referer() ?: admin_url('admin.php?page=wfe-orders');
            wp_safe_redirect(add_query_arg('wfe_error', 'no_orders_selected', $redirect));
            exit;
        }

        $note = $target_status === 'fulfillment'
            ? __('Bulk marked as Fulfillment from Fulfillment Export.', 'woo-fulfillment-export')
            : __('Bulk moved back to Processing from Fulfillment Export.', 'woo-fulfillment-export');

        $updated = 0;
        foreach ($order_ids as $order_id) {
            $order = $order_id ? wc_get_order($order_id) : null;
            if (!$order instanceof WC_Order) {
                continue;
            }
            if ($order->get_status() === $target_status) {
                continue;
            }
            $order->update_status($target_status, $note, true);
            $updated++;
        }

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=wfe-orders');
        $redirect = add_query_arg([
            'wfe_success' => $target_status === 'fulfillment' ? 'bulk_fulfillment' : 'bulk_processing',
            'wfe_count' => $updated,
        ], $redirect);
        wp_safe_redirect($redirect);
        exit;
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
            'orders_per_page' => max(5, min(200, absint($_POST['orders_per_page'] ?? 30))),
            'ajax_chunk_size' => max(1, min(100, absint($_POST['ajax_chunk_size'] ?? 20))),
            'github_branch' => sanitize_text_field(wp_unslash($_POST['github_branch'] ?? 'main')),
            'github_token' => sanitize_text_field(wp_unslash($_POST['github_token'] ?? '')),
        ];

        if (!in_array($settings['row_mode'], ['item_per_row', 'order_per_row'], true)) {
            $settings['row_mode'] = 'item_per_row';
        }

        WFE_Settings::save($settings);

        wp_safe_redirect(admin_url('admin.php?page=wfe-settings&wfe_success=saved'));
        exit;
    }

    public function handle_save_api_connection(): void
    {
        $this->assert_access('wfe_api_connection');

        if (!empty($_POST['test_api'])) {
            $this->test_posted_api_connection();
            exit;
        }

        $repo = new WFE_Api_Connection_Repository();
        $previous_key = WFE_Api_Connection_Repository::sanitize_key(wp_unslash($_POST['previous_key'] ?? ''));
        $key = $repo->save($this->posted_api_connection(), $previous_key);

        wp_safe_redirect(admin_url('admin.php?page=wfe-api-connections&edit_key=' . rawurlencode($key) . '&wfe_success=api_saved'));
        exit;
    }

    public function handle_delete_api_connection(): void
    {
        $this->assert_access('wfe_delete_api_connection');

        $key = WFE_Api_Connection_Repository::sanitize_key(wp_unslash($_POST['connection_key'] ?? ''));
        (new WFE_Api_Connection_Repository())->delete($key);

        wp_safe_redirect(admin_url('admin.php?page=wfe-api-connections&wfe_success=api_deleted'));
        exit;
    }

    public function handle_test_api_connection(): void
    {
        $this->assert_access('wfe_api_connection');
        $this->test_posted_api_connection();
        exit;
    }

    private function test_posted_api_connection(): void
    {
        $connection = $this->posted_api_connection();
        $connection['enabled'] = 1;
        $query = sanitize_text_field(wp_unslash($_POST['test_query'] ?? ''));
        $result = (new WFE_Api_Client())->request($connection, $query);

        set_transient('wfe_api_test_' . get_current_user_id(), [
            'result' => $result,
            'query' => $query,
            'connection' => $connection,
        ], MINUTE_IN_SECONDS * 5);

        wp_safe_redirect(admin_url('admin.php?page=wfe-api-connections&edit_key=' . rawurlencode((string) ($connection['key'] ?? '')) . '&wfe_test=1'));
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


    private function assert_ajax_access(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to export orders.', 'woo-fulfillment-export')], 403);
        }
        check_ajax_referer('wfe_ajax_export', 'nonce');
    }

    private function get_export_job(string $job_id): ?array
    {
        if ($job_id === '' || !preg_match('/^[a-f0-9\-]{20,60}$/i', $job_id)) {
            return null;
        }
        $job = get_transient('wfe_export_job_' . $job_id);
        return is_array($job) ? $job : null;
    }

    private function mark_orders_fulfillment(array $order_ids): void
    {
        $order_ids = array_values(array_unique(array_map('absint', $order_ids)));
        foreach ($order_ids as $order_id) {
            $order = $order_id ? wc_get_order($order_id) : null;
            if (!$order instanceof WC_Order) {
                continue;
            }
            if ($order->get_status() === 'fulfillment') {
                continue;
            }
            $order->update_status('fulfillment', __('Exported by Fulfillment Export and moved to Fulfillment.', 'woo-fulfillment-export'), true);
        }
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
            'order_query' => sanitize_text_field(wp_unslash($source['order_query'] ?? '')),
            'customer' => sanitize_text_field(wp_unslash($source['customer'] ?? '')),
            'product' => sanitize_text_field(wp_unslash($source['product'] ?? '')),
            'sku' => sanitize_text_field(wp_unslash($source['sku'] ?? '')),
            'category' => sanitize_text_field(wp_unslash($source['category'] ?? '')),
        ];
    }

    private function posted_api_connection(): array
    {
        $headers = isset($_POST['headers']) ? wp_unslash($_POST['headers']) : [];
        $previous_key = WFE_Api_Connection_Repository::sanitize_key(wp_unslash($_POST['previous_key'] ?? ''));
        $existing = $previous_key !== '' ? (new WFE_Api_Connection_Repository())->find($previous_key) : null;
        if (is_array($existing) && is_array($headers)) {
            $headers = $this->preserve_blank_api_header_values($headers, (array) ($existing['headers'] ?? []));
        }

        return [
            'name' => sanitize_text_field(wp_unslash($_POST['connection_name'] ?? '')),
            'key' => WFE_Api_Connection_Repository::sanitize_key(wp_unslash($_POST['connection_key'] ?? '')),
            'base_url' => esc_url_raw(wp_unslash($_POST['base_url'] ?? '')),
            'method' => sanitize_text_field(wp_unslash($_POST['method'] ?? 'GET')),
            'default_params' => sanitize_textarea_field(wp_unslash($_POST['default_params'] ?? '')),
            'dynamic_param' => sanitize_key(wp_unslash($_POST['dynamic_param'] ?? 'query')),
            'response_path' => WFE_Api_Connection_Repository::sanitize_response_path(wp_unslash($_POST['response_path'] ?? 'data.0.URL')),
            'timeout' => absint($_POST['timeout'] ?? 15),
            'enabled' => !empty($_POST['enabled']) ? 1 : 0,
            'cache_enabled' => !empty($_POST['cache_enabled']) ? 1 : 0,
            'cache_ttl' => absint($_POST['cache_ttl'] ?? 3600),
            'headers' => is_array($headers) ? $headers : [],
        ];
    }

    private function preserve_blank_api_header_values(array $headers, array $existing_headers): array
    {
        $existing = [];
        foreach ($existing_headers as $header) {
            if (!is_array($header)) {
                continue;
            }
            $name = strtolower((string) ($header['name'] ?? ''));
            if ($name !== '') {
                $existing[$name] = (string) ($header['value'] ?? '');
            }
        }

        if (!isset($headers['name'], $headers['value']) || !is_array($headers['name']) || !is_array($headers['value'])) {
            return $headers;
        }

        foreach ($headers['name'] as $index => $name) {
            $lookup = strtolower((string) $name);
            if (($headers['value'][$index] ?? '') === '' && isset($existing[$lookup])) {
                $headers['value'][$index] = $existing[$lookup];
            }
        }

        return $headers;
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
