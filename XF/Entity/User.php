<?php

namespace TaylorJ\Blogs\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	public function canViewBlogs(&$error = null)
	{
		return $this->hasPermission('taylorjBlogs', 'viewBlogs');
	}

	public function canViewBlogPosts(&$error = null)
	{
		return $this->hasPermission('taylorjBlogs', 'viewBlogs');
	}

	public function canCreateBlog(&$error = null)
	{
		return $this->hasPermission('taylorjBlogs', 'canCreate');
	}

	public function hasBlogPostPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('resource_category', $contentId, $permission);
	}

	public function cacheResourceCategoryPermissions(?array $categoryIds = null)
	{
		if (is_array($categoryIds))
		{
			\XF::permissionCache()->cacheContentPermsByIds($this->permission_combination_id, 'resource_category', $categoryIds);
		}
		else
		{
			\XF::permissionCache()->cacheAllContentPerms($this->permission_combination_id, 'resource_category');
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		return $structure;
	}

	public function hasBlogPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('taylorjBlogsPermissions', $contentId, $permission);
	}

}
