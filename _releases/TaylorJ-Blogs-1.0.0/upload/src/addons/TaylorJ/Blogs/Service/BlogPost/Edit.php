<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use XF\App;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use XF\Entity\Thread;
use TaylorJ\Blogs\Service\BlogPost\ThreadCreator;
use TaylorJ\Blogs\Entity\BlogPost;

class Edit extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var BlogPost
	 */
	protected $blogPost;

	/**
	 * @var BlogPost
	 */
	protected $blog;

	/**
	 * @var Creator|null
	 */
	protected $threadCreator;

	public function __construct(App $app, BlogPost $blogPost)
	{
		parent::__construct($app);
		$this->blogPost = $blogPost;
		$this->blog = $blogPost->Blog;
	}

	public function setTitle($title)
	{
		$this->blogPost->blog_post_title = $title;
	}

	public function setContent($content)
	{
		$this->blogPost->blog_post_content = $content;
	}

	protected function finalSetup() {}

	protected function _validate()
	{
		$this->blogPost->preSave();
		$errors = $this->blogPost->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$blogPost = $this->blogPost;

		$blogPost->fastUpdate('blog_post_date', \XF::$time);

		$blogPost->save(true, false);

		if ($blogPost->blog_post_state == 'visible') {
			$creator = $this->setupBlogPostThreadCreation($blogPost);
			if ($creator && $creator->validate()) {
				$thread = $creator->save();
				$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
				$this->threadCreator = $creator;

				$this->afterResourceThreadCreated($thread);
			}
		}

		return $blogPost;
	}

	public function finalSteps() {}

	protected function setupBlogPostThreadCreation(BlogPost $blogPost)
	{
		$forumFinder = \XF::finder('XF:Forum')
			->where('node_id', \XF::app()->options()->taylorjBlogsBlogPostForum)
			->fetchOne();

		/** @var Forum $forum */
		$forum = $forumFinder ? $forumFinder : \XF::finder('XF:Forum')->fetchOne();

		/** @var ThreadCreator $creator */
		$creator = $this->service('TaylorJ\Blogs:BlogPost\ThreadCreator', $forum, $blogPost);
		$creator->setIsAutomated();

		$creator->setContent($this->blogPost->getExpectedThreadTitle(), $this->getThreadMessage(), false);

		$creator->setDiscussionTypeAndDataRaw('blogPost');

		$thread = $creator->getThread();
		$thread->discussion_state = $this->blogPost->blog_post_state;

		return $creator;
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

	protected function afterResourceThreadCreated(Thread $thread)
	{
		$this->repository('XF:Thread')->markThreadReadByVisitor($thread);
		$this->repository('XF:ThreadWatch')->autoWatchThread($thread, \XF::visitor(), true);
	}

	public function handlePostStateChange(BlogPost $blogPost)
	{
		$blogPost->fastUpdate('blog_post_state', 'visible');
		$blogPost->fastUpdate('blog_post_date', \XF::$time);

		$creator = $this->setupBlogPostThreadCreation($blogPost);
		if ($creator && $creator->validate()) {
			$thread = $creator->save();
			$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
			$this->threadCreator = $creator;

			$this->afterResourceThreadCreated($thread);
		}

		return $blogPost;
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
}
