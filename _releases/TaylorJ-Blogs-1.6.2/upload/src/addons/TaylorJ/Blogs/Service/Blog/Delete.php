<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;

class Delete extends AbstractService
{
	/**
	 * @var Blog
	 */
	public $blog;

	/**
	 * @var User|null
	 */
	protected $user;


	protected $alert = false;
	protected $alertReason = '';

	protected $postByUser = null;
	protected $addPost = false;

	protected $blogDeleteReason = '';

	public function __construct(App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
	}

	public function getBlog()
	{
		return $this->blog;
	}

	public function setUser(?User $user = null)
	{
		$this->user = $user;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function setBlogDeleteReason($reason)
	{
		$this->blogDeleteReason = $reason;
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user ?: \XF::visitor();
		$wasVisible = $this->blog->isVisible();

		if ($type == 'soft')
		{
			$result = $this->blog->softDelete($reason, $user);
		}
		else
		{
			$result = $this->blog->delete();
		}

		$this->updateCommentsThread($type, $reason);

		if ($result && $wasVisible && $this->alert && $this->blog->user_id != $user->user_id)
		{
			/** @var BlogPostRepo $blogPostRepo */
			$blogPostRepo = $this->repository('TaylorJ\Blogs:Blog');
			$blogPostRepo->sendModeratorActionAlert($this->blog, 'delete', $this->alertReason);
		}

		return $result;
	}

	protected function updateCommentsThread($type, $reason)
	{
		$blog = $this->blog;

		foreach ($blog->BlogPosts AS $blogPost)
		{
			/** @var BlogPostDelete $deleter */
			$deleter = $this->service('TaylorJ\Blogs:BlogPost\Delete', $blogPost);

			$deleter->delete($type, $reason);
		}
	}
}
