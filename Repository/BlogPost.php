<?php

namespace TaylorJ\Blogs\Repository;

use TaylorJ\Blogs\Finder\BlogPost as BlogPostFinder;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class BlogPost extends Repository
{
	public function logThreadView(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
	{
		$this->db()->query("
			INSERT INTO xf_taylorj_blogs_blog_post_view
				(blog_post_id, total)
			VALUES
				(? , 1)
			ON DUPLICATE KEY UPDATE
				total = total + 1
		", $blogPost->blog_post_id);
	}

	public function batchUpdateThreadViews()
	{
		$db = $this->db();
		$db->query("
			UPDATE xf_taylorj_blogs_blog_post AS t
			INNER JOIN xf_taylorj_blogs_blog_post_view AS tv ON (t.blog_post_id = tv.blog_post_id)
			SET t.view_count = t.view_count + tv.total
		");
		$db->emptyTable('xf_taylorj_blogs_blog_post_view');
	}

	public function findBlogPostForThread(Thread $thread)
	{
		/** @var BlogPostFinder $finder */
		$finder = $this->finder('TaylorJ\Blogs:BlogPost');

		$finder->where('discussion_thread_id', $thread->thread_id);

		return $finder;
	}

	public function updateJob($blogPost)
	{
		$jobId = 'taylorjblogs_scheduledpost_' . $blogPost->blog_post_id;
		$db = $this->db();

		$db->query("
			UPDATE xf_job
			SET trigger_date = ?
			WHERE unique_key = ?
		", [$blogPost->scheduled_post_date_time, $jobId]);
	}

	public function removeJob($blogPost)
	{
		$jobId = 'taylorjblogs_scheduledpost_' . $blogPost->blog_post_id;
		/*$db = $this->db();*/
		/**/
		/*$db->query("DELETE FROM xf_job WHERE unique_key = ?", [$jobId]);*/
		$this->app()->jobManager()->cancelUniqueJob($jobId);
	}

	/**
	 * @return ThreadFinder
	 */
	public function findLatestBlogPosts()
	{
		return $this->finder(BlogPostFinder::class)
			->where('blog_post_state', 'visible')
			->order('blog_post_date', 'DESC');
	}

	public function findBlogPostsByUser($userId)
	{
		$blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')
			->where('user_id', $userId)
			->setDefaultOrder('blog_post_date', 'desc');

		return $blogPostFinder;
	}

	public function findOtherPostsByOwnerRandom($userId)
	{
		/** @var BlogPostFinder $finder */
		$finder = $this->finder('TaylorJ\Blogs:BlogPost');

		$randomBlogPosts = $finder
			->where('user_id', $userId)
			->order($finder->expression('RAND()'));

		return $randomBlogPosts;
	}

	public function getUserBlogPostCount($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_taylorj_blogs_blog_post
			WHERE user_id = ?
				AND blog_post_state = 'visible'
		", $userId);
	}

	public function sendModeratorActionAlert(
		\TaylorJ\Blogs\Entity\BlogPost $blogPost,
		$action,
		$reason = '',
		array $extra = [],
		?User $forceUser = null
	)
	{
		if (!$forceUser)
		{
			if (!$blogPost->user_id || !$blogPost->User)
			{
				return false;
			}

			$forceUser = $blogPost->User;
		}

		$extra = array_merge([
			'title' => $blogPost->blog_post_title,
			'link' => $this->app()->router('public')->buildLink('nopath:blogs', $blogPost),
			'reason' => $reason,
		], $extra);

		/** @var UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$forceUser,
			0,
			'',
			'user',
			$forceUser->user_id,
			"blog_post_{$action}",
			$extra,
			['dependsOnAddOnId' => 'TaylorJ/Blogs']
		);

		return true;
	}

}
