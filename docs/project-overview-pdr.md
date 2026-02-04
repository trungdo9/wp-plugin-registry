# WP Plugin Registry - Project Overview and PDR

## Project Description

WP Plugin Registry is a WordPress plugin that enables users to install, manage, and update WordPress plugins directly from GitHub repositories. It provides a comprehensive interface for managing GitHub-hosted plugins with support for both WordPress admin dashboard and WP-CLI commands.

## Purpose and Goals

### Primary Purpose
The plugin bridges the gap between WordPress plugin management and GitHub repositories, allowing developers and site administrators to:

- Install plugins from any public or private GitHub repository
- Track installed GitHub plugins in a dedicated registry
- Receive notifications when new versions are available
- Update plugins with a single click or command
- Integrate with GitHub Actions for automated workflows

### Key Goals

1. **Simplified Installation**: Provide a user-friendly interface for installing GitHub-hosted plugins without manual file uploads
2. **Centralized Management**: Maintain a registry of all GitHub-installed plugins for easy tracking
3. **Automated Updates**: Check for and apply updates from GitHub releases automatically
4. **Developer Workflow Integration**: Support GitHub Actions triggers for CI/CD pipelines
5. **Full Compatibility**: Work seamlessly with both WordPress admin interface and WP-CLI

## Target Users

- WordPress developers who develop plugins on GitHub
- Site administrators managing multiple WordPress installations
- Agencies maintaining custom plugin distributions
- DevOps engineers implementing automated WordPress deployments

## Functional Requirements

### Core Features

| Feature | Description | Priority |
|---------|-------------|----------|
| GitHub Repository Installation | Install plugins from GitHub URLs | Critical |
| Plugin Registry | Track all installed GitHub plugins | Critical |
| Version Management | Track installed vs. latest versions | Critical |
| Update Checking | Detect when new releases are available | High |
| Plugin Activation/Deactivation | Standard WordPress plugin controls | High |
| Plugin Uninstallation | Clean removal of plugins and registry entries | High |
| Activity Logging | Record all plugin operations | Medium |
| GitHub Actions Integration | Trigger workflows on plugin events | Medium |

### Installation Requirements

- **Input Formats**:
  - Full GitHub URL: `https://github.com/owner/repo.git`
  - Short format: `owner/repo`
- **Branch/Tag Support**: Download from any branch, tag, or commit SHA
- **Authentication**: Optional GitHub token for private repositories

### Management Features

- List all registered GitHub plugins
- Filter by status (active, inactive, update available)
- View detailed plugin information
- Bulk update capability
- Activity log viewer

## Non-Functional Requirements

### Performance
- Minimal database footprint (two small tables)
- Efficient GitHub API usage with rate limiting awareness
- Lazy loading of plugin data

### Security
- Sanitize all user inputs
- Use WordPress nonces for form submissions
- Require manage_options capability for admin operations
- Support private repositories via GitHub tokens

### Compatibility
- WordPress 5.8+
- PHP 7.4+
- PHP PharData class for extraction

### Extensibility
- PSR-4 autoloading for namespace support
- Singleton pattern for core classes
- Hook system for WordPress integration

## User Stories

| ID | User Story | Acceptance Criteria |
|----|------------|-------------------|
| US1 | As an admin, I want to install a plugin from GitHub | GitHub URL is parsed correctly, plugin downloaded and extracted, registry updated |
| US2 | As an admin, I want to update a plugin to the latest release | Latest release fetched from GitHub, old version deactivated, new version installed, registry updated |
| US3 | As a developer, I want to use WP-CLI for management | All operations available via wp-cli commands with proper documentation |
| US4 | As a site manager, I want to see plugin activity history | Activity log shows all operations with timestamps and user information |
| US5 | As a DevOps engineer, I want to trigger GitHub Actions | Workflows triggered on configured events with proper payloads |

## Success Metrics

- Successful installation rate > 95%
- Update check completion within 5 seconds
- Zero security vulnerabilities in code
- 100% WP-CLI command coverage for core features

## Constraints and Dependencies

### Technical Constraints
- Requires WordPress plugins directory write access
- Requires PHP PharData extension
- GitHub API rate limits apply (60 requests/hour without token)

### Dependencies
- WordPress 5.8+
- PHP 7.4+
- GitHub API v3

## Timeline and Milestones

| Phase | Description | Deliverables |
|-------|-------------|--------------|
| Phase 1 | Core Infrastructure | Registry, Downloader, GitHubClient |
| Phase 2 | Admin Interface | Menu pages, plugin management UI |
| Phase 3 | WP-CLI Commands | Full CLI command coverage |
| Phase 4 | GitHub Actions | Workflow integration |
| Phase 5 | Testing & Documentation | Unit tests, user docs |

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-02-04 | Initial release |

## References

- Plugin Repository: https://github.com/trungdo9/wp-plugin-registry
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- GitHub REST API: https://docs.github.com/en/rest
