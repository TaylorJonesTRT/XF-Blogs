<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;
use XF\Service\Thread\Replier;
use XFRM\Repository\ResourceItem;

class Delete extends AbstractService
{
	/**
	 * @var Blog
	 */
	protected $blog;

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

	public function __construct(App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;

		/*if (!empty($app->options()->xfrmResourceDeleteThreadAction['add_post']))*/
		/*{*/
		/*	$this->addPost = true;*/
		/*}*/
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

	/*public function setSendAlert($alert, $reason = null)*/
	/*{*/
	/*	$this->alert = (bool) $alert;*/
	/*	if ($reason !== null)*/
	/*	{*/
	/*		$this->alertReason = $reason;*/
	/*	}*/
	/*}*/
	/**/
	/*public function setAddPost($addPost)*/
	/*{*/
	/*	$this->addPost = (bool) $addPost;*/
	/*}*/
	/**/
	/*public function setPostByUser(?User $user = null)*/
	/*{*/
	/*	$this->postByUser = $user;*/
	/*}*/
	/**/
	/*public function setPostDeleteReason($reason)*/
	/*{*/
	/*	$this->postDeleteReason = $reason;*/
	/*}*/

	/*public function delete($type, $reason = '')*/
	public function delete()
	{
		/*$user = $this->user ?: \XF::visitor();*/
		/*$wasVisible = $this->blog->isVisible();*/

		/*if ($type == 'soft')*/
		/*{*/
		/*	$result = $this->blog->softDelete($reason, $user);*/
		/*}*/
		/*else*/
		/*{*/
		/*	$result = $this->blog->delete();*/
		/*}*/

		$result = $this->blog->delete();

		/*$this->updateResourceThread();*/

		/*if ($result && $wasVisible && $this->alert && $this->blog->user_id != $user->user_id)*/
		/*{*/
		/** @var ResourceItem $resourceRepo */
		/*	$resourceRepo = $this->repository('XFRM:ResourceItem');*/
		/*	$resourceRepo->sendModeratorActionAlert($this->blog, 'delete', $this->alertReason);*/
		/*}*/

		return $result;
	}

	/*protected function updateResourceThread()*/
	/*{*/
	/*	if (!$this->addPost)*/
	/*	{*/
	/*		return;*/
	/*	}*/
	/**/
	/*	$resource = $this->blog;*/
	/*	$thread = $resource->Discussion;*/
	/*	if (!$thread)*/
	/*	{*/
	/*		return;*/
	/*	}*/
	/**/
	/*	if ($this->postByUser)*/
	/*	{*/
	/*		$asUser = $this->postByUser;*/
	/*	}*/
	/*	else*/
	/*	{*/
	/*		$asUser = $resource->User ?: $this->repository('XF:User')->getGuestUser($resource->username);*/
	/*	}*/
	/**/
	/*	\XF::asVisitor($asUser, function () use ($thread)*/
	/*	{*/
	/*		$replier = $this->setupResourceThreadReply($thread);*/
	/*		if ($replier && $replier->validate())*/
	/*		{*/
	/*			$existingLastPostDate = $replier->getThread()->last_post_date;*/
	/**/
	/*			$post = $replier->save();*/
	/*			$this->afterResourceThreadReplied($post, $existingLastPostDate);*/
	/**/
	/*			\XF::runLater(function () use ($replier)*/
	/*			{*/
	/*				$replier->sendNotifications();*/
	/*			});*/
	/*		}*/
	/*	});*/
	/*}*/
	/**/
	/*protected function setupResourceThreadReply(Thread $thread)*/
	/*{*/
	/*	if (!$thread->Forum)*/
	/*	{*/
	/*		// thread has been orphaned somehow?*/
	/*		return null;*/
	/*	}*/
	/**/
	/** @var Replier $replier */
	/*	$replier = $this->service('XF:Thread\Replier', $thread);*/
	/*	$replier->setIsAutomated();*/
	/*	$replier->setMessage($this->getThreadReplyMessage(), false);*/
	/**/
	/*	return $replier;*/
	/*}*/
	/**/
	/*protected function getThreadReplyMessage()*/
	/*{*/
	/*	$resource = $this->blog;*/
	/*	$username = $resource->User ? $resource->User->username : $resource->username;*/
	/*	$phraseName = $this->postDeleteReason ? 'xfrm_resource_thread_delete_reason_x' : 'xfrm_resource_thread_delete';*/
	/**/
	/*	$phrase = \XF::phrase($phraseName, [*/
	/*		'title' => $resource->title_,*/
	/*		'tag_line' => $resource->tag_line_,*/
	/*		'username' => $username,*/
	/*		'reason' => $this->postDeleteReason,*/
	/*	]);*/
	/**/
	/*	return $phrase->render('raw');*/
	/*}*/
	/**/
	/*protected function afterResourceThreadReplied(Post $post, $existingLastPostDate)*/
	/*{*/
	/*	$thread = $post->Thread;*/
	/**/
	/*	if (\XF::visitor()->user_id && $post->Thread->getVisitorReadDate() >= $existingLastPostDate)*/
	/*	{*/
	/*		$this->repository('XF:Thread')->markThreadReadByVisitor($thread);*/
	/*	}*/
	/*}*/
}
