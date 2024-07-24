<?php

namespace TaylorJ\UserBlogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\ParameterBag;

/**
 * COLUMNS
 * @property int $id
 * @property int $user_id
 * @property int $blog_id
 * @property string $blog_post_title
 * @property int $blog_post_creation_date
 * @property int $blog_post_last_edit_date
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class BlogPost extends Entity
{
    protected function verifyTitle(&$value)
    {
        if (strlen($value) < 10)
        {
//          the error below needs to be changed to use a phrase rather than hard coded text
            $this->error('Blog titles need to be at least 10 characters long', 'title');
            return false;
        }

        $value = utf8_ucwords($value);

        return true;
    }

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_taylorj_userblogs_blog';
		$structure->shortName = 'TaylorJ\UserBlogs:Blog';
		$structure->contentType = 'taylorj_userblogs_blog';
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'blog_id' => ['type' => self::UINT],
            'blog_post_title' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'censor' => true],
            'blog_post_content' => ['type' => self::STR, 'required' => true, 'censor' => true],
            'blog_post_creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'blog_post_last_edit_date' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
            'User' => [
            	'entity'     => 'XF:User',
            	'type'       => self::TO_ONE,
            	'conditions' => 'user_id',
            	'primary'    => true
            ],
            'Blog' => [
                'entity'    => 'TaylorJ\UserBlogs:Blog',
                'type'      => self::TO_ONE,
                'conditions'=> 'blog_id',
                'primary'   => true
            ]
        ];
		$structure->defaultWith = ['User'];
		$structure->getters['blog_header_image'] = true;
		$structure->behaviors = [];

		return $structure;
	}

}