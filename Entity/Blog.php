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

	public function getBreadcrumbs($includeSelf = true, $linkType = 'public')
	{
		if ($linkType == 'public')
		{
			$link = 'userblogs/blog';
		}
		return $this->_getBreadcrumbs($includeSelf, $linkType, $link);
	}

	public function canView(&$error = null)
	{
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('blogs', 'viewOwn') || !$visitor->hasPermission('blogs', 'viewAny'))
        {
            return false;
        }

        return true;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id == $this->user_id)
		{
            if (!$visitor->hasPermission('blogs', 'canEditOwn'))
            {
                $error = \XF::phrase('taylorj_userblogs_blog_error_edit');
                return false;
            }
		}
        else
        {
            if ($visitor->hasPermission('blogs', 'canEditAny'))
            {
                $error = \XF::phrase('taylorj_userblogs_blog_error_edit');
                return false;
            }
        }

		return true;
	}
	
	public function canDelete(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id == $this->user_id)
		{
            if (!$visitor->hasPermission('blogs', 'canDeleteOwn'))
            {
                $error = \XF::phrase('taylorj_userblogs_blog_error_delete');
                return false;
            }
		}
        else
        {
            if (!$visitor->hasPermission('blogs', 'deleteAny'))
            {
                $error = \XF::phrase('taylorj_userblogs_blog_error_delete');
                return false;
            }
        }

		return true;	
	}
    
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

	protected function adjustUserBlogCount($amount)
	{
		if ($this->user_id
			&& $this->User
		)
		{
			$this->User->fastUpdate('taylorj_userblogs_blog_count', max(0, $this->User->taylorj_userblogs_blog_count + $amount));
		}
	}
    
    protected function _postSave()
    {
        $this->adjustUserBlogCount(1);
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
            'blog_has_header' => ['type' => self::BOOL, 'default' => false],
            'blog_post_count' => ['type' => self::UINT, 'default' => 0],
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
    
	protected function _getBreadcrumbs($includeSelf, $linkType, $link)
	{
		/** @var \XF\Mvc\Router $router */
		$router = $this->app()->container('router.' . $linkType);
		$structure = $this->structure();

		$output = [];
		// if ($this->breadcrumb_data)
		// {
		// 	foreach ($this->breadcrumb_data AS $crumb)
		// 	{
		// 		$output[] = [
		// 			'value' => $crumb['title'],
		// 			'href' => $router->buildLink($link, $crumb),
		// 			$structure->primaryKey => $crumb[$structure->primaryKey]
		// 		];
		// 	}
		// }

		if ($includeSelf)
		{
			$output[] = [
				'value' => $this->blog_title,
				'href' => $router->buildLink($link, $this),
				$structure->primaryKey => $this->{$structure->primaryKey}
			];
		}

		return $output;
	}

}