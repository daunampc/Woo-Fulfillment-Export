<?php
defined('ABSPATH') || exit;

$mapping = is_array($mapping) ? $mapping : [];
$mapping_columns = [];
$detected_headers = is_array($inspection['headers'] ?? null) ? $inspection['headers'] : [];

foreach ($detected_headers as $column => $label) {
    $column = WFE_Mapping_Repository::sanitize_column_key((string) $column);
    if ($column === '') {
        continue;
    }
    $mapping_columns[$column] = [
        'header' => (string) $label,
        'mapping' => (string) (($mapping['columns'][$column] ?? '')),
        'default' => (string) (($mapping['defaults'][$column] ?? '')),
    ];
}

foreach ((array) ($mapping['columns'] ?? []) as $column => $expression) {
    $column = WFE_Mapping_Repository::sanitize_column_key((string) $column);
    if ($column === '') {
        continue;
    }
    $mapping_columns[$column] = [
        'header' => (string) (($mapping['headers'][$column] ?? ($mapping_columns[$column]['header'] ?? $column))),
        'mapping' => (string) $expression,
        'default' => (string) (($mapping['defaults'][$column] ?? ($mapping_columns[$column]['default'] ?? ''))),
    ];
}

if (!$mapping_columns) {
    foreach (range(1, 8) as $index) {
        $column = WFE_Mapping_Repository::column_letter($index);
        $mapping_columns[$column] = [
            'header' => $column,
            'mapping' => '',
            'default' => '',
        ];
    }
}

uksort($mapping_columns, static function ($a, $b) {
    return WFE_Mapping_Repository::column_index($a) <=> WFE_Mapping_Repository::column_index($b);
});
?>
<div class="wrap wfe-wrap wfe-admin-wrap">
    <h1><?php esc_html_e('Fulfillment Export', 'woo-fulfillment-export'); ?></h1>
    <?php WFE_Admin_Menu::tabs('wfe-mapping'); ?>
    <?php WFE_Admin_Menu::notice(); ?>

    <?php if (!$templates): ?>
        <section class="wfe-panel">
            <p><?php esc_html_e('Create or upload a CSV/XLSX template first.', 'woo-fulfillment-export'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wfe-templates')); ?>"><?php esc_html_e('Go to templates', 'woo-fulfillment-export'); ?></a></p>
        </section>
    <?php else: ?>
        <form method="get" class="wfe-panel wfe-template-switcher">
            <input type="hidden" name="page" value="wfe-mapping">
            <div class="wfe-field">
                <label for="wfe-template-id"><?php esc_html_e('Template', 'woo-fulfillment-export'); ?></label>
                <select id="wfe-template-id" name="template_id" onchange="this.form.submit()">
                    <?php foreach ($templates as $template): ?>
                        <option value="<?php echo esc_attr($template['id']); ?>" <?php selected($selected_template_id, $template['id']); ?>>
                            <?php echo esc_html($template['name'] . ' (' . strtoupper($template['file_type']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selected_template && ($selected_template['file_type'] ?? '') === 'xlsx' && ($selected_template['source'] ?? '') === 'upload'): ?>
                <div class="wfe-field">
                    <label for="wfe-sheet-index"><?php esc_html_e('Sheet', 'woo-fulfillment-export'); ?></label>
                    <select id="wfe-sheet-index" name="sheet_index" onchange="this.form.submit()">
                        <?php foreach ((array) ($inspection['sheets'] ?? []) as $sheet): ?>
                            <option value="<?php echo esc_attr($sheet['index']); ?>" <?php selected($sheet_index, $sheet['index']); ?>>
                                <?php echo esc_html($sheet['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wfe-field">
                    <label for="wfe-header-row"><?php esc_html_e('Header row', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-header-row" type="number" name="header_row" min="1" value="<?php echo esc_attr($header_row); ?>">
                </div>
                <div class="wfe-actions">
                    <button class="button"><?php esc_html_e('Read headers', 'woo-fulfillment-export'); ?></button>
                </div>
            <?php endif; ?>
        </form>

        <?php if (!empty($inspection['error'])): ?>
            <div class="notice notice-warning"><p><?php echo esc_html($inspection['error']); ?></p></div>
        <?php endif; ?>

        <div class="wfe-grid-main">
            <section class="wfe-panel">
                <h2><?php esc_html_e('Column mapping', 'woo-fulfillment-export'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wfe-mapping-form">
                    <?php wp_nonce_field('wfe_save_mapping'); ?>
                    <input type="hidden" name="action" value="wfe_save_mapping">
                    <input type="hidden" name="template_id" value="<?php echo esc_attr($selected_template_id); ?>">
                    <input type="hidden" name="sheet_index" value="<?php echo esc_attr($sheet_index); ?>">
                    <input type="hidden" name="header_row" value="<?php echo esc_attr($header_row); ?>">

                    <div class="wfe-mapping-options">
                        <label>
                            <span><?php esc_html_e('Start writing at row', 'woo-fulfillment-export'); ?></span>
                            <input type="number" name="start_row" min="1" value="<?php echo esc_attr($mapping['start_row'] ?? 2); ?>">
                        </label>
                        <label>
                            <span><?php esc_html_e('Row mode', 'woo-fulfillment-export'); ?></span>
                            <select name="row_mode">
                                <option value="item_per_row" <?php selected($mapping['row_mode'] ?? 'item_per_row', 'item_per_row'); ?>><?php esc_html_e('One order item per row', 'woo-fulfillment-export'); ?></option>
                               <option value="order_per_row" <?php selected($mapping['row_mode'] ?? 'item_per_row', 'order_per_row'); ?>><?php esc_html_e('One order per row', 'woo-fulfillment-export'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Preview order ID', 'woo-fulfillment-export'); ?></span>
                            <input type="number" name="preview_order_id" min="1" value="<?php echo esc_attr($preview_order_id ?: ''); ?>" placeholder="123">
                        </label>
                    </div>

                    <div class="wfe-table-scroll">
                        <table class="widefat striped wfe-table">
                            <thead>
                            <tr>
                                <th><?php esc_html_e('Column', 'woo-fulfillment-export'); ?></th>
                                <th><?php esc_html_e('Header', 'woo-fulfillment-export'); ?></th>
                                <th><?php esc_html_e('Mapping expression', 'woo-fulfillment-export'); ?></th>
                                <th><?php esc_html_e('Default', 'woo-fulfillment-export'); ?></th>
                                <th><?php esc_html_e('Preview', 'woo-fulfillment-export'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($mapping_columns as $column => $config): ?>
                                <?php
                                $preview_value = '';
                                if (is_array($preview_row)) {
                                    $preview_value = WFE_Mapping_Repository::value_for_column([
                                        'mapping' => $config['mapping'],
                                        'default' => $config['default'],
                                    ], $preview_row);
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($column); ?></strong></td>
                                    <td><input name="column_header[<?php echo esc_attr($column); ?>]" value="<?php echo esc_attr($config['header']); ?>"></td>
                                    <td><input class="wfe-mapping-input" name="column_mapping[<?php echo esc_attr($column); ?>]" value="<?php echo esc_attr($config['mapping']); ?>" placeholder="{value}"></td>
                                    <td><input class="wfe-mapping-input" name="column_default[<?php echo esc_attr($column); ?>]" value="<?php echo esc_attr($config['default']); ?>"></td>
                                    <td class="wfe-preview-cell"><?php echo esc_html($preview_value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="wfe-toolbar">
                        <button class="button button-primary"><?php esc_html_e('Save mapping', 'woo-fulfillment-export'); ?></button>
                        <?php if ($preview_order_id > 0): ?>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-mapping&template_id=' . rawurlencode($selected_template_id) . '&sheet_index=' . $sheet_index . '&header_row=' . $header_row)); ?>"><?php esc_html_e('Clear preview', 'woo-fulfillment-export'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <aside class="wfe-panel wfe-placeholders">
                <h2><?php esc_html_e('Placeholders', 'woo-fulfillment-export'); ?></h2>
                <?php foreach ($placeholder_groups as $group => $placeholders): ?>
                    <details open>
                        <summary><?php echo esc_html($group); ?></summary>
                        <?php if (strpos((string) $group, 'WCPA') !== false): ?>
                            <p class="description wfe-help-text"><?php esc_html_e('Use {wcpa:Label} or {wcpa:field_name}. Example: {wcpa:Size} returns the selected Size value.', 'woo-fulfillment-export'); ?></p>
                            <div class="wfe-wcpa-builder">
                                <input type="text" id="wfe-wcpa-field" placeholder="<?php echo esc_attr__('Size', 'woo-fulfillment-export'); ?>">
                                <button type="button" class="button" id="wfe-insert-wcpa"><?php esc_html_e('Insert WCPA', 'woo-fulfillment-export'); ?></button>
                            </div>
                        <?php endif; ?>
<<<<<<< HEAD
=======
                        <?php if (strpos((string) $group, 'API Dynamic') !== false): ?>
                            <p class="description wfe-help-text"><?php esc_html_e('This calls the configured API using the resolved query value and returns the configured response path, e.g. data.0.URL.', 'woo-fulfillment-export'); ?></p>
                            <?php foreach ((array) ($api_connections ?? []) as $connection): ?>
                                <?php if (empty($connection['enabled'])) { continue; } ?>
                                <div class="wfe-api-builder">
                                    <div>
                                        <strong><?php echo esc_html($connection['name']); ?></strong>
                                        <code><?php echo esc_html($connection['key']); ?></code>
                                        <span><?php echo esc_html($connection['response_path']); ?></span>
                                    </div>
                                    <label>
                                        <span><?php esc_html_e('Query value', 'woo-fulfillment-export'); ?></span>
                                        <select class="wfe-api-query-select">
                                            <option value="{product_sku}"><?php esc_html_e('Product SKU', 'woo-fulfillment-export'); ?></option>
                                            <option value="{order_number}"><?php esc_html_e('Order Number', 'woo-fulfillment-export'); ?></option>
                                            <option value="{product_name}"><?php esc_html_e('Product Name', 'woo-fulfillment-export'); ?></option>
                                            <option value="__wcpa__"><?php esc_html_e('WCPA label', 'woo-fulfillment-export'); ?></option>
                                            <option value="__custom__"><?php esc_html_e('Custom text', 'woo-fulfillment-export'); ?></option>
                                        </select>
                                    </label>
                                    <input class="wfe-api-custom-query" type="text" placeholder="<?php echo esc_attr__('Size or custom query', 'woo-fulfillment-export'); ?>">
                                    <button type="button" class="button wfe-insert-api" data-connection="<?php echo esc_attr($connection['key']); ?>"><?php esc_html_e('Insert API placeholder', 'woo-fulfillment-export'); ?></button>
                                    <code><?php echo esc_html('{api:' . $connection['key'] . ':{product_sku}}'); ?></code>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($api_connections)): ?>
                                <p><a href="<?php echo esc_url(admin_url('admin.php?page=wfe-api-connections')); ?>"><?php esc_html_e('Create an API connection first.', 'woo-fulfillment-export'); ?></a></p>
                            <?php endif; ?>
                        <?php endif; ?>
>>>>>>> 33573ee (first commit)
                        <div class="wfe-chip-list">
                            <?php foreach ($placeholders as $placeholder): ?>
                                <button type="button" class="button wfe-placeholder-chip" data-placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_html($placeholder); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

                <?php if (!empty($preview_wcpa_fields)): ?>
                    <?php
                    $wcpa_seen = [];
                    $wcpa_rows = [];
                    foreach ($preview_wcpa_fields as $field) {
                        $placeholder = (string) ($field['placeholder'] ?? '');
                        $value = (string) ($field['value'] ?? '');
                        if ($placeholder === '' || $value === '') {
                            continue;
                        }
                        $signature = strtolower($placeholder . '|' . $value);
                        if (isset($wcpa_seen[$signature])) {
                            continue;
                        }
                        $wcpa_seen[$signature] = true;
                        $wcpa_rows[] = $field;
                    }
                    ?>
                    <?php if ($wcpa_rows): ?>
                        <div class="wfe-wcpa-preview">
                            <h3><?php esc_html_e('Detected WCPA fields', 'woo-fulfillment-export'); ?></h3>
                            <div class="wfe-table-scroll">
                                <table class="widefat striped wfe-table wfe-wcpa-table">
                                    <thead><tr><th><?php esc_html_e('Label', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Value', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Placeholder', 'woo-fulfillment-export'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($wcpa_rows as $field): ?>
                                        <tr>
                                            <td><?php echo esc_html($field['label'] ?? ''); ?></td>
                                            <td><?php echo esc_html($field['value'] ?? ''); ?></td>
                                            <td><button type="button" class="button wfe-placeholder-chip" data-placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"><?php echo esc_html($field['placeholder'] ?? ''); ?></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    let activeInput = null;
    document.addEventListener('focusin', function(event) {
        if (event.target.classList && event.target.classList.contains('wfe-mapping-input')) {
            activeInput = event.target;
        }
    });
    function insertPlaceholder(value) {
            if (!activeInput) {
                activeInput = document.querySelector('.wfe-mapping-input');
            }
            if (!activeInput) {
                return;
            }
            const start = activeInput.selectionStart || activeInput.value.length;
            const end = activeInput.selectionEnd || activeInput.value.length;
            activeInput.value = activeInput.value.slice(0, start) + value + activeInput.value.slice(end);
            activeInput.focus();
            activeInput.selectionStart = activeInput.selectionEnd = start + value.length;
    }
    document.querySelectorAll('.wfe-placeholder-chip').forEach(function(button) {
        button.addEventListener('click', function() {
            insertPlaceholder(button.getAttribute('data-placeholder'));
        });
    });
    const wcpaInput = document.getElementById('wfe-wcpa-field');
    const wcpaButton = document.getElementById('wfe-insert-wcpa');
    if (wcpaInput && wcpaButton) {
        wcpaButton.addEventListener('click', function() {
            const field = wcpaInput.value.trim();
            if (!field) {
                wcpaInput.focus();
                return;
            }
            insertPlaceholder('{wcpa:' + field + '}');
        });
    }
<<<<<<< HEAD
=======
    document.querySelectorAll('.wfe-insert-api').forEach(function(button) {
        button.addEventListener('click', function() {
            const box = button.closest('.wfe-api-builder');
            const select = box ? box.querySelector('.wfe-api-query-select') : null;
            const custom = box ? box.querySelector('.wfe-api-custom-query') : null;
            let query = select ? select.value : '{product_sku}';
            const customValue = custom ? custom.value.trim() : '';
            if (query === '__wcpa__') {
                if (!customValue) {
                    custom.focus();
                    return;
                }
                query = '{wcpa:' + customValue + '}';
            } else if (query === '__custom__') {
                if (!customValue) {
                    custom.focus();
                    return;
                }
                query = customValue;
            }
            insertPlaceholder('{api:' + button.getAttribute('data-connection') + ':' + query + '}');
        });
    });
>>>>>>> 33573ee (first commit)
})();
</script>
