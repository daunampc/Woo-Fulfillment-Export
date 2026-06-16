<?php

defined('ABSPATH') || exit;

final class WFE_GitHub_Updater
{
    private string $plugin_basename;

    public function __construct()
    {
        $this->plugin_basename = plugin_basename(WFE_FILE);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_pre_download', [$this, 'download_private_package'], 10, 3);
    }

    public function check_for_update($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $settings = WFE_Settings::all();
        $repo = trim((string) ($settings['github_repo'] ?? ''));
        if ($repo === '' || strpos($repo, '/') === false) {
            return $transient;
        }

        $release = $this->latest_release($repo, (string) ($settings['github_token'] ?? ''));
        if (!$release) {
            return $transient;
        }

        $remote_version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
        if ($remote_version === '' || version_compare($remote_version, WFE_VERSION, '<=')) {
            return $transient;
        }

        $package = $this->package_url($release, $repo);
        if ($package === '') {
            return $transient;
        }

        $transient->response[$this->plugin_basename] = (object) [
            'id' => $this->plugin_basename,
            'slug' => dirname($this->plugin_basename),
            'plugin' => $this->plugin_basename,
            'new_version' => $remote_version,
            'url' => 'https://github.com/' . $repo,
            'package' => $package,
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
        ];

        return $transient;
    }

    public function plugin_info($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== dirname($this->plugin_basename)) {
            return $result;
        }

        $settings = WFE_Settings::all();
        $repo = trim((string) ($settings['github_repo'] ?? ''));
        if ($repo === '' || strpos($repo, '/') === false) {
            return $result;
        }

        $release = $this->latest_release($repo, (string) ($settings['github_token'] ?? ''));
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'Woo Fulfillment Export',
            'slug' => dirname($this->plugin_basename),
            'version' => ltrim((string) ($release['tag_name'] ?? WFE_VERSION), 'vV'),
            'author' => 'Admin',
            'homepage' => 'https://github.com/' . $repo,
            'download_link' => $this->package_url($release, $repo),
            'sections' => [
                'description' => 'WooCommerce fulfillment export with CSV/XLSX templates.',
                'changelog' => wp_kses_post((string) ($release['body'] ?? '')),
            ],
        ];
    }

    public function download_private_package($reply, string $package, $upgrader)
    {
        if (strpos($package, 'api.github.com/repos/') === false && strpos($package, 'github.com/') === false) {
            return $reply;
        }

        $settings = WFE_Settings::all();
        $token = trim((string) ($settings['github_token'] ?? ''));
        if ($token === '') {
            return $reply;
        }

        $tmp = wp_tempnam($package);
        if (!$tmp) {
            return $reply;
        }

        $response = wp_remote_get($package, [
            'timeout' => 300,
            'stream' => true,
            'filename' => $tmp,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Woo Fulfillment Export',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
            @unlink($tmp);
            return $reply;
        }

        return $tmp;
    }

    private function latest_release(string $repo, string $token): ?array
    {
        $cache_key = 'wfe_github_release_' . md5($repo . '|' . ($token !== '' ? 'token' : 'public'));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get('https://api.github.com/repos/' . rawurlencode(explode('/', $repo)[0]) . '/' . rawurlencode(explode('/', $repo)[1]) . '/releases/latest', [
            'timeout' => 15,
            'headers' => array_filter([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Woo Fulfillment Export',
                'Authorization' => $token !== '' ? 'Bearer ' . $token : null,
            ]),
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return null;
        }

        set_transient($cache_key, $body, 30 * MINUTE_IN_SECONDS);
        return $body;
    }

    private function package_url(array $release, string $repo): string
    {
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                $name = (string) ($asset['name'] ?? '');
                if (substr($name, -4) === '.zip' && !empty($asset['browser_download_url'])) {
                    return (string) $asset['browser_download_url'];
                }
            }
        }

        $branch = sanitize_text_field((string) WFE_Settings::get('github_branch', 'main'));
        return (string) ($release['zipball_url'] ?? ('https://github.com/' . $repo . '/archive/refs/heads/' . rawurlencode($branch ?: 'main') . '.zip'));
    }
}
