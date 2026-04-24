<?php

namespace TaylorJ\Blogs\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use TaylorJ\Blogs\Service\BlogPost\Approve;

class Blog extends AbstractHandler
{
	protected function canActionContent(Entity $content, &$error = null)
	{
		/** @var $content \TaylorJ\Blogs\Entity\Blog */
		return $content->canApproveUnapprove($error);
	}

	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['User'];
	}

	public function actionApprove(\TaylorJ\Blogs\Entity\Blog $blog)
	{
		/** @var Approve $approver */
		$approver = \XF::service('TaylorJ\Blogs:Blog\Approve', $blog);
		$approver->setNotifyRunTime(1); // may be a lot happening
		$approver->approve();
	}

	public function actionDelete(\TaylorJ\Blogs\Entity\Blog $blog)
	{
		$this->quickUpdate($blog, 'blog_state', 'deleted');
	}
}
