=== Icon Picker using IcoMoon for ACF ===
Contributors: louisesalas
Tags: acf, icomoon, icons, svg, icon picker
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds IcoMoon icon picker support for Advanced Custom Fields.

== Description ==

Icon Picker using IcoMoon for ACF allows you to use your custom IcoMoon icon sets within Advanced Custom Fields.

**Features:**

* Upload IcoMoon selection.json or SVG sprite files
* Custom ACF field type: "IcoMoon Icon Picker"
* Visual icon picker with search functionality
* Multiple return formats (icon name, SVG, CSS class, or array)
* Single or multiple icon selection
* Helper functions for theme templates

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/icon-picker-icomoon-for-acf/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > IcoMoon Icons
4. Upload your IcoMoon selection.json or SVG sprite file

== Usage ==

```php
// Output an icon
ipiacf_icon( 'home' );

// Get icon as string
$icon = ipiacf_get_icon( 'home', ['class' => 'my-icon'] );

// Using with ACF
$icon_name = get_field( 'my_icon_field' );
if ( $icon_name ) {
    ipiacf_icon( $icon_name );
}
```

== Changelog ==

= 1.0.3 =
* Renamed plugin to "Icon Picker using IcoMoon for ACF" for trademark compliance
* Updated text domain to "icon-picker-icomoon-for-acf"
* Updated all prefixes to use unique "ipiacf" prefix to prevent conflicts

= 1.0.1 =
* Security: Added `wp_unslash()` for proper nonce verification
* Security: Added input sanitization for XPath queries to prevent injection
* Security: Improved file upload validation with MIME type checking
* Improved: Added error handling for file write operations
* Improved: Use `wp_delete_file()` for safer file deletion
* Improved: Added explicit autoload parameters for options
* Improved: Fixed duplicate script localization on admin pages
* Added: Uninstall hook for complete cleanup on plugin removal
* Added: Multisite support for uninstall cleanup

= 1.0.0 =
* Initial release
