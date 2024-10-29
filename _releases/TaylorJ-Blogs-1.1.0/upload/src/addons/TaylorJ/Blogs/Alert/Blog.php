<?php

namespace TaylorJ\Blogs\Alert;

use XF\Mvc\Entity\Entity;

class Blog extends \XF\Alert\AbstractHandler
{
	public function canViewContent(Entity $entity, &$error = null)
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $entity */
		return $entity->canView();
	}

	public function getOptOutActions()
	{
		return [
			'edit',
		];
	}

	public function getOptOutDisplayOrder()
	{
		return 1000;
	}
}

