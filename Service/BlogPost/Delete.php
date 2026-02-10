<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Repository\BlogPost as BlogPostRepo;
use XF\App;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\Thread\Replier;

class Delete extends AbstractService
{
	/**
	 * @var BlogPost
	 */
	public $blogPost;

	/**
	 * @var User|null
	 */
	protected $user;

	protected $addPost = false;
	protected $postDeleteReason = '';

	/**
	 * @var null|User
	 */
	protected $postByUser = null;

	protected $alert = false;
	protected $alertReason = '';

	public function __construct(App $app, BlogPost $blogPost)
	{
		parent::__construct($app);
		$this->blogPost = $blogPost;

		if (!empty($app->options()->taylorjBlogsBlogPostDeleteThreadAction['add_post']))
		{
			$this->addPost = true;
		}
	}

	public function getBlogPost()
	{
		return $this->blogPost;
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

	public function setAddPost($addPost)
	{
		$this->addPost = (bool) $addPost;
	}

	public function setPostByUser(?User $user = null)
	{
		$this->postByUser = $user;
	}

	public function setPostDeleteReason($reason)
	{
		$this->postDeleteReason = $reason;
	}

	public function delete($type, $reason = '')
	{
		$user = $this->user ?: \XF::visitor();
		$wasVisible = $this->blogPost->isVisible();

		if ($type == 'soft')
		{
			$result = $this->blogPost->softDelete($reason, $user);
		}
		else
		{
			$result = $this->blogPost->delete();
		}

		$this->updateCommentsThread();

		if ($result && $wasVisible && $this->alert && $this->blogPost->user_id != $user->user_id)
		{
			/** @var BlogPostRepo $blogPostRepo */
			$blogPostRepo = $this->repository('TaylorJ\Blogs:BlogPost');
			$blogPostRepo->sendModeratorActionAlert($this->blogPost, 'delete', $this->alertReason);
		}

		return $result;
	}

	protected function updateCommentsThread()
	{
		if (!$this->addPost)
		{
			return;
		}

		$blogPost = $this->blogPost;
		$thread = $blogPost->Discussion;
		if (!$thread)
		{
			return;
		}

		if ($this->postByUser)
		{
			$asUser = $this->postByUser;
		}
		else
		{
			$asUser = $blogPost->User ?: $this->repository('XF:User')->getGuestUser($blogPost->username);
		}

		\XF::asVisitor($asUser, function () use ($thread)
		{
			$replier = $this->setupCommentsThreadReply($thread);
			if ($replier && $replier->validate())
			{
				$existingLastPostDate = $replier->getThread()->last_post_date;

				/* @var $post Post */
				$post = $replier->save();
				$this->afterCommentsThreadReplied($post, $existingLastPostDate);

				\XF::runLater(function () use ($replier)
				{
					$replier->sendNotifications();
				});
			}
		});
	}

	protected function setupCommentsThreadReply(Thread $thread)
	{
		if (!$thread->Forum)
		{
			// thread has been orphaned somehow?
			return null;
		}

		/** @var Replier $replier */
		$replier = $this->service('XF:Thread\Replier', $thread);
		$replier->setIsAutomated();
		$replier->setMessage($this->getThreadReplyMessage(), false);

		return $replier;
	}

	protected function getThreadReplyMessage()
	{
		$blogPost = $this->blogPost;
		$username = $blogPost->User ? $blogPost->User->username : $blogPost->username;
		$phraseName = $this->postDeleteReason ? 'taylorj_blogs_blog_post_thread_delete_reason_x' : 'taylorj_blogs_blog_post_thread_delete';

		$phrase = \XF::phrase($phraseName, [
			'title' => $blogPost->blog_post_title,
			'username' => $username,
			'reason' => $this->postDeleteReason,
		]);

		return $phrase->render('raw');
	}

	protected function afterCommentsThreadReplied(Post $post, $existingLastPostDate)
	{
		$thread = $post->Thread;

		if (\XF::visitor()->user_id && $post->Thread->getVisitorReadDate() >= $existingLastPostDate)
		{
			$this->repository('XF:Thread')->markThreadReadByVisitor($thread);
		}
	}
}
