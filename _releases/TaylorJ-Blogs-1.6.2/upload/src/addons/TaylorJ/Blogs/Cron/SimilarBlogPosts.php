<?php

namespace TaylorJ\Blogs\Cron;

class SimilarBlogPosts
{
	public static function updateCaches()
	{
		\XF::app()->jobManager()->enqueueUnique(
			'taylorjBlogsSimilarPosts',
			'TaylorJ\Blogs:SimilarBlogPosts',
			[],
			false
		);
	}
}
