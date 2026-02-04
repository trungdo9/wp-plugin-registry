<?php
namespace WPPluginRegistry\GitHub;

/**
 * GitHub API Client for WordPress
 */
class GitHubClient {
    private $api_base = 'https://api.github.com';
    private $token;
    private $rate_limit_remaining = 60;
    private $rate_limit_reset;

    public function __construct($token = '') {
        $this->token = $token ?: $this->get_stored_token();
    }

    private function get_stored_token(): string {
        $options = get_option('wppr_settings', []);
        return $options['github_token'] ?? '';
    }

    /**
     * Parse GitHub URL to extract owner and repo
     */
    public function parse_url(string $url): ?array {
        $patterns = [
            '/github\.com\/([^\/]+)\/([^\/]+)/',
            '/^([^\/]+)\/([^\/]+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'owner' => $matches[1],
                    'repo' => preg_replace('/\.git$/', '', $matches[2]),
                ];
            }
        }

        return null;
    }

    /**
     * Get latest release information
     */
    public function get_latest_release(string $owner, string $repo): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases/latest";
        return $this->request($endpoint);
    }

    /**
     * List all releases
     */
    public function list_releases(string $owner, string $repo, int $per_page = 30): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases?per_page={$per_page}";
        return $this->request($endpoint);
    }

    /**
     * Get release by tag
     */
    public function get_release_by_tag(string $owner, string $repo, string $tag): array {
        $endpoint = "/repos/{$owner}/{$repo}/releases/tags/{$tag}";
        return $this->request($endpoint);
    }

    /**
     * Get repository information
     */
    public function get_repo_info(string $owner, string $repo): array {
        $endpoint = "/repos/{$owner}/{$repo}";
        return $this->request($endpoint);
    }

    /**
     * Get download URL for tarball
     */
    public function get_tarball_url(string $owner, string $repo, string $ref): string {
        $url = "https://api.github.com/repos/{$owner}/{$repo}/tarball/{$ref}";

        if (!empty($this->token)) {
            $url = "https://{$this->token}:x-oauth-basic@github.com/{$owner}/{$repo}/tarball/{$ref}";
        }

        return $url;
    }

    /**
     * Download tarball to file
     */
    public function download_tarball(string $owner, string $repo, string $ref, string $destination): array {
        $url = $this->get_tarball_url($owner, $repo, $ref);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => fopen($destination, 'wb'),
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'WP-Plugin-Registry/' . WPPR_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($this->token)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/vnd.github+json',
            ]);
        }

        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$success || $http_code !== 200) {
            @unlink($destination);
            return [
                'success' => false,
                'error' => $error ?: "HTTP {$http_code}",
            ];
        }

        return ['success' => true, 'path' => $destination];
    }

    /**
     * Make API request
     */
    private function request(string $endpoint): array {
        $url = $this->api_base . $endpoint;

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WP-Plugin-Registry/' . WPPR_VERSION,
            ],
        ];

        if (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Update rate limit info
        $this->update_rate_limit($response);

        if ($code >= 400) {
            return [
                'success' => false,
                'error' => json_decode($body, true)['message'] ?? 'API request failed',
                'code' => $code,
            ];
        }

        return [
            'success' => true,
            'data' => json_decode($body, true),
        ];
    }

    private function update_rate_limit($response): void {
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-ratelimit-remaining'])) {
            $this->rate_limit_remaining = (int) $headers['x-ratelimit-remaining'];
        }
        if (isset($headers['x-ratelimit-reset'])) {
            $this->rate_limit_reset = (int) $headers['x-ratelimit-reset'];
        }
    }

    public function get_rate_limit_status(): array {
        return [
            'remaining' => $this->rate_limit_remaining,
            'reset' => $this->rate_limit_reset,
        ];
    }
}
