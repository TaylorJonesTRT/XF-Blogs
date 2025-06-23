<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Service\Blog\Notify;
use XF\App;
use XF\Entity\Thread;
use XF\Service\AbstractService;
use XF\Service\Message\PreparerService;
use XF\Service\Tag\Changer;
use XF\Service\Thread\Creator;
use XF\Service\ValidateAndSavableTrait;

class Create extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var BlogPost
	 */
	public $blogPost;

	/**
	 * @var TaylorJ\Blogs\Entity\Blog
	 */
	protected $update;

	/**
	 * @var Creator|null
	 */
	protected $threadCreator;

	/**
	 * @var Blog
	 */
	protected $blog;

	/**
	 * @var Changer
	 */
	protected $tagChanger;

	protected $performValidations = true;

	public function __construct(App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
		$this->initialize();
	}

	protected function initialize()
	{
		$blogPost = $this->blog->getNewBlogPost();
		$this->blogPost = $blogPost;
		$this->tagChanger = $this->service('XF:Tag\Changer', 'taylorj_blogs_blog_post', $this->blog);
	}

	public function setTitle($title)
	{
		$this->blogPost->blog_post_title = $title;
	}

	public function setContent($content)
	{
		$preparer = \XF::service(
			PreparerService::class,
			'taylorj_blogs_blog_post',
			$this->blogPost
		);

		$this->blogPost->blog_post_content = $preparer->prepare($content);
		$this->blogPost->embed_metadata = $preparer->getEmbedMetadata();
		$preparer->pushEntityErrorIfInvalid($this->blogPost, 'blog_post_content');
	}

	public function setTags($tags)
	{
		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger->setEditableTags($tags);
		}
	}

	public function setBlogPostState($state)
	{
		if ($state == 'visible')
		{
			$this->blogPost->scheduled_post_date_time = 0;
			$this->blogPost->blog_post_state = $this->blogPost->getNewContentState();
		}
		else if ($state == 'scheduled')
		{
			$this->blogPost->blog_post_state = $state;
		}
		else
		{
			$this->blogPost->blog_post_state = $state;
			$this->blogPost->scheduled_post_date_time = 0;
			$this->blogPost->blog_post_date = 0;
		}
	}

	public function setScheduledPostDateTime($scheduledPostTime)
	{
		$tz = new \DateTimeZone(\XF::options()->guestTimeZone);

		$postDate = $scheduledPostTime['dd'];
		$postHour = $scheduledPostTime['hh'];
		$postMinute = $scheduledPostTime['mm'];

		$dateTime = new \DateTime("$postDate $postHour:$postMinute", $tz);

		$this->blogPost->scheduled_post_date_time = $dateTime->format('U');
	}

	public function finalSteps()
	{
		if ($this->blogPost->blog_post_state === 'scheduled')
		{
			$this->insertJob();
		}
	}

	protected function _validate()
	{
		$this->blogPost->preSave();
		$errors = $this->blogPost->getErrors();

		if ($this->tagChanger->canEdit())
		{
			$tagErrors = $this->tagChanger->getErrors();
			if ($tagErrors)
			{
				$errors = array_merge($errors, $tagErrors);
			}
		}

		return $errors;
	}

	protected function _save()
	{
		$blogPost = $this->blogPost;

		$blogPost->save(true, false);

		$commentThread = \XF::options()->taylorjBlogsBlogPostComments;

		if ($blogPost->blog_post_state == 'visible' && \XF::options()->taylorjBlogsBlogPostComments)
		{
			$creator = $this->setupBlogPostThreadCreation();
			if ($creator && $creator->validate())
			{
				$thread = $creator->save();
				$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterResourceThreadCreated($thread);
			}
		}

		if ($this->tagChanger->canEdit())
		{
			$this->tagChanger
				->setContentId($blogPost->blog_post_id, true)
				->save($this->performValidations);
		}

		return $blogPost;
	}

	public function insertJob()
	{
		$jobid = 'taylorjblogs_scheduledpost_' . $this->blogPost->blog_post_id;
		$app = \XF::app();
		$app->jobManager()->enqueueLater($jobid, $this->blogPost->scheduled_post_date_time, 'TaylorJ\Blogs:PostBlogPost', ['blog_post_id' => $this->blogPost->blog_post_id]);
	}

	public function sendNotifications()
	{
		if ($this->blog->isVisible())
		{
			/** @var Notify $notifier */
			$notifier = $this->service('TaylorJ\Blogs:Blog\Notify', $this->blog, $this->blogPost, 'newBlogPost');
			$notifier->notifyAndEnqueue();
		}
	}

	protected function setupBlogPostThreadCreation()
	{
		$forumFinder = \XF::finder('XF:Forum')
			->where('node_id', \XF::app()->options()->taylorjBlogsBlogPostForum)
			->fetchOne();

		$forum = $forumFinder ? $forumFinder : \XF::finder('XF:Forum')->fetchOne();

		/** @var Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $forum);
		$creator->setIsAutomated();

		$creator->setContent($this->blogPost->getExpectedThreadTitle(), $this->getThreadMessage(), false);

		$creator->setDiscussionTypeAndDataRaw('blogPost');

		$thread = $creator->getThread();
		$thread->discussion_state = $this->blogPost->blog_post_state;

		return $creator;
	}

	protected function afterResourceThreadCreated(Thread $thread)
	{
		$this->repository('XF:Thread')->markThreadReadByVisitor($thread);
		$this->repository('XF:ThreadWatch')->autoWatchThread($thread, \XF::visitor(), true);
	}

	protected function getThreadMessage()
	{
		$blogPost = $this->blogPost;

		$snippet = $this->app->bbCode()->render(
			$this->app->stringFormatter()->wholeWordTrim($this->blogPost->blog_post_content, 500),
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('taylorj_blogs_blog_post_thread_create', [
			'title' => $blogPost->blog_post_title_,
			'username' => $blogPost->User->username,
			'snippet' => $snippet,
			'blog_post_link' => $this->app->router('public')->buildLink('canonical:blogs/post', $blogPost),
		]);

		return $phrase->render('raw');
	}
}
