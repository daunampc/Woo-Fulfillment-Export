<?php

defined('ABSPATH') || exit;

final class WFE_Dynamic_Api_Resolver
{
    public static function resolve_placeholders(string $template, array $data): string
    {
        return preg_replace_callback('/\{api:([a-zA-Z0-9_\-]+):((?:\{[^{}]+\})|[^{}]*)\}/', static function ($matches) use ($data) {
            $connection_key = WFE_Api_Connection_Repository::sanitize_key((string) ($matches[1] ?? ''));
            $raw_query = (string) ($matches[2] ?? '');
            if ($connection_key === '') {
                return '';
            }

            $query = WFE_Placeholder_Resolver::resolve($raw_query, $data);
            $query = trim($query);
            if ($query === '') {
                return '';
            }

            $connection = (new WFE_Api_Connection_Repository())->find($connection_key);
            if (!$connection) {
                error_log('Woo Fulfillment Export API error [' . $connection_key . ']: connection not found.');
                return '';
            }

            return (new WFE_Api_Client())->resolve($connection, $query);
        }, $template) ?? '';
    }
}
