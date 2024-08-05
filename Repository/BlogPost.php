<?php

namespace TaylorJ\Blogs\Repository;

use XF\Mvc\Entity\Repository;
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
}