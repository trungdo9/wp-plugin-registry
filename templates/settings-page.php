<?php
/**
 * General settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = $options ?? [];
$logs_options = $logs_options ?? [];
?>

<div class="wrap">
    <h1><?php _e('Plugin Registry Settings', 'wp-plugin-registry'); ?></h1>

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(urldecode($_GET['message'])); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('wppr_settings'); ?>

        <div class="wppr-card">
            <h2><?php _e('GitHub Settings', 'wp-plugin-registry'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="github_token"><?php _e('Personal Access Token', 'wp-plugin-registry'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="github_token" name="wppr_settings[github_token]"
                               class="regular-text"
                               value="<?php echo esc_attr($options['github_token'] ?? ''); ?>" autocomplete="off">
                        <p class="description">
                            <?php _e('Required for private repositories and GitHub Actions triggers. Create at GitHub Settings > Developer settings > Personal access tokens', 'wp-plugin-registry'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wppr-card">
            <h2><?php _e('Activity Logs', 'wp-plugin-registry'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Logging', 'wp-plugin-registry'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="wppr_settings[enable_logging]"
                                   value="1" <?php checked(isset($options['enable_logging']) && $options['enable_logging'], true); ?>>
                            <?php _e('Enable activity logging', 'wp-plugin-registry'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_retention"><?php _e('Log Retention (days)', 'wp-plugin-registry'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="log_retention" name="wppr_settings[log_retention]"
                               class="small-text"
                               value="<?php echo esc_attr($options['log_retention'] ?? 30); ?>" min="1" max="365">
                        <p class="description">
                            <?php _e('Logs older than this will be automatically deleted (default: 30 days).', 'wp-plugin-registry'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'wp-plugin-registry')); ?>
    </form>

    <div class="wppr-card">
        <h2><?php _e('Quick Links', 'wp-plugin-registry'); ?></h2>
        <ul>
            <li>
                <a href="<?php echo admin_url('admin.php?page=wp-plugin-registry'); ?>">
                    <?php _e('Installed Plugins', 'wp-plugin-registry'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=wp-plugin-registry-logs'); ?>">
                    <?php _e('Activity Logs', 'wp-plugin-registry'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=wp-plugin-registry-github'); ?>">
                    <?php _e('GitHub Actions Settings', 'wp-plugin-registry'); ?>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.wppr-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wppr-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #c3c4c7;
    margin-bottom: 15px;
}
</style>
