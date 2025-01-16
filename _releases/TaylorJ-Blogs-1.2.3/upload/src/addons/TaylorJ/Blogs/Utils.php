<?php

namespace TaylorJ\Blogs;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Entity\BlogPost;
use XF\Entity\Thread;
use XF\Repository\PostRepository;
use XF\Util\File;

class Utils
{
	/**
	 * @var BlogPost
	 */
	protected $blogPost;

	/**
	 * @var Blog
	 */
	protected $blog;

	/**
	 * @var Creator|null
	 */
	protected $threadCreator;

	public static function hours()
	{
		$hours = [];
		for ($i = 0; $i < 24; $i++)
		{
			$hh = str_pad($i, 2, '0', STR_PAD_LEFT);
			$hours[$hh] = $hh;
		}

		return $hours;
	}

	public static function minutes()
	{
		$minutes = [];
		for ($i = 0; $i < 60; $i += 1)
		{
			$mm = str_pad($i, 2, '0', STR_PAD_LEFT);
			$minutes[$mm] = $mm;
		}

		return $minutes;
	}

	public static function repo($class)
	{
		return \XF::app()->repository($class);
	}

	public static function log($msg)
	{
		\XF::logError('[TaylorJ\Blogs] --> ' . $msg);
	}

	/**
	 * @return Repository\BlogPost
	 */
	public static function getBlogPostRepo()
	{
		return \XF::app()->repository('TaylorJ\Blogs:BlogPost');
	}

	/**
	 * @return Repository\Blog
	 */
	public static function getBlogRepo()
	{
		return \XF::app()->repository('TaylorJ\Blogs:Blog');
	}

	/**
	 * @return PostRepository
	 */
	public function getPostRepo()
	{
		return \XF::app()->repository(PostRepository::class);
	}

	public static function setupBlogPostThreadCreation(BlogPost $blogPost)
	{
		$forumFinder = \XF::finder('XF:Forum')
			->where('node_id', \XF::app()->options()->taylorjBlogsBlogPostForum)
			->fetchOne();

		$forum = $forumFinder ? $forumFinder : 1;

		/** @var Creator $creator */
		$creator = \XF::app()->service('TaylorJ\Blogs:BlogPost\ThreadCreator', $forum, $blogPost);
		$creator->setIsAutomated();

		$creator->setContent($blogPost->getExpectedThreadTitle(), Utils::getThreadMessage($blogPost), false);

		$creator->setDiscussionTypeAndDataRaw('blogPost');

		$thread = $creator->getThread();
		$thread->discussion_state = $blogPost->blog_post_state;

		return $creator;
	}

	public static function afterResourceThreadCreated(Thread $thread)
	{
		\XF::app()->repository('XF:Thread')->markThreadReadByVisitor($thread);
		\XF::app()->repository('XF:ThreadWatch')->autoWatchThread($thread, \XF::visitor(), true);
	}

	public static function getThreadMessage(BlogPost $blogPost)
	{
		$app = \XF::app();

		$snippet = \XF::app()->bbCode()->render(
			$app->stringFormatter()->wholeWordTrim($blogPost->blog_post_content, 500),
			'bbCodeClean',
			'post',
			null
		);

		$phrase = \XF::phrase('taylorj_blogs_blog_post_thread_create', [
			'title' => $blogPost->blog_post_title_,
			'username' => $blogPost->User->username,
			'snippet' => $snippet,
			'blog_post_link' => $app->router('public')->buildLink('canonical:blogs/post', $blogPost),
		]);

		return $phrase->render('raw');
	}

	public function adjustBlogPostCount(Blog $blog, $amount)
	{
		if (
			$blog->user_id
			&& $blog->User
		)
		{
			$blog->fastUpdate('blog_post_count', max(0, $blog->blog_post_count + $amount));
		}
	}

	public function adjustUserBlogPostCount(Blog $blog, $amount)
	{
		if (
			$blog->user_id
			&& $blog->User
		)
		{
			$blog->User->fastUpdate('taylorj_blogs_blog_post_count', max(0, $blog->User->taylorj_blogs_blog_post_count + $amount));
		}
	}

	public function adjustUserBlogCount(Blog $blog, $amount)
	{
		if (
			$blog->user_id
			&& $blog->User
		)
		{
			$blog->User->fastUpdate('taylorj_blogs_blog_count', max(0, $blog->User->taylorj_blogs_blog_count + $amount));
		}
	}

	protected function deleteBlogHeaderFiles(Blog $blog)
	{
		if ($blog->avatar_date)
		{
			File::deleteFromAbstractedPath($blog->blog_header_image);
		}
	}

}
