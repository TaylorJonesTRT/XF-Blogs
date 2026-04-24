<?php

namespace TaylorJ\Blogs\ThreadType;

use XF\Entity\Thread;
use XF\Http\Request;
use XF\ThreadType\AbstractHandler;

class BlogPost extends AbstractHandler
{
	public function getTypeIconClass(): string
	{
		return '';
	}

	public function getThreadViewAndTemplate(Thread $thread): array
	{
		return ['TaylorJ\Blogs:Thread\ViewTypeBlogPost', 'taylorj_blogs_thread_view_type_blog_post'];
	}

	public function adjustThreadViewParams(Thread $thread, array $viewParams, Request $request): array
	{
		$thread = $viewParams['thread'] ?? null;
		if ($thread)
		{
			/** @var \TaylorJ\Blogs\Entity\BlogPost $blogPost */
			$blogPost = \XF::repository('TaylorJ\Blogs:BlogPost')->findBlogPostForThread($thread)->fetchOne();
			if ($blogPost && $blogPost->canView())
			{
				$viewParams['blogPost'] = $blogPost;
			}
		}

		return $viewParams;
	}

	public function allowExternalCreation(): bool
	{
		return false;
	}

	public function canThreadTypeBeChanged(Thread $thread): bool
	{
		return false;
	}

	public function canConvertThreadToType(bool $isBulk): bool
	{
		return false;
	}
}
