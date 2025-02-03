<?php

namespace CacheControl;

class Page
{
	public function isAdmin(): bool
	{
		return is_admin();
	}

	public function isArchivePage(): bool
	{
		return is_post_type_archive();
	}

	public function isFrontPage(): bool
	{
		return is_front_page();
	}

	public function isHomePage(): bool
	{
		return is_home();
	}

	public function isLoggedInUser(): bool
	{
		return is_user_logged_in();
	}

	public function isPreviewPage(): bool
	{
		return is_preview();
	}

	public function postType(): string
	{
		return get_post_type() ?: 'unknown';
	}

	public function taxonomies(): array
	{
		// Note: this implementation is actually buggy
		// get_post_taxonomies will never return null, only an empty array
		// Spotted in a refactor, will be fixed in a separate commit
		return get_post_taxonomies() ?? ['none'];
	}

	public function templateName(): string
	{
		return get_page_template_slug() ?: 'default';
	}

	public function requiresPassword(): bool
	{
		global $post;
		return !empty($post->post_password);
	}

	public function postId(): int
	{
		$post = get_post();

		if (is_a($post, \WP_Post::class)) {
			return $post->ID;
		}
		return 0;
	}
}
