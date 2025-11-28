=== ACF IcoMoon Integration ===
Contributors: Louise Salas
Tags: acf, icomoon, icons, svg, advanced custom fields
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds IcoMoon icon picker support for Advanced Custom Fields.

== Description ==

ACF IcoMoon Integration allows you to use your custom IcoMoon icon sets within Advanced Custom Fields.

**Features:**

* Upload IcoMoon selection.json or SVG sprite files
* Custom ACF field type: "IcoMoon Icon Picker"
* Visual icon picker with search functionality
* Multiple return formats (icon name, SVG, CSS class, or array)
* Single or multiple icon selection
* Helper functions for theme templates

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/acf-icomoon-integration/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > IcoMoon Icons
4. Upload your IcoMoon selection.json or SVG sprite file

== Usage ==

```php
// Output an icon
icomoon_icon( 'home' );

// Get icon as string
$icon = icomoon_get_icon( 'home', ['class' => 'my-icon'] );

// Using with ACF
$icon_name = get_field( 'my_icon_field' );
if ( $icon_name ) {
    icomoon_icon( $icon_name );
}
```

== Changelog ==

= 1.0.0 =
* Initial release

