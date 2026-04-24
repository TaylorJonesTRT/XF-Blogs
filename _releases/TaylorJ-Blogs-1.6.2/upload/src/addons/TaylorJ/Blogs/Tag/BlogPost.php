<?php

namespace TaylorJ\Blogs\Tag;

use XF\Mvc\Entity\Entity;
use XF\Tag\AbstractHandler;
use TaylorJ\Blogs\Entity\Blog;

class BlogPost extends AbstractHandler
{
	public function getPermissionsFromContext(Entity $entity)
	{
		$visitor = \XF::visitor();

		if ($entity instanceof \TaylorJ\Blogs\Entity\BlogPost) {
			$blogPost = $entity;
			$blog = $blogPost->Blog;
		} elseif ($entity instanceof Blog) {
			$blogPost = null;
			$blog = $entity;
		} else {
			throw new \InvalidArgumentException("Entity must be a blog post or blog");
		}

		$removeOthers = $visitor->hasPermission('taylojBlogPost', 'manageAnyTag');

		$edit = $blogPost ? $blogPost->canEditTags() : $blog->canEditTags();

		return [
			'edit' => $edit,
			'removeOthers' => $removeOthers,
			'minTotal' => \XF::options()->taylorjBlogPostsMinTagCount,
		];
	}

	public function getContentVisibility(Entity $entity)
	{
		return $entity->blog_post_state == 'visible';
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'blogPost' => $entity,
			'options' => $options,
		];
	}

	public function getEntityWith($forView = false)
	{
		$get = ['Blog'];
		if ($forView) {
			$get[] = 'User';

			$visitor = \XF::visitor();
			$get[] = 'Blog';
		}

		return $get;
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		/** @var \TaylorJ\Blogs\Entity\BlogPost $entity */
		return $entity->canUseInlineModeration($error);
	}
}
