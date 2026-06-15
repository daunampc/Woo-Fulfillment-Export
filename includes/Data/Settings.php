<?php

defined('ABSPATH') || exit;

final class WFE_Settings
{
    private const OPTION = 'wfe_settings';

    public static function defaults(): array
    {
        return [
            'default_statuses' => ['processing'],
            'row_mode' => 'item_per_row',
            'export_limit' => 500,
            'scan_limit' => 2000,
        ];
    }

    public static function all(): array
    {
        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $settings = array_merge(self::defaults(), $settings);
        $settings['default_statuses'] = WFE_Order_Query::sanitize_statuses((array) ($settings['default_statuses'] ?? ['processing']));
        if (!$settings['default_statuses']) {
            $settings['default_statuses'] = ['processing'];
        }
        $settings['row_mode'] = in_array($settings['row_mode'], ['item_per_row', 'order_per_row'], true) ? $settings['row_mode'] : 'item_per_row';
        $settings['export_limit'] = max(1, absint($settings['export_limit']));
        $settings['scan_limit'] = max(100, absint($settings['scan_limit']));

        return $settings;
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function save(array $settings): void
    {
        $settings = array_merge(self::all(), $settings);
        $settings['default_statuses'] = WFE_Order_Query::sanitize_statuses((array) ($settings['default_statuses'] ?? []));
        $settings['row_mode'] = in_array($settings['row_mode'], ['item_per_row', 'order_per_row'], true) ? $settings['row_mode'] : 'item_per_row';
        $settings['export_limit'] = max(1, min(5000, absint($settings['export_limit'] ?? 500)));
        $settings['scan_limit'] = max(100, min(10000, absint($settings['scan_limit'] ?? 2000)));

        update_option(self::OPTION, $settings, false);
    }
}
