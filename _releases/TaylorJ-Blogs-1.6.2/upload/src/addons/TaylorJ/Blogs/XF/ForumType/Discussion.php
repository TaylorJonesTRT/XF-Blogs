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

		$blogPostTarget = \XF::db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'taylorjBlogsBlogPostForum'
			LIMIT 1
		");
		if (!$blogPostTarget) {
			$this->removeBlogPostTypeFromList($filterable);
		}

		return $filterable;
	}

	protected function removeBlogPostTypeFromList(array &$list)
	{
		$blogPostKey = array_search('blogPost', $list);
		if ($blogPostKey !== false) {
			unset($list[$blogPostKey]);
		}
	}
}
