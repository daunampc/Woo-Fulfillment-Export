<?php defined('ABSPATH') || exit; ?>
<div class="wrap wfe-wrap wfe-admin-wrap">
    <h1><?php esc_html_e('Fulfillment Export', 'woo-fulfillment-export'); ?></h1>
    <?php WFE_Admin_Menu::tabs('wfe-orders'); ?>
    <?php WFE_Admin_Menu::notice(); ?>

    <form method="get" class="wfe-panel wfe-filter-grid">
        <input type="hidden" name="page" value="wfe-orders">

        <div class="wfe-field wfe-field-wide">
            <label><?php esc_html_e('Order status', 'woo-fulfillment-export'); ?></label>
            <div class="wfe-checkbox-group wfe-status-options">
                <?php foreach ($available_statuses as $key => $label): $clean = str_replace('wc-', '', $key); ?>
                    <label class="wfe-checkbox">
                        <input type="checkbox" name="status[]" value="<?php echo esc_attr($clean); ?>" <?php checked(in_array($clean, $status, true)); ?>>
                        <span><?php echo esc_html($label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wfe-field">
            <label for="wfe-date-from"><?php esc_html_e('From date', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-date-from" type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
        </div>
        <div class="wfe-field">
            <label for="wfe-date-to"><?php esc_html_e('To date', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-date-to" type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
        </div>
        <div class="wfe-field">
            <label for="wfe-customer"><?php esc_html_e('Customer keyword', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-customer" type="search" name="customer" value="<?php echo esc_attr($filters['customer']); ?>">
        </div>
        <div class="wfe-field">
            <label for="wfe-order-query"><?php esc_html_e('Order ID / Number', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-order-query" type="search" name="order_query" value="<?php echo esc_attr($filters['order_query']); ?>" placeholder="#263950">
        </div>
        <div class="wfe-field">
            <label for="wfe-product"><?php esc_html_e('Product name or ID', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-product" type="search" name="product" value="<?php echo esc_attr($filters['product']); ?>">
        </div>
        <div class="wfe-field">
            <label for="wfe-sku"><?php esc_html_e('SKU', 'woo-fulfillment-export'); ?></label>
            <input id="wfe-sku" type="search" name="sku" value="<?php echo esc_attr($filters['sku']); ?>">
        </div>
        <div class="wfe-field">
            <label for="wfe-category"><?php esc_html_e('Product category', 'woo-fulfillment-export'); ?></label>
            <select id="wfe-category" name="category">
                <option value=""><?php esc_html_e('Any category', 'woo-fulfillment-export'); ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($filters['category'], $category->slug); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="wfe-field">
            <label for="wfe-per-page"><?php esc_html_e('Orders per page', 'woo-fulfillment-export'); ?></label>
            <select id="wfe-per-page" name="per_page">
                <?php foreach ([10, 20, 30, 50, 100, 200] as $per_page_option): ?>
                    <option value="<?php echo esc_attr($per_page_option); ?>" <?php selected((int) $filters['limit'], $per_page_option); ?>><?php echo esc_html($per_page_option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="wfe-actions">
            <button class="button button-primary"><?php esc_html_e('Filter orders', 'woo-fulfillment-export'); ?></button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-orders')); ?>"><?php esc_html_e('Reset', 'woo-fulfillment-export'); ?></a>
        </div>
    </form>

    <?php if (!empty($result->truncated)): ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Results were limited by the scan limit. Narrow the date/status filters or increase the scan limit in Settings.', 'woo-fulfillment-export'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wfe-panel" id="wfe-export-form">
        <?php wp_nonce_field('wfe_export_orders'); ?>
        <input type="hidden" name="action" value="wfe_export_orders">
        <?php foreach ($status as $selected_status): ?>
            <input type="hidden" name="status[]" value="<?php echo esc_attr($selected_status); ?>">
        <?php endforeach; ?>
        <input type="hidden" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
        <input type="hidden" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
        <input type="hidden" name="order_query" value="<?php echo esc_attr($filters['order_query']); ?>">
        <input type="hidden" name="customer" value="<?php echo esc_attr($filters['customer']); ?>">
        <input type="hidden" name="product" value="<?php echo esc_attr($filters['product']); ?>">
        <input type="hidden" name="sku" value="<?php echo esc_attr($filters['sku']); ?>">
        <input type="hidden" name="category" value="<?php echo esc_attr($filters['category']); ?>">
        <input type="hidden" name="per_page" value="<?php echo esc_attr($filters['limit']); ?>">

        <div class="wfe-toolbar">
            <select name="template_id" required>
                <option value=""><?php esc_html_e('Select CSV/XLSX template', 'woo-fulfillment-export'); ?></option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?php echo esc_attr($template['id']); ?>">
                        <?php echo esc_html($template['name'] . ' (' . strtoupper($template['file_type']) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button button-primary"><?php esc_html_e('Export selected or filtered orders', 'woo-fulfillment-export'); ?></button>
            <span class="wfe-selected-count" id="wfe-selected-count" aria-live="polite"><?php esc_html_e('0 selected', 'woo-fulfillment-export'); ?></span>
        </div>
        <div class="wfe-export-progress" id="wfe-export-progress" hidden>
            <div class="wfe-progress-bar"><span style="width:0%"></span></div>
            <p class="wfe-progress-text"><?php esc_html_e('Preparing export...', 'woo-fulfillment-export'); ?></p>
        </div>

        <div class="wfe-table-scroll">
            <table class="widefat striped wfe-table">
                <thead>
                <tr>
                    <td class="check-column"><label class="wfe-checkbox wfe-table-check"><input id="wfe-select-all-orders" type="checkbox"><span class="screen-reader-text"><?php esc_html_e('Select all orders', 'woo-fulfillment-export'); ?></span></label></td>
                    <th><?php esc_html_e('Order', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Date', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Status', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Customer', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Phone', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Items', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Total', 'woo-fulfillment-export'); ?></th>
                    <th><?php esc_html_e('Action', 'woo-fulfillment-export'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($result->orders)): ?>
                    <tr><td colspan="9"><?php esc_html_e('No orders found.', 'woo-fulfillment-export'); ?></td></tr>
                <?php endif; ?>

                <?php foreach ($result->orders as $order): ?>
                    <?php /** @var WC_Order $order */ ?>
                    <tr>
                        <th class="check-column"><label class="wfe-checkbox wfe-table-check"><input class="wfe-order-check" type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->get_id()); ?>"><span class="screen-reader-text"><?php esc_html_e('Select order', 'woo-fulfillment-export'); ?></span></label></th>
                        <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                        <td><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : ''); ?></td>
                        <td><span class="wfe-status-badge wfe-status-<?php echo esc_attr($order->get_status()); ?>"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span></td>
                        <td><?php echo esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())); ?></td>
                        <td><?php echo esc_html($order->get_billing_phone()); ?></td>
                        <td><?php echo esc_html($order->get_item_count()); ?></td>
                        <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                        <td class="wfe-row-actions">
                            <?php
                            $target_status = $order->get_status() === 'fulfillment' ? 'processing' : 'fulfillment';
                            $action_url = wp_nonce_url(add_query_arg([
                                'action' => 'wfe_mark_order_fulfillment',
                                'order_id' => $order->get_id(),
                                'target_status' => $target_status,
                            ], admin_url('admin-post.php')), 'wfe_mark_order_fulfillment');
                            ?>
                            <a href="<?php echo esc_url($action_url); ?>" class="button button-small wfe-fulfillment-action <?php echo $target_status === 'fulfillment' ? 'wfe-action-fulfillment' : 'wfe-action-processing'; ?>">
                                <?php echo esc_html($target_status === 'fulfillment' ? __('Mark fulfillment', 'woo-fulfillment-export') : __('Back to processing', 'woo-fulfillment-export')); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if (!empty($result->max_num_pages) && $result->max_num_pages > 1): ?>
        <div class="tablenav wfe-tablenav">
            <div class="tablenav-pages wfe-pagination">
                <span class="displaying-num"><?php printf(esc_html__('%s orders', 'woo-fulfillment-export'), esc_html(number_format_i18n((int) ($result->total ?? 0)))); ?></span>
                <span class="pagination-links">
                    <?php
                    $pagination_links = paginate_links([
                        'base' => add_query_arg(['paged' => '%#%', 'per_page' => (int) $filters['limit']]),
                        'format' => '',
                        'prev_text' => '&lsaquo;',
                        'next_text' => '&rsaquo;',
                        'total' => (int) $result->max_num_pages,
                        'current' => $page,
                        'type' => 'array',
                    ]);
                    if (is_array($pagination_links)) {
                        foreach ($pagination_links as $link) {
                            echo wp_kses_post($link);
                        }
                    }
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const selectAll = document.getElementById('wfe-select-all-orders');
    const count = document.getElementById('wfe-selected-count');
    const checks = Array.from(document.querySelectorAll('.wfe-order-check'));
    function updateCount() {
        const selected = checks.filter(function(check) { return check.checked; }).length;
        if (count) {
            count.textContent = selected + ' selected';
        }
        if (selectAll) {
            selectAll.checked = checks.length > 0 && selected === checks.length;
            selectAll.indeterminate = selected > 0 && selected < checks.length;
        }
    }
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checks.forEach(function(check) { check.checked = selectAll.checked; });
            updateCount();
        });
    }
    checks.forEach(function(check) {
        check.addEventListener('change', updateCount);
    });
    updateCount();
})();
</script>
