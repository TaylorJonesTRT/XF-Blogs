<?php

namespace TaylorJ\Blogs\Repository;

use XF\Mvc\Entity\Repository;
use XF\Entity\Thread;
use TaylorJ\Blogs\Finder\BlogPost as BlogPostFinder;
use XF\Util\File;

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
		/** @var \TaylorJ\Blogs\Finder\BlogPost $finder */
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
}
