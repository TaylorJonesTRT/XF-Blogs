<?php

namespace TaylorJ\UserBlogs;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['taylorj_userblogs_blog_count'] = ['type' => Entity::INT, 'default' => 0];
    }
}
