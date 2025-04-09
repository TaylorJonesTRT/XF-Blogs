<?php

namespace TaylorJ\Blogs\InlineMod;

use TaylorJ\Blogs\Service\BlogPost\Approve;
use XF\InlineMod\AbstractHandler;
use XF\InlineMod\FeaturableTrait;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<\TaylorJ\Blogs\Entity\BlogPost>
 */
class BlogPost extends AbstractHandler
{
	use FeaturableTrait;

	public function getPossibleActions()
	{
		$actions = [];

		$actions['delete'] = $this->getActionHandler('TaylorJ\Blogs:BlogPost\Delete');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('taylorj_blogs_undelete_blog_posts'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var \TaylorJ\Blogs\Entity\BlogPost $entity */
				if ($entity->blog_post_state == 'deleted')
				{
					$entity->blog_post_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('taylorj_blogs_approve_blog_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var \TaylorJ\Blogs\Entity\BlogPost $entity */
				if ($entity->blog_post_state == 'moderated')
				{
					/** @var Approve $approver */
					$approver = \XF::service('TaylorJ\Blogs:BlogPost\Approve', $entity);
					$approver->setNotifyRunTime(1); // may be a lot happening
					$approver->approve();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('taylorj_blogs_unapprove_blog_posts'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var \TaylorJ\Blogs\Entity\BlogPost $entity */
				if ($entity->blog_post_state == 'visible')
				{
					$entity->blog_post_state = 'moderated';
					$entity->save();
				}
			}
		);

		/*static::addPossibleFeatureActions(*/
		/*	$this,*/
		/*	$actions,*/
		/*	\XF::phrase('xfrm_feature_resources'),*/
		/*	\XF::phrase('xfrm_unfeature_resources'),*/
		/*	'canFeatureUnfeature'*/
		/*);*/

		/*$actions['reassign'] = $this->getActionHandler('TaylorJ\Blogs:ResourceItem\Reassign');*/
		/*$actions['move'] = $this->getActionHandler('XFRM:ResourceItem\Move');*/
		/*$actions['apply_prefix'] = $this->getActionHandler('XFRM:ResourceItem\ApplyPrefix');*/

		return $actions;
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Blog'];
	}
}
