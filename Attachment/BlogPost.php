<?php

namespace TaylorJ\UserBlogs\Attachment;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;

class BlogPost extends \XF\Attachment\AbstractHandler
{
	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		return $container->canViewAttachments();
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$em = \XF::em();

		if (!empty($context['blog_post_id']))
		{
			$blogPost = $em->find('TaylorJ\UserBlogs:BlogPost', intval($context['blog_post_id']));
			if (!$blogPost || !$blogPost->canEdit())
			{
				return false;
			}

			return $blogPost->canUploadAndManageAttachments();
		}
		else
		{
			$blogPost = $em->create('TaylorJ\UserBlogs:BlogPost');
			return $blogPost->canUploadAndManageAttachments();
		}
	}

	public function onAttachmentDelete(Attachment $attachment, Entity $container = null)
	{
		return;
	}

	public function getConstraints(array $context)
	{
		return \XF::repository('XF:Attachment')->getDefaultAttachmentConstraints();
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['blog_post_id']) ? intval($context['blog_post_id']) : null;
	}

	public function getContainerLink(Entity $container, array $extraParams = [])
	{
		return \XF::app()->router('public')->buildLink('taylorj-userblogs', $container, $extraParams);
	}

	public function getContext(Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof \TaylorJ\UserBlogs\Entity\BlogPost)
		{
			$extraContext['blog_post_id'] = $entity->blog_post_id;
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be a blog post");
		}

		return $extraContext;
	}
}