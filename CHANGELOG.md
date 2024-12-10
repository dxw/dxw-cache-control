# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.0.0] - 2024-12-10

### Removed
- BREAKING CHANGE: support for PHP 7.4

### Added
- Support for PHP 8.2 and up

## [0.2.2] - 2024-05-08
### Changed
- Add an option to set a 5 minute max-age

## [0.2.1] - 2024-01-19
### Fixed
- Individual post cache config no longer throws a fatal error where the cache value is set as 'default'.
- Where there is an empty row in the individual page cache settings this config will no longer be processed.

## [0.2.0] - 2023-09-15
### Added
- Individual posts (including pages and custom post types) can now be targeted and assigned their own cache values.

## [0.1.2] - 2023-08-25
### Fixed
- Bug where if the config has been set there is a fatal error caused by a mismatched type.

## [0.1.1] - 2022-11-xx
### Added
- Kahlan test suite for the plugin classes
- Checks that the wp_headers filter hasn't modified to cache-control header to no-cache
- Ensure developer-mode can't be configured or run on production instances
- Sane cache configuration for posts or pages with password protection.

### Changed
- Logic surrounding applied taxonomy configs where post_type is unconfigured
- Make the call to populate the templates array safer, by checking that the function exists
- Tidy taxonomy type group name
- Make the Meta-cc headers consistent
- Use the send_headers action rather than template_redirect (requires wordpress 6.1 or greater)
- Sets the setCacheHeader method to run first in the send_headers action to give other plugins the opportunity to override the cache setting

### Fixed
- Fix namespacing in composer.json
- Fix fatal error caused by passing a string where int expected
- Fix a bug in the taxonomy processing 
- Fix bug in template config logic

## [0.1.0] - 2022-10-20
- Initial release