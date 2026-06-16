<?php

defined('ABSPATH') || exit;

final class WFE_Product_Helper
{
    public static function extract_item_data(WC_Order $order, WC_Order_Item_Product $item): array
    {
        $variation_id = $item->get_variation_id();
        $variation = $variation_id ? wc_get_product($variation_id) : null;
        $parent_product = null;

        if ($variation instanceof WC_Product_Variation && $variation->get_parent_id()) {
            $parent_product = wc_get_product($variation->get_parent_id());
        }

        $sku = self::item_sku($item);
        $categories = self::item_categories($item);
        $variation_data = self::variation_data($variation instanceof WC_Product_Variation ? $variation : null);
        $image_data = self::item_image_data($item);

        $data = array_merge(self::order_level_data($order), [
            'product_id' => $item->get_product_id(),
            'variation_id' => $variation_id,
            'product_name' => $item->get_name(),
            'product_sku' => $sku,
            'product_parent_sku' => $parent_product ? $parent_product->get_sku() : '',
            'product_categories' => implode(', ', wp_list_pluck($categories, 'name')),
            'product_category_ids' => implode(', ', wp_list_pluck($categories, 'term_id')),
            'product_category_slugs' => implode(', ', wp_list_pluck($categories, 'slug')),
            'quantity' => $item->get_quantity(),
            'line_subtotal' => $item->get_subtotal(),
            'line_total' => $item->get_total(),
        ], $variation_data, $image_data);

        foreach ($item->get_meta_data() as $meta) {
            if (strpos((string) $meta->key, '_') === 0) {
                continue;
            }
            if (is_scalar($meta->value)) {
                $meta_key = self::normalize_meta_key((string) $meta->key);
                $data['item_meta:' . $meta_key] = (string) $meta->value;
                $data[(string) $meta->key] = (string) $meta->value;
            }
        }

        return $data;
    }

    public static function order_level_data(WC_Order $order): array
    {
        $billing_full_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $shipping_full_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());

        $data = [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'order_date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i:s') : '',
            'payment_method' => $order->get_payment_method_title(),
            'shipping_method' => self::shipping_methods($order),
            'currency' => $order->get_currency(),
            'order_total' => $order->get_total(),
            'order_subtotal' => $order->get_subtotal(),
            'order_discount' => $order->get_discount_total(),
            'order_shipping_total' => $order->get_shipping_total(),
            'customer_note' => $order->get_customer_note(),
            'customer_name' => $billing_full_name,
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_full_name' => $billing_full_name,
            'billing_phone' => $order->get_billing_phone(),
            'billing_email' => $order->get_billing_email(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_address_2' => $order->get_billing_address_2(),
            'billing_city' => $order->get_billing_city(),
            'billing_state' => $order->get_billing_state(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'billing_address' => self::format_billing_address($order),
            'billing_full_address' => self::format_billing_address($order),
            'shipping_name' => $shipping_full_name,
            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_full_name' => $shipping_full_name,
            'shipping_phone' => method_exists($order, 'get_shipping_phone') ? $order->get_shipping_phone() : '',
            'shipping_address_1' => $order->get_shipping_address_1(),
            'shipping_address_2' => $order->get_shipping_address_2(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_state' => $order->get_shipping_state(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_country' => $order->get_shipping_country(),
            'shipping_address' => self::format_shipping_address($order),
            'shipping_full_address' => self::format_shipping_address($order),
        ];

        foreach ($order->get_meta_data() as $meta) {
            if (strpos((string) $meta->key, '_') === 0 || !is_scalar($meta->value)) {
                continue;
            }
            $data['order_meta:' . self::normalize_meta_key((string) $meta->key)] = (string) $meta->value;
        }

        return $data;
    }

    public static function order_matches_filters(WC_Order $order, array $filters): bool
    {
        $order_query = trim((string) ($filters['order_query'] ?? ''));
        if ($order_query !== '' && !self::order_matches_order_query($order, $order_query)) {
            return false;
        }

        $customer = trim((string) ($filters['customer'] ?? ''));
        if ($customer !== '' && !self::order_matches_customer($order, $customer)) {
            return false;
        }

        $product_filter = trim((string) ($filters['product'] ?? ''));
        $sku_filter = trim((string) ($filters['sku'] ?? ''));
        $category_filter = trim((string) ($filters['category'] ?? ''));

        if ($product_filter === '' && $sku_filter === '' && $category_filter === '') {
            return true;
        }

        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product && self::item_matches_filters($item, $product_filter, $sku_filter, $category_filter)) {
                return true;
            }
        }

        return false;
    }

    public static function order_matches_order_query(WC_Order $order, string $query): bool
    {
        $query = trim(ltrim($query, '#'));
        if ($query === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) $order->get_id(),
            (string) $order->get_order_number(),
        ])));

        return strpos($haystack, strtolower($query)) !== false;
    }

    public static function order_matches_status_date(WC_Order $order, array $filters): bool
    {
        $statuses = WFE_Order_Query::sanitize_statuses((array) ($filters['status'] ?? []));
        if ($statuses && !in_array($order->get_status(), $statuses, true)) {
            return false;
        }

        $created = $order->get_date_created();
        if (!$created) {
            return true;
        }

        $date = $created->date_i18n('Y-m-d');
        $from = sanitize_text_field((string) ($filters['date_from'] ?? ''));
        $to = sanitize_text_field((string) ($filters['date_to'] ?? ''));

        if ($from !== '' && $date < $from) {
            return false;
        }
        if ($to !== '' && $date > $to) {
            return false;
        }

        return true;
    }

    public static function item_sku(WC_Order_Item_Product $item): string
    {
        $product = $item->get_product();
        $sku = $product ? (string) $product->get_sku() : '';

        if ($sku !== '') {
            return $sku;
        }

        $variation_id = $item->get_variation_id();
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation instanceof WC_Product_Variation && $variation->get_parent_id()) {
                $parent = wc_get_product($variation->get_parent_id());
                if ($parent) {
                    return (string) $parent->get_sku();
                }
            }
        }

        return '';
    }

    public static function item_categories(WC_Order_Item_Product $item): array
    {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();

        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation instanceof WC_Product_Variation && $variation->get_parent_id()) {
                $product_id = $variation->get_parent_id();
            }
        }

        $terms = $product_id ? get_the_terms($product_id, 'product_cat') : [];
        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        return array_values($terms);
    }

    public static function item_image_data(WC_Order_Item_Product $item): array
    {
        $image_id = 0;
        $product = $item->get_product();

        if ($product instanceof WC_Product) {
            $image_id = (int) $product->get_image_id();
        }

        if (!$image_id && $item->get_variation_id()) {
            $parent = wc_get_product($item->get_product_id());
            if ($parent instanceof WC_Product) {
                $image_id = (int) $parent->get_image_id();
            }
        }

        $url = $image_id ? (string) wp_get_attachment_url($image_id) : '';

        return [
            'product_image' => $url,
            'product_image_url' => $url,
            'product_image_id' => $image_id ? (string) $image_id : '',
        ];
    }

    private static function order_matches_customer(WC_Order $order, string $keyword): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $order->get_order_number(),
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            $order->get_billing_email(),
            $order->get_billing_phone(),
            $order->get_shipping_first_name(),
            $order->get_shipping_last_name(),
            trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
            method_exists($order, 'get_shipping_phone') ? $order->get_shipping_phone() : '',
        ])));

        return strpos($haystack, strtolower($keyword)) !== false;
    }

    private static function item_matches_filters(WC_Order_Item_Product $item, string $product_filter, string $sku_filter, string $category_filter): bool
    {
        if ($product_filter !== '') {
            $product_id = (string) $item->get_product_id();
            $variation_id = (string) $item->get_variation_id();
            $name = strtolower($item->get_name());

            if ($product_filter !== $product_id && $product_filter !== $variation_id && strpos($name, strtolower($product_filter)) === false) {
                return false;
            }
        }

        if ($sku_filter !== '' && strpos(strtolower(self::item_sku($item)), strtolower($sku_filter)) === false) {
            return false;
        }

        if ($category_filter !== '') {
            $matched = false;
            foreach (self::item_categories($item) as $term) {
                if ((string) $term->term_id === $category_filter || strtolower($term->slug) === strtolower($category_filter)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private static function variation_data(?WC_Product_Variation $variation): array
    {
        if (!$variation instanceof WC_Product_Variation) {
            return [
                'variation_attributes' => '',
            ];
        }

        $data = [];
        $parts = [];

        foreach ($variation->get_attributes() as $key => $value) {
            $clean_key = sanitize_key(str_replace('pa_', '', (string) $key));
            $raw_key = sanitize_key((string) $key);
            $label = wc_attribute_label((string) $key);
            $label_key = WFE_Placeholder_Resolver::normalize_key($label);
            $value_label = self::term_label((string) $key, (string) $value);

            $parts[] = $label . ': ' . $value_label;
            $data['variation_' . $clean_key] = $value_label;
            $data['variation:' . $clean_key] = $value_label;
            $data['variation:' . $raw_key] = $value_label;
            $data['variation:' . $label_key] = $value_label;
        }

        $data['variation_attributes'] = implode(', ', $parts);

        return $data;
    }

    private static function normalize_meta_key(string $key): string
    {
        return WFE_Placeholder_Resolver::normalize_key($key);
    }

    private static function shipping_methods(WC_Order $order): string
    {
        $methods = [];
        foreach ($order->get_shipping_methods() as $shipping) {
            $methods[] = $shipping->get_name();
        }
        return implode(', ', $methods);
    }

    private static function format_shipping_address(WC_Order $order): string
    {
        return trim(implode(', ', array_filter([
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_state(),
            $order->get_shipping_postcode(),
            $order->get_shipping_country(),
        ])));
    }

    private static function format_billing_address(WC_Order $order): string
    {
        return trim(implode(', ', array_filter([
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
            $order->get_billing_country(),
        ])));
    }

    private static function term_label(string $attribute, string $value): string
    {
        if (taxonomy_exists($attribute)) {
            $term = get_term_by('slug', $value, $attribute);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }
        return $value;
    }
}
