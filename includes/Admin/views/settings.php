<?php defined('ABSPATH') || exit; ?>
<div class="wrap wfe-wrap wfe-admin-wrap">
    <h1><?php esc_html_e('Fulfillment Export', 'woo-fulfillment-export'); ?></h1>
    <?php WFE_Admin_Menu::tabs('wfe-settings'); ?>
    <?php WFE_Admin_Menu::notice(); ?>

    <section class="wfe-panel">
        <h2><?php esc_html_e('Defaults and limits', 'woo-fulfillment-export'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wfe_save_settings'); ?>
            <input type="hidden" name="action" value="wfe_save_settings">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Default statuses', 'woo-fulfillment-export'); ?></th>
                    <td>
                        <div class="wfe-checkbox-group wfe-status-options">
                            <?php foreach (wc_get_order_statuses() as $key => $label): $clean = str_replace('wc-', '', $key); ?>
                                <label class="wfe-checkbox">
                                    <input type="checkbox" name="default_statuses[]" value="<?php echo esc_attr($clean); ?>" <?php checked(in_array($clean, $settings['default_statuses'], true)); ?>>
                                    <span><?php echo esc_html($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-row-mode"><?php esc_html_e('Default row mode', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <select id="wfe-row-mode" name="row_mode">
                            <option value="item_per_row" <?php selected($settings['row_mode'], 'item_per_row'); ?>><?php esc_html_e('One order item per row', 'woo-fulfillment-export'); ?></option>
                            <option value="order_per_row" <?php selected($settings['row_mode'], 'order_per_row'); ?>><?php esc_html_e('One order per row', 'woo-fulfillment-export'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-export-limit"><?php esc_html_e('Export batch limit', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-export-limit" type="number" name="export_limit" min="1" max="5000" value="<?php echo esc_attr($settings['export_limit']); ?>">
                        <p class="description"><?php esc_html_e('Maximum orders exported in one request when exporting by filters/status.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-scan-limit"><?php esc_html_e('Filter scan limit', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-scan-limit" type="number" name="scan_limit" min="100" max="10000" value="<?php echo esc_attr($settings['scan_limit']); ?>">
                        <p class="description"><?php esc_html_e('Maximum orders scanned in PHP for product, SKU, category, and customer filters.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
            </table>

            <button class="button button-primary"><?php esc_html_e('Save settings', 'woo-fulfillment-export'); ?></button>
        </form>
    </section>
</div>
