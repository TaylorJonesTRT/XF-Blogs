<?php

namespace TaylorJ\Blogs\XF\Entity;

use XF\Mvc\Entity\Structure;

use function is_array;

class User extends XFCP_User
{
    public function canViewBlogs(&$error = null)
    {
        return $this->hasPermission('taylorjBlogs', 'viewBlogs');
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        return $structure;
    }
}
