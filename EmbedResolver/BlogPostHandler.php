<?php

namespace TaylorJ\Blogs\EmbedResolver;

use XF\EmbedResolver\AbstractHandler;

class BlogPostHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Blog', 'Blog.Permissions|' . $visitor->permission_combination_id];
	}
}
