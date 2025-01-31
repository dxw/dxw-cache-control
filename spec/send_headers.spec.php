<?php

use Kahlan\Plugin\Double;

describe(\CacheControl\SendHeaders::class, function () {
	beforeEach(function () {
		$this->sendHeaders = new \CacheControl\SendHeaders();

		global $post;
		$post = (object) [];
		allow('is_admin')->toBeCalled()->andReturn(false);
		allow('is_post_type_archive')->toBeCalled()->andReturn(false);
		allow('is_front_page')->toBeCalled()->andReturn(false);
		allow('is_home')->toBeCalled()->andReturn(false);
		allow('is_user_logged_in')->toBeCalled()->andReturn(false);
		allow('is_preview')->toBeCalled()->andReturn(false);
		allow('get_post_type')->toBeCalled()->andReturn('post');
		allow('get_post_taxonomies')->toBeCalled()->andReturn(['category', 'post_tag', 'custom-taxonomy']);
		allow('get_page_template_slug')->toBeCalled()->andReturn('default');
		allow('get_post_types')->toBeCalled()->andReturn(['post', 'page', 'custom-post']);
		allow('wp_get_environment_type')->toBeCalled()->andReturn('local');
		allow('get_post')->toBeCalled()->andReturn(null);

		$this->config = [
			'cache_control_plugin_developer_mode' => false,
			'cache_control_plugin_front_page_cache' => 'default',
			'cache_control_plugin_home_page_cache' => 'default',
			'cache_control_plugin_archives_cache' => 'default',
			'cache_control_post_type_post_settings' => [
				'cache_control_post_type_post_cache_age' => 'default',
				'cache_control_post_type_post_override_archive' => false,
				'cache_control_post_type_post_overridden_by_taxonomy' => false,
			],
			'cache_control_post_type_page_settings' => [
				'cache_control_post_type_page_cache_age' => 'default',
				'cache_control_post_type_page_overridden_by_taxonomy' => false,
				'cache_control_post_type_page_overridden_by_template' => false,
			],
			'cache_control_post_type_custom-post_settings' => [
				'cache_control_post_type_custom-post_cache_age' => 'default',
				'cache_control_post_type_custom-post_override_archive' => false,
				'cache_control_post_type_custom-post_overridden_by_taxonomy' => false,
			],
			'cache_control_taxonomy_category_settings' => [
				'cache_control_taxonomy_category_cache_ignore' => false,
				'cache_control_taxonomy_category_cache_age' => 'default',
				'cache_control_taxonomy_category_priority' => 1,
			],
			'cache_control_taxonomy_post_tag_settings' => [
				'cache_control_taxonomy_post_tag_cache_ignore' => true,
			],
			'cache_control_taxonomy_custom-taxonomy_settings' => [
				'cache_control_taxonomy_custom-taxonomy_cache_ignore' => false,
				'cache_control_taxonomy_custom-taxonomy_cache_age' => 'default',
				'cache_control_taxonomy_custom-taxonomy_priority' => 2,
			],
			'cache_control_template_custom-post_settings' => [
				'cache_control_template_page-custom_cache_age' => 'default',
				'cache_control_template_page-custom_override_taxonomy' => false,
			],
			'field_cache_control_individual_post_settings' => []
		];

		$this->headers = [
			'no-cache' => [
				'Cache-Control' => 'no-cache, must-revalidate, max-age=0'
			],
			'cache' => [
				'Cache-Control' => 'max-age=60'
			]
		];

		$this->currentSubConfig = [];
		$this->key = '';
		$this->row = '';

		allow('get_field')->toBeCalled()->andRun(function (string $key, string $string) {
			if (array_key_exists($key, $this->config)) {
				return $this->config[$key];
			}
			return null;
		});

		allow('have_rows')->toBeCalled()->andRun(function (string $key, string $string) {
			if ($this->row == '' || $this->row != $key) {
				if (array_key_exists($key, $this->config) && count($this->config[$key])) {
					$this->key = $key;
					$this->currentSubConfig = $this->config[$key];

					return true;
				}
			}
			return false;
		});

		allow('get_sub_field')->toBeCalled()->andRun(function (string $key) {
			if (array_key_exists($key, $this->currentSubConfig)) {
				return $this->currentSubConfig[$key];
			}
		});

		allow('the_row')->toBeCalled()->andRun(function () {
			$this->row = $this->key;
		});
	});

	it('is registerable', function () {
		expect($this->sendHeaders)->toBeAnInstanceOf(\Dxw\Iguana\Registerable::class);
	});

	describe('->register()', function () {
		it('adds an action and a filter', function () {
			allow('add_filter')->toBeCalled();
			allow('add_action')->toBeCalled();
			expect('add_filter')->toBeCalled()->once()->with(
				'wp_headers',
				[
					$this->sendHeaders,
					'getContext'
				],
				99
			);
			expect('add_action')->toBeCalled()->once()->with(
				'send_headers',
				[
					$this->sendHeaders,
					'setCacheHeader',
				],
				1
			);

			$this->sendHeaders->register();
		});
	});

	describe('->getContext()', function () {
		it('passed in array is empty', function () {
			$result = $this->sendHeaders->getContext([]);
			expect($result)->toEqual([]);
		});

		it('passed in array has values', function () {
			$result = $this->sendHeaders->getContext($this->headers['no-cache']);
			expect($result)->toEqual($this->headers['no-cache']);
		});
	});

	describe('->setCacheHeader()', function () {
		context('we have a logged in user', function () {
			beforeEach(function () {
				allow('is_user_logged_in')->toBeCalled()->andReturn(true);
			});

			it('sets a private cache header for preview pages', function () {
				allow('is_preview')->toBeCalled()->andReturn(true);
				expect('get_field')->toBeCalled()->once();

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});

			it('is not in developer mode', function () {
				expect('get_field')->toBeCalled()->once();

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});

			it('is not in developer mode and wp_headers has been used and set no-cache', function () {
				expect('get_field')->toBeCalled()->once();

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->getContext($this->headers['no-cache']);
				$this->sendHeaders->setCacheHeader();
			});

			it('is not in developer mode and wp_headers has been used and set a cache', function () {
				expect('get_field')->toBeCalled()->once();

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->getContext($this->headers['cache']);
				$this->sendHeaders->setCacheHeader();
			});
		});

		context('the user is not logged in', function () {
			it('sets a private cache header for preview pages', function () {
				allow('is_preview')->toBeCalled()->andReturn(true);
				expect('get_field')->toBeCalled()->once();

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});
		});

		context('Testing developer-mode', function () {
			beforeEach(function () {
				allow('is_user_logged_in')->toBeCalled()->andReturn(true);
				$this->config['cache_control_plugin_developer_mode'] = true;
				allow('is_front_page')->toBeCalled()->andReturn(true);
			});

			it('is in developer mode on the local dev environment', function () {
				expect('get_post_types')->toBeCalled()->once();
				expect('get_field')->toBeCalled()->times(1);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Meta-cc-post-type: post');
				expect('header')->toBeCalled()->once()->with('Meta-cc-taxonomy:category,post_tag,custom-taxonomy');
				expect('header')->toBeCalled()->once()->with('Meta-cc-front-page: yes');
				expect('header')->toBeCalled()->once()->with('Meta-cc-home-page: no');
				expect('header')->toBeCalled()->once()->with('Meta-cc-archive: no');
				expect('header')->toBeCalled()->once()->with('Meta-cc-is-admin: no');
				expect('header')->toBeCalled()->once()->with('Meta-cc-logged-in-user: yes');
				expect('header')->toBeCalled()->once()->with('Meta-cc-template_name: default');
				expect('header')->toBeCalled()->once()->with('Meta-cc-requires-password: no');
				expect('header')->toBeCalled()->once()->with('Meta-cc-post-types: post,page,custom-post');
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});

			it('is on the local environment', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('local');
				expect('get_post_types')->toBeCalled()->once();
				expect('get_field')->toBeCalled()->times(1);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->times(12);

				$this->sendHeaders->setCacheHeader();
			});

			it('is on the development environment', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('development');
				expect('get_post_types')->toBeCalled()->once();
				expect('get_field')->toBeCalled()->times(1);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->times(12);

				$this->sendHeaders->setCacheHeader();
			});

			it('is on the staging environment', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('staging');
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});

			it('is on the production environment', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('production');

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

				$this->sendHeaders->setCacheHeader();
			});

			// this is the option that gives us the max number of dev-mode headers
			// so we test this to check that we are the number of headers we expect
			it('is in developer-mode and we are on a home page that is an archive', function () {
				$this->config['cache_control_plugin_archives_cache'] = 120;
				$this->config['cache_control_plugin_home_page_cache'] = 3600;

				allow('is_home')->toBeCalled()->andReturn(true);
				allow('is_post_type_archive')->toBeCalled()->andReturn(true);
				$this->config['cache_control_plugin_developer_mode'] = true;
				allow('is_front_page')->toBeCalled()->andReturn(false);
				allow('get_page_template_slug')->toBeCalled()->andReturn('custom-template.php');

				expect('get_post_types')->toBeCalled()->once();
				expect('get_field')->toBeCalled()->times(1);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->times(12);

				$this->sendHeaders->setCacheHeader();
			});
		});

		context("We don't have a logged in user and the wp_headers filter has values", function () {
			beforeEach(function () {
				// for simplicities sake we are using the front page for this example.
				allow('is_front_page')->toBeCalled()->andReturn(true);
			});
			it('sets a cache-control header with no-cache', function () {
				// we expect nothing to happen in this instance.

				$this->sendHeaders->getContext($this->headers['no-cache']);
				$result = $this->sendHeaders->setCacheHeader();
				expect($result)->toEqual(null);
			});

			it('sets a cache-control header with a cache', function () {
				expect('get_field')->toBeCalled()->times(3);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->getContext($this->headers['cache']);
				$result = $this->sendHeaders->setCacheHeader();
				expect($result)->toEqual(null);
			});
		});

		context('serving the front_page', function () {
			beforeEach(function () {
				allow('is_front_page')->toBeCalled()->andReturn(true);
			});

			context('has a config value of default', function () {
				it('is not in developer mode', function () {
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is in developer mode', function () {
					$this->config['cache_control_plugin_developer_mode'] = true;

					expect('get_post_types')->toBeCalled()->once();
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Meta-cc-post-type: post');
					expect('header')->toBeCalled()->once()->with('Meta-cc-taxonomy:category,post_tag,custom-taxonomy');
					expect('header')->toBeCalled()->once()->with('Meta-cc-front-page: yes');
					expect('header')->toBeCalled()->once()->with('Meta-cc-home-page: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-archive: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-is-admin: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-logged-in-user: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-template_name: default');
					expect('header')->toBeCalled()->once()->with('Meta-cc-requires-password: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-post-types: post,page,custom-post');
					expect('header')->toBeCalled()->once()->with('Meta-cc-front-page-cache-value: default');
					expect('header')->toBeCalled()->once()->with('Meta-cc-configured-max-age: 86400');
					expect('header')->toBeCalled()->once()->with('Meta-cc-currently-used-config: default');
					expect('header')->toBeCalled()->once()->with('Meta-cc-final-configured-max-age: 86400');
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('has a config value of 1hr', function () {
				beforeEach(function () {
					$this->config['cache_control_plugin_front_page_cache'] = '3600';
				});

				it('is not in developer mode', function () {
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=3600, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is in developer mode', function () {
					$this->config['cache_control_plugin_developer_mode'] = true;
					allow('is_front_page')->toBeCalled()->andReturn(true);

					expect('get_post_types')->toBeCalled()->once();
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Meta-cc-post-type: post');
					expect('header')->toBeCalled()->once()->with('Meta-cc-taxonomy:category,post_tag,custom-taxonomy');
					expect('header')->toBeCalled()->once()->with('Meta-cc-front-page: yes');
					expect('header')->toBeCalled()->once()->with('Meta-cc-home-page: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-archive: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-is-admin: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-logged-in-user: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-template_name: default');
					expect('header')->toBeCalled()->once()->with('Meta-cc-requires-password: no');
					expect('header')->toBeCalled()->once()->with('Meta-cc-post-types: post,page,custom-post');
					expect('header')->toBeCalled()->once()->with('Meta-cc-front-page-cache-value: 3600');
					expect('header')->toBeCalled()->once()->with('Meta-cc-configured-max-age: 3600');
					expect('header')->toBeCalled()->once()->with('Meta-cc-currently-used-config: frontPage');
					expect('header')->toBeCalled()->once()->with('Meta-cc-final-configured-max-age: 3600');
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=3600, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('config values are incorrectly set', function () {
				it('config values are unset (return null)', function () {
					$this->config['cache_control_plugin_front_page_cache'] = null;

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return int)', function () {
					$this->config['cache_control_plugin_front_page_cache'] = 3600;

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return array)', function () {
					$this->config['cache_control_plugin_front_page_cache'] = [];

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});
			});
		});

		context('serving the home_page', function () {
			beforeEach(function () {
				allow('is_home')->toBeCalled()->andReturn(true);
				allow('is_post_type_archive')->toBeCalled()->andReturn(true);
			});

			it('There is no non-default configuration', function () {
				expect('get_field')->toBeCalled()->times(5);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			context('home page is configured', function () {
				beforeEach(function () {
					$this->config['cache_control_plugin_home_page_cache'] = '3600';
				});

				it('is configured, with no other configuration', function () {
					expect('get_field')->toBeCalled()->times(5);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=3600, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is configured, other applicable configurations are present', function () {
					$this->config['cache_control_plugin_archives_cache'] = '120';
					$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_cache_age'] = 900;
					$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_taxonomy'] = true;
					$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;

					expect('get_field')->toBeCalled()->times(3);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=3600, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is configured is also the front page which has default config', function () {
					allow('is_front_page')->toBeCalled()->andReturn(true);
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is configured is also the front page which has config of 1hr', function () {
					allow('is_front_page')->toBeCalled()->andReturn(true);
					$this->config['cache_control_plugin_front_page_cache'] = '3600';
					expect('get_field')->toBeCalled()->times(3);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=3600, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('home page is not configured', function () {
				beforeEach(function () {
					allow('get_post_type')->toBeCalled()->andReturn('post');
				});

				it('archive is configured', function () {
					$this->config['cache_control_plugin_archives_cache'] = '120';

					expect('get_field')->toBeCalled()->times(5);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=120, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('config values are incorrectly set', function () {
				it('config values are unset (return null)', function () {
					$this->config['cache_control_plugin_home_page_cache'] = null;

					expect('get_field')->toBeCalled()->times(4);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return int)', function () {
					$this->config['cache_control_plugin_home_page_cache'] = 3600;

					expect('get_field')->toBeCalled()->times(4);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return array)', function () {
					$this->config['cache_control_plugin_home_page_cache'] = [];

					expect('get_field')->toBeCalled()->times(4);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});
			});
		});

		context('archive page tests', function () {
			beforeEach(function () {
				allow('is_post_type_archive')->toBeCalled()->andReturn(true);
			});

			it('There is no non-default configuration', function () {
				expect('get_field')->toBeCalled()->times(3);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			context('config values are incorrectly set', function () {
				it('config values are unset (return null)', function () {
					$this->config['cache_control_plugin_archives_cache'] = null;

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return int)', function () {
					$this->config['cache_control_plugin_archives_cache'] = 3600;

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('config values are set incorrectly (return array)', function () {
					$this->config['cache_control_plugin_archives_cache'] = [];

					expect('get_field')->toBeCalled()->times(2);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('archive is configured', function () {
				beforeEach(function () {
					$this->config['cache_control_plugin_archives_cache'] = '120';
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 900;
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_overridden_by_taxonomy'] = true;
					$this->config['cache_control_template_custom-post_settings']['cache_control_template_custom-post_cache_age'] = 7200;
				});

				it("isn't overridden by post_type, no taxonomy is configured", function () {
					expect('get_field')->toBeCalled()->times(3);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=120, public');

					$this->sendHeaders->setCacheHeader();
				});

				it("is overridden by post_type, no taxonomy is configured", function () {
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_override_archive'] = true;
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=900, public');

					$this->sendHeaders->setCacheHeader();
				});

				it("isn't overridden by post_type, taxonomy is configured", function () {
					$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});

				it("is overridden by post_type, taxonomy is configured", function () {
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_override_archive'] = true;
					$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('archive is not configured', function () {
				beforeEach(function () {
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 900;
					$this->config['cache_control_template_custom-post_settings']['cache_control_template_custom-post_cache_age'] = 7200;
				});

				it('post type configured, no taxonomy configured', function () {
					expect('get_field')->toBeCalled()->times(3);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=900, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('post type configured and overriden taxonomy flag set to false, taxonomy configured', function () {
					$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('post type configured and overriden taxonomy flag set to true, taxonomy configured', function () {
					$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_overridden_by_taxonomy'] = true;
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});
			});
		});

		context('individual post tests', function () {
			beforeEach(function () {
				$this->postObj = Double::instance([
					'class' => 'WP_Post',
				]);
				$this->postObj->ID = 2;

				$this->config['field_cache_control_individual_post_settings'] = [
					[
						'cache_control_individual_post_post_id' => 2,
						'cache_control_individual_post_cache_age' => '120'
					]
				];
			});

			it('current page has no post ID', function () {
				allow('get_post')->toBeCalled()->andReturn(null);
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('current page has post ID, and a cache override is configured', function () {
				allow('get_post')->toBeCalled()->andReturn($this->postObj);
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=120, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('current page has post ID, and a cache override is configured as "default"', function () {
				$this->config['field_cache_control_individual_post_settings'] = [
					[
						'cache_control_individual_post_post_id' => 2,
						'cache_control_individual_post_cache_age' => 'default'
					]
				];
				allow('get_post')->toBeCalled()->andReturn($this->postObj);
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('current page has post ID, and a cache override is configured for id " "', function () {
				$this->config['field_cache_control_individual_post_settings'] = [
					[
						'cache_control_individual_post_post_id' => '',
						'cache_control_individual_post_cache_age' => 120
					]
				];
				allow('get_post')->toBeCalled()->andReturn($this->postObj);
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('current page has post ID, but not one configured with a cache override', function () {
				$this->postObj->ID = 3;

				allow('get_post')->toBeCalled()->andReturn($this->postObj);
				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});
		});

		context('post_type tests', function () {
			beforeEach(function () {
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 900;
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_cache_age'] = 120;
				$this->config['cache_control_post_type_custom-post_settings']['cache_control_post_type_custom-post_cache_age'] = 43200;
				$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
				$this->config['cache_control_template_page-custom_settings']['cache_control_template_page-custom_cache_age'] = 604800;
			});

			context('the post_type is post', function () {
				it('has the overridden by taxonomy flag set to false', function () {
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=900, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('has the overridden by taxonomy flag set to true', function () {
					$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_overridden_by_taxonomy'] = true;

					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(11);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});
			});

			context('post_type is page', function () {
				beforeEach(function () {
					allow('get_post_type')->toBeCalled()->andReturn('page');
				});

				it('has the overridden by taxonomy flag set to false', function () {
					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(10);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=120, public');

					$this->sendHeaders->setCacheHeader();
				});

				it('is a password protected page', function () {
					allow($this->sendHeaders)->toReceive('hasPassword')->andReturn(true);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: no-cache, no-store, private');

					$this->sendHeaders->setCacheHeader();
				});

				it('has the overridden by taxonomy flag set to true', function () {
					$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_taxonomy'] = true;

					expect('get_field')->toBeCalled()->times(1);
					expect('get_sub_field')->toBeCalled()->times(10);

					allow('header')->toBeCalled();
					expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

					$this->sendHeaders->setCacheHeader();
				});
			});
		});

		context('taxonomy tests', function () {
			beforeEach(function () {
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 900;
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_overridden_by_taxonomy'] = true;
				$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
			});

			it('category is configured but post_type has no config', function () {
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 'default';
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_overridden_by_taxonomy'] = false;
				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('category is configured but post_type has default max-age, but overridden by taxonomy is set', function () {
				$this->config['cache_control_post_type_post_settings']['cache_control_post_type_post_cache_age'] = 'default';
				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=86400, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('category is configured and has highest priority, other taxonomies are not', function () {
				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('category is configured and has lowest priority, other taxonomies are not', function () {
				$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_priority'] = 3;

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('category is configured and has highest priority, other taxonomies are configured', function () {
				$this->config['cache_control_taxonomy_custom-taxonomy_settings']['cache_control_taxonomy_custom-taxonomy_cache_age'] = 7200;
				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('category is configured and has lowest priority, other taxonomies are configured', function () {
				$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_priority'] = 3;
				$this->config['cache_control_taxonomy_custom-taxonomy_settings']['cache_control_taxonomy_custom-taxonomy_cache_age'] = 7200;

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=7200, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('categories list has an unexpected taxonomy value', function () {
				allow('get_post_taxonomies')->toBeCalled()->andReturn(['category', 'post_tag', 'custom-taxonomy', 'nonsense']);

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(11);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

				$this->sendHeaders->setCacheHeader();
			});
		});

		context('template tests', function () {
			beforeEach(function () {
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_cache_age'] = 120;
				$this->config['cache_control_taxonomy_category_settings']['cache_control_taxonomy_category_cache_age'] = 1800;
				$this->config['cache_control_template_page-custom_settings']['cache_control_template_page-custom_cache_age'] = 604800;

				allow('get_post_type')->toBeCalled()->andReturn('page');
				allow('get_page_template_slug')->toBeCalled()->andReturn('page-custom.php');
			});

			it('has a custom template and overridden by template set to false', function () {
				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(12);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=120, public');

				$this->sendHeaders->setCacheHeader();
			});

			it('has a custom template and overridden by template set to true', function () {
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_template'] = true;

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(12);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=604800, public');

				$this->sendHeaders->setCacheHeader();
			});

			it("has a custom template which doesn't override taxonomy, and taxonomy overrides post_type", function () {
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_template'] = true;
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_taxonomy'] = true;

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(12);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=1800, public');

				$this->sendHeaders->setCacheHeader();
			});

			it("has a custom template which overrides taxonomy, and taxonomy overrides post_type", function () {
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_template'] = true;
				$this->config['cache_control_post_type_page_settings']['cache_control_post_type_page_overridden_by_taxonomy'] = true;
				$this->config['cache_control_template_page-custom_settings']['cache_control_template_page-custom_override_taxonomy'] = true;

				expect('get_field')->toBeCalled()->times(1);
				expect('get_sub_field')->toBeCalled()->times(12);

				allow('header')->toBeCalled();
				expect('header')->toBeCalled()->once()->with('Cache-Control: max-age=604800, public');

				$this->sendHeaders->setCacheHeader();
			});
		});
	});
});
