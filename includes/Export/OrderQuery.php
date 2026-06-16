<?php

defined('ABSPATH') || exit;

final class WFE_Order_Query
{
    public function get_orders(array $filters = [])
    {
        $limit = max(1, absint($filters['limit'] ?? 30));
        $page = max(1, absint($filters['page'] ?? 1));

        if ($this->requires_php_filtering($filters)) {
            $settings = WFE_Settings::all();
            $scan_limit = max($limit * $page, absint($settings['scan_limit'] ?? 2000));
            $collection = $this->collect_filtered_orders($filters, $scan_limit);
            $total = count($collection['orders']);
            $offset = ($page - 1) * $limit;

            return (object) [
                'orders' => array_slice($collection['orders'], $offset, $limit),
                'total' => $total,
                'max_num_pages' => max(1, (int) ceil($total / $limit)),
                'truncated' => $collection['truncated'],
            ];
        }

        $args = [
            'limit' => $limit,
            'page' => $page,
            'paginate' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => $this->statuses_for_query((array) ($filters['status'] ?? [])),
        ];

        $args = $this->apply_date_filters($args, $filters);

        return wc_get_orders($args);
    }

    public function collect_order_ids_for_export(array $filters, int $limit): array
    {
        $limit = max(1, $limit);
        $collection = $this->collect_filtered_orders($filters, $limit);

        return [
            'order_ids' => array_map(static fn(WC_Order $order) => $order->get_id(), $collection['orders']),
            'truncated' => $collection['truncated'],
        ];
    }


    public static function fulfillment_status_options(): array
    {
        $all = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $options = [];

        foreach (['wc-processing', 'wc-fulfillment'] as $key) {
            $options[$key] = $all[$key] ?? ($key === 'wc-processing' ? __('Processing', 'woo-fulfillment-export') : __('Fulfillment', 'woo-fulfillment-export'));
        }

        return $options;
    }

    public static function fulfillment_status_slugs(): array
    {
        return array_map(static function ($key) {
            return str_replace('wc-', '', (string) $key);
        }, array_keys(self::fulfillment_status_options()));
    }

    public static function sanitize_statuses(array $statuses): array
    {
        $clean = [];
        $known = array_map(static fn($status) => str_replace('wc-', '', (string) $status), array_keys(wc_get_order_statuses()));

        foreach ($statuses as $status) {
            $status = sanitize_key((string) $status);
            $status = preg_replace('/^wc-/', '', $status);
            if ($status === 'any') {
                return $known;
            }
            if ($status !== '' && in_array($status, $known, true)) {
                $clean[] = $status;
            }
        }

        return array_values(array_unique($clean));
    }

    private function collect_filtered_orders(array $filters, int $limit): array
    {
        $settings = WFE_Settings::all();
        $scan_limit = max($limit, absint($settings['scan_limit'] ?? 2000));
        $base_limit = 100;
        $page = 1;
        $orders = [];
        $scanned = 0;
        $truncated = false;
        $direct_order = $this->direct_order_from_query((string) ($filters['order_query'] ?? ''));

        if ($direct_order instanceof WC_Order && WFE_Product_Helper::order_matches_status_date($direct_order, $filters) && WFE_Product_Helper::order_matches_filters($direct_order, $filters)) {
            $orders[] = $direct_order;
            if (count($orders) >= $limit) {
                return [
                    'orders' => $orders,
                    'truncated' => false,
                ];
            }
        }

        do {
            $args = [
                'limit' => $base_limit,
                'page' => $page,
                'paginate' => true,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => $this->statuses_for_query((array) ($filters['status'] ?? [])),
            ];
            $args = $this->apply_date_filters($args, $filters);

            $result = wc_get_orders($args);
            $batch = is_object($result) && isset($result->orders) ? $result->orders : [];

            foreach ($batch as $order) {
                if (!$order instanceof WC_Order) {
                    continue;
                }

                $scanned++;
                if ($direct_order instanceof WC_Order && $order->get_id() === $direct_order->get_id()) {
                    continue;
                }

                if (WFE_Product_Helper::order_matches_filters($order, $filters)) {
                    $orders[] = $order;
                    if (count($orders) >= $limit) {
                        $truncated = true;
                        break 2;
                    }
                }

                if ($scanned >= $scan_limit) {
                    $truncated = true;
                    break 2;
                }
            }

            $page++;
            $max_pages = is_object($result) && isset($result->max_num_pages) ? (int) $result->max_num_pages : 0;
        } while ($batch && ($max_pages === 0 || $page <= $max_pages));

        return [
            'orders' => $orders,
            'truncated' => $truncated,
        ];
    }

    private function requires_php_filtering(array $filters): bool
    {
        foreach (['order_query', 'customer', 'product', 'sku', 'category'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function statuses_for_query(array $statuses): array
    {
        $statuses = self::sanitize_statuses($statuses);
        if (!$statuses) {
            $statuses = WFE_Settings::get('default_statuses', self::fulfillment_status_slugs());
        }

        $allowed = self::fulfillment_status_slugs();
        $statuses = array_values(array_intersect($statuses, $allowed));

        return $statuses ?: $allowed;
    }

    private function apply_date_filters(array $args, array $filters): array
    {
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $from = sanitize_text_field($filters['date_from'] ?? '1970-01-01');
            $to = sanitize_text_field($filters['date_to'] ?? current_time('Y-m-d'));

            if ($from === '') {
                $from = '1970-01-01';
            }
            if ($to === '') {
                $to = current_time('Y-m-d');
            }

            $args['date_created'] = $from . '...' . $to;
        }

        return $args;
    }

    private function direct_order_from_query(string $query): ?WC_Order
    {
        $query = trim(ltrim($query, '#'));
        if ($query === '' || !ctype_digit($query)) {
            return null;
        }

        $order = wc_get_order((int) $query);
        return $order instanceof WC_Order ? $order : null;
    }
}
