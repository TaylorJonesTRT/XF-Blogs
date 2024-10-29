<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use XF\App;
use XF\Service\AbstractService;
use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Service\Blog\Notify;

use TaylorJ\Blogs\Utils;

class Approve extends AbstractService
{
	/**
	 * @var BlogPost 
	 */
	protected $blogPost;

	protected $notifyRunTime = 3;

	public function __construct(App $app, BlogPost $blogPost)
	{
		parent::__construct($app);
		$this->blogPost = $blogPost;
	}

	public function getBlogPost()
	{
		return $this->blogPost;
	}

	public function setNotifyRunTime($time)
	{
		$this->notifyRunTime = $time;
	}

	public function approve()
	{
		if ($this->blogPost->blog_post_state == 'moderated') {
			$this->blogPost->blog_post_state = 'visible';
			$this->blogPost->save();

			$this->onApprove();
			return true;
		} else {
			return false;
		}
	}

	protected function onApprove()
	{
		$blog = $this->blogPost->Blog;

		$creator = Utils::setupBlogPostThreadCreation($this->blogPost);
		if ($creator && $creator->validate()) {
			$thread = $creator->save();
			$this->blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
			Utils::afterResourceThreadCreated($thread);
		}
	}
}
