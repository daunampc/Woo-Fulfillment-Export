<?php

defined('ABSPATH') || exit;

final class WFE_Mapping_Repository
{
    private string $option = 'wfe_template_mappings';

    public function all(): array
    {
        $mappings = get_option($this->option, []);
        return is_array($mappings) ? $mappings : [];
    }

    public function find(string $template_id): ?array
    {
        $all = $this->all();
        if (empty($all[$template_id]) || !is_array($all[$template_id])) {
            return null;
        }

        return $this->normalize($all[$template_id]);
    }

    public function save(string $template_id, array $mapping): void
    {
        $all = $this->all();
        $all[$template_id] = $this->normalize($mapping);
        update_option($this->option, $all, false);
    }

    public function delete(string $template_id): void
    {
        $all = $this->all();
        unset($all[$template_id]);
        update_option($this->option, $all, false);
    }

    public static function parse_columns_text(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $columns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$column, $value] = array_map('trim', explode('=', $line, 2));
            $column = strtoupper(preg_replace('/[^A-Z]/i', '', $column));
            if ($column === '') {
                continue;
            }

            $columns[$column] = $value;
        }

        return $columns;
    }

    public static function columns_to_text(array $columns): string
    {
        $lines = [];
        foreach ($columns as $column => $mapping) {
            if ((string) $mapping === '') {
                continue;
            }
            $lines[] = strtoupper((string) $column) . '=' . (string) $mapping;
        }
        return implode("\n", $lines);
    }

    public static function sanitize_columns_array(array $values): array
    {
        $clean = [];

        foreach ($values as $column => $value) {
            $column = self::sanitize_column_key((string) $column);
            if ($column === '') {
                continue;
            }

            $value = sanitize_text_field((string) $value);
            if ($value === '') {
                continue;
            }

            $clean[$column] = $value;
        }

        return $clean;
    }

    public static function sanitize_labels_array(array $values): array
    {
        $clean = [];

        foreach ($values as $column => $value) {
            $column = self::sanitize_column_key((string) $column);
            if ($column === '') {
                continue;
            }

            $value = sanitize_text_field((string) $value);
            if ($value === '') {
                continue;
            }

            $clean[$column] = $value;
        }

        return $clean;
    }

    public static function sanitize_column_key(string $column): string
    {
        $column = strtoupper(preg_replace('/[^A-Z]/i', '', $column));
        return $column ?: '';
    }

    public static function column_letter(int $index): string
    {
        $index = max(1, $index);
        $letter = '';

        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = (int) floor($index / 26);
        }

        return $letter;
    }

    public static function column_index(string $column): int
    {
        $column = self::sanitize_column_key($column);
        $result = 0;

        for ($i = 0, $len = strlen($column); $i < $len; $i++) {
            $result = $result * 26 + (ord($column[$i]) - 64);
        }

        return $result;
    }

    public static function columns_from_manual_template(array $columns): array
    {
        $result = [];
        foreach ($columns as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = self::sanitize_column_key((string) ($column['column'] ?? self::column_letter((int) $index + 1)));
            $mapping = sanitize_text_field((string) ($column['mapping'] ?? ''));
            $default = sanitize_text_field((string) ($column['default'] ?? ''));
            $header = sanitize_text_field((string) ($column['header'] ?? ''));
            if ($key !== '' && ($mapping !== '' || $default !== '' || $header !== '')) {
                $result[$key] = $mapping;
            }
        }
        return $result;
    }

    public static function headers_from_manual_template(array $columns): array
    {
        $result = [];
        foreach ($columns as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = self::sanitize_column_key((string) ($column['column'] ?? self::column_letter((int) $index + 1)));
            $header = sanitize_text_field((string) ($column['header'] ?? ''));
            if ($key !== '' && $header !== '') {
                $result[$key] = $header;
            }
        }
        return $result;
    }

    public static function defaults_from_manual_template(array $columns): array
    {
        $result = [];
        foreach ($columns as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = self::sanitize_column_key((string) ($column['column'] ?? self::column_letter((int) $index + 1)));
            $default = sanitize_text_field((string) ($column['default'] ?? ''));
            if ($key !== '' && $default !== '') {
                $result[$key] = $default;
            }
        }
        return $result;
    }

    public static function mapping_from_manual_template(array $template): array
    {
        $columns = is_array($template['columns'] ?? null) ? $template['columns'] : [];

        return [
            'sheet_index' => 0,
            'header_row' => 1,
            'start_row' => 2,
            'row_mode' => WFE_Settings::get('row_mode', 'item_per_row'),
            'one_row_per' => WFE_Settings::get('row_mode', 'item_per_row') === 'order_per_row' ? 'order' : 'item',
            'columns' => self::columns_from_manual_template($columns),
            'headers' => self::headers_from_manual_template($columns),
            'defaults' => self::defaults_from_manual_template($columns),
            'raw' => self::columns_to_text(self::columns_from_manual_template($columns)),
        ];
    }

    public static function export_columns(array $template, array $mapping): array
    {
        $columns = is_array($mapping['columns'] ?? null) ? $mapping['columns'] : [];
        $headers = is_array($mapping['headers'] ?? null) ? $mapping['headers'] : [];
        $defaults = is_array($mapping['defaults'] ?? null) ? $mapping['defaults'] : [];

        if (!$columns && ($template['source'] ?? '') === 'manual') {
            $manual_mapping = self::mapping_from_manual_template($template);
            $columns = $manual_mapping['columns'];
            $headers = $manual_mapping['headers'];
            $defaults = $manual_mapping['defaults'];
        }

        $keys = array_values(array_unique(array_merge(array_keys($headers), array_keys($columns), array_keys($defaults))));
        $result = [];
        foreach ($keys as $column) {
            $column = self::sanitize_column_key((string) $column);
            if ($column === '') {
                continue;
            }

            $expression = (string) ($columns[$column] ?? '');
            $header = sanitize_text_field((string) ($headers[$column] ?? $column));
            $default = sanitize_text_field((string) ($defaults[$column] ?? ''));
            if ($expression === '' && $default === '' && $header === '') {
                continue;
            }

            $result[$column] = [
                'column' => $column,
                'header' => $header,
                'mapping' => sanitize_text_field((string) $expression),
                'default' => $default,
            ];
        }

        uksort($result, static function ($a, $b) {
            return self::column_index($a) <=> self::column_index($b);
        });

        return $result;
    }

    public static function value_for_column(array $column, array $row_data): string
    {
        $mapping = (string) ($column['mapping'] ?? '');
        $default = (string) ($column['default'] ?? '');
        $value = $mapping !== '' ? WFE_Placeholder_Resolver::resolve($mapping, $row_data) : '';

        if ($value === '' && $default !== '') {
            $value = WFE_Placeholder_Resolver::resolve($default, $row_data);
        }

        return $value;
    }

    private function normalize(array $mapping): array
    {
        $row_mode = sanitize_key((string) ($mapping['row_mode'] ?? ($mapping['one_row_per'] ?? 'item_per_row')));
        if ($row_mode === 'item') {
            $row_mode = 'item_per_row';
        }
        if ($row_mode === 'order') {
            $row_mode = 'order_per_row';
        }
        if (!in_array($row_mode, ['item_per_row', 'order_per_row'], true)) {
            $row_mode = 'item_per_row';
        }

        $columns = self::sanitize_columns_array(is_array($mapping['columns'] ?? null) ? $mapping['columns'] : []);
        $headers = self::sanitize_labels_array(is_array($mapping['headers'] ?? null) ? $mapping['headers'] : []);
        $defaults = self::sanitize_columns_array(is_array($mapping['defaults'] ?? null) ? $mapping['defaults'] : []);

        return [
            'sheet_index' => max(0, absint($mapping['sheet_index'] ?? 0)),
            'header_row' => max(1, absint($mapping['header_row'] ?? 1)),
            'start_row' => max(1, absint($mapping['start_row'] ?? 2)),
            'row_mode' => $row_mode,
            'one_row_per' => $row_mode === 'order_per_row' ? 'order' : 'item',
            'columns' => $columns,
            'headers' => $headers,
            'defaults' => $defaults,
            'raw' => sanitize_textarea_field((string) ($mapping['raw'] ?? self::columns_to_text($columns))),
            'updated_at' => sanitize_text_field((string) ($mapping['updated_at'] ?? '')),
        ];
    }
}
