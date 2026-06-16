<?php

defined('ABSPATH') || exit;

final class WFE_Settings
{
    private const OPTION = 'wfe_settings';

    public static function defaults(): array
    {
        return [
<<<<<<< HEAD
            'default_statuses' => ['processing'],
            'row_mode' => 'item_per_row',
            'export_limit' => 500,
            'scan_limit' => 2000,
=======
            'default_statuses' => ['processing', 'fulfillment'],
            'row_mode' => 'item_per_row',
            'export_limit' => 500,
            'scan_limit' => 2000,
            'orders_per_page' => 30,
            'ajax_chunk_size' => 20,
            'github_repo' => '',
            'github_branch' => 'main',
            'github_token' => '',
>>>>>>> 33573ee (first commit)
        ];
    }

    public static function all(): array
    {
        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $settings = array_merge(self::defaults(), $settings);
<<<<<<< HEAD
        $settings['default_statuses'] = WFE_Order_Query::sanitize_statuses((array) ($settings['default_statuses'] ?? ['processing']));
        if (!$settings['default_statuses']) {
            $settings['default_statuses'] = ['processing'];
=======
        $settings['default_statuses'] = WFE_Order_Query::sanitize_statuses((array) ($settings['default_statuses'] ?? ['processing', 'fulfillment']));
        if (!$settings['default_statuses']) {
            $settings['default_statuses'] = ['processing', 'fulfillment'];
>>>>>>> 33573ee (first commit)
        }
        $settings['row_mode'] = in_array($settings['row_mode'], ['item_per_row', 'order_per_row'], true) ? $settings['row_mode'] : 'item_per_row';
        $settings['export_limit'] = max(1, absint($settings['export_limit']));
        $settings['scan_limit'] = max(100, absint($settings['scan_limit']));
<<<<<<< HEAD
=======
        $settings['orders_per_page'] = max(5, min(200, absint($settings['orders_per_page'] ?? 30)));
        $settings['ajax_chunk_size'] = max(1, min(100, absint($settings['ajax_chunk_size'] ?? 20)));
        $settings['github_repo'] = sanitize_text_field((string) ($settings['github_repo'] ?? ''));
        $settings['github_branch'] = sanitize_text_field((string) ($settings['github_branch'] ?? 'main'));
        $settings['github_token'] = sanitize_text_field((string) ($settings['github_token'] ?? ''));
>>>>>>> 33573ee (first commit)

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
<<<<<<< HEAD
=======
        $settings['orders_per_page'] = max(5, min(200, absint($settings['orders_per_page'] ?? 30)));
        $settings['ajax_chunk_size'] = max(1, min(100, absint($settings['ajax_chunk_size'] ?? 20)));
        $settings['github_repo'] = sanitize_text_field((string) ($settings['github_repo'] ?? ''));
        $settings['github_branch'] = sanitize_text_field((string) ($settings['github_branch'] ?? 'main'));
        $settings['github_token'] = sanitize_text_field((string) ($settings['github_token'] ?? ''));
>>>>>>> 33573ee (first commit)

        update_option(self::OPTION, $settings, false);
    }
}
