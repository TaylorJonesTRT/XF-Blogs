<?php

namespace TaylorJ\Blogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $blog_id
 *
 * RELATIONS
 * @property \TaylorJ\Blogs\Entity\Blog $Blog
 * @property \XF\Entity\User $User
 */
class BlogWatch extends Entity
{
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_taylorj_blogs_blog_watch';
        $structure->shortName = 'TaylorJ\Blogs:BlogWatch';
        $structure->contentType = 'taylorj_blogs_blog_watch';
        $structure->primaryKey = ['user_id', 'blog_id'];
        $structure->columns = [
            'user_id' => ['type' => self::UINT],
            'blog_id' => ['type' => self::UINT],
        ];
        $structure->relations = [
			'Blog' => [
				'entity' => 'TaylorJ\Blogs:Blog',
				'type' => self::TO_ONE,
				'conditions' => 'blog_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
        ];
        $structure->defaultWith = [];
        $structure->getters = [];
        $structure->behaviors = [];

        return $structure;
    }
}