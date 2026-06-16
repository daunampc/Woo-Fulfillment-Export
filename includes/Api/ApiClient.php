<?php

defined('ABSPATH') || exit;

final class WFE_Api_Client
{
    private static array $memory_cache = [];

    public function resolve(array $connection, string $query): string
    {
        $result = $this->request($connection, $query);
        return !empty($result['success']) ? (string) ($result['value'] ?? '') : '';
    }

    public function request(array $connection, string $query): array
    {
        $query = trim($query);
        if ($query === '' || empty($connection['enabled']) || empty($connection['base_url'])) {
            return $this->error('Connection is disabled, incomplete, or query is empty.');
        }

        $cache_key = $this->cache_key((string) ($connection['key'] ?? ''), $query);
        if (isset(self::$memory_cache[$cache_key])) {
            return self::$memory_cache[$cache_key];
        }

        if (!empty($connection['cache_enabled'])) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                self::$memory_cache[$cache_key] = $cached;
                return $cached;
            }
        }

        $args = [
            'method' => strtoupper((string) ($connection['method'] ?? 'GET')),
            'timeout' => max(1, absint($connection['timeout'] ?? 15)),
            'headers' => $this->headers($connection),
        ];

        $params = $this->default_params((string) ($connection['default_params'] ?? ''));
        $dynamic_param = sanitize_key((string) ($connection['dynamic_param'] ?? 'query'));
        $params[$dynamic_param ?: 'query'] = $query;

        $url = (string) ($connection['base_url'] ?? '');
        if ($args['method'] === 'GET') {
            $url = add_query_arg($params, $url);
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = $params;
            $response = wp_remote_request($url, $args);
        }

        if (is_wp_error($response)) {
            $this->log_error($connection, $response->get_error_message());
            return $this->remember($cache_key, $this->error($response->get_error_message()), $connection);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300) {
            $message = 'API returned HTTP ' . $status;
            $this->log_error($connection, $message);
            return $this->remember($cache_key, $this->error($message, $status), $connection);
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            $message = 'API returned invalid JSON.';
            $this->log_error($connection, $message);
            return $this->remember($cache_key, $this->error($message, $status), $connection);
        }

        $path = (string) ($connection['response_path'] ?? 'data.0.URL');
        $value = WFE_Array_Helper::get($json, $path, '');
        $value = is_scalar($value) ? (string) $value : '';
        $data = WFE_Array_Helper::get($json, 'data', []);

        $result = [
            'success' => true,
            'status' => $status,
            'count' => is_array($data) ? count($data) : 0,
            'value' => $value,
            'error' => '',
        ];

        return $this->remember($cache_key, $result, $connection);
    }

    private function remember(string $cache_key, array $result, array $connection): array
    {
        self::$memory_cache[$cache_key] = $result;

        if (!empty($connection['cache_enabled']) && !empty($result['success'])) {
            set_transient($cache_key, $result, max(1, absint($connection['cache_ttl'] ?? 3600)));
        }

        return $result;
    }

    private function headers(array $connection): array
    {
        $headers = [];
        foreach ((array) ($connection['headers'] ?? []) as $header) {
            if (!is_array($header)) {
                continue;
            }
            $name = (string) ($header['name'] ?? '');
            $value = (string) ($header['value'] ?? '');
            if ($name !== '' && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function default_params(string $text): array
    {
        $params = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = sanitize_key($key);
            if ($key !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function cache_key(string $connection_key, string $query): string
    {
        return 'wfe_api_' . md5($connection_key . '|' . $query);
    }

    private function error(string $message, int $status = 0): array
    {
        return [
            'success' => false,
            'status' => $status,
            'count' => 0,
            'value' => '',
            'error' => $message,
        ];
    }

    private function log_error(array $connection, string $message): void
    {
        error_log('Woo Fulfillment Export API error [' . sanitize_key((string) ($connection['key'] ?? 'unknown')) . ']: ' . $message);
    }
}
