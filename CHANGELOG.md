# Changelog

All notable changes to ACF IcoMoon Integration will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2024-11-28

### Security
- Added `wp_unslash()` for proper sanitization during nonce verification
- Added input sanitization for XPath queries to prevent potential injection attacks
- Improved file upload validation with proper MIME type checking against allowed types

### Added
- `uninstall.php` for complete cleanup when plugin is removed
- Multisite support for uninstall cleanup across all network sites
- Explicit autoload parameters for database options

### Changed
- Use `wp_delete_file()` instead of `unlink()` for safer file deletion
- Refactored file validation to use the defined allowed types array for both extension and MIME checks

### Fixed
- Added error handling for `file_put_contents()` operations with user feedback
- Fixed duplicate script localization on admin pages by adding scope checks and static flags
- Admin class now only localizes scripts on the settings page
- Field class tracks localization state to prevent duplicate data

## [1.0.0] - 2024-11-28

### Added
- Initial release
- IcoMoon selection.json file parsing
- SVG sprite file parsing and generation
- Custom ACF field type: "IcoMoon Icon Picker"
- Visual icon picker with search functionality
- Multiple return formats: icon name, SVG use tag, CSS class, or full array
- Single and multiple icon selection support
- Helper functions: `icomoon_icon()`, `icomoon_get_icon()`, `icomoon_has_icons()`, `icomoon_get_icons()`
- Admin settings page under Settings > IcoMoon Icons
- Inline SVG sprite output for frontend rendering
- ACF dependency check with admin notice

