<?php
defined('ABSPATH') || exit;

$form_connection = $edit_connection ?: [
    'name' => 'VTN TaskSave',
    'key' => 'vtn_tasksave_url',
    'base_url' => 'https://api.vtnpoddesign.com/api/tasks/tasksave',
    'method' => 'GET',
    'default_params' => "page=1\nlimit=32",
    'dynamic_param' => 'query',
    'response_path' => 'data.0.URL',
    'timeout' => 15,
    'enabled' => 1,
    'cache_enabled' => 1,
    'cache_ttl' => 3600,
    'headers' => [
        ['name' => 'Accept', 'value' => 'application/json'],
        ['name' => 'Authorization', 'value' => ''],
    ],
];

$headers = is_array($form_connection['headers'] ?? null) ? $form_connection['headers'] : [];
for ($i = count($headers); $i < 3; $i++) {
    $headers[] = ['name' => '', 'value' => ''];
}
?>
<div class="wrap wfe-wrap wfe-admin-wrap">
    <h1><?php esc_html_e('Fulfillment Export', 'woo-fulfillment-export'); ?></h1>
    <?php WFE_Admin_Menu::tabs('wfe-api-connections'); ?>
    <?php WFE_Admin_Menu::notice(); ?>

    <?php if (!empty($test_result['result']) && is_array($test_result['result'])): ?>
        <?php $result = $test_result['result']; ?>
        <div class="notice <?php echo !empty($result['success']) ? 'notice-success' : 'notice-error'; ?>">
            <p>
                <?php if (!empty($result['success'])): ?>
                    <?php
                    printf(
                        esc_html__('Test succeeded. HTTP %1$d, %2$d item(s), URL: %3$s', 'woo-fulfillment-export'),
                        absint($result['status'] ?? 0),
                        absint($result['count'] ?? 0),
                        esc_html((string) ($result['value'] ?? ''))
                    );
                    ?>
                <?php else: ?>
                    <?php
                    printf(
                        esc_html__('Test failed. HTTP %1$d. %2$s', 'woo-fulfillment-export'),
                        absint($result['status'] ?? 0),
                        esc_html((string) ($result['error'] ?? 'Unknown error'))
                    );
                    ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <section class="wfe-panel">
        <h2><?php echo esc_html($edit_connection ? __('Edit API connection', 'woo-fulfillment-export') : __('Add API connection', 'woo-fulfillment-export')); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wfe-api-form">
            <?php wp_nonce_field('wfe_api_connection'); ?>
            <input type="hidden" name="action" value="wfe_save_api_connection">
            <input type="hidden" name="previous_key" value="<?php echo esc_attr($edit_key); ?>">

            <div class="wfe-filter-grid">
                <div class="wfe-field">
                    <label for="wfe-api-name"><?php esc_html_e('Connection name', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-name" type="text" name="connection_name" value="<?php echo esc_attr($form_connection['name'] ?? ''); ?>" required>
                </div>
                <div class="wfe-field">
                    <label for="wfe-api-key"><?php esc_html_e('Connection key', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-key" type="text" name="connection_key" value="<?php echo esc_attr($form_connection['key'] ?? ''); ?>" required>
                </div>
                <div class="wfe-field wfe-field-wide">
                    <label for="wfe-api-url"><?php esc_html_e('Base URL', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-url" type="text" name="base_url" value="<?php echo esc_attr($form_connection['base_url'] ?? ''); ?>" required>
                </div>
                <div class="wfe-field">
                    <label for="wfe-api-method"><?php esc_html_e('HTTP method', 'woo-fulfillment-export'); ?></label>
                    <select id="wfe-api-method" name="method">
                        <option value="GET" <?php selected($form_connection['method'] ?? 'GET', 'GET'); ?>>GET</option>
                        <option value="POST" <?php selected($form_connection['method'] ?? 'GET', 'POST'); ?>>POST</option>
                    </select>
                </div>
                <div class="wfe-field">
                    <label for="wfe-api-dynamic-param"><?php esc_html_e('Dynamic query param', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-dynamic-param" type="text" name="dynamic_param" value="<?php echo esc_attr($form_connection['dynamic_param'] ?? 'query'); ?>">
                </div>
                <div class="wfe-field">
                    <label for="wfe-api-response-path"><?php esc_html_e('Response path', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-response-path" type="text" name="response_path" value="<?php echo esc_attr($form_connection['response_path'] ?? 'data.0.URL'); ?>">
                </div>
                <div class="wfe-field">
                    <label for="wfe-api-timeout"><?php esc_html_e('Timeout', 'woo-fulfillment-export'); ?></label>
                    <input id="wfe-api-timeout" type="number" min="1" max="60" name="timeout" value="<?php echo esc_attr($form_connection['timeout'] ?? 15); ?>">
                </div>
                <div class="wfe-field wfe-field-wide">
                    <label for="wfe-api-default-params"><?php esc_html_e('Default query params', 'woo-fulfillment-export'); ?></label>
                    <textarea id="wfe-api-default-params" name="default_params" rows="3" class="large-text code"><?php echo esc_textarea($form_connection['default_params'] ?? "page=1\nlimit=32"); ?></textarea>
                    <p class="description"><?php esc_html_e('One key=value pair per line.', 'woo-fulfillment-export'); ?></p>
                </div>
            </div>

            <div class="wfe-checkbox-group wfe-api-options">
                <label class="wfe-checkbox">
                    <input type="checkbox" name="enabled" value="1" <?php checked(!empty($form_connection['enabled'])); ?>>
                    <span><?php esc_html_e('Enabled', 'woo-fulfillment-export'); ?></span>
                </label>
                <label class="wfe-checkbox">
                    <input type="checkbox" name="cache_enabled" value="1" <?php checked(!empty($form_connection['cache_enabled'])); ?>>
                    <span><?php esc_html_e('Enable cache', 'woo-fulfillment-export'); ?></span>
                </label>
                <label class="wfe-inline-number">
                    <span><?php esc_html_e('Cache TTL', 'woo-fulfillment-export'); ?></span>
                    <input type="number" min="1" name="cache_ttl" value="<?php echo esc_attr($form_connection['cache_ttl'] ?? 3600); ?>">
                </label>
            </div>

            <h3><?php esc_html_e('Headers', 'woo-fulfillment-export'); ?></h3>
            <div class="wfe-table-scroll">
                <table class="widefat wfe-table wfe-header-table" id="wfe-header-table">
                    <thead><tr><th><?php esc_html_e('Header name', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Header value', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Remove', 'woo-fulfillment-export'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($headers as $header): ?>
                        <?php $has_value = !empty($header['value']); ?>
                        <tr>
                            <td><input type="text" name="headers[name][]" value="<?php echo esc_attr($header['name'] ?? ''); ?>" placeholder="Authorization"></td>
                            <td><input type="password" name="headers[value][]" value="" placeholder="<?php echo esc_attr($has_value ? __('Saved secret - leave blank to keep', 'woo-fulfillment-export') : 'Bearer xxx'); ?>"></td>
                            <td><button type="button" class="button wfe-remove-row">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="wfe-toolbar">
                <button class="button button-primary"><?php esc_html_e('Save connection', 'woo-fulfillment-export'); ?></button>
                <label class="wfe-test-query">
                    <span><?php esc_html_e('Test query', 'woo-fulfillment-export'); ?></span>
                    <input type="text" name="test_query" value="<?php echo esc_attr($test_result['query'] ?? 'a'); ?>">
                </label>
                <button class="button" name="test_api" value="1"><?php esc_html_e('Test API', 'woo-fulfillment-export'); ?></button>
                <button type="button" class="button" id="wfe-add-header"><?php esc_html_e('Add Header', 'woo-fulfillment-export'); ?></button>
                <?php if ($edit_connection): ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-api-connections')); ?>"><?php esc_html_e('New connection', 'woo-fulfillment-export'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="wfe-panel">
        <h2><?php esc_html_e('Connections', 'woo-fulfillment-export'); ?></h2>
        <div class="wfe-table-scroll">
            <table class="widefat striped wfe-table">
                <thead><tr><th><?php esc_html_e('Name', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Key', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Response path', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Cache', 'woo-fulfillment-export'); ?></th><th><?php esc_html_e('Actions', 'woo-fulfillment-export'); ?></th></tr></thead>
                <tbody>
                <?php if (!$connections): ?>
                    <tr><td colspan="5"><?php esc_html_e('No API connections yet.', 'woo-fulfillment-export'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($connections as $connection): ?>
                    <tr>
                        <td><strong><?php echo esc_html($connection['name']); ?></strong></td>
                        <td><code><?php echo esc_html($connection['key']); ?></code></td>
                        <td><code><?php echo esc_html($connection['response_path']); ?></code></td>
                        <td><?php echo esc_html(!empty($connection['cache_enabled']) ? sprintf(__('Yes, %ss', 'woo-fulfillment-export'), absint($connection['cache_ttl'])) : __('No', 'woo-fulfillment-export')); ?></td>
                        <td class="wfe-actions-inline">
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wfe-api-connections&edit_key=' . rawurlencode($connection['key']))); ?>"><?php esc_html_e('Edit', 'woo-fulfillment-export'); ?></a>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this API connection?', 'woo-fulfillment-export')); ?>')">
                                <?php wp_nonce_field('wfe_delete_api_connection'); ?>
                                <input type="hidden" name="action" value="wfe_delete_api_connection">
                                <input type="hidden" name="connection_key" value="<?php echo esc_attr($connection['key']); ?>">
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
    const table = document.getElementById('wfe-header-table');
    const addButton = document.getElementById('wfe-add-header');
    if (!table || !addButton) {
        return;
    }
    addButton.addEventListener('click', function() {
        const row = document.createElement('tr');
        row.innerHTML = '<td><input type="text" name="headers[name][]" value="" placeholder="Authorization"></td><td><input type="password" name="headers[value][]" value="" placeholder="Bearer xxx"></td><td><button type="button" class="button wfe-remove-row">&times;</button></td>';
        table.querySelector('tbody').appendChild(row);
    });
    table.addEventListener('click', function(event) {
        if (event.target.classList.contains('wfe-remove-row')) {
            event.target.closest('tr').remove();
        }
    });
})();
</script>
