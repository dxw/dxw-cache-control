<?php

describe(\CacheControl\Options::class, function () {
	beforeEach(function () {
		$this->options = new \CacheControl\Options();
	});

	it('is registerable', function () {
		expect($this->options)->toBeAnInstanceOf(\Dxw\Iguana\Registerable::class);
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
				expect('acf_add_local_field_group')->toBeCalled()->times(3)->with(\Kahlan\Arg::toBeAn('array'));
				allow('get_post_types')->toBeCalled()->andReturn(
					[
						'post' => (object) ['name' => 'post', 'label' => 'Post'],
						'page' => (object) ['name' => 'post', 'label' => 'Post']
					]
				);
				expect('get_post_types')->toBeCalled()->once();
				allow('get_taxonomies')->toBeCalled()->andReturn(
					[
						'category' => (object) ['name' => 'category', 'label' => 'Categories'],
						'post_tag' => (object) ['name' => 'post_tag', 'label' => 'Tags']
					]
				);
				expect('get_post_types')->toBeCalled()->once();

				$this->options->addOptions();
			});
		});
	});
});
