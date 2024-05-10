<?php

namespace CacheControl;

class Options implements \Dxw\Iguana\Registerable
{
	protected array $templates = [];
	protected bool $hasCustomTemplates = false;

	public function register(): void
	{
		add_action('acf/init', [$this, 'addOptionsPage']);
		add_action('init', [$this, 'addOptions'], 999);
	}

	public function addOptionsPage(): void
	{
		if (function_exists('acf_add_options_page')) {
			acf_add_options_page([
				'page_title' 	=> 'Cache Control Settings',
				'menu_title'	=> 'Cache Control',
				'menu_slug' 	=> 'cache-control-settings',
				'capability'	=> 'manage_options',
				'parent_slug' => 'options-general.php'
			]);
		}
	}

	public function addOptions(): void
	{
		if (function_exists('acf_get_post_templates')) {
			$this->templates = acf_get_post_templates();
		}
		$ageOptions = [
			'default'   => 'Default',
			120 		=> '2 mins',
			300 		=> '5 mins',
			900 		=> '15 mins',
			1800 		=> '30 mins',
			3600		=> '1 hr',
			7200		=> '2 hrs',
			43200		=> '12 hrs',
			86400		=> '1 day',
			172800		=> '2 days',
			604800		=> '1 week',
		];

		if (function_exists('acf_add_local_field_group')):

			$developer_mode = [];
			if (wp_get_environment_type() != 'production') {
				$developer_mode = [
					'key' => 'field_cache_control_plugin_settings-developer_mode',
					'label' => 'Developer mode',
					'name' => 'cache_control_plugin_developer_mode',
					'type' => 'true_false',
					'instructions' => 'Output additional headers for development purposes',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'message' => '',
					'default_value' => 0,
					'ui' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
				];
			}

			acf_add_local_field_group([
				'key' => 'group_cache_control_global_settings',
				'title' => 'Cache Control Global Settings',
				'fields' => [
					[
						'key' => 'field_cache_control_global_message',
						'label' => '',
						'name' => '',
						'type' => 'message',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => 'General settings and non overridable values, default cache is set at 1 day',
						'new_lines' => 'wpautop',
						'esc_html' => 0,
					],
					$developer_mode,
					[
						'key' => 'field_cache_control_plugin_settings-front_page_cache',
						'label' => 'Front page cache',
						'name' => 'cache_control_plugin_front_page_cache',
						'type' => 'select',
						'choices' => $ageOptions,
						'instructions' => 'Set max-age value for front page, this overrides all other cache values',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => '',
						'default_value' => 'default',
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					],
					[
						'key' => 'field_cache_control_plugin_settings-home_page_cache',
						'label' => 'Home page cache',
						'name' => 'cache_control_plugin_home_page_cache',
						'type' => 'select',
						'choices' => $ageOptions,
						'instructions' => 'Set max-age value for home page, this overrides any cache value other than the front page cache (not processed if set to default)',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => '',
						'default_value' => 'default',
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					],
					[
						'key' => 'field_cache_control_plugin_settings-archives_cache',
						'label' => 'Archives cache',
						'name' => 'cache_control_plugin_archives_cache',
						'type' => 'select',
						'choices' => $ageOptions,
						'instructions' => 'Set max-age value for archives pages, this can be overridden by taxonomies and, optionally, post types',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => '',
						'default_value' => 'default',
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					],
				],
				'location' => [
					[
						[
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'cache-control-settings',
						],
					],
				],
				'menu_order' => 0,
				'position' => 'normal',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => true,
				'description' => '',
				'show_in_rest' => 0,
			]);

			acf_add_local_field_group([
					'key' => 'group_cache_control_post_type_settings',
					'title' => 'Cache Control Post Type Settings',
					'fields' => $this->getPostTypesConfig($ageOptions),
					'location' => [
						[
							[
								'param' => 'options_page',
								'operator' => '==',
								'value' => 'cache-control-settings',
							],
						],
					],
					'menu_order' => 0,
					'position' => 'normal',
					'style' => 'default',
					'label_placement' => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen' => '',
					'active' => true,
					'description' => '',
					'show_in_rest' => 0,
				]);

			acf_add_local_field_group(
				[
				'key' => 'group_cache_control_individual_post_settings',
				'title' => 'Cache Control Individual Post Settings',
				'fields' => [
					[
					'key' => 'field_cache_control_individual_post_settings',
					'label' => 'Cache Control Individual Post Settings',
					'name' => 'cache_control_individual_post_caches',
					'aria-label' => '',
					'type' => 'repeater',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'layout' => 'table',
					'pagination' => 1,
					'rows_per_page' => 20,
					'min' => 0,
					'max' => 0,
					'collapsed' => '',
					'button_label' => 'Add Post',
					'sub_fields' => [
						[
							'key' => 'field_cache_control_individual_post_post_id',
							'label' => 'Post',
							'name' => 'cache_control_individual_post_post_id',
							'aria-label' => '',
							'type' => 'post_object',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'post_type' => '',
							'post_status' => '',
							'taxonomy' => '',
							'return_format' => 'id',
							'multiple' => 0,
							'allow_null' => 0,
							'ui' => 1,
							'parent_repeater' => 'group_cache_control_post_type_settings',
						],
						[
							'key' => 'field_cache_control_individual_post_cache_age',
							'label' => 'Post Cache',
							'name' => 'cache_control_individual_post_cache_age',
							'aria-label' => '',
							'type' => 'select',
							'instructions' => 'Set max-age value for the selected post.',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => [
								'width' => '',
								'class' => '',
								'id' => '',
							],
							'choices' => $ageOptions,
							'default_value' => false,
							'return_format' => 'value',
							'multiple' => 0,
							'allow_null' => 0,
							'ui' => 0,
							'ajax' => 0,
							'placeholder' => '',
							'parent_repeater' => 'group_cache_control_post_type_settings',
						],
					],
				],
					],
				'location' => [
					[
						[
							'param' => 'options_page',
							'operator' => '==',
							'value' => 'cache-control-settings',
						],
					],
				],
				'menu_order' => 0,
				'position' => 'normal',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => true,
				'description' => '',
				'show_in_rest' => 0,
			]
			);

			acf_add_local_field_group([
					'key' => 'group_cache_control_taxonomy_settings',
					'title' => 'Cache Control Taxonomy Settings',
					'fields' => $this->getTaxonomiesConfig($ageOptions),
					'location' => [
						[
							[
								'param' => 'options_page',
								'operator' => '==',
								'value' => 'cache-control-settings',
							],
						],
					],
					'menu_order' => 0,
					'position' => 'normal',
					'style' => 'default',
					'label_placement' => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen' => '',
					'active' => true,
					'description' => '',
					'show_in_rest' => 0,
				]);

			if ($this->hasCustomTemplates) {
				acf_add_local_field_group([
						'key' => 'group_cache_control_template_settings',
						'title' => 'Cache Control Template Settings',
						'fields' => $this->getTemplatesConfig($ageOptions),
						'location' => [
							[
								[
									'param' => 'options_page',
									'operator' => '==',
									'value' => 'cache-control-settings',
								],
							],
						],
						'menu_order' => 0,
						'position' => 'normal',
						'style' => 'default',
						'label_placement' => 'top',
						'instruction_placement' => 'label',
						'hide_on_screen' => '',
						'active' => true,
						'description' => '',
						'show_in_rest' => 0,
					]);
			}
		endif;
	}

	protected function getPostTypesConfig(array $ageOptions): array
	{
		$postTypes = [
			[
				'key' => 'field_cache_control_post_type_message',
				'label' => '',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => 'Configuration options for the available public post types.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			]
		];

		foreach (get_post_types(['public' => true], 'objects') as $postType) {
			$postTypeSubFields = [
				[
					'key' => 'field_cache_control_post_type_'.$postType->name.'_cache_age',
					'label' => $postType->label.' cache',
					'name' => 'cache_control_post_type_'.$postType->name.'_cache_age',
					'type' => 'select',
					'choices' => $ageOptions,
					'instructions' => 'Set max-age value for the '.$postType->label.' post type',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'message' => '',
					'default_value' => 'default',
					'ui' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
				],
			];

			$overrideArchiveConfig = [
				'key' => 'field_cache_control_post_type_'.$postType->name.'_override_archive',
				'label' => 'Overrides archive cache age',
				'name' => 'cache_control_post_type_'.$postType->name.'_override_archive',
				'type' => 'true_false',
				'instructions' => 'Can this content type cache setting override the default archive value',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => '',
				'default_value' => 0,
				'ui' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
			];
			if ($postType->name != 'page') {
				$postTypeSubFields[] = $overrideArchiveConfig;
			}

			$overriddenByTaxonomy = [
				'key' => 'field_cache_control_post_type_'.$postType->name.'_overridden_by_taxonomy',
				'label' => 'Is Overridden by Taxonomy cache configs',
				'name' => 'cache_control_post_type_'.$postType->name.'_overridden_by_taxonomy',
				'type' => 'true_false',
				'instructions' => 'Can this content type cache setting be overridden by taxonomy config',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => '',
				'default_value' => ($postType->name == 'page' ? 0 : 1),
				'ui' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
			];
			$postTypeSubFields[] = $overriddenByTaxonomy;

			$overriddenByTemplate = [
				'key' => 'field_cache_control_post_type_'.$postType->name.'_overridden_by_template',
				'label' => 'Is Overridden by Template cache config',
				'name' => 'cache_control_post_type_'.$postType->name.'_overridden_by_template',
				'type' => 'true_false',
				'instructions' => 'Can this content type cache setting be overridden by taxonomy config',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => '',
				'default_value' => ($postType->name == 'page' ? 1 : 0),
				'ui' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
			];
			if (array_key_exists($postType->name, $this->templates) && count($this->templates[$postType->name]) > 0) {
				$this->hasCustomTemplates = true;
				$postTypeSubFields[] = $overriddenByTemplate;
			}

			$postTypeFieldsPostTypeConfig = [
				'key' => 'cache_control_post_type_'.$postType->name.'_settings',
				'label' => $postType->label.' Settings',
				'name' => 'cache_control'.$postType->name.'_settings',
				'type' => 'group',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'layout' => 'block',
				'sub_fields' => $postTypeSubFields,
			];
			$postTypes[] = $postTypeFieldsPostTypeConfig;
		}
		return $postTypes;
	}

	protected function getTaxonomiesConfig(array $ageOptions): array
	{
		// get Our Taxonomies and configure their options.
		$numberOfTaxonomies = count(get_taxonomies(['public' => true]));
		$taxonomyDefaultPriority = (int) floor($numberOfTaxonomies / 2);

		$taxonomyFields = [
			[
				'key' => 'field_cache_control_taxonomy_message',
				'label' => '',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => 'Configuration options for the available public taxonomies.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			]
		];

		foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
			$ignoreDefault = 0;
			$defaultIgnored = ['post_tag', 'post_format'];
			if (in_array($taxonomy->name, $defaultIgnored)) {
				$ignoreDefault = 1;
			}

			$taxonomySubFields = [
				[
					'key' => 'field_cache_control_taxonomy_'.$taxonomy->name.'_cache_ignore',
					'label' => 'Ignore taxonomy',
					'name' => 'cache_control_taxonomy_'.$taxonomy->name.'_cache_ignore',
					'type' => 'true_false',
					'instructions' => 'Select to ignore any values for this taxonomy when configuring the cache',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'message' => '',
					'default_value' => $ignoreDefault,
					'ui' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
				],
				[
					'key' => 'field_cache_control_taxonomy_'.$taxonomy->name.'_cache_age',
					'label' => $taxonomy->label.' ('.$taxonomy->name.') cache',
					'name' => 'cache_control_taxonomy_'.$taxonomy->name.'_cache_age',
					'type' => 'select',
					'choices' => $ageOptions,
					'instructions' => 'Set max-age value for the '.$taxonomy->label.' ('.$taxonomy->name.') taxonomy',
					'required' => 0,
					'conditional_logic' => [
						[
							[
								'field' => 'field_cache_control_taxonomy_'.$taxonomy->name.'_cache_ignore',
								'operator' => '!=',
								'value' => '1',
							],
						],
					],
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'message' => '',
					'default_value' => 'default',
					'ui' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
				],
				[
					'key' => 'field_cache_control_taxonomy_'.$taxonomy->name.'_priority',
					'label' => 'Cache priority',
					'name' => 'cache_control_taxonomy_'.$taxonomy->name.'_priority',
					'type' => 'range',
					'instructions' => 'Set priority for this taxonomy in caching, highest priority is priority 1',
					'required' => 0,
					'conditional_logic' => [
						[
							[
								'field' => 'field_cache_control_taxonomy_'.$taxonomy->name.'_cache_ignore',
								'operator' => '!=',
								'value' => '1',
							],
						],
					],
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'default_value' => $taxonomyDefaultPriority,
					'min' => 1,
					'max' => $numberOfTaxonomies,
					'step' => '',
					'prepend' => '',
					'append' => '',
				],
			];

			$taxonomyFieldsTaxonomyConfig = [
				'key' => 'cache_control_taxonomy_'.$taxonomy->name.'_settings',
				'label' => $taxonomy->label.' ('.$taxonomy->name.') Settings',
				'name' => 'cache_control_'.$taxonomy->name.'_settings',
				'type' => 'group',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'layout' => 'block',
				'sub_fields' => $taxonomySubFields,
			];
			$taxonomyFields[] = $taxonomyFieldsTaxonomyConfig;
		}
		return $taxonomyFields;
	}

	protected function getTemplatesConfig(array $ageOptions): array
	{
		$templateFields = [
			[
				'key' => 'field_cache_control_template_message',
				'label' => '',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => [
					'width' => '',
					'class' => '',
					'id' => '',
				],
				'message' => 'Configuration options for the available templates.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			]
		];
		foreach ($this->templates as $postType => $postTypeTemplates) {
			foreach ($postTypeTemplates as $templateFile => $templateName) {
				$localTemplateFile = preg_replace('/\.php$/', '', $templateFile);
				$templateSubFields = [
					[
						'key' => 'field_cache_control_template_'.$localTemplateFile.'_message',
						'label' => '',
						'name' => '',
						'type' => 'message',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => 'Template used in the '.$postType.' post type.',
						'new_lines' => 'wpautop',
						'esc_html' => 0,
					],
					[
						'key' => 'field_cache_control_template_'.$localTemplateFile.'_cache_age',
						'label' => $templateName.' cache',
						'name' => 'cache_control_template_'.$localTemplateFile.'_cache_age',
						'type' => 'select',
						'choices' => $ageOptions,
						'instructions' => 'Set max-age value for the '.$templateName.' template',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => '',
						'default_value' => 'default',
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					],
					[
						'key' => 'field_cache_control_template_'.$localTemplateFile.'_override_taxonomy',
						'label' => 'Overrides taxonomy cache age',
						'name' => 'cache_control_template_'.$localTemplateFile.'_override_taxonomy',
						'type' => 'true_false',
						'instructions' => 'Can this template cache setting override the taxonomy config',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => [
							'width' => '',
							'class' => '',
							'id' => '',
						],
						'message' => '',
						'default_value' => 0,
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					]
				];

				$templateFieldsTemplateConfig = [
					'key' => 'cache_control_template_'.$localTemplateFile.'_settings',
					'label' => $templateName.' Settings',
					'name' => 'cache_control'.$localTemplateFile.'_settings',
					'type' => 'group',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'layout' => 'block',
					'sub_fields' => $templateSubFields,
				];
				$templateFields[] = $templateFieldsTemplateConfig;
			}
		}
		return $templateFields;
	}
}
