<?php

namespace CacheControl;

class SendHeaders implements \Dxw\Iguana\Registerable
{
	protected int $maxAge = 86400;
	protected bool $overridesArchive = false;
	protected bool $developerMode = false;
	protected string $currentConfig = 'default';
	protected array $pageProperties = [];
	protected string $homePageCacheAge = 'default';
	protected string $frontPageCacheAge = 'default';
	protected string $archiveCacheAge = 'default';

	public function register(): void
	{
		add_action('template_redirect', [$this, 'setCacheHeader']);
	}

	public function setCacheHeader(): void
	{
		$this->developerMode = get_field('cache_control_plugin_developer_mode', 'option') ?? false;

		//Get our page properties that we will be using to figure out our cache settings,
		$this->getPageProperties();

		if (count($this->pageProperties)) {
			// if we are logged in, or on the front page we don't need to worry about configuring things further
			if ($this->pageProperties['isLoggedInUser'] && !$this->developerMode) {
				header('Cache-Control: no-cache, private');
				return;
			}

			if ($this->pageProperties['isFrontPage']) {
				$this->frontPageCacheAge = get_field('cache_control_plugin_front_page_cache', 'option');
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

			if ($this->pageProperties['isLoggedInUser']) {
				header('Meta-cc-configured-cache: no-cache (logged in user)');
			}
			if ($this->developerMode) {
				header('Meta-cc-currently-used-config: ' . $this->currentConfig);
				header('Meta-cc-final-configured-max-age: ' . $this->maxAge);
			}
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
		$this->pageProperties = [
			'isAdmin' => is_admin(),
			'isArchivePage' => is_post_type_archive(),
			'isFrontPage' => is_front_page(),
			'isHomePage' => is_home(),
			'isLoggedInUser' => is_user_logged_in(),
			'postType' => get_post_type() ?? 'unknown',
			'taxonomies' => get_post_taxonomies() ?? ['none'],
			'templateName' => get_page_template_slug() ?: 'default',
		];

		// If we are in developer mode we want to see what the current page is setting.
		if ($this->developerMode) {
			header('Meta-cc-post-type: ' . $this->pageProperties['postType']);
			header('Meta-cc-taxonomy:' . implode(',', $this->pageProperties['taxonomies']));
			header('Meta-cc-front-page: ' . ($this->pageProperties['isFrontPage'] ? 'yes' : 'no'));
			header('Meta-cc-home-page: ' . ($this->pageProperties['isHomePage'] ? 'yes' : 'no'));
			header('Meta-cc-archive: ' . ($this->pageProperties['isArchivePage'] ? 'yes' : 'no'));
			header('Meta-cc-is-admin: ' . ($this->pageProperties['isAdmin'] ? 'yes' : 'no'));
			header('Meta-cc-logged-in-user: ' . ($this->pageProperties['isLoggedInUser'] ? 'yes' : 'no'));
			header('Meta-cc-template_name: ' . $this->pageProperties['templateName']);
			header('meta-cc-post-types: ' . implode(',', get_post_types(['public' => true])));
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

		// Get post type options.
		if (have_rows('cache_control_post_type_' . $this->pageProperties['postType'] . '_settings', 'option')) {
			while (have_rows('cache_control_post_type_' . $this->pageProperties['postType'] . '_settings', 'option')) {
				the_row();
				$postTypeConfig['maxAge'] = get_sub_field('cache_control_post_type_' . $this->pageProperties['postType'] . '_cache_age');
				if ($this->pageProperties['postType'] != 'page') {
					$postTypeConfig['overridesArchive'] = get_sub_field('cache_control_post_type_' . $this->pageProperties['postType'] . '_override_archive');
				}
				$postTypeConfig['overriddenByTaxonomy'] = get_sub_field(
					'cache_control_post_type_' . $this->pageProperties['postType'] . '_overridden_by_taxonomy'
				) ?: false;
				$postTypeConfig['overriddenByTemplate'] = get_sub_field(
					'cache_control_post_type_' . $this->pageProperties['postType'] . '_overridden_by_template'
				) ?: false;

				// Only set these values if the maxAge is set to a value other than default.
				if ($postTypeConfig['maxAge'] != 'default') {
					$this->currentConfig = 'postType';
					$this->maxAge = $postTypeConfig['maxAge'];
					$this->overridesArchive = $postTypeConfig['overridesArchive'];
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
		if (count($this->pageProperties['taxonomies']) > 0 && !in_array('none', $this->pageProperties['taxonomies'])) {
			foreach ($this->pageProperties['taxonomies'] as $taxonomy) {
				if (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
					while (have_rows('cache_control_taxonomy_' . $taxonomy . '_settings', 'option')) {
						the_row();
						if (get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_ignore') == false) {
							$localMaxAge = get_sub_field('cache_control_taxonomy_' . $taxonomy . '_cache_age');
							$localPriority = 'cache_control_taxonomy_' . $taxonomy . '_priority';
							if ($localMaxAge != 'default' && $localMaxAge < $taxonomyConfig['priority']) {
								$taxonomyConfig['maxAge'] = $localMaxAge;
								$taxonomyConfig['priority'] = $localPriority;
							}
						}
					}
				}
			}
		}
		if ($taxonomyConfig['maxAge'] != 'default' && $postTypeConfig['overriddenByTaxonomy']) {
			$this->currentConfig= 'taxonomy';
			$this->maxAge = $taxonomyConfig['maxAge'];
		}
		if ($this->developerMode) {
			header('Meta-cc-config-taxonomy-max-age: ' . $taxonomyConfig['maxAge']);
			header('Meta-cc-config-taxonomy-priority: ' . $taxonomyConfig['priority']);
		}

		// Get template options.
		if ($this->pageProperties['templateName'] != 'default') {
			$localTemplateFile = preg_replace('/\.php$/', '', $this->pageProperties['templateName']);
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
				$this->maxAge = $postTypeConfig['maxAge'];
			}
		}
		if ($this->developerMode) {
			header('Meta-cc-config-template-max-age: ' . $templateConfig['maxAge']);
			header('Meta-cc-config-taxonomy-priority: ' . ($templateConfig['overridesTaxonomy'] ? 'yes' : 'no'));
		}

		if ($this->pageProperties['isArchivePage']) {
			// Do we have a configured taxonomy cache age?
			if ($taxonomyConfig['maxAge'] != 'default') {
				$this->currentConfig = 'taxonomy';
				$this->maxAge = $taxonomyConfig['maxAge'];
			} else {
				// Does the pastType override archive settings;
				if (!$this->overridesArchive) {
					$this->archiveCacheAge = get_field('cache_control_plugin_archives_cache', 'option');
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

		if ($this->pageProperties['isHomePage']) {
			$this->homePageCacheAge = get_field('cache_control_plugin_home_page_cache', 'option');
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
