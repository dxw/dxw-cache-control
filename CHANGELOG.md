# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2022-10-20
- Initial release

## [0.1.1] - 2022-11-xx
### Added
- Kahlan test suite for the plugin classes
- Checks that the wp_headers filter hasn't modified to cache-control header to no-cache

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
