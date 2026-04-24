<?php

namespace TaylorJ\Blogs\Job;

use TaylorJ\Blogs\Entity\BlogPost;
use XF\Job\AbstractRebuildJob;

class CleanOldBlogPosts extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit("SELECT `blog_post_id` FROM `xf_taylorj_blogs_blog_post` WHERE `blog_post_id` > ? ORDER BY `blog_post_id`", $batch),
			$start
		);
	}

	protected function rebuildById($id)
	{
		/** @var BlogPost $blogPost */
		$blogPost = \XF::finder('TaylorJ\Blogs:BlogPost')
			->where('blog_post_id', $id)
			->fetchOne();

		$blog = \XF::finder('TaylorJ\Blogs:Blog')
			->where('blog_id', $blogPost->blog_id)
			->fetchOne();

		if (!$blog)
		{
			$blogPost->delete();
		}

	}

	protected function getStatusType()
	{
		return \XF::phrase('taylorj_blogs_blog_post_cleanup_job');
	}
}
