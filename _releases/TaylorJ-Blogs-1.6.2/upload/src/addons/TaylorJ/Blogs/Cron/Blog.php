<?php

namespace TaylorJ\Blogs\Cron;

use TaylorJ\Blogs\Repository\Blog as BlogRepo;

class Blog
{
	public static function runBlogPostCountUpdate()
	{
		$app = \XF::app();

		/** @var BlogRepo $blogRepo */
		$blogRepo = $app->repository('TaylorJ\Blogs:Blog');
		$blogRepo->batchUpdateBlogPostCounts();
	}
}
