# WP Plugin Registry - Codebase Summary

## Project Overview

WP Plugin Registry is a WordPress plugin for managing plugins hosted on GitHub. The codebase is organized following WordPress plugin best practices with PSR-4 autoloading, singleton pattern for core classes, and clear separation of concerns.

## Directory Structure

```
wp-plugin-registry/
├── assets/                    # Frontend assets
│   ├── css/                   # Admin stylesheets
│   │   └── admin.css
│   └── js/                    # Admin JavaScript
│       └── admin.js
├── .claude/                   # Claude AI configuration
│   ├── agents/                # AI agent definitions
│   ├── commands/              # CLI commands for AI
│   ├── skills/                # Reusable AI skills
│   └── settings.json
├── .github/                   # GitHub-specific files
├── languages/                 # Translation files
├── src/                       # Main plugin source code
│   ├── Admin/                 # Admin interface classes
│   │   └── Admin.php
│   ├── CLI/                   # WP-CLI commands
│   │   └── Commands.php
│   ├── GitHub/                # GitHub integration
│   │   ├── Downloader.php
│   │   ├── GitHubActions.php
│   │   └── GitHubClient.php
│   ├── Plugin/                # Plugin management
│   │   ├── ActivityLogger.php
│   │   ├── Manager.php
│   │   └── Registry.php
│   ├── Traits/               # Shared traits
│   │   └── Singleton.php
│   └── Main.php               # Plugin entry point
├── templates/                 # Admin page templates
│   ├── admin-page.php
│   ├── github-actions-page.php
│   ├── logs-page.php
│   └── settings-page.php
├── vendor/                    # Composer dependencies
├── composer.json              # Composer configuration
├── composer.lock
├── uninstall.php              # Cleanup on uninstall
└── wp-plugin-registry.php     # Main plugin file
```

## Core Components

### Main Plugin Entry Point

**File**: `wp-plugin-registry.php`

The main plugin file that:
- Defines plugin constants (version, paths, URLs)
- Registers activation/deactivation hooks
- Loads all classes on `plugins_loaded`
- Initializes the plugin via the `Main` class

**Constants Defined**:
| Constant | Purpose |
|----------|---------|
| `WPPR_VERSION` | Plugin version number |
| `WPPR_FILE` | Path to main plugin file |
| `WPPR_PATH` | Plugin directory path |
| `WPPR_URL` | Plugin directory URL |
| `WPPR_BASENAME` | Plugin basename |
| `WPPR_PLUGINS_DIR` | WordPress plugins directory |

### Main Class

**File**: `src/Main.php`

Singleton class that orchestrates plugin initialization:
- Initializes the Plugin Registry
- Creates the Plugin Manager with GitHubClient
- Loads Admin interface if in admin area
- Registers WP-CLI commands if available

### GitHub Integration Layer

#### GitHubClient

**File**: `src/GitHub/GitHubClient.php`

Handles all GitHub API communications:
- `parse_url()` - Extract owner/repo from URL
- `get_latest_release()` - Fetch latest release info
- `list_releases()` - List all releases
- `get_repo_info()` - Get repository metadata
- `download_tarball()` - Download repository as tarball
- `request()` - Low-level API request handler

**API Endpoints Used**:
- `/repos/{owner}/{repo}/releases/latest`
- `/repos/{owner}/{repo}/releases`
- `/repos/{owner}/{repo}`
- Rate limit headers: `x-ratelimit-remaining`, `x-ratelimit-reset`

#### Downloader

**File**: `src/GitHub/Downloader.php`

Downloads and extracts GitHub repositories:
- `download_and_extract()` - Main entry point
- `extract_to_plugins()` - Extract tarball to plugins dir
- `get_root_folder()` - Determine archive root directory

**Process**:
1. Create temporary file
2. Download tarball via GitHubClient
3. Extract using PHP PharData
4. Move to plugins directory

#### GitHubActions

**File**: `src/GitHub/GitHubActions.php`

Integrates with GitHub Actions workflows:
- `trigger()` - Dispatch workflow events
- `dispatch_workflow()` - API call to trigger workflow
- `get_workflows()` - List available workflows
- Event handlers: `on_install()`, `on_update()`, `on_uninstall()`

**Available Triggers**:
- `on_release` - New release published
- `on_update_available` - New version detected
- `on_install` - Plugin installed
- `on_update` - Plugin updated
- `on_uninstall` - Plugin removed

### Plugin Management Layer

#### Registry

**File**: `src/Plugin/Registry.php`

Singleton class managing the plugin registry database table:

**Database Table**: `wppr_plugin_registry`

| Column | Type | Description |
|--------|------|-------------|
| id | mediumint(9) | Primary key |
| plugin_slug | varchar(191) | Unique plugin identifier |
| github_owner | varchar(100) | GitHub username |
| github_repo | varchar(100) | Repository name |
| local_path | varchar(500) | Installation path |
| installed_version | varchar(50) | Current version |
| latest_version | varchar(50) | Latest available |
| source_url | varchar(1000) | Original GitHub URL |
| branch | varchar(100) | Source branch |
| has_update | tinyint(1) | Update available flag |
| created_at | datetime | Installation time |
| updated_at | datetime | Last update time |

**Key Methods**:
- `get_all()` - List all registered plugins
- `get()` - Get single plugin by slug
- `add()` - Register new plugin
- `update()` - Update plugin info
- `remove()` - Unregister plugin
- `exists()` - Check if plugin is registered
- `get_plugins_with_updates()` - Find plugins needing updates

#### Manager

**File**: `src/Plugin/Manager.php`

Handles complete plugin lifecycle:

| Operation | Method |
|-----------|--------|
| Install | `install($url, $version)` |
| Update | `update($slug)` |
| Activate | `activate($slug)` |
| Deactivate | `deactivate($slug)` |
| Uninstall | `uninstall($slug)` |
| Check Updates | `check_for_updates()` |

**Helper Methods**:
- `get_plugin_version()` - Read version from plugin header
- `find_main_plugin_file()` - Locate primary PHP file
- `delete_directory()` - Recursive directory removal

#### ActivityLogger

**File**: `src/Plugin/ActivityLogger.php`

Singleton class for audit logging:

**Database Table**: `wppr_activity_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | mediumint(9) | Primary key |
| action | varchar(50) | Action type |
| plugin_slug | varchar(191) | Plugin identifier |
| message | text | Human-readable message |
| extra_data | text | JSON-encoded additional data |
| user_id | mediumint(9) | WordPress user ID |
| ip_address | varchar(45) | Client IP |
| created_at | datetime | Event timestamp |

**Action Types**:
- `install`, `update`, `activate`, `deactivate`, `uninstall`
- `update_check`, `github_action`

**Key Methods**:
- `log()` - Generic logging
- `log_install()`, `log_update()`, `log_activate()`, etc.
- `get_all()`, `get_recent()`, `get_by_plugin()`
- `clear_all()`, `clear_plugin_logs()`
- `delete_old_logs()` - Auto-cleanup (30-day retention)

### Admin Interface

**File**: `src/Admin/Admin.php`

Manages WordPress admin area integration:

**Menu Structure**:
1. **WP Plugin Registry** (main menu)
   - Plugins (default view)
   - Activity Logs
   - GitHub Actions
   - Settings

**Admin Hooks**:
- `admin_menu` - Register menu pages
- `admin_enqueue_scripts` - Load CSS/JS
- `admin_init` - Register settings
- `admin_post_*` - Handle form submissions

**Action Handlers**:
- `handle_install()` - Process plugin installation
- `handle_update()` - Process plugin updates
- `handle_activate()` - Activate plugin
- `handle_deactivate()` - Deactivate plugin
- `handle_uninstall()` - Uninstall plugin
- `handle_clear_logs()` - Clear activity logs
- `handle_test_github()` - Test GitHub connection

### WP-CLI Commands

**File**: `src/CLI/Commands.php`

Available commands:

| Command | Description |
|---------|-------------|
| `wp wppr install <url>` | Install plugin from GitHub |
| `wp wppr update <slug>` | Update a plugin |
| `wp wppr activate <slug>` | Activate a plugin |
| `wp wppr deactivate <slug>` | Deactivate a plugin |
| `wp wppr uninstall <slug>` | Uninstall a plugin |
| `wp wppr check-updates` | Check all plugins for updates |
| `wp wppr list` | List all registered plugins |
| `wp wppr info <slug>` | Show plugin details |
| `wp wppr version <slug>` | Show plugin version |

### Shared Traits

**File**: `src/Traits/Singleton.php`

Singleton trait preventing direct instantiation:
- Private constructor
- Private clone/unserialize methods
- Static `get_instance()` method

## Supporting Files

### Uninstall Handler

**File**: `uninstall.php`

Executed when plugin is deleted from WordPress:
- Drops custom database tables
- Removes plugin options
- Clears transients

### Templates

| File | Purpose |
|------|---------|
| `admin-page.php` | Main plugin management interface |
| `logs-page.php` | Activity log viewer |
| `github-actions-page.php` | GitHub Actions configuration |
| `settings-page.php` | General settings |

### Assets

- `assets/css/admin.css` - Admin interface styles
- `assets/js/admin.js` - Admin JavaScript with AJAX support

## Dependencies

### PHP Requirements

- PHP >= 7.4
- PharData class (for archive extraction)

### WordPress Dependencies

- WordPress >= 5.8
- WordPress HTTP API (for GitHub API calls)

### Composer

- PSR-4 autoloading configured
- Namespace prefix: `WPPluginRegistry\`
- Base path: `src/`

## Data Flow Summary

1. **Installation**: GitHub URL -> GitHubClient -> Downloader -> Registry
2. **Update Check**: Registry -> GitHubClient -> Version Compare -> Update Flag
3. **Update Execution**: Registry -> Downloader -> File Replace -> Registry Update
4. **Activity Tracking**: Manager -> ActivityLogger -> Database Insert
5. **GitHub Actions**: Event -> GitHubActions -> API Dispatch -> Workflow Trigger
