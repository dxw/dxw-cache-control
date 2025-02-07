<?php

namespace CacheControl;

class SendHeaders implements \Dxw\Iguana\Registerable
{
	protected int $maxAge = 86400;
	protected $taxMaxAge = 'default';
	protected bool $overridesArchive = false;
	protected bool $developerMode = false;
	protected bool $overriddenByTaxonomy = false;
	protected bool $overriddenByTemplate = false;
	protected string $currentConfig = 'default';
	protected string $homePageCacheAge = 'default';
	protected string $frontPageCacheAge = 'default';
	protected string $archiveCacheAge = 'default';
	protected array $headers = [];

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
		$this->sendDefaultDevelopmentHeaders();

		// if we are logged in, or on the front page we don't need to worry about configuring things further
		if (is_user_logged_in() || $this->hasPassword() || is_preview()) {
			header('Cache-Control: no-cache, no-store, private');
			return;
		}

		/*
			 * If something is setting no-cache using the wp_headers filter
			 * we don't want to mess with that
			 */
		/** @psalm-suppress RedundantCondition */
		if (
			!is_user_logged_in()
			&& array_key_exists('Cache-Control', $this->headers)
			&& preg_match('/no-cache/', $this->headers['Cache-Control'])
		) {
			return;
		}

		if (is_front_page()) {
			$this->frontPageConfig();
		} else {
			$this->postConfig();
			$this->postTypeConfig();
			$this->taxonomyConfig();
			$this->templateConfig();
			$this->archiveConfig();
			$this->homePageConfig();
			$this->sendUpdatedDevelopmentHeaders();
		}

		/** @psalm-suppress TypeDoesNotContainType */
		if (is_user_logged_in()) {
			header('Meta-cc-configured-cache: no-cache (logged in user)');
		}
		/** @psalm-suppress TypeDoesNotContainType */
		if ($this->hasPassword()) {
			header('Meta-cc-configured-cache: no-cache (requires password)');
		}
		if ($this->developerMode) {
			header('Meta-cc-currently-used-config: ' . $this->currentConfig);
			header('Meta-cc-final-configured-max-age: ' . $this->maxAge);
		}

		header('Cache-Control: max-age=' . $this->maxAge . ', public');
	}

	protected function sendDefaultDevelopmentHeaders(): void
	{
		// If we are in developer mode we want to see what the current page is setting.
		if ($this->developerMode) {
			header('Meta-cc-post-type: ' . get_post_type() ?: 'unknown');
			header('Meta-cc-taxonomy:' . implode(',', get_post_taxonomies()));
			header('Meta-cc-front-page: ' . (is_front_page() ? 'yes' : 'no'));
			header('Meta-cc-home-page: ' . (is_home() ? 'yes' : 'no'));
			header('Meta-cc-archive: ' . (is_post_type_archive() ? 'yes' : 'no'));
			header('Meta-cc-is-admin: ' . (is_admin() ? 'yes' : 'no'));
			header('Meta-cc-logged-in-user: ' . (is_user_logged_in() ? 'yes' : 'no'));
			header('Meta-cc-template_name: ' . $this->getTemplateSlug());
			header('Meta-cc-requires-password: ' . ($this->hasPassword() ? 'yes' : 'no'));
			header('Meta-cc-post-types: ' . implode(',', get_post_types(['public' => true])));
			header('Meta-cc-post-id: ' . $this->getPostId());
		}
	}

	private function frontPageConfig()
	{
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
	}

	public function postConfig()
	{
		// Check if we have an individual cache configured for this page and return this value if we do
		if (have_rows('field_cache_control_individual_post_settings', 'options')) {
			$rows = get_field('field_cache_control_individual_post_settings', 'options');
			foreach ($rows as $row) {
				if (!empty($row['cache_control_individual_post_post_id']) && $this->getPostId() == $row['cache_control_individual_post_post_id']) {
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
	}

	public function postTypeConfig()
	{
		$postTypeConfig = [
			'maxAge' => 'default',
			'overridesArchive' => false,
			'overriddenByTaxonomy' => true,
			'overriddenByTemplate' => true,
		];

		// Get post type options.
		if (have_rows('cache_control_post_type_' . get_post_type() . '_settings', 'option')) {
			while (have_rows('cache_control_post_type_' . get_post_type() . '_settings', 'option')) {
				the_row();
				$postTypeConfig['maxAge'] = get_sub_field('cache_control_post_type_' . get_post_type() . '_cache_age');
				if (get_post_type() != 'page') {
					$postTypeConfig['overridesArchive'] = get_sub_field('cache_control_post_type_' . get_post_type() . '_override_archive');
				}
				$postTypeConfig['overriddenByTaxonomy'] = get_sub_field(
					'cache_control_post_type_' . get_post_type() . '_overridden_by_taxonomy'
				) ?: false;
				$postTypeConfig['overriddenByTemplate'] = get_sub_field(
					'cache_control_post_type_' . get_post_type() . '_overridden_by_template'
				) ?: false;

				$this->overriddenByTemplate = $postTypeConfig['overriddenByTemplate'];

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
	}

	public function taxonomyConfig()
	{
		$taxonomyConfig = [
			'maxAge' => 'default',
			'priority' => 999
		];

		// Get taxonomy options.
		if (count(get_post_taxonomies()) > 0) {
			foreach (get_post_taxonomies() as $taxonomy) {
				if (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
					while (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
						the_row();
						if (get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_ignore') == false) {
							$localMaxAge = get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_age');
							$localPriority = get_sub_field('cache_control_taxonomy_' . $taxonomy . '_priority');
							if ($localMaxAge != 'default' && $localPriority < $taxonomyConfig['priority']) {
								$taxonomyConfig['maxAge'] = $localMaxAge;
								$this->taxMaxAge = $taxonomyConfig['maxAge'];
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
	}

	public function templateConfig()
	{
		$templateConfig = [
			'maxAge' => 'default',
			'overridesTaxonomy' => false,
		];

		// Get template options.
		if ($this->getTemplateSlug() != 'default') {
			$localTemplateFile = preg_replace('/\.php$/', '', $this->getTemplateSlug());
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
				($this->currentConfig == 'postType' && $this->overriddenByTemplate)
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
	}

	public function archiveConfig()
	{
		if (is_post_type_archive()) {
			// Do we have a configured taxonomy cache age?
			if ($this->taxMaxAge != 'default') {
				$this->currentConfig = 'taxonomy';
				$this->maxAge = $this->taxMaxAge;
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
	}

	public function homePageConfig()
	{
		if (is_home()) {
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
	}

	protected function sendUpdatedDevelopmentHeaders(): void
	{
		if ($this->developerMode) {
			header('Meta-cc-configured-max-age: ' . $this->maxAge);
			header('Meta-cc-configured-overrides-archive: ' . ($this->overridesArchive ? 'yes' : 'no'));
		}
	}

	protected function hasPassword(): bool
	{
		global $post;

		return !empty($post->post_password);
	}

	/**
	 * @psalm-suppress ArgumentTypeCoercion
	 */
	protected function getPostId(): int
	{
		$post = get_post();

		if (is_a($post, 'WP_Post')) {
			return $post->ID;
		}
		return 0;
	}

	public function getTemplateSlug()
	{
		return get_page_template_slug() ?? 'default';
	}
}
