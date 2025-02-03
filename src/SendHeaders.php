<?php

namespace CacheControl;

class SendHeaders implements \Dxw\Iguana\Registerable
{
	protected int $maxAge = 86400;
	protected bool $overridesArchive = false;
	protected bool $developerMode = false;
	protected bool $overriddenByTaxonomy = false;
	protected string $currentConfig = 'default';
	protected object $page;
	protected string $homePageCacheAge = 'default';
	protected string $frontPageCacheAge = 'default';
	protected string $archiveCacheAge = 'default';
	protected array $headers = [];

	public function __construct(\CacheControl\Page $page)
	{
		$this->page = $page;
	}

	public function register(): void
	{
		add_filter('wp_headers', [$this, 'getContext'], 99);
		add_action('send_headers', [$this, 'setCacheHeader'], 1);
	}

	// get any headers that have been set by filters
	public function getContext(array $headers): array
	{
		$this->headers = $headers;
		return $headers;
	}

	public function setCacheHeader(): void
	{
		if (wp_get_environment_type() === 'local' || wp_get_environment_type() === 'development') {
			$this->developerMode = get_field('cache_control_plugin_developer_mode', 'option') ?? false;
		}

		//Get our page properties that we will be using to figure out our cache settings,
		$this->getPageProperties();

		// if we are logged in, or on the front page we don't need to worry about configuring things further
		if ($this->page->isLoggedInUser() || $this->page->requiresPassword() || $this->page->isPreviewPage()) {
			header('Cache-Control: no-cache, no-store, private');
			return;
		}

		/*
			* If something is setting no-cache using the wp_headers filter
			* we don't want to mess with that
			*/
		/** @psalm-suppress RedundantCondition */
		if (
			!$this->page->isLoggedInUser()
			&& array_key_exists('Cache-Control', $this->headers)
			&& preg_match('/no-cache/', $this->headers['Cache-Control'])
		) {
			return;
		}

		if ($this->page->isFrontPage()) {
			if (is_string(get_field('cache_control_plugin_front_page_cache', 'option'))) {
				$this->frontPageCacheAge = get_field('cache_control_plugin_front_page_cache', 'option');
			}
			if ($this->frontPageCacheAge && $this->frontPageCacheAge != 'default') {
				$this->currentConfig = 'frontPage';
				$this->maxAge = (int) $this->frontPageCacheAge;
			}
			if ($this->developerMode) {
				header('Meta-cc-front-page-cache-value: ' . $this->frontPageCacheAge);
				header('Meta-cc-configured-max-age: ' . $this->maxAge);
			}
		} else {
			$this->getPageConfiguration();
		}

		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->page->isLoggedInUser()) {
			header('Meta-cc-configured-cache: no-cache (logged in user)');
		}
		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->page->requiresPassword()) {
			header('Meta-cc-configured-cache: no-cache (requires password)');
		}
		if ($this->developerMode) {
			header('Meta-cc-currently-used-config: ' . $this->currentConfig);
			header('Meta-cc-final-configured-max-age: ' . $this->maxAge);
		}

		header('Cache-Control: max-age=' . $this->maxAge .', public');
	}

	/**
	 * getPageProperties
	 *
	 * Populate our page properties for the page we are on, set $this->pageValues.
	 *
	 * @return void
	 */
	protected function getPageProperties(): void
	{
		// If we are in developer mode we want to see what the current page is setting.
		if ($this->developerMode) {
			header('Meta-cc-post-type: ' . $this->page->postType());
			header('Meta-cc-taxonomy:' . implode(',', $this->page->taxonomies()));
			header('Meta-cc-front-page: ' . ($this->page->isFrontPage() ? 'yes' : 'no'));
			header('Meta-cc-home-page: ' . ($this->page->isHomePage() ? 'yes' : 'no'));
			header('Meta-cc-archive: ' . ($this->page->isArchivePage() ? 'yes' : 'no'));
			header('Meta-cc-is-admin: ' . ($this->page->isAdmin() ? 'yes' : 'no'));
			header('Meta-cc-logged-in-user: ' . ($this->page->isLoggedInUser() ? 'yes' : 'no'));
			header('Meta-cc-template_name: ' . $this->page->templateName());
			header('Meta-cc-requires-password: ' . ($this->page->requiresPassword() ? 'yes' : 'no'));
			header('Meta-cc-post-types: ' . implode(',', get_post_types(['public' => true])));
			header('Meta-cc-post-id: '. $this->page->postId());
		}
	}

	protected function getPageConfiguration(): void
	{
		// Where is our cache config coming from currently
		$this->currentConfig = 'default';

		// Our cache type config containers.
		$postTypeConfig = [
			'maxAge' => 'default',
			'overridesArchive' => false,
			'overriddenByTaxonomy' => true,
			'overriddenByTemplate' => true,
		];
		$taxonomyConfig = [
			'maxAge' => 'default',
			'priority' => 999
		];
		$templateConfig = [
			'maxAge' => 'default',
			'overridesTaxonomy' => false,
		];

		// Check if we have an individual cache configured for this page and return this value if we do
		if (have_rows('field_cache_control_individual_post_settings', 'options')) {
			$rows = get_field('field_cache_control_individual_post_settings', 'options');
			foreach ($rows as $row) {
				if (!empty($row['cache_control_individual_post_post_id']) && $this->page->postId() == $row['cache_control_individual_post_post_id']) {
					if ($row['cache_control_individual_post_cache_age'] != 'default') {
						$this->maxAge = $row['cache_control_individual_post_cache_age'];
					}

					if ($this->developerMode) {
						header('Meta-cc-config-individual-post-max-age: ' . $this->maxAge);
						header('Meta-cc-individual-page-cache-setting-triggered: Yes');
						header('Meta-cc-configured-max-age: ' . $this->maxAge);
					}
					return;
				}
			}
		}
		if ($this->developerMode) {
			header('Meta-cc-individual-page-cache-setting-triggered: No');
		}

		// Get post type options.
		if (have_rows('cache_control_post_type_' . $this->page->postType() . '_settings', 'option')) {
			while (have_rows('cache_control_post_type_' . $this->page->postType() . '_settings', 'option')) {
				the_row();
				$postTypeConfig['maxAge'] = get_sub_field('cache_control_post_type_' . $this->page->postType() . '_cache_age');
				if ($this->page->postType() != 'page') {
					$postTypeConfig['overridesArchive'] = get_sub_field('cache_control_post_type_' . $this->page->postType() . '_override_archive');
				}
				$postTypeConfig['overriddenByTaxonomy'] = get_sub_field(
					'cache_control_post_type_' . $this->page->postType() . '_overridden_by_taxonomy'
				) ?: false;
				$postTypeConfig['overriddenByTemplate'] = get_sub_field(
					'cache_control_post_type_' . $this->page->postType() . '_overridden_by_template'
				) ?: false;

				// Only set these values if the maxAge is set to a value other than default.
				if ($postTypeConfig['maxAge'] != 'default') {
					$this->currentConfig = 'postType';
					$this->maxAge = $postTypeConfig['maxAge'];
					$this->overridesArchive = $postTypeConfig['overridesArchive'];
					$this->overriddenByTaxonomy = $postTypeConfig['overriddenByTaxonomy'];
				}
			}
		}
		if ($this->developerMode) {
			header('Meta-cc-config-post-type-max-age: ' . $postTypeConfig['maxAge']);
			header('Meta-cc-config-post-type-overrides-archive: ' . ($postTypeConfig['overridesArchive'] ? 'yes' : 'no'));
			header('Meta-cc-config-post-type-overridden-by-taxonomy: ' . ($postTypeConfig['overriddenByTaxonomy'] ? 'yes' : 'no'));
			header('Meta-cc-config-post-type-overridden-by-template: ' . ($postTypeConfig['overriddenByTemplate'] ? 'yes' : 'no'));
		}

		// Get taxonomy options.
		if (count($this->page->taxonomies()) > 0 && !in_array('none', $this->page->taxonomies())) {
			foreach ($this->page->taxonomies() as $taxonomy) {
				if (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
					while (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
						the_row();
						if (get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_ignore') == false) {
							$localMaxAge = get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_age');
							$localPriority = get_sub_field('cache_control_taxonomy_' . $taxonomy . '_priority');
							if ($localMaxAge != 'default' && $localPriority < $taxonomyConfig['priority']) {
								$taxonomyConfig['maxAge'] = $localMaxAge;
								$taxonomyConfig['priority'] = $localPriority;
							}
						}
					}
				}
			}
		}
		if ($taxonomyConfig['maxAge'] != 'default' && $this->overriddenByTaxonomy) {
			$this->currentConfig = 'taxonomy';
			$this->maxAge = $taxonomyConfig['maxAge'];
		}
		if ($this->developerMode) {
			header('Meta-cc-config-taxonomy-max-age: ' . $taxonomyConfig['maxAge']);
			header('Meta-cc-config-taxonomy-priority: ' . $taxonomyConfig['priority']);
		}

		// Get template options.
		if ($this->page->templateName() != 'default') {
			$localTemplateFile = preg_replace('/\.php$/', '', $this->page->templateName());
			if ($this->developerMode) {
				header('Meta-cc-config-template-local-name: ' . $localTemplateFile);
			}
			if (have_rows('cache_control_template_' . $localTemplateFile . '_settings', 'option')) {
				while (have_rows('cache_control_template_' . $localTemplateFile . '_settings', 'option')) {
					the_row();
					$templateConfig['maxAge'] = get_sub_field('cache_control_template_' . $localTemplateFile . '_cache_age');
					$templateConfig['overridesTaxonomy'] = get_sub_field('cache_control_template_' . $localTemplateFile . '_override_taxonomy');
				}
			}
		}
		if ($templateConfig['maxAge'] != 'default') {
			if (
				($this->currentConfig == 'postType' && $postTypeConfig['overriddenByTemplate'])
				||
				($this->currentConfig == 'taxonomy' && $templateConfig['overridesTaxonomy'])
			) {
				$this->currentConfig = 'template';
				$this->maxAge = $templateConfig['maxAge'];
			}
		}

		if ($this->developerMode) {
			header('Meta-cc-config-template-max-age: ' . $templateConfig['maxAge']);
			header('Meta-cc-config-taxonomy-priority: ' . ($templateConfig['overridesTaxonomy'] ? 'yes' : 'no'));
		}

		if ($this->page->isArchivePage()) {
			// Do we have a configured taxonomy cache age?
			if ($taxonomyConfig['maxAge'] != 'default') {
				$this->currentConfig = 'taxonomy';
				$this->maxAge = $taxonomyConfig['maxAge'];
			} else {
				// Does the pastType override archive settings;
				if (!$this->overridesArchive) {
					if (is_string(get_field('cache_control_plugin_archives_cache', 'option'))) {
						$this->archiveCacheAge = get_field('cache_control_plugin_archives_cache', 'option');
					}
					if ($this->archiveCacheAge && $this->archiveCacheAge != 'default') {
						$this->currentConfig = 'archive';
						$this->maxAge = (int) $this->archiveCacheAge;
						if ($this->developerMode) {
							header('Meta-cc-archive-cache-value: ' . $this->archiveCacheAge);
						}
					}
				}
			}
		}

		if ($this->page->isHomePage()) {
			if (is_string(get_field('cache_control_plugin_home_page_cache', 'option'))) {
				$this->homePageCacheAge = get_field('cache_control_plugin_home_page_cache', 'option');
			}
			if ($this->homePageCacheAge && $this->homePageCacheAge != 'default') {
				$this->currentConfig = 'homePage';
				$this->maxAge = (int) $this->homePageCacheAge;
				if ($this->developerMode) {
					header('Meta-cc-home-page-cache-value: ' . $this->homePageCacheAge);
				}
			}
		}

		if ($this->developerMode) {
			header('Meta-cc-configured-max-age: ' . $this->maxAge);
			header('Meta-cc-configured-overrides-archive: ' . ($this->overridesArchive ? 'yes' : 'no'));
		}
	}
}
