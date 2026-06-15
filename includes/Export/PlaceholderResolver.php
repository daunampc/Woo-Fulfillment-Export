<?php

defined('ABSPATH') || exit;

final class WFE_Placeholder_Resolver
{
    public static function resolve(string $template, array $data): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', static function ($matches) use ($data) {
            $key = trim((string) $matches[1]);
            foreach (self::key_variants($key) as $variant) {
                if (array_key_exists($variant, $data)) {
                    return self::stringify($data[$variant]);
                }
            }

            return '';
        }, $template) ?? '';
    }

    public static function groups(): array
    {
        return [
            __('Order fields', 'woo-fulfillment-export') => [
                '{order_id}',
                '{order_number}',
                '{order_status}',
                '{order_date}',
                '{payment_method}',
                '{shipping_method}',
                '{order_total}',
                '{order_subtotal}',
                '{order_discount}',
                '{order_shipping_total}',
                '{customer_note}',
            ],
            __('Customer fields', 'woo-fulfillment-export') => [
                '{customer_name}',
                '{billing_email}',
                '{billing_phone}',
            ],
            __('Billing fields', 'woo-fulfillment-export') => [
                '{billing_first_name}',
                '{billing_last_name}',
                '{billing_full_name}',
                '{billing_phone}',
                '{billing_email}',
                '{billing_address_1}',
                '{billing_address_2}',
                '{billing_city}',
                '{billing_state}',
                '{billing_postcode}',
                '{billing_country}',
                '{billing_full_address}',
            ],
            __('Shipping fields', 'woo-fulfillment-export') => [
                '{shipping_first_name}',
                '{shipping_last_name}',
                '{shipping_full_name}',
                '{shipping_phone}',
                '{shipping_address_1}',
                '{shipping_address_2}',
                '{shipping_city}',
                '{shipping_state}',
                '{shipping_postcode}',
                '{shipping_country}',
                '{shipping_full_address}',
            ],
            __('Product fields', 'woo-fulfillment-export') => [
                '{product_id}',
                '{variation_id}',
                '{product_name}',
                '{product_sku}',
                '{product_categories}',
                '{product_image}',
                '{product_image_url}',
                '{product_image_id}',
                '{quantity}',
                '{line_total}',
                '{line_subtotal}',
            ],
            __('Variation fields', 'woo-fulfillment-export') => [
                '{variation_attributes}',
                '{variation:size}',
                '{variation:color}',
                '{variation:pa_size}',
            ],
            __('WCPA/Product Addon fields', 'woo-fulfillment-export') => [
                '{wcpa:field_name}',
                '{wcpa:field_label}',
                '{wcpa_all}',
            ],
            __('Custom meta fields', 'woo-fulfillment-export') => [
                '{order_meta:meta_key}',
                '{item_meta:meta_key}',
            ],
        ];
    }

    private static function key_variants(string $key): array
    {
        $variants = [$key, sanitize_key($key), self::normalize_key($key)];

        if (strpos($key, ':') !== false) {
            [$prefix, $tail] = explode(':', $key, 2);
            $prefix = sanitize_key($prefix);
            $variants[] = $prefix . ':' . $tail;
            $variants[] = $prefix . ':' . sanitize_key($tail);
            $variants[] = $prefix . ':' . self::normalize_key($tail);
        }

        return array_values(array_unique(array_filter($variants, static fn($value) => $value !== '')));
    }

    public static function normalize_key(string $key): string
    {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_:\-]+/', '_', $key);
        $key = preg_replace('/_+/', '_', (string) $key);
        return trim((string) $key, '_');
    }

    private static function stringify($value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return '';
    }
}
