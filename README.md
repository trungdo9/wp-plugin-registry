# WP Plugin Registry

A WordPress plugin for installing, managing, and updating plugins directly from GitHub repositories.

## Description

WP Plugin Registry bridges WordPress plugin management with GitHub repositories. Install plugins from any GitHub URL, track updates, manage plugin lifecycle, and integrate with GitHub Actions workflows.

## Features

### Core Functionality
- **GitHub Installation**: Install plugins from any GitHub repository URL
- **Plugin Registry**: Track all GitHub-installed plugins in one place
- **Version Management**: Monitor installed vs. available versions
- **One-Click Updates**: Update plugins when new releases are available
- **Full Lifecycle Control**: Activate, deactivate, and uninstall plugins
- **Activity Logging**: Complete audit trail of all plugin operations

### User Interfaces
- **Admin Dashboard**: Full-featured WordPress admin interface
- **WP-CLI Commands**: Complete command-line management

### Advanced Features
- **GitHub Actions Integration**: Trigger workflows on plugin events
- **Private Repository Support**: Authenticate with personal access tokens
- **Batch Operations**: Check all plugins for updates at once
- **Activity Logs**: Filterable history of all plugin operations

## Requirements

- WordPress 5.8+
- PHP 7.4+
- PHP PharData extension

## Installation

### From WordPress Admin

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Select the ZIP file and click Install Now
4. Activate the plugin

### Manual Installation

1. Upload the `wp-plugin-registry` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu

### Via Composer

```bash
composer require trungdo9/wp-plugin-registry
```

## Usage

### Admin Interface

1. Navigate to WP Plugin Registry in your admin menu
2. Enter a GitHub repository URL
3. Click Install
4. Manage installed plugins from the dashboard

### WP-CLI Commands

```bash
# Install a plugin
wp wppr install https://github.com/owner/repo.git
wp wppr install https://github.com/owner/repo.git --version=v1.0.0 --activate

# Update a plugin
wp wppr update owner-repo

# Check all plugins for updates
wp wppr check-updates
wp wppr check-updates --format=json

# List all registered plugins
wp wppr list
wp wppr list --status=active
wp wppr list --format=json

# Activate/Deactivate a plugin
wp wppr activate owner-repo
wp wppr deactivate owner-repo

# Uninstall a plugin
wp wppr uninstall owner-repo --yes

# Get plugin information
wp wppr info owner-repo

# Get plugin version
wp wppr version owner-repo
```

## Configuration

### GitHub Token (Optional)

For private repositories or higher API rate limits:

1. Go to WP Plugin Registry > Settings
2. Enter your GitHub Personal Access Token
3. Save settings

### GitHub Actions

To enable GitHub Actions integration:

1. Go to WP Plugin Registry > GitHub Actions
2. Enable GitHub Actions and enter your token
3. Configure event triggers as needed

## Plugin Management

### URL Formats Supported

- Full URL: `https://github.com/owner/repo.git`
- Short format: `owner/repo`

### Branches and Tags

Specify branches or tags during installation:

```bash
# Install from specific branch
wp wppr install https://github.com/owner/repo.git --version=develop

# Install from specific tag
wp wppr install https://github.com/owner/repo.git --version=v1.2.3
```

## Database Tables

The plugin creates two database tables:

- `wppr_plugin_registry`: Stores plugin metadata and version information
- `wppr_activity_logs`: Records all plugin operations for audit purposes

## Scheduled Events

The plugin runs a daily cleanup task that:
- Removes activity logs older than 30 days

## Security

- All inputs are sanitized
- All outputs are escaped
- Nonce verification on all admin forms
- Capability checks (`manage_options`) for all operations
- Secure token storage via WordPress options

## Support

- GitHub: https://github.com/trungdo9/wp-plugin-registry
- WordPress Plugin Directory: https://wordpress.org/plugins/wp-plugin-registry/

## License

GPL v2 or later - see LICENSE file for details.

## Changelog

### 1.0.0
- Initial release
- GitHub repository installation
- Plugin registry management
- WP-CLI command support
- GitHub Actions integration
- Activity logging
