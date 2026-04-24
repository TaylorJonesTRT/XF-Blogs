<?php

namespace TaylorJ\Blogs\XF\Entity;

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

    public function hasBlogPermission($contentId, $permission)
    {
        return $this->PermissionSet->hasContentPermission('taylorjBlogsPermissions', $contentId, $permission);
    }

}
