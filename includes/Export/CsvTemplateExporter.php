<?php

defined('ABSPATH') || exit;

final class WFE_Csv_Template_Exporter
{
    public function download(array $template, array $mapping, array $rows): void
    {
        $tmp = wp_tempnam('wfe-export.csv');
        if (!$tmp) {
            wp_die(esc_html__('Could not prepare CSV export file.', 'woo-fulfillment-export'));
        }

        $this->save_file($template, $mapping, $rows, $tmp);

        $filename = sanitize_file_name('fulfillment-orders-' . current_time('Y-m-d-His') . '.csv');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public function save_file(array $template, array $mapping, array $rows, string $target): void
    {
        $columns = WFE_Mapping_Repository::export_columns($template, $mapping);
        if (!$columns) {
            wp_die(esc_html__('No mapped columns found for CSV export.', 'woo-fulfillment-export'));
        }

        $output = fopen($target, 'w');
        if (!$output) {
            wp_die(esc_html__('Could not open CSV output stream.', 'woo-fulfillment-export'));
        }

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, array_map(static fn($column) => (string) ($column['header'] ?? ''), $columns));

        foreach ($rows as $row_data) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = WFE_Mapping_Repository::value_for_column($column, is_array($row_data) ? $row_data : []);
            }
            fputcsv($output, $line);
        }

        fclose($output);
    }
}
