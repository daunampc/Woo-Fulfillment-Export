<?php

defined('ABSPATH') || exit;

final class WFE_Template_Repository
{
    private string $option = 'wfe_templates';

    public static function templates_dir(): string
    {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'woo-fulfillment-export/templates';
    }

    public static function legacy_templates_dir(): string
    {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'wfe-templates';
    }

    public static function is_template_path(string $path): bool
    {
        $real = wp_normalize_path($path);

        foreach ([self::templates_dir(), self::legacy_templates_dir()] as $dir) {
            $base = wp_normalize_path(trailingslashit($dir));
            if (strpos($real, $base) === 0) {
                return true;
            }
        }

        return false;
    }

    public function all(): array
    {
        $templates = get_option($this->option, []);
        if (!is_array($templates)) {
            return [];
        }

        $normalized = [];
        $changed = false;

        foreach ($templates as $id => $template) {
            if (!is_array($template)) {
                $changed = true;
                continue;
            }

            $template = $this->normalize($template, (string) $id);
            $normalized[$template['id']] = $template;
            if ($template !== ($templates[$id] ?? null)) {
                $changed = true;
            }
        }

        if ($changed) {
            update_option($this->option, $normalized, false);
        }

        return $normalized;
    }

    public function find(string $id): ?array
    {
        $all = $this->all();
        return $all[$id] ?? null;
    }

    public function create(string $name, string $file_path, string $filename): string
    {
        return $this->create_uploaded($name, $file_path, $filename, strtolower(pathinfo($filename, PATHINFO_EXTENSION)));
    }

    public function create_uploaded(string $name, string $file_path, string $file_name, string $file_type): string
    {
        $all = $this->all();
        $id = uniqid('tpl_', true);
        $now = current_time('mysql');
        $all[$id] = [
            'id' => $id,
            'name' => sanitize_text_field($name),
            'source' => 'upload',
            'file_path' => $file_path,
            'file_name' => sanitize_file_name($file_name),
            'filename' => sanitize_file_name($file_name),
            'file_type' => in_array($file_type, ['csv', 'xlsx'], true) ? $file_type : 'xlsx',
            'columns' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        update_option($this->option, $all, false);
        return $id;
    }

    public function save_manual(string $id, string $name, string $file_type, array $columns): string
    {
        $all = $this->all();
        $id = $id !== '' && isset($all[$id]) ? $id : uniqid('tpl_', true);
        $existing = $all[$id] ?? [];
        $now = current_time('mysql');

        $all[$id] = [
            'id' => $id,
            'name' => sanitize_text_field($name),
            'source' => 'manual',
            'file_path' => '',
            'file_name' => '',
            'filename' => '',
            'file_type' => in_array($file_type, ['csv', 'xlsx'], true) ? $file_type : 'csv',
            'columns' => $this->sanitize_columns($columns),
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        update_option($this->option, $all, false);
        return $id;
    }

    public function delete(string $id): void
    {
        $all = $this->all();
        if (!empty($all[$id]['file_path']) && self::is_template_path($all[$id]['file_path']) && file_exists($all[$id]['file_path'])) {
            @unlink($all[$id]['file_path']);
        }
        unset($all[$id]);
        update_option($this->option, $all, false);

        $mapping_repo = new WFE_Mapping_Repository();
        $mapping_repo->delete($id);
    }

    private function normalize(array $template, string $fallback_id): array
    {
        $file_name = (string) ($template['file_name'] ?? ($template['filename'] ?? ''));
        $file_type = strtolower((string) ($template['file_type'] ?? pathinfo($file_name, PATHINFO_EXTENSION)));
        if (!in_array($file_type, ['csv', 'xlsx'], true)) {
            $file_type = 'xlsx';
        }

        $source = (string) ($template['source'] ?? '');
        if (!in_array($source, ['upload', 'manual'], true)) {
            $source = !empty($template['columns']) && empty($template['file_path']) ? 'manual' : 'upload';
        }

        return [
            'id' => sanitize_text_field((string) ($template['id'] ?? $fallback_id)),
            'name' => sanitize_text_field((string) ($template['name'] ?? __('Untitled template', 'woo-fulfillment-export'))),
            'source' => $source,
            'file_path' => (string) ($template['file_path'] ?? ''),
            'file_name' => sanitize_file_name($file_name),
            'filename' => sanitize_file_name($file_name),
            'file_type' => $file_type,
            'columns' => $this->sanitize_columns(is_array($template['columns'] ?? null) ? $template['columns'] : []),
            'created_at' => sanitize_text_field((string) ($template['created_at'] ?? current_time('mysql'))),
            'updated_at' => sanitize_text_field((string) ($template['updated_at'] ?? ($template['created_at'] ?? current_time('mysql')))),
        ];
    }

    private function sanitize_columns(array $columns): array
    {
        $clean = [];
        $position = 1;

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }

            $header = sanitize_text_field((string) ($column['header'] ?? ''));
            $mapping = sanitize_text_field((string) ($column['mapping'] ?? ''));
            $default = sanitize_text_field((string) ($column['default'] ?? ''));

            if ($header === '' && $mapping === '' && $default === '') {
                continue;
            }

            $clean[] = [
                'column' => WFE_Mapping_Repository::column_letter($position),
                'header' => $header !== '' ? $header : sprintf(__('Column %s', 'woo-fulfillment-export'), WFE_Mapping_Repository::column_letter($position)),
                'mapping' => $mapping,
                'default' => $default,
            ];
            $position++;
        }

        return $clean;
    }
}
