# Cache Control Plugin

This plugin adds the ability to set the cache-control header for any wordpress page displayed in the frontend to a 
custom max-age value which is administrator controlled in the backend.

The plugin only modifies the headers on the frontend of the site, and does not opperate at all in the administration 
pages of a wordpress site.

## What this plugin does

1. Adds a Cache Control page to the site settings, which allows values for the max-age of the cache to be configured by post type, taxonomy and template.
2. On loading any frontend page of the site, it inspects the properties of the served page and sets the cache-control: header to the configured max-age value and to be public, unless there is a logged in user, in which case the cache-control header is set to no-cache, private

## Configuration

The default cache max-age is set to 24 hours, other than the front page config, any config set to default will not be processed.
The front page config is always respected, regardless of other configuration, even if it is set to the default value.

### Global options
- Developer mode flag: Disables the Logged In User override, so do not set on production, outputs various headers with the prefix 'Meta-cc' that provide information on page properties and config state.
- Front Page Cache: Sets the max-age for the configured front page of the site.
- Home Page Cache: Sets the max-age for the configured home page of the site (see [Wordpress function reference: is_home()](https://developer.wordpress.org/reference/functions/is_home/)) for details.
- Archive Cache: Sets the max-age value for archive pages.

### Post Type options
- $post_type cache: Sets the max age for the post type.
- Overrides archive: Flags that this post type cache setting can override the archive setting for this post type. not shown for the 'Page' post type.
- Is Overridden by Taxonomy cache configs: Flags that this config can be overridden by a configured taxonomy setting.
- Is Overridden by Template cache config: Flags that this config can be overridden by a configured template setting, only displayed if the post type has templates available.

### Taxonomy options
- Ignore taxonomy: Flags that this taxonomy is not to be configured or processed when determining cache max-age.
- $taxonomy_name ($taxonomy_slug) cache: Sets the max-age for the taxonomy.
- Cache priority: Sets priority for the Taxonomy, used to determine which taxonomy setting should be used when there are multiple configured taxonomies, lover is better.

### Template options

This config section is only displayed if there are custom templates available, otherwise it will not show in the settings.
Where this section is available each template config will state which post type this will affect.

- $template_name cache: sets the max-age value for the template.
- Overrides taxonomy cache age: sets wthere this template setting can override a configured taxonomy cache config, only applies to posts, taxonomy cache setting is always preferred on archive pages.

## Priority of configuration directives

Generally speaking any directive with the max age set to default is not processed, so
regardless of priority, any lower priority directive with a deviation from the default value will be respected. The
Font page configuration is the exception to this, in that the configured value for this page will be respected even if it is set to default.

1. Logged in user: Overrides all other settings, sets cache-control: no-cache, private
2. Front Page config: Overrides all other configuration, even if set to default cache age, no further configuration is processed.
3. Home Page config: Overrides all other configuration values, apart from isFrontPage.
4. Template config: Overrides any post type config with overriddenByTemplate set, overrides any taxonomy config is flagged with overridesTaxonomy.
5. Taxonomy config: Overrides archive config, overrides any post type config set with the overriddenByTaxonomy, overrides any taxonomy config with a priority value set higher than the processing taxonomy config, can be overridden by templates with the overridesTaxonomy flag set.
6. Post type config: Overrides archive config if flagged as overridesArchive, can be overridden by templates and taxonomies where appropriately configured.
7. Archive config: Always overridden by a configured taxonomy config, may be overridden by a post type config set to override archive, or a template config if that template overrides the post type config.

## How to use this plugin

1. Install this plugin (ideally via [Whippet](https://github.com/dxw/whippet)).
2. Activate the plugin, if this is a multi-site install this plugin can only be enabled at the network level.
3. Configure the available options appropriately to give you the cacheing levels desired. 

## Development

Install the dependencies:

```
composer install
```

Run the tests:
```
vendor/bin/kahlan spec
```

Run the linters:
```
vendor/bin/psalm
vendor/bin/php-cs-fixer fix
```
