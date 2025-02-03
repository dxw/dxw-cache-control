<?php

describe(\CacheControl\Page::class, function () {
	beforeEach(function () {
		$this->page = new \CacheControl\Page();
	});

	describe('isAdmin()', function () {
		it('returns the result of is_admin()', function () {
			allow('is_admin')->toBeCalled()->andReturn(true, false);
			expect($this->page->isAdmin())->toEqual(true);
			expect($this->page->isAdmin())->toEqual(false);
		});
	});

	describe('isArchivePage()', function () {
		it('returns the result of is_post_type_archive()', function () {
			allow('is_post_type_archive')->toBeCalled()->andReturn(true, false);
			expect($this->page->isArchivePage())->toEqual(true);
			expect($this->page->isArchivePage())->toEqual(false);
		});
	});

	describe('isFrontPage()', function () {
		it('returns the result of is_front_page()', function () {
			allow('is_front_page')->toBeCalled()->andReturn(true, false);
			expect($this->page->isFrontPage())->toEqual(true);
			expect($this->page->isFrontPage())->toEqual(false);
		});
	});

	describe('isHomePage()', function () {
		it('returns the result of is_home()', function () {
			allow('is_home')->toBeCalled()->andReturn(true, false);
			expect($this->page->isHomePage())->toEqual(true);
			expect($this->page->isHomePage())->toEqual(false);
		});
	});

	describe('isLoggedInUser()', function () {
		it('returns the result of is_user_logged_in()', function () {
			allow('is_user_logged_in')->toBeCalled()->andReturn(true, false);
			expect($this->page->isLoggedInUser())->toEqual(true);
			expect($this->page->isLoggedInUser())->toEqual(false);
		});
	});

	describe('isPreviewPage()', function () {
		it('returns the result of is_preview()', function () {
			allow('is_preview')->toBeCalled()->andReturn(true, false);
			expect($this->page->isPreviewPage())->toEqual(true);
			expect($this->page->isPreviewPage())->toEqual(false);
		});
	});

	describe('postType()', function () {
		context('post type is found', function () {
			it('returns the result of get_post_type()', function () {
				allow('get_post_type')->toBeCalled()->andReturn('foo');
				expect($this->page->postType())->toEqual('foo');
			});
		});
		context('post type is not known', function () {
			it('returns \'unknown\'', function () {
				allow('get_post_type')->toBeCalled()->andReturn(false);
				expect($this->page->postType())->toEqual('unknown');
			});
		});
	});

	describe('taxonomies()', function () {
		context('post has taxonomies', function () {
			it('returns the result of get_post_taxonomies()', function () {
				allow('get_post_taxonomies')->toBeCalled()->andReturn(['foo', 'bar']);
				expect($this->page->taxonomies())->toEqual(['foo', 'bar']);
			});
		});
		context('post has no taxonomies', function () {
			it('returns [\'none\']', function () {
				allow('get_post_taxonomies')->toBeCalled()->andReturn(null);
				expect($this->page->taxonomies())->toEqual(['none']);
			});
		});
	});

	describe('templateName()', function () {
		context('get_page_template_slug returns false', function () {
			it('returns \'default\'', function () {
				allow('get_page_template_slug')->toBeCalled()->andReturn(false);
				expect($this->page->templateName())->toEqual('default');
			});
		});
		context('get_page_template_slug returns an empty string', function () {
			it('returns \'default\'', function () {
				allow('get_page_template_slug')->toBeCalled()->andReturn('');
				expect($this->page->templateName())->toEqual('default');
			});
		});
		context('get_page_template_slug returns a popu;lated string', function () {
			it('returns that string', function () {
				allow('get_page_template_slug')->toBeCalled()->andReturn('foo');
				expect($this->page->templateName())->toEqual('foo');
			});
		});
	});

	describe('->requiresPassword()', function () {
		context('the post has not password set', function () {
			it('returns false', function () {
				global $post;
				$post = (object) [
					'post_password' => ''
				];
				expect($this->page->requiresPassword())->toEqual(false);
			});
		});
		context('the post has a password set', function () {
			it('returns true', function () {
				global $post;
				$post = (object) [
					'post_password' => 'foobar'
				];
				expect($this->page->requiresPassword())->toEqual(true);
			});
		});
	});

	describe('->postId()', function () {
		context('it returns something that is not an instance of WP_Post', function () {
			it('returns 0', function () {
				allow('get_post')->toBeCalled()->andReturn('foo');
				expect($this->page->postId())->toEqual(0);
			});
		});
		context('it returns a WP_Post object', function () {
			it('returns the ID of that post', function () {
				$post = (object) [
					'ID' => 123
				];
				allow('get_post')->toBeCalled()->andReturn($post);
				allow('is_a')->toBeCalled()->andReturn(true);
				expect($this->page->postId())->toEqual(123);
			});
		});
	});
});
