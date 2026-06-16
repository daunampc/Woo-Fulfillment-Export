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
<<<<<<< HEAD
                            <?php foreach (wc_get_order_statuses() as $key => $label): $clean = str_replace('wc-', '', $key); ?>
=======
                            <?php foreach (WFE_Order_Query::fulfillment_status_options() as $key => $label): $clean = str_replace('wc-', '', $key); ?>
>>>>>>> 33573ee (first commit)
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
<<<<<<< HEAD
=======
                <tr>
                    <th scope="row"><label for="wfe-orders-per-page"><?php esc_html_e('Orders per page', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-orders-per-page" type="number" name="orders_per_page" min="5" max="200" value="<?php echo esc_attr($settings['orders_per_page']); ?>">
                        <p class="description"><?php esc_html_e('Default number of orders shown on the Orders page.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-ajax-chunk-size"><?php esc_html_e('AJAX export chunk size', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-ajax-chunk-size" type="number" name="ajax_chunk_size" min="1" max="100" value="<?php echo esc_attr($settings['ajax_chunk_size']); ?>">
                        <p class="description"><?php esc_html_e('How many orders are processed per AJAX request during export. Lower this if the server is weak.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-github-repo"><?php esc_html_e('GitHub repository', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-github-repo" type="text" name="github_repo" class="regular-text" placeholder="owner/repository" value="<?php echo esc_attr($settings['github_repo']); ?>">
                        <p class="description"><?php esc_html_e('Optional. Use owner/repository to enable WordPress update checks from GitHub latest releases.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-github-branch"><?php esc_html_e('GitHub fallback branch', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-github-branch" type="text" name="github_branch" class="regular-text" value="<?php echo esc_attr($settings['github_branch']); ?>">
                        <p class="description"><?php esc_html_e('Used only as a fallback when a release package is not available.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wfe-github-token"><?php esc_html_e('GitHub token', 'woo-fulfillment-export'); ?></label></th>
                    <td>
                        <input id="wfe-github-token" type="password" name="github_token" class="regular-text" value="<?php echo esc_attr($settings['github_token']); ?>" autocomplete="off">
                        <p class="description"><?php esc_html_e('Optional. Needed only for private repositories or private release assets.', 'woo-fulfillment-export'); ?></p>
                    </td>
                </tr>
>>>>>>> 33573ee (first commit)
            </table>

            <button class="button button-primary"><?php esc_html_e('Save settings', 'woo-fulfillment-export'); ?></button>
        </form>
    </section>
</div>
