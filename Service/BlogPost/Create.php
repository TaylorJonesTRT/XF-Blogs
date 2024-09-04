<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Entity\BlogPost;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Service\AbstractService;
use XF\Service\Thread\Creator;

use TaylorJ\Blogs\Utils as Utils;

class Create extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \TaylorJ\Blogs\Entity\BlogPost
	 */
	protected $blogPost;

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

	public function __construct(\XF\App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
		$this->initialize();
	}

	protected function initialize()
	{
		$blogPost = $this->blog->getNewBlogPost();
		$this->blogPost = $blogPost;
	}

	public function setTitle($title)
	{
		$this->blogPost->blog_post_title = $title;
	}

	public function setContent($content)
	{
		$preparer = \XF::service(
			\XF\Service\Message\PreparerService::class,
			'taylorj_blogs_blog_post',
			$this->blogPost
		);

		$this->blogPost->blog_post_content = $preparer->prepare($content);
		$this->blogPost->embed_metadata = $preparer->getEmbedMetadata();
		$preparer->pushEntityErrorIfInvalid($this->blogPost, 'blog_post_content');
	}

	public function setScheduledPostDateTime($scheduledPostTime)
	{
		$tz = new \DateTimeZone(\XF::visitor()->timezone);

		$postDate = $scheduledPostTime['dd'];
		$postHour = $scheduledPostTime['hh'];
		$postMinute = $scheduledPostTime['mm'];

		if (!$scheduledPostTime['blog_post_schedule']) {
			$dateTime = new \DateTime("$postDate $postHour:$postMinute", $tz);

			$this->blogPost->scheduled_post_date_time = $dateTime->format('U');
			$this->blogPost->blog_post_state = 'scheduled';
		} else {
			$this->blogPost->scheduled_post_date_time = 0;
			$this->blogPost->blog_post_state = 'visible';
		}
	}

	public function finalSteps()
	{
		if ($this->blogPost->blog_post_state === 'scheduled') {
			$this->insertJob();
		}
	}

	protected function _validate()
	{
		$this->blogPost->preSave();
		$errors = $this->blogPost->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$blogPost = $this->blogPost;

		$blogPost->save(true, false);

		if ($blogPost->blog_post_state == 'visible') {
			$creator = $this->setupBlogPostThreadCreation();
			if ($creator && $creator->validate()) {
				$thread = $creator->save();
				$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterResourceThreadCreated($thread);
			}
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
		if ($this->blog->isVisible()) {
			/** @var \TaylorJ\Blogs\Service\Blog\Notify $notifier */
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
