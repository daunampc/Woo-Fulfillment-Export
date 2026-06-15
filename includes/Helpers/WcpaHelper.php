<?php

defined('ABSPATH') || exit;

final class WFE_WCPA_Helper
{
    public static function extract_wcpa_data(WC_Order_Item_Product $item): array
    {
        $data = [];

        foreach (self::extract_wcpa_fields($item) as $field) {
            self::add_field(
                $data,
                (string) ($field['name'] ?? ''),
                (string) ($field['label'] ?? ''),
                $field['value'] ?? ''
            );
        }

        return $data;
    }

    public static function extract_wcpa_fields(WC_Order_Item_Product $item): array
    {
        $fields = [];

        foreach ($item->get_meta_data() as $meta) {
            $key = is_object($meta) ? (string) ($meta->key ?? '') : '';
            $value = is_object($meta) ? ($meta->value ?? '') : '';

            if ($key === '') {
                continue;
            }

            if (strpos($key, '_') !== 0 && self::normalize_value($value) !== '') {
                $fields[] = [
                    'name' => $key,
                    'label' => $key,
                    'value' => $value,
                ];
            }

            if (stripos($key, 'wcpa') !== false) {
                self::walk_wcpa_data(self::decode_maybe_structured($value), $fields);
            }
        }

        foreach (self::structured_meta_keys() as $meta_key) {
            $raw = $item->get_meta($meta_key, true);
            if (empty($raw)) {
                continue;
            }
            self::walk_wcpa_data(self::decode_maybe_structured($raw), $fields);
        }

        return self::dedupe_fields($fields);
    }

    private static function structured_meta_keys(): array
    {
        return [
            '_WCPA_order_meta_data',
            '_wcpa_order_meta_data',
            'WCPA_order_meta_data',
            'wcpa_order_meta_data',
            'wcpa_order_meta',
            '_wcpa_order_meta',
        ];
    }

    private static function walk_wcpa_data($node, array &$fields): void
    {
        if (is_object($node)) {
            $node = (array) $node;
        }

        if (!is_array($node)) {
            return;
        }

        $label = self::first_scalar($node, ['label', 'field_label', 'title', 'fieldLabel']);
        $name = self::first_scalar($node, ['name', 'field_name', 'elementId', 'element_id', 'id', 'key', 'fieldKey']);
        $value = self::first_existing($node, ['value', 'display_value', 'raw_value', 'displayValue', 'cartValue']);

        if (($label !== '' || $name !== '') && $value !== null && self::normalize_value($value) !== '') {
            $fields[] = [
                'name' => $name,
                'label' => $label,
                'value' => $value,
            ];
        }

        foreach ($node as $child) {
            if (is_array($child) || is_object($child)) {
                self::walk_wcpa_data($child, $fields);
            }
        }
    }

    private static function add_field(array &$data, string $name, string $label, $value): void
    {
        $value = self::normalize_value($value);
        if ($value === '') {
            return;
        }

        $keys = array_filter(array_unique([
            $name,
            $label,
            sanitize_key($name),
            sanitize_key($label),
            strtolower($name),
            strtolower($label),
            self::normalize_key($name),
            self::normalize_key($label),
        ]));

        foreach ($keys as $key) {
            $lookup_key = 'wcpa:' . $key;
            if (!isset($data[$lookup_key]) || $data[$lookup_key] === '') {
                $data[$lookup_key] = $value;
                continue;
            }

            $existing = array_map('trim', explode(',', $data[$lookup_key]));
            if (!in_array($value, $existing, true)) {
                $data[$lookup_key] .= ', ' . $value;
            }
        }
    }

    private static function dedupe_fields(array $fields): array
    {
        $deduped = [];
        $seen = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $label = (string) ($field['label'] ?? '');
            $value = self::normalize_value($field['value'] ?? '');
            if ($value === '') {
                continue;
            }

            $signature = self::normalize_key($name) . '|' . self::normalize_key($label) . '|' . $value;
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $deduped[] = [
                'name' => $name,
                'label' => $label !== '' ? $label : $name,
                'value' => $value,
                'placeholder' => '{wcpa:' . ($label !== '' ? $label : $name) . '}',
            ];
        }

        return $deduped;
    }

    private static function decode_maybe_structured($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (function_exists('maybe_unserialize')) {
            $maybe = maybe_unserialize($value);
            if ($maybe !== $value) {
                return $maybe;
            }
        }

        return $value;
    }

    private static function normalize_value($value): string
    {
        if (is_object($value)) {
            return self::normalize_value((array) $value);
        }

        if (is_array($value)) {
            foreach (['value', 'display_value', 'raw_value', 'label', 'name', 'title'] as $preferred_key) {
                if (array_key_exists($preferred_key, $value)) {
                    $preferred_value = self::normalize_value($value[$preferred_key]);
                    if ($preferred_value !== '') {
                        return $preferred_value;
                    }
                }
            }

            $parts = [];
            foreach ($value as $item) {
                $part = self::normalize_value($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return implode(', ', array_unique($parts));
        }

        if (is_scalar($value)) {
            return trim(wp_strip_all_tags((string) $value));
        }

        return '';
    }

    private static function first_scalar(array $node, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $node) && is_scalar($node[$key])) {
                return trim((string) $node[$key]);
            }
        }

        return '';
    }

    private static function first_existing(array $node, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $node)) {
                return $node[$key];
            }
        }

        return null;
    }

    private static function normalize_key(string $key): string
    {
        return WFE_Placeholder_Resolver::normalize_key($key);
    }
}
