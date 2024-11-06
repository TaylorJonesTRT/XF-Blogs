<?php

namespace TaylorJ\Blogs\Job;

use TaylorJ\Blogs\Repository\BlogPost;
use XF\Job\AbstractRebuildJob;

class UserBlogPostCountTotal extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT user_id
				FROM xf_user
				WHERE user_id > ?
				ORDER BY user_id
			",
			$batch
		), $start);
	}

	protected function rebuildById($id)
	{
		/** @var BlogPost $repo */
		$repo = $this->app->repository('TaylorJ\Blogs:BlogPost');
		$count = $repo->getUserBlogPostCount($id);

		$this->app->db()->update('xf_user', ['taylorj_blogs_blog_post_count' => $count], 'user_id = ?', $id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('taylorj_blogs_blog_post_user_counts');
	}
}
