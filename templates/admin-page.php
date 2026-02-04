<?php
/**
 * Admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugins = $plugins ?? [];
$options = $options ?? [];
?>

<div class="wrap">
    <h1><?php _e('WP Plugin Registry', 'wp-plugin-registry'); ?></h1>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="wppr-grid">
        <!-- Install Form -->
        <div class="wppr-card">
            <h2><?php _e('Install from GitHub', 'wp-plugin-registry'); ?></h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('wppr_install', 'wppr_nonce'); ?>
                <input type="hidden" name="action" value="wppr_install">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_url"><?php _e('GitHub URL', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="github_url" name="github_url"
                                   class="regular-text"
                                   placeholder="https://github.com/owner/repo.git" required>
                            <p class="description">
                                <?php _e('Enter the GitHub repository URL', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="version"><?php _e('Version/Branch', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="version" name="version"
                                   class="regular-text" value="main">
                            <p class="description">
                                <?php _e('Branch or tag name (e.g., main, master, v1.0.0)', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="activate"><?php _e('Activation', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="activate" name="activate" value="1">
                                <?php _e('Activate after installation', 'wp-plugin-registry'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Install Plugin', 'wp-plugin-registry')); ?>
            </form>
        </div>

        <!-- Token Settings -->
        <div class="wppr-card">
            <h2><?php _e('GitHub Settings', 'wp-plugin-registry'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('wppr_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_token"><?php _e('Personal Access Token', 'wp-plugin-registry'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="github_token" name="wppr_settings[github_token]"
                                   class="regular-text"
                                   value="<?php echo esc_attr($options['github_token'] ?? ''); ?>">
                            <p class="description">
                                <?php _e('Required for private repositories. Create at GitHub Settings > Developer settings > Personal access tokens', 'wp-plugin-registry'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'wp-plugin-registry')); ?>
            </form>
        </div>
    </div>

    <!-- Plugin List -->
    <h2><?php _e('Installed Plugins', 'wp-plugin-registry'); ?></h2>

    <?php if (empty($plugins)): ?>
        <p><?php _e('No GitHub plugins installed yet.', 'wp-plugin-registry'); ?></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Version', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Latest', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Status', 'wp-plugin-registry'); ?></th>
                    <th><?php _e('Actions', 'wp-plugin-registry'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin): ?>
                    <?php
                    $plugin_file = $plugin['local_path'] . '/' . $plugin['local_dir'] . '.php';
                    $is_active = file_exists($plugin_file) && is_plugin_active($plugin_file);
                    $has_update = $plugin['has_update'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($plugin['wp_plugin_data']['Name'] ?? $plugin['slug']); ?></strong>
                            <br>
                            <small><?php echo esc_html($plugin['source_url']); ?></small>
                        </td>
                        <td><?php echo esc_html($plugin['installed_version']); ?></td>
                        <td>
                            <?php echo esc_html($plugin['latest_version']); ?>
                            <?php if ($has_update): ?>
                                <span class="update-badge"><?php _e('Update available', 'wp-plugin-registry'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="status-active"><?php _e('Active', 'wp-plugin-registry'); ?></span>
                            <?php else: ?>
                                <span class="status-inactive"><?php _e('Inactive', 'wp-plugin-registry'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($has_update): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_update&slug=' . $plugin['slug']), 'wppr_update_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Update', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($is_active): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_deactivate&slug=' . $plugin['slug']), 'wppr_deactivate_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Deactivate', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_activate&slug=' . $plugin['slug']), 'wppr_activate_' . $plugin['slug']); ?>"
                                       class="button button-secondary">
                                        <?php _e('Activate', 'wp-plugin-registry'); ?>
                                    </a>
                                <?php endif; ?>

                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-plugin-registry&action=wppr_uninstall&slug=' . $plugin['slug']), 'wppr_uninstall_' . $plugin['slug']); ?>"
                                   class="button button-link-delete"
                                   onclick="return confirm('<?php _e('Are you sure you want to uninstall this plugin?', 'wp-plugin-registry'); ?>')">
                                    <?php _e('Uninstall', 'wp-plugin-registry'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
