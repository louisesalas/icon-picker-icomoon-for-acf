# Changelog

All notable changes to Icon Picker using IcoMoon for ACF will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2025-12-18

### Changed
- Renamed all prefixes to use unique "IPIACF" prefix to prevent conflicts with other plugins
- Updated class names: `IPIACF_Integration`, `IPIACF_Parser`, `IPIACF_Sanitizer`, `IPIACF_Admin`, `IPIACF_Frontend`, `IPIACF_Field`
- Updated function names: `ipiacf_icon()`, `ipiacf_get_icon()`, `ipiacf_has_icons()`, `ipiacf_get_icons()`
- Updated constants: `IPIACF_VERSION`, `IPIACF_PLUGIN_DIR`, `IPIACF_PLUGIN_URL`, `IPIACF_PLUGIN_BASENAME`
- Updated option names: `ipiacf_icons`, `ipiacf_sprite_url`, `ipiacf_sprite_path`
- Updated all CSS classes and JavaScript identifiers to use `ipiacf-` prefix
- Fixed deprecated `libxml_disable_entity_loader()` for PHP 8.0+ compatibility

## [1.0.2] - 2025-12-02

### Security - CRITICAL UPDATE
- **Fixed critical Stored XSS vulnerability** via malicious SVG file uploads
- **Fixed critical XXE injection vulnerability** in XML/SVG parsing that could expose server files
- **Fixed high severity path traversal vulnerability** that could allow unauthorized file access
- Restricted SVG MIME type validation (removed `text/html` from allowed types)
- Enhanced output escaping throughout codebase for XSS prevention

### Added
- New `IPIACF_Sanitizer` class for comprehensive SVG content sanitization
- Whitelist-based SVG element and attribute filtering (removes scripts, event handlers, dangerous CSS)
- Path validation method to ensure all file operations stay within WordPress uploads directory
- Security documentation: `SECURITY-CHANGELOG.md` and `SECURITY-FIXES-SUMMARY.md`
- Helper functions extracted to dedicated `includes/helper-functions.php` file

### Changed
- XML/SVG parsing now uses secure libxml options (`LIBXML_NONET`, `LIBXML_NOENT`, `LIBXML_NOCDATA`)
- External entity loading disabled across all XML operations to prevent XXE attacks
- All uploaded SVG files are now sanitized before storage
- Admin class constructor now requires `IPIACF_Sanitizer` instance
- Improved file path security with `realpath()` validation

### Technical Details
- All SVG content sanitized with whitelist approach (dangerous elements/attributes removed)
- DOCTYPE and ENTITY declarations in SVG files now rejected
- Event handler attributes (onclick, onerror, onload, etc.) stripped from SVG uploads
- CSS expressions and javascript: protocols removed from style attributes
- File paths validated to prevent directory traversal attacks

**⚠️ IMPORTANT:** After updating, it is recommended to re-upload your IcoMoon files to ensure they are properly sanitized with the new security features.

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
- Helper functions: `ipiacf_icon()`, `ipiacf_get_icon()`, `ipiacf_has_icons()`, `ipiacf_get_icons()`
- Admin settings page under Settings > IcoMoon Icons
- Inline SVG sprite output for frontend rendering
- ACF dependency check with admin notice
