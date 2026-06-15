<?php

defined('ABSPATH') || exit;

final class WFE_Order_Formatter
{
    public function format_orders(array $order_ids, string $one_row_per = 'item'): array
    {
        $rows = [];
        $row_mode = $this->normalize_row_mode($one_row_per);

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                continue;
            }

            if ($row_mode === 'order_per_row') {
                $rows[] = $this->format_order_row($order);
                continue;
            }

            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $base = WFE_Product_Helper::extract_item_data($order, $item);
                $wcpa = WFE_WCPA_Helper::extract_wcpa_data($item);
                $rows[] = array_merge($base, $wcpa);
            }
        }

        return $rows;
    }

    public function preview_order(int $order_id, string $row_mode = 'item_per_row'): ?array
    {
        $rows = $this->format_orders([$order_id], $row_mode);
        return $rows[0] ?? null;
    }

    private function format_order_row(WC_Order $order): array
    {
        $data = WFE_Product_Helper::order_level_data($order);
        $product_lines = [];
        $product_names = [];
        $product_ids = [];
        $variation_ids = [];
        $skus = [];
        $quantities = [];
        $categories = [];
        $variation_parts = [];
        $line_subtotals = [];
        $line_totals = [];
        $image_urls = [];
        $image_ids = [];
        $wcpa_parts = [];

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $item_data = WFE_Product_Helper::extract_item_data($order, $item);
            $product_lines[] = $item->get_name() . ' x ' . $item->get_quantity();
            $product_names[] = $item->get_name();
            $product_ids[] = (string) $item->get_product_id();
            if ($item->get_variation_id()) {
                $variation_ids[] = (string) $item->get_variation_id();
            }
            $sku = WFE_Product_Helper::item_sku($item);
            if ($sku !== '') {
                $skus[] = $sku;
            }
            $quantities[] = (string) $item->get_quantity();
            $line_subtotals[] = (string) $item->get_subtotal();
            $line_totals[] = (string) $item->get_total();
            foreach (WFE_Product_Helper::item_categories($item) as $term) {
                $categories[] = $term->name;
            }
            if (!empty($item_data['variation_attributes'])) {
                $variation_parts[] = $item_data['variation_attributes'];
            }
            if (!empty($item_data['product_image_url'])) {
                $image_urls[] = $item_data['product_image_url'];
            }
            if (!empty($item_data['product_image_id'])) {
                $image_ids[] = $item_data['product_image_id'];
            }

            $wcpa = WFE_WCPA_Helper::extract_wcpa_data($item);
            foreach ($wcpa as $key => $value) {
                if ($value !== '') {
                    $wcpa_parts[] = $key . ': ' . $value;
                    if (empty($data[$key])) {
                        $data[$key] = $value;
                    }
                }
            }
        }

        $data['products'] = implode("\n", $product_lines);
        $data['product_id'] = implode(', ', array_unique($product_ids));
        $data['variation_id'] = implode(', ', array_unique($variation_ids));
        $data['product_name'] = implode(', ', array_unique($product_names));
        $data['product_sku'] = implode(', ', array_unique($skus));
        $data['product_skus'] = implode(', ', array_unique($skus));
        $data['quantities'] = implode(', ', $quantities);
        $data['quantity'] = implode(', ', $quantities);
        $data['product_categories'] = implode(', ', array_unique($categories));
        $data['variation_attributes'] = implode("\n", array_unique($variation_parts));
        $data['line_subtotal'] = implode(', ', $line_subtotals);
        $data['line_total'] = implode(', ', $line_totals);
        $data['product_image'] = implode(', ', array_unique($image_urls));
        $data['product_image_url'] = implode(', ', array_unique($image_urls));
        $data['product_image_id'] = implode(', ', array_unique($image_ids));
        $data['wcpa_all'] = implode("\n", $wcpa_parts);

        return $data;
    }

    private function normalize_row_mode(string $row_mode): string
    {
        if ($row_mode === 'order') {
            return 'order_per_row';
        }
        if ($row_mode === 'item') {
            return 'item_per_row';
        }

        return $row_mode === 'order_per_row' ? 'order_per_row' : 'item_per_row';
    }
}
