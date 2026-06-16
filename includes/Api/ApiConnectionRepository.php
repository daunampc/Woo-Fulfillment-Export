<?php

defined('ABSPATH') || exit;

final class WFE_Api_Connection_Repository
{
    private string $option = 'wfe_api_connections';

    public function all(): array
    {
        $connections = get_option($this->option, null);
        if (!is_array($connections)) {
            $connections = [
                'vtn_tasksave_url' => $this->default_vtn_connection(),
            ];
            update_option($this->option, $connections, false);
        }

        $clean = [];
        foreach ($connections as $key => $connection) {
            if (!is_array($connection)) {
                continue;
            }
            $normalized = $this->normalize($connection, (string) $key);
            if ($normalized['key'] !== '') {
                $clean[$normalized['key']] = $normalized;
            }
        }

        return $clean;
    }

    public function find(string $key): ?array
    {
        $key = self::sanitize_key($key);
        $all = $this->all();
        return $all[$key] ?? null;
    }

    public function save(array $connection, string $previous_key = ''): string
    {
        $all = $this->all();
        $previous_key = self::sanitize_key($previous_key);
        $existing = $previous_key !== '' && isset($all[$previous_key]) ? $all[$previous_key] : [];
        $connection = $this->normalize($connection, (string) ($connection['key'] ?? $previous_key), $existing);

        if ($connection['key'] === '') {
            return '';
        }

        if ($previous_key !== '' && $previous_key !== $connection['key']) {
            unset($all[$previous_key]);
        }

        $all[$connection['key']] = $connection;
        update_option($this->option, $all, false);

        return $connection['key'];
    }

    public function delete(string $key): void
    {
        $key = self::sanitize_key($key);
        $all = $this->all();
        unset($all[$key]);
        update_option($this->option, $all, false);
    }

    public static function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', sanitize_text_field($key)) ?: '';
    }

    public static function sanitize_response_path(string $path): string
    {
        return preg_replace('/[^a-zA-Z0-9_.\-]/', '', sanitize_text_field($path)) ?: '';
    }

    private function normalize(array $connection, string $fallback_key = '', array $existing = []): array
    {
        $key = self::sanitize_key((string) ($connection['key'] ?? $fallback_key));
        $method = strtoupper(sanitize_text_field((string) ($connection['method'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'GET';
        }

        return [
            'name' => sanitize_text_field((string) ($connection['name'] ?? '')),
            'key' => $key,
            'base_url' => esc_url_raw((string) ($connection['base_url'] ?? '')),
            'method' => $method,
            'default_params' => $this->sanitize_params_text((string) ($connection['default_params'] ?? '')),
            'dynamic_param' => sanitize_key((string) ($connection['dynamic_param'] ?? 'query')),
            'response_path' => self::sanitize_response_path((string) ($connection['response_path'] ?? 'data.0.URL')),
            'timeout' => max(1, min(60, absint($connection['timeout'] ?? 15))),
            'enabled' => !empty($connection['enabled']) ? 1 : 0,
            'cache_enabled' => !empty($connection['cache_enabled']) ? 1 : 0,
            'cache_ttl' => max(1, min(DAY_IN_SECONDS, absint($connection['cache_ttl'] ?? 3600))),
            'headers' => $this->sanitize_headers(is_array($connection['headers'] ?? null) ? $connection['headers'] : [], is_array($existing['headers'] ?? null) ? $existing['headers'] : []),
            'updated_at' => current_time('mysql'),
        ];
    }

    private function default_vtn_connection(): array
    {
        return [
            'name' => 'VTN TaskSave',
            'key' => 'vtn_tasksave_url',
            'base_url' => 'https://api.vtnpoddesign.com/api/tasks/tasksave',
            'method' => 'GET',
            'default_params' => "page=1\nlimit=32",
            'dynamic_param' => 'query',
            'response_path' => 'data.0.URL',
            'timeout' => 15,
            'enabled' => 1,
            'cache_enabled' => 1,
            'cache_ttl' => 3600,
            'headers' => [
                ['name' => 'Accept', 'value' => 'application/json'],
            ],
            'updated_at' => current_time('mysql'),
        ];
    }

    private function sanitize_params_text(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = sanitize_key($key);
            if ($key === '') {
                continue;
            }
            $clean[] = $key . '=' . sanitize_text_field($value);
        }

        return implode("\n", $clean);
    }

    private function sanitize_headers(array $headers, array $existing_headers = []): array
    {
        $existing = [];
        foreach ($existing_headers as $header) {
            if (!is_array($header)) {
                continue;
            }
            $name = $this->sanitize_header_name((string) ($header['name'] ?? ''));
            if ($name !== '') {
                $existing[strtolower($name)] = (string) ($header['value'] ?? '');
            }
        }

        $clean = [];
        $names = is_array($headers['name'] ?? null) ? $headers['name'] : wp_list_pluck($headers, 'name');
        $values = is_array($headers['value'] ?? null) ? $headers['value'] : wp_list_pluck($headers, 'value');

        foreach ($names as $index => $name) {
            $name = $this->sanitize_header_name((string) $name);
            $value = isset($values[$index]) ? sanitize_text_field((string) $values[$index]) : '';
            if ($name === '') {
                continue;
            }

            if ($value === '' && isset($existing[strtolower($name)])) {
                $value = $existing[strtolower($name)];
            }

            $clean[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $clean;
    }

    private function sanitize_header_name(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', sanitize_text_field($name)) ?: '';
    }
}
