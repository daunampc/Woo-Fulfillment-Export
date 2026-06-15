<?php
defined('ABSPATH') || exit;

$manual_columns = $edit_template ? (array) ($edit_template['columns'] ?? []) : [
    ['header' => 'Order ID', 'mapping' => '{order_id}', 'default' => ''],
    ['header' => 'Customer Name', 'mapping' => '{billing_full_name}', 'default' => ''],
    ['header' => 'SKU', 'mapping' => '{product_sku}', 'default' => ''],
    ['header' => 'Addon Text', 'mapping' => '{wcpa:engraving_text}', 'default' => ''],
];
for ($i = count($manual_columns); $i < 8; $i++) {
    $manual_columns[] = ['header' => '', 'mapping' => '', 'default' => ''];
}
?>
<div class="wrap wfe-wrap wfe-admin-wrap">
    <h1><?php esc_html_e('Fulfillment Export', 'woo-fulfillment-export'); ?></h1>
    <?php WFE_Admin_Menu::tabs('wfe-templates'); ?>
    <?php WFE_Admin_Menu::notice(); ?>

    <div class="wfe-grid-2">
        <section class="wfe-panel">
            <h2><?php esc_html_e('Upload template', 'woo-fulfillment-export'); ?></h2>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wfe_upload_template'); ?>
                <input type="hidden" name="action" value="wfe_upload_template">
                <div class="wfe-form-stack">
                    <label>
                        <span><?php esc_html_e('Template name', 'woo-fulfillment-export'); ?></span>
                        <input class="regular-text" name="template_name" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('CSV or XLSX file', 'woo-fulfillment-export'); ?></span>
                        <input type="file" name="template_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </label>
                    <button class="button button-primary"><?php esc_html_e('Upload template', 'woo-fulfillment-export'); ?></button>
                </div>
            </form>
        </section>

        <section class="wfe-panel">
            <h2><?php echo esc_html($edit_template ? __('Edit manual template', 'woo-fulfillment-export') : __('Create manual template', 'woo-fulfillment-export')); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wfe_save_manual_template'); ?>
                <input type="hidden" name="action" value="wfe_save_manual_template">
                <input type="hidden" name="template_id" value="<?php echo esc_attr($edit_template['id'] ?? ''); ?>">

                <div class="wfe-form-stack">
                    <label>
                        <span><?php esc_html_e('Template name', 'woo-fulfillment-export'); ?></span>
                        <input class="regular-text" name="manual_template_name" value="<?php echo esc_attr($edit_template['name'] ?? ''); ?>" required>
                    </label>
                    <label>
                        <span><?php esc_html_e('Template type', 'woo-fulfillment-export'); ?></span>
                        <select name="manual_template_type">
                            <option value="csv" <?php selected($edit_template['file_type'] ?? 'csv', 'csv'); ?>>CSV</option>
                            <option value="xlsx" <?php selected($edit_template['file_type'] ?? 'csv', 'xlsx'); ?>>XLSX</option>
                        </select>
                    </label>
                </div>

                <div class="wfe-table-scroll">
                    <table class="widefat wfe-table wfe-manual-columns" id="wfe-manual-columns">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Header', 'woo-fulfillment-export'); ?></th>
                            <th><?php esc_html_e('Mapping / placeholder', 'woo-fulfillment-export'); ?></th>
                            <th><?php esc_html_e('Default value', 'woo-fulfillment-export'); ?></th>
                            <th><?php esc_html_e('Remove', 'woo-fulfillment-export'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($manual_columns as $column): ?>
                            <tr>
                                <td><input name="manual_columns[header][]" value="<?php echo esc_attr($column['header'] ?? ''); ?>"></td>
                                <td><input name="manual_columns[mapping][]" value="<?php echo esc_attr($column['mapping'] ?? ''); ?>" placeholder="{order_number}"></td>
                                <td><input name="manual_columns[default][]" value="<?php echo esc_attr($column['default'] ?? ''); ?>"></td>
                                <td><button type="button" class="button wfe-remove-row">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="wfe-toolbar">
                    <button type="button" class="button" id="wfe-add-manual-column"><?php esc_html_e('Add column', 'woo-fulfillment-export'); ?></button>
                    <button class="button button-primary"><?php esc_html_e('Save manual template', 'woo-fulfillment-export'); ?></button>
                    <?php if ($edit_template): ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-templates')); ?>"><?php esc_html_e('New manual template', 'woo-fulfillment-export'); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    </div>

    <section class="wfe-panel">
        <h2><?php esc_html_e('Templates', 'woo-fulfillment-export'); ?></h2>
        <div class="wfe-table-scroll">
            <table class="widefat striped wfe-table">
                <thead><tr><th><?php esc_html_e('Name', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Type', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Source', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('File', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Updated', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Actions', 'woo-fulfillment-export'); ?></th></tr></thead>
                <tbody>
                <?php if (!$templates): ?>
                    <tr><td colspan="6"><?php esc_html_e('No templates yet.', 'woo-fulfillment-export'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                        <td><?php echo esc_html(strtoupper($template['file_type'])); ?></td>
                        <td><?php echo esc_html($template['source'] === 'manual' ? __('Manual', 'woo-fulfillment-export') : __('Uploaded', 'woo-fulfillment-export')); ?></td>
                        <td><?php echo esc_html($template['file_name'] ?: '-'); ?></td>
                        <td><?php echo esc_html($template['updated_at'] ?? ($template['created_at'] ?? '')); ?></td>
                        <td class="wfe-actions-inline">
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-mapping&template_id=' . rawurlencode($template['id']))); ?>"><?php esc_html_e('Mapping', 'woo-fulfillment-export'); ?></a>
                            <?php if (($template['source'] ?? '') === 'manual'): ?>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-templates&edit_template_id=' . rawurlencode($template['id']))); ?>"><?php esc_html_e('Edit', 'woo-fulfillment-export'); ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this template?', 'woo-fulfillment-export')); ?>')">
                                <?php wp_nonce_field('wfe_delete_template'); ?>
                                <input type="hidden" name="action" value="wfe_delete_template">
                                <input type="hidden" name="template_id" value="<?php echo esc_attr($template['id']); ?>">
                                <button class="button button-link-delete"><?php esc_html_e('Delete', 'woo-fulfillment-export'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(function() {
    const table = document.getElementById('wfe-manual-columns');
    const addButton = document.getElementById('wfe-add-manual-column');
    if (!table || !addButton) {
        return;
    }
    addButton.addEventListener('click', function() {
        const row = document.createElement('tr');
        row.innerHTML = '<td><input name="manual_columns[header][]" value=""></td><td><input name="manual_columns[mapping][]" value="" placeholder="{order_number}"></td><td><input name="manual_columns[default][]" value=""></td><td><button type="button" class="button wfe-remove-row">&times;</button></td>';
        table.querySelector('tbody').appendChild(row);
    });
    table.addEventListener('click', function(event) {
        if (event.target.classList.contains('wfe-remove-row')) {
            event.target.closest('tr').remove();
        }
    });
})();
</script>
