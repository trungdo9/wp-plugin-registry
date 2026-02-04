# WP Plugin Registry - Code Standards

## Coding Conventions

This document outlines the coding standards, patterns, and practices used in the WP Plugin Registry project.

## PHP Version Requirements

- **Minimum**: PHP 7.4
- **Recommended**: PHP 8.0+

## Namespace Convention

The project follows PSR-4 autoloading with the namespace prefix `WPPluginRegistry`:

```php
namespace WPPluginRegistry;

namespace WPPluginRegistry\Plugin;
namespace WPPluginRegistry\GitHub;
namespace WPPluginRegistry\Admin;
namespace WPPluginRegistry\CLI;
namespace WPPluginRegistry\Traits;
```

## Directory Structure

```
src/
├── Admin/           # Admin interface classes
├── CLI/            # WP-CLI commands
├── GitHub/         # GitHub API integration
├── Plugin/         # Plugin management logic
├── Traits/         # Reusable traits
└── Main.php        # Entry point
```

## Class Structure

### Naming Conventions

| Component | Convention | Example |
|-----------|------------|---------|
| Classes | PascalCase | `GitHubClient`, `ActivityLogger` |
| Methods | camelCase | `downloadAndExtract()`, `get_all()` |
| Properties | camelCase | `$this->github`, `$this->registry` |
| Constants | UPPER_SNAKE_CASE | `WPPR_VERSION`, `TABLE_NAME` |
| Variables | camelCase | `$owner`, `$repo`, `$slug` |

### Class Organization

1. Class docblock with description
2. Namespace declaration
3. Use statements (if needed)
4. Class properties
5. Constructor (if needed)
6. Public methods
7. Private/protected methods

### Example Class Structure

```php
<?php
namespace WPPluginRegistry\Plugin;

use WPPluginRegistry\Traits\Singleton;

/**
 * Brief description of the class purpose.
 *
 * Longer description if needed with additional details.
 */
class ClassName {
    use Singleton;

    // Constants
    private const TABLE_NAME = 'wppr_table_name';

    // Private properties
    private $property_name;

    // Constructor (usually private for singleton)
    private function __construct() {}

    // Public methods
    public function publicMethod() {}

    // Protected/private methods
    private function privateMethod() {}
}
```

## Design Patterns

### Singleton Pattern

Used for core classes that should have only one instance:

```php
use WPPluginRegistry\Traits\Singleton;

class Registry {
    use Singleton;

    // Class implementation
}

// Usage
$registry = Registry::get_instance();
```

### Repository Pattern

The `Registry` class acts as a repository for plugin data:

- `get()` - Retrieve single entity
- `get_all()` - Retrieve all entities
- `add()` - Create new entity
- `update()` - Modify existing entity
- `remove()` - Delete entity

### Service Layer

The `Manager` class acts as a service coordinating multiple components:

```php
class Manager {
    public function __construct($github) {
        $this->github = $github;
        $this->downloader = new Downloader($this->github);
        $this->activity_logger = new ActivityLogger();
        $this->github_actions = new GitHubActions();
    }
}
```

## Coding Standards

### PHP Tags and Encoding

```php
<?php
/**
 * Plugin Name: Example
 */
// No closing PHP tag at end of file
```

### File Headers

All PHP files must include:

```php
<?php
/**
 * Short description
 *
 * Longer description if needed
 */
namespace WPPluginRegistry\...

defined('ABSPATH') || exit;
```

### Docblocks

#### Class Docblocks

```php
/**
 * Brief description of the class.
 *
 * Longer description with details about functionality,
 * usage, and any important notes.
 */
class ClassName {}
```

#### Method Docblocks

```php
/**
 * Brief description of what the method does.
 *
 * @param Type $param_name Description
 * @return ReturnType Description
 */
public function methodName($param_name) {}
```

#### Property Docblocks

```php
/** @var Type Description */
private $property_name;
```

### Control Structures

```php
// Single line conditionals
if ($condition) {
    do_something();
}

// Proper bracing for multi-line
if ($condition) {
    // Multiple statements
} elseif ($other_condition) {
    // Else if
} else {
    // Default
}

// Switch statements
switch ($value) {
    case 'option1':
        do_something();
        break;
    case 'option2':
        do_other_thing();
        break;
    default:
        do_default();
}
```

### Arrays

```php
// Short array syntax preferred
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];

// Indexed arrays
$items = ['item1', 'item2', 'item3'];
```

### Strings

```php
// Single quotes for static strings
$message = 'This is a message';

// Double quotes for interpolation
$output = "Value: {$value}";

// Heredoc (when needed)
$long_text = <<<EOT
This is a multi-line
string with {$variable}
EOT;
```

### Comparison Operators

```php
// Type-safe comparisons where possible
if ($result === true) {}

// Loose comparisons for mixed types
if ($value == 'some string') {}
```

## Database Operations

### WordPress $wpdb Usage

```php
global $wpdb;

// Table name with prefix
$table = $wpdb->prefix . self::TABLE_NAME;

// Prepare statements (REQUIRED)
$wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug);

// Insert/Update
$wpdb->insert($table, $data);
$wpdb->update($table, $data, $where);
$wpdb->delete($table, $where);
```

### Database Table Creation

```php
public function create_tables() {
    global $wpdb;

    $table = $wpdb->prefix . self::TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        column_name varchar(100) NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
```

## WordPress Integration

### Hooks Registration

```php
// Actions
add_action('hook_name', [$this, 'method_name']);
add_action('hook_name', 'function_name');

// Filters
add_filter('hook_name', [$this, 'method_name']);

// Shortcodes
add_shortcode('shortcode_name', [$this, 'method_name']);
```

### Admin Menu

```php
add_menu_page(
    $page_title,    // Page title
    $menu_title,    // Menu title
    $capability,    // Required capability
    $menu_slug,     // Menu slug
    [$this, 'callback'],  // Callback function
    $icon_url,      // Icon URL
    $position       // Menu position
);
```

### Settings API

```php
// Register setting
register_setting($option_group, $option_name);

// Add settings section
add_settings_section($id, $title, [$this, 'callback'], $page);

// Add settings field
add_settings_field($id, $label, [$this, 'callback'], $page, $section);
```

### Nonces

```php
// Generate nonce field
wp_nonce_field('action_name', 'nonce_field_name');

// Verify nonce
check_admin_referer('action_name', 'nonce_field_name');
```

### Sanitization

```php
// Text fields
$value = sanitize_text_field($_POST['field']);

// Email
$email = sanitize_email($_POST['email']);

// File paths
$path = sanitize_file_name($_POST['filename']);

// HTML content
$content = wp_kses_post($_POST['content']);

// URL
$url = esc_url($_POST['url']);

// SQL (use $wpdb->prepare instead)
```

### Output Escaping

```php
// HTML attributes
echo esc_attr($value);
echo esc_html($value);

// URLs
echo esc_url($value);

// JavaScript
echo esc_js($value);

// General output
echo esc_html($value);

// Content with HTML
echo wp_kses_post($content);
```

## WP-CLI Commands

### Command Registration

```php
use WPPluginRegistry\Plugin\Manager;
use WPPluginRegistry\Plugin\Registry;

class Commands {
    private static Manager $manager;
    private static Registry $registry;

    public static function register(Manager $manager, Registry $registry): void {
        self::$manager = $manager;
        self::$registry = $registry;

        \WP_CLI::add_command('wppr', __CLASS__);
    }
}
```

### Command Docblock Format

```php
/**
 * Command description.
 *
 * ## OPTIONS
 *
 * <arg>
 * : Argument description
 * ---
 * default: default value
 * ---
 *
 * [--flag=<value>]
 * : Flag description
 * ---
 * default: value
 * ---
 *
 * ## EXAMPLES
 *
 *     wp command_name example
 *     wp command_name example --flag=value
 *
 */
public function command_name($args, $assoc_args) {}
```

### WP-CLI Output

```php
// Success
\WP_CLI::success($message);

// Error (halts execution)
\WP_CLI::error($message);

// Warning (continues execution)
\WP_CLI::warning($message);

// Regular output
\WP_CLI::line($message);

// Formatted tables
\WP_CLI::format_items($format, $data, $headers);

// Debug (with --debug flag)
\WP_CLI::debug($message);
```

## Error Handling

### Return Format

```php
// Consistent success/error format
return [
    'success' => true,
    'data' => $data,
];

return [
    'success' => false,
    'error' => $error_message,
    'code' => $error_code,
];
```

### WordPress Errors

```php
if (is_wp_error($result)) {
    return [
        'success' => false,
        'error' => $result->get_error_message(),
    ];
}
```

## Testing Requirements

### Unit Tests

All new functionality should include:
- Test coverage for all public methods
- Edge case handling
- Error conditions

### Test Files Structure

```
tests/
├── bootstrap.php
├── test-base.php
├── TestClassName.php
```

## Security Checklist

- [ ] All user inputs sanitized
- [ ] All outputs escaped
- [ ] Nonces used for form submissions
- [ ] Capability checks for admin operations
- [ ] Database queries prepared
- [ ] File operations validated
- [ ] GitHub tokens stored securely

## Code Review Checklist

- [ ] Follows PSR-12 coding standards
- [ ] Docblocks complete and accurate
- [ ] No debug statements left
- [ ] Constants used for magic values
- [ ] Functions are single-purpose
- [ ] No hardcoded paths (use constants)
- [ ] Error handling implemented
- [ ] Performance considerations made
