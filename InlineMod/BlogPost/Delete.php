<?php

namespace TaylorJ\Blogs\InlineMod\BlogPost;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

use function count;

/**
 * @extends AbstractAction<\TaylorJ\Blogs\Entity\BlogPost>
 */
class Delete extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('taylorj_blogs_delete_blog_posts...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canDelete($options['type'], $error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var \TaylorJ\Blogs\Service\BlogPost\Delete $deleter */
		$deleter = $this->app()->service('TaylorJ\Blogs:BlogPost\Delete', $entity);

		if ($options['alert'])
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		if ($options['public_delete_reason'] && $entity->canSetPublicDeleteReason())
		{
			$deleter->setPostDeleteReason($options['public_delete_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);

		if ($options['type'] == 'hard')
		{
			$this->returnUrl = $this->app()->router()->buildLink('blogs/blog', $entity->Blog);
		}
	}

	public function getBaseOptions()
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'public_delete_reason' => '',
			'alert' => false,
			'alert_reason' => '',
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$canSetPublicReason = false;

		foreach ($entities AS $entity)
		{
			if ($entity->canSetPublicDeleteReason())
			{
				$canSetPublicReason = true;
				break;
			}
		}

		$viewParams = [
			'blogPosts' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
			'canSetPublicReason' => $canSetPublicReason,
		];
		return $controller->view('TaylorJ\Blogs:Public:InlineMod\BlogPost\Delete', 'inline_mod_blog_posts_delete', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'public_delete_reason' => $request->filter('public_delete_reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
	}
}
