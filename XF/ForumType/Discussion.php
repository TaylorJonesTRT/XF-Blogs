<?php

namespace TaylorJ\Blogs\XF\ForumType;

use XF\Entity\Forum;

class Discussion extends XFCP_Discussion
{
	public function getExtraAllowedThreadTypes(Forum $forum): array
	{
		$allowed = parent::getExtraAllowedThreadTypes($forum);
		$allowed[] = 'blogPost';

		return $allowed;
	}

	public function getCreatableThreadTypes(Forum $forum): array
	{
		$creatable = parent::getCreatableThreadTypes($forum);
		$this->removeBlogPostTypeFromList($creatable);

		return $creatable;
	}

	public function getFilterableThreadTypes(Forum $forum): array
	{
		$filterable = parent::getFilterableThreadTypes($forum);

		$resourceTarget = \XF::db()->fetchOne("
			SELECT 1
			FROM xf_option
            WHERE option_id = 'taylorjBlogsBlogPostForum' AS option AND
			WHERE option.option_value = ?
			LIMIT 1
		", $forum->node_id);
		if (!$resourceTarget)
		{
			$this->removeBlogPostTypeFromList($filterable);
		}

		return $filterable;
	}

	protected function removeBlogPostTypeFromList(array &$list)
	{
		$blogPostKey = array_search('blogPost', $list);
		if ($blogPostKey !== false)
		{
			unset($list[$blogPostKey]);
		}
	}
}
