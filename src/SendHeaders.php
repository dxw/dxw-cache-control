<?php

namespace CacheControl;

class SendHeaders implements \Dxw\Iguana\Registerable
{
	protected int $maxAge = 86400;
	protected bool $overridesArchive = false;
	protected DeveloperMode $developerMode;
	protected bool $overriddenByTaxonomy = false;
	protected string $currentConfig = 'default';
	protected Page $page;
	protected string $homePageCacheAge = 'default';
	protected string $frontPageCacheAge = 'default';
	protected string $archiveCacheAge = 'default';
	protected array $headers = [];

	public function __construct(\CacheControl\Page $page, \CacheControl\DeveloperMode $developerMode)
	{
		$this->page = $page;
		$this->developerMode = $developerMode;
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
		$this->addDeveloperMeta();

		// if we are logged in, or on the front page we don't need to worry about configuring things further
		if ($this->page->isLoggedInUser() || $this->page->requiresPassword() || $this->page->isPreviewPage()) {
			$this->developerMode->output();
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
			$this->developerMode->output();
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
			$this->developerMode->addHeader('front-page-cache-value', $this->frontPageCacheAge);
			$this->developerMode->addHeader('configured-max-age', $this->maxAge);
		} else {
			$this->getPageConfiguration();
		}

		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->page->isLoggedInUser()) {
			$this->developerMode->addHeader('configured-cache', 'no-cache (logged in user)');
		}
		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->page->requiresPassword()) {
			$this->developerMode->addHeader('configured-cache', 'no-cache (requires password)');
		}
		$this->developerMode->addHeader('currently-used-config', $this->currentConfig);
		$this->developerMode->addHeader('final-configured-max-age', $this->maxAge);

		$this->developerMode->output();
		header('Cache-Control: max-age=' . $this->maxAge .', public');
	}

	/**
	 * addDeveloperMeta
	 *
	 * Output additional info headers
	 * If in developer mode
	 *
	 * @return void
	 */
	protected function addDeveloperMeta(): void
	{
		$this->developerMode->addHeader('post-type', $this->page->postType());
		$this->developerMode->addHeader('taxonomy', implode(',', $this->page->taxonomies()));
		$this->developerMode->addHeader('front-page', $this->page->isFrontPage() ? 'yes' : 'no');
		$this->developerMode->addHeader('home-page', $this->page->isHomePage() ? 'yes' : 'no');
		$this->developerMode->addHeader('archive', $this->page->isArchivePage() ? 'yes' : 'no');
		$this->developerMode->addHeader('is-admin', $this->page->isAdmin() ? 'yes' : 'no');
		$this->developerMode->addHeader('logged-in-user', $this->page->isLoggedInUser() ? 'yes' : 'no');
		$this->developerMode->addHeader('template_name', $this->page->templateName());
		$this->developerMode->addHeader('requires-password', $this->page->requiresPassword() ? 'yes' : 'no');
		$this->developerMode->addHeader('post-types', implode(',', get_post_types(['public' => true])));
		$this->developerMode->addHeader('post-id', $this->page->postId());
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


					$this->developerMode->addHeader('config-individual-post-max-age', $this->maxAge);
					$this->developerMode->addHeader('individual-page-cache-setting-triggered', 'Yes');
					$this->developerMode->addHeader('configured-max-age', $this->maxAge);
					return;
				}
			}
		}
		$this->developerMode->addHeader('individual-page-cache-setting-triggered', 'No');


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

		$this->developerMode->addHeader('config-post-type-max-age', $postTypeConfig['maxAge']);
		$this->developerMode->addHeader('config-post-type-overrides-archive', $postTypeConfig['overridesArchive'] ? 'yes' : 'no');
		$this->developerMode->addHeader('config-post-type-overridden-by-taxonomy', $postTypeConfig['overriddenByTaxonomy'] ? 'yes' : 'no');
		$this->developerMode->addHeader('config-post-type-overridden-by-template', $postTypeConfig['overriddenByTemplate'] ? 'yes' : 'no');

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

		$this->developerMode->addHeader('config-taxonomy-max-age', $taxonomyConfig['maxAge']);
		$this->developerMode->addHeader('config-taxonomy-priority', $taxonomyConfig['priority']);

		// Get template options.
		if ($this->page->templateName() != 'default') {
			$localTemplateFile = preg_replace('/\.php$/', '', $this->page->templateName());

			$this->developerMode->addHeader('config-template-local-name', $localTemplateFile);

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

		$this->developerMode->addHeader('config-template-max-age', $templateConfig['maxAge']);
		$this->developerMode->addHeader('config-taxonomy-priority', $templateConfig['overridesTaxonomy'] ? 'yes' : 'no');

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
						$this->developerMode->addHeader('archive-cache-value', $this->archiveCacheAge);
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
				$this->developerMode->addHeader('home-page-cache-value', $this->homePageCacheAge);
			}
		}

		$this->developerMode->addHeader('configured-max-age', $this->maxAge);
		$this->developerMode->addHeader('configured-overrides-archive', $this->overridesArchive ? 'yes' : 'no');
	}
}
