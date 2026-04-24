<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Service\BlogPost\Notify;
use XF\App;
use XF\Service\AbstractService;

class Approve extends AbstractService
{
	/**
	 * @var Blog
	 */
	public $blog;

	protected $notifyRunTime = 3;

	public function __construct(App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->blog->blog_state == 'moderated')
		{
			$this->blog->blog_state = 'visible';
			$this->blog->save();

			$this->onApprove();
			return true;
		}
		else
		{
			return false;
		}
	}

	protected function onApprove()
	{
		$blog = $this->blog;

		if ($blog)
		{
			/** @var Notify $notifier */
			/*$notifier = $this->service('TaylorJ\Blogs:BlogPost\Notify', $blog, 'taylorj_blogs_blog_post');*/
			/*$notifier->notifyAndEnqueue($this->notifyRunTime);*/
		}
	}
}
