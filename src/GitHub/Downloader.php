<?php
namespace WPPluginRegistry\GitHub;

/**
 * Download and extract GitHub repositories
 */
class Downloader {
    private GitHubClient $github;

    public function __construct(GitHubClient $github) {
        $this->github = $github;
    }

    /**
     * Download and extract GitHub repository
     */
    public function download_and_extract(string $owner, string $repo, string $ref, string $slug): array {
        // Create temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'wppr_');
        $temp_file = $temp_file . '.tar.gz';
        rename($temp_file, $temp_file);

        // Download
        $result = $this->github->download_tarball($owner, $repo, $ref, $temp_file);

        if (!$result['success']) {
            @unlink($temp_file);
            return $result;
        }

        // Extract
        $extract_result = $this->extract_to_plugins($temp_file, $slug);

        // Cleanup temp file
        @unlink($temp_file);

        if (!$extract_result['success']) {
            return $extract_result;
        }

        return [
            'success' => true,
            'path' => $extract_result['path'],
        ];
    }

    /**
     * Extract tarball to plugins directory
     */
    private function extract_to_plugins(string $tarball, string $slug): array {
        $plugins_dir = WPPR_PLUGINS_DIR;

        if (!is_dir($plugins_dir)) {
            @mkdir($plugins_dir, 0755, true);
        }

        if (!class_exists('PharData')) {
            return [
                'success' => false,
                'error' => __('PharData class not available', 'wp-plugin-registry'),
            ];
        }

        try {
            $phar = new \PharData($tarball);

            // Get root folder name
            $root_folder = $this->get_root_folder($phar);

            if (!$root_folder) {
                return [
                    'success' => false,
                    'error' => __('Could not determine root folder', 'wp-plugin-registry'),
                ];
            }

            $destination = $plugins_dir . '/' . $slug;

            // Clean up existing installation
            if (is_dir($destination)) {
                $this->delete_directory($destination);
            }

            // Extract
            $phar->extractTo($destination, null, true);

            return [
                'success' => true,
                'path' => $destination,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get root folder name from archive
     */
    private function get_root_folder(\PharData $phar): ?string {
        foreach ($phar as $entry) {
            $filename = $entry->getFilename();
            $parts = explode('/', $filename);
            if (count($parts) > 1) {
                return $parts[0];
            }
        }
        return null;
    }

    /**
     * Delete directory recursively
     */
    private function delete_directory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }

        return @rmdir($dir);
    }
}
