# WP Plugin Registry - System Architecture

## High-Level Architecture

WP Plugin Registry follows a layered architecture with clear separation between:

1. **Presentation Layer** - Admin UI and WP-CLI
2. **Application Layer** - Manager and Command classes
3. **Domain Layer** - Registry and ActivityLogger
4. **Infrastructure Layer** - GitHubClient, Downloader, GitHubActions

## Component Diagram

```
+---------------------+
|   WordPress Core    |
+---------------------+
         |
         v
+---------------------+
|     Admin UI        | <-- src/Admin/Admin.php
+---------------------+
         |
         v
+---------------------+
|     WP-CLI          | <-- src/CLI/Commands.php
+---------------------+
         |
         v
+---------------------+
|      Manager        | <-- src/Plugin/Manager.php
+---------------------+
         |
    +----+----+----+
    |    |    |    |
    v    v    v    v
+----+ +----+ +----+ +----+
|Reg-| |Log-| |GH- | |Down|
|istry| |ger | |Cli | |loader|
+----+ +----+ +----+ +----+
    |    |    |    |
    v    v    v    v
+---------------------+
|    WordPress DB     |
+---------------------+
         |
         v
+---------------------+
|    GitHub API       |
+---------------------+
```

## Data Flow Diagrams

### Plugin Installation Flow

```
User: Admin UI / CLI
         |
         v
URL Input (https://github.com/owner/repo)
         |
         v
GitHubClient::parse_url() [Extract owner/repo]
         |
         v
GitHubClient::download_tarball() [Download .tar.gz]
         |
         v
Downloader::extract_to_plugins() [Extract to wp-content/plugins]
         |
         v
Plugin Manager reads version from plugin header
         |
         v
GitHubClient::get_latest_release() [Get latest version]
         |
         v
Registry::add() [Store in wppr_plugin_registry]
         |
         v
ActivityLogger::log_install() [Record activity]
         |
         v
GitHubActions::on_install() [Trigger workflow - optional]
         |
         v
Result returned to user
```

### Update Check Flow

```
Scheduled Event / Manual Trigger
         |
         v
Registry::get_all() [Get all registered plugins]
         |
         v
Loop through plugins
         |
         v
GitHubClient::get_latest_release() [Check for updates]
         |
         v
Compare versions (version_compare())
         |
         v
Registry::update() [Set has_update flag]
         |
         v
ActivityLogger::log_update_check() [Record check]
         |
         v
Return list of plugins needing updates
```

### Plugin Update Flow

```
User: Click Update / CLI command
         |
         v
Registry::get() [Fetch plugin details]
         |
         v
GitHubClient::get_latest_release() [Get new version]
         |
         v
Manager::deactivate() [Deactivate current version]
         |
         v
Downloader::download_and_extract() [Download new version]
         |
         v
Manager::get_plugin_version() [Read new version]
         |
         v
Registry::update() [Update registry with new path/version]
         |
         v
ActivityLogger::log_update() [Record update]
         |
         v
GitHubActions::on_update() [Trigger workflow - optional]
         |
         v
Result returned to user
```

## Database Schema

### wppr_plugin_registry Table

Stores metadata for all installed GitHub plugins.

```sql
CREATE TABLE wppr_plugin_registry (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    plugin_slug varchar(191) NOT NULL UNIQUE,
    github_owner varchar(100) NOT NULL,
    github_repo varchar(100) NOT NULL,
    local_path varchar(500) NOT NULL,
    installed_version varchar(50) DEFAULT '',
    latest_version varchar(50) DEFAULT '',
    source_url varchar(1000) DEFAULT '',
    branch varchar(100) DEFAULT 'main',
    has_update tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT '0000-00-00 00:00:00',
    updated_at datetime DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (id),
    KEY plugin_slug (plugin_slug),
    KEY local_path (local_path(255))
);
```

### wppr_activity_logs Table

Records all plugin operations for audit purposes.

```sql
CREATE TABLE wppr_activity_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    action varchar(50) NOT NULL,
    plugin_slug varchar(191) NOT NULL,
    message text NOT NULL,
    extra_data text,
    user_id mediumint(9) DEFAULT 0,
    ip_address varchar(45) DEFAULT '',
    created_at datetime DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (id),
    KEY plugin_slug (plugin_slug),
    KEY action (action),
    KEY created_at (created_at)
);
```

## Class Interactions

### Manager Class Dependencies

```
Manager
  |
  +-- GitHubClient (injected via constructor)
  |      |
  |      +-- Handles API requests
  |      +-- Downloads tarballs
  |
  +-- Downloader (created internally)
  |      |
  |      +-- Extracts archives
  |
  +-- ActivityLogger (created internally)
  |      |
  |      +-- Records activities
  |
  +-- GitHubActions (created internally)
  |
  +-- Registry (singleton, accessed via get_instance())
         |
         +-- Database operations
```

### Request Lifecycle (Admin POST)

```
HTTP POST Request
         |
         v
WordPress routes to admin-post.php
         |
         v
admin_post_* hook fires
         |
         v
Admin::handle_*() method called
         |
         v
Nonce verification
         |
         v
Capability check (manage_options)
         |
         v
Sanitize input
         |
         v
Call Manager method
         |
         v
Redirect with message/error
```

### WordPress Hooks Used

| Hook | Purpose |
|------|---------|
| `plugins_loaded` | Initialize plugin classes |
| `admin_menu` | Register admin menu pages |
| `admin_enqueue_scripts` | Load admin assets |
| `admin_init` | Register settings |
| `admin_post_*` | Handle form submissions |
| `wppr_daily_cleanup` | Scheduled cleanup task |

### Scheduled Events

| Event | Schedule | Action |
|-------|----------|--------|
| `wppr_daily_cleanup` | Daily | Delete logs older than 30 days |

## GitHub API Integration

### Authentication

The GitHubClient supports two authentication methods:

1. **No Authentication**: 60 requests/hour rate limit
2. **Personal Access Token**: 5,000 requests/hour rate limit

Token is stored in `wppr_settings[github_token]`.

### API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /repos/{owner}/{repo}/releases/latest` | Get latest release |
| `GET /repos/{owner}/{repo}/releases` | List all releases |
| `GET /repos/{owner}/{repo}` | Get repository info |
| `GET /repos/{owner}/{repo}/tarball/{ref}` | Download tarball |
| `POST /repos/{owner}/{repo}/actions/workflows/{id}/dispatches` | Trigger workflow |
| `GET /repos/{owner}/{repo}/actions/workflows` | List workflows |

### Rate Limiting

```php
// Rate limit info stored in GitHubClient
$this->rate_limit_remaining = 60;  // Remaining requests
$this->rate_limit_reset = 1234567890;  // Reset timestamp
```

## File System Operations

### Installation Path

```
WordPress/wp-content/plugins/
  +-- owner-repo/          # Named by owner-repo slug
      +-- plugin files...
      +-- uninstall.php    # If exists
```

### Download Process

1. Create temp file in system temp directory
2. Download tarball via GitHub API
3. Extract using PHP PharData class
4. Move extracted files to plugins directory
5. Clean up temp files

## GitHub Actions Integration

### Workflow Dispatch

When enabled, GitHub Actions can be triggered on plugin events:

```json
// Repository workflow can receive:
{
  "event_type": "install|update|uninstall|release|update_available",
  "plugin_slug": "owner-repo",
  "version": "1.0.0",
  "source": "wp-plugin-registry"
}
```

### Event Triggers

| Trigger | Default | Payload Includes |
|---------|---------|------------------|
| `on_release` | Enabled | tag_name, version |
| `on_update_available` | Disabled | current_version, latest_version |
| `on_install` | Disabled | version |
| `on_update` | Enabled | old_version, new_version |
| `on_uninstall` | Disabled | version |

## Security Architecture

### Input Validation

- GitHub URL parsing with regex patterns
- Sanitization via WordPress sanitization functions
- Capability checks before operations

### Output Escaping

- HTML attributes: `esc_attr()`
- HTML content: `esc_html()`, `wp_kses_post()`
- URLs: `esc_url()`
- JavaScript: `esc_js()`

### Nonce Protection

All admin forms use WordPress nonces:
- Form generation: `wp_nonce_field()`
- Verification: `check_admin_referer()`

### Capability Requirements

| Operation | Required Capability |
|-----------|-------------------|
| Install plugin | `manage_options` |
| Update plugin | `manage_options` |
| Activate plugin | `manage_options` |
| Uninstall plugin | `manage_options` |
| View activity logs | `manage_options` |
| Change settings | `manage_options` |

## Performance Considerations

### Database Queries

- Indexed columns: `plugin_slug`, `local_path`, `action`, `created_at`
- Pagination for activity logs
- Efficient join operations

### GitHub API Usage

- Rate limit awareness and tracking
- Batch update checks minimize requests
- Token authentication for higher limits

### Caching

- Plugin data cached in registry object
- Activity logs retrieved with pagination

## Error Handling

### Error Response Format

```php
[
    'success' => false,
    'error' => 'Human-readable error message',
    'code' => 'Optional error code'
]
```

### Error Sources

1. **GitHub API Errors**: Rate limits, invalid repos, private repos
2. **Download Errors**: Network issues, disk space
3. **Extraction Errors**: Corrupted archives, missing permissions
4. **Activation Errors**: Plugin conflicts, missing dependencies
5. **Database Errors**: Connection issues, query failures
