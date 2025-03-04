<?php

describe(\CacheControl\Options::class, function () {
	beforeEach(function () {
		$this->options = new \CacheControl\Options();
		allow('wp_get_environment_type')->toBeCalled()->andReturn('production');
	});

	it('is registerable', function () {
		expect($this->options)->toBeAnInstanceOf(\Dxw\Iguana\Registerable::class);
	});

	describe('->register()', function () {
		it('adds actions and filters', function () {
			allow('add_action')->toBeCalled();
			expect('add_action')->toBeCalled()->times(2);
			expect('add_action')->toBeCalled()->with('acf/init', [$this->options, 'addOptionsPage']);
			expect('add_action')->toBeCalled()->with('init', [$this->options, 'addOptions'], 999);
			allow('add_filter')->toBeCalled();
			expect('add_filter')->toBeCalled()->with('plugin_action_links_dxw-cache-control/index.php', [$this->options, 'addActionLinks']);

			$this->options->register();
		});
	});

	describe('->addActionLinks()', function () {
		it('adds a link to the settings page', function () {
			allow('admin_url')->toBeCalled()->with('options-general.php?page=cache-control-settings')->andReturn('https://example.com/wp-admin/options-general.php?page=cache-control-settings');

			$expected = ['<a href="https://example.com/wp-admin/options-general.php?page=cache-control-settings">Settings</a>'];
			$result = $this->options->addActionLinks([]);

			expect($result)->toEqual($expected);
		});
	});

	describe('->addOptionsPage()', function () {
		context('ACF is not activated', function () {
			it('does nothing', function () {
				allow('function_exists')->toBeCalled()->andReturn(false);
				expect('function_exists')->toBeCalled()->once();

				$this->options->addOptionsPage();
			});
		});

		context('ACF is activated', function () {
			it('adds the options page', function () {
				allow('function_exists')->toBeCalled()->andReturn(true);
				allow('acf_add_options_page')->toBeCalled();
				expect('acf_add_options_page')->toBeCalled()->once()->with(\Kahlan\Arg::toBeAn('array'));

				$this->options->addOptionsPage();
			});
		});
	});

	describe('->addOptions()', function () {
		context('ACF is not activated', function () {
			it('does nothing', function () {
				allow('function_exists')->toBeCalled()->andReturn(false);
				expect('function_exists')->toBeCalled()->once();

				$this->options->addOptionsPage();
			});
		});

		context('ACF is activated', function () {
			it('adds the options page (there are no custom templates)', function () {
				allow('function_exists')->toBeCalled()->andReturn(true);
				expect('function_exists')->toBeCalled()->times(2);
				allow('acf_get_post_templates')->toBeCalled()->andReturn(['page' => []]);
				expect('acf_get_post_templates')->toBeCalled()->once();
				allow('acf_add_local_field_group')->toBeCalled();
				expect('acf_add_local_field_group')->toBeCalled()->times(4)->with(\Kahlan\Arg::toBeAn('array'));
				allow('get_post_types')->toBeCalled()->andReturn(
					[
						'post' => (object)['name' => 'post', 'label' => 'Post'],
						'page' => (object)['name' => 'page', 'label' => 'Page']
					]
				);
				expect('get_post_types')->toBeCalled()->once()->with(['public' => true], 'objects');
				allow('get_taxonomies')->toBeCalled()->andReturn(
					[
						'category' => (object)['name' => 'category', 'label' => 'Categories'],
						'post_tag' => (object)['name' => 'post_tag', 'label' => 'Tags']
					]
				);
				expect('get_taxonomies')->toBeCalled()->times(2);

				$this->options->addOptions();
			});

			it('adds the options page (there are custom templates)', function () {
				allow('function_exists')->toBeCalled()->andReturn(true);
				allow('acf_get_post_templates')
					->toBeCalled()
					->andReturn(['page' => ['Custom Template' => 'custom-template.php']]);
				expect('acf_get_post_templates')->toBeCalled()->once();
				allow('acf_add_local_field_group')->toBeCalled();
				expect('acf_add_local_field_group')->toBeCalled()->times(5)->with(\Kahlan\Arg::toBeAn('array'));

				allow('get_post_types')->toBeCalled()->andReturn(
					[
						'post' => (object)['name' => 'post', 'label' => 'Post'],
						'page' => (object)['name' => 'page', 'label' => 'Page']
					]
				);
				expect('get_post_types')->toBeCalled()->once()->with(['public' => true], 'objects');
				allow('get_taxonomies')->toBeCalled()->andReturn(
					[
						'category' => (object)['name' => 'category', 'label' => 'Categories'],
						'post_tag' => (object)['name' => 'post_tag', 'label' => 'Tags']
					]
				);
				expect('get_taxonomies')->toBeCalled()->times(2);

				$this->options->addOptions();
			});
		});
	});
});
