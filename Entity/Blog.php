<?php

namespace TaylorJ\UserBlogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\ParameterBag;

/**
 * COLUMNS
 * @property int $blog_id
 * @property int $user_id
 * @property string $blog_title
 * @property string $blog_description
 * @property string $blog_header_image_
 * @property int $blog_creation_date
 * @property int $blog_last_post_date
 *
 * GETTERS
 * @property string $blog_header_image
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class Blog extends Entity
{

    public function getBlogHeaderImage(bool $canonical = false): string
    {
        $blogHeaderImage = $this->app()->applyExternalDataUrl(
            "taylorj_blogs/blog_header_images/{$this->blog_id}.jpg",
            $canonical
        );

        if (!$this->blog_has_header)
        {
            return false;
        }


        return $blogHeaderImage;
    }

    public function getTotalBlogPosts()
    {
        $test = $this->finder('TaylorJ\Blogs:Post')
            ->where('blog_id', '=', $this->id);

        return $test;
    }

    protected function verifyTitle(&$value)
    {
        if (strlen($value) < 10)
        {
//          the error below needs to be changed to use a phrase rather than hard coded text
            $this->error(\XF::phrase('taylorj_userblogs_titile_verification_error'), 'title');
            return false;
        }

        $value = utf8_ucwords($value);

        return true;
    }

    public function canUploadAndManageAttachments()
	{
		$visitor = \XF::visitor();

		// return ($visitor->user_id && $visitor->hasPermission('EWRcarta', 'manageAttachments'));
        return true;
	}

	public function getNewBlogPost()
	{
		$blogPost = $this->_em->create('TaylorJ\UserBlogs:BlogPost');
		$blogPost->blog_id = $this->blog_id;

		return $blogPost;
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_taylorj_userblogs_blog';
		$structure->shortName = 'TaylorJ\UserBlogs:Blog';
		$structure->contentType = 'taylorj_userblogs_blog';
		$structure->primaryKey = 'blog_id';
		$structure->columns = [
			'blog_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'blog_title' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'censor' => true],
            'blog_description' => ['type' => self::STR, 'maxLength' => 255, 'required' => false, 'censor' => true],
            'blog_creation_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'blog_last_post_date' => ['type' => self::UINT, 'default' => 0],
            'blog_has_header' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->relations = [
            'User' => [
            	'entity'     => 'XF:User',
            	'type'       => self::TO_ONE,
            	'conditions' => 'user_id',
            	'primary'    => true
            ],
        ];
		$structure->defaultWith = ['User'];
		$structure->getters['blog_header_image'] = true;
		$structure->behaviors = [];

		return $structure;
	}

}