<?php

namespace TaylorJ\Blogs\Entity;

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
 * @property array $breadcrumb_data
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
			$link = 'blogs/blog';
		}
		return $this->_getBreadcrumbs($includeSelf, $linkType, $link);
	}

	public function canView(&$error = null)
	{
        $visitor = \XF::visitor();
        
        if (!$visitor->hasPermission('taylorjBlogs', 'viewOwn') || !$visitor->hasPermission('taylorjBlogs', 'viewAny'))
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
            if (!$visitor->hasPermission('taylorjBlogs', 'canEditOwn'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_error_edit');
                return false;
            }
		}
        else
        {
            if ($visitor->hasPermission('taylorjBlogs', 'canEditAny'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_error_edit');
                return false;
            }
			else
			{
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
            if (!$visitor->hasPermission('taylorjBlogs', 'canDeleteOwn'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_error_delete');
                return false;
            }
		}
        else
        {
            if (!$visitor->hasPermission('taylorjBlogs', 'deleteAny'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_error_delete');
                return false;
            }
        }

		return true;	
	}
	
	public function canPost(&$error = null)
	{
		$visitor = \XF::visitor();

        if ($this->user_id === $visitor->user_id)
        {
            if (!$visitor->hasPermission('taylorjBlogPost', 'canPost'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_post_error_new');
				return false;
            }
			else
			{
				return true;
			}
		}
		return false;
	}
	
	public function canWatch(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($this->user_id === $visitor->user_id)
		{
			return false;
		}
	
		return true;
	}
	
	public function canViewScheduledPosts(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($this->user_id === $visitor->user_id)
		{
			return true;
		}
		
		return false;
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
            $this->error(\XF::phrase('taylorj_blogs_titile_verification_error'), 'title');
            return false;
        }

        $value = utf8_ucwords($value);

        return true;
    }

    public function canUploadAndManageAttachments()
	{
		$visitor = \XF::visitor();

		//TODO: decide later on if we should have a permission for allowing the
		// use of the attachment system for posts
        return true;
	}

	public function getNewBlogPost()
	{
		$blogPost = $this->_em->create('TaylorJ\Blogs:BlogPost');
		$blogPost->blog_id = $this->blog_id;

		return $blogPost;
	}

	protected function adjustUserBlogCount($amount)
	{
		if ($this->user_id
			&& $this->User
		)
		{
			$this->User->fastUpdate('taylorj_blogs_blog_count', max(0, $this->User->taylorj_blogs_blog_count + $amount));
		}
	}

	public function isVisible()
	{
		return true;
	}

    
    protected function _postSave()
    {
        $this->adjustUserBlogCount(1);
    }

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_taylorj_blogs_blog';
		$structure->shortName = 'TaylorJ\Blogs:Blog';
		$structure->contentType = 'taylorj_blogs_blog';
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
			'BlogPost' => [
				'entity'	=> 'TaylorJ\Blogs:BlogPost',
				'type'		=> self::TO_MANY,
				'conditions' => 'blog_post_id',
				'primary'	=> true
			],
			'BlogWatch' => [
				'entity' => 'TaylorJ\Blogs:BlogWatch',
				'type' => self::TO_MANY,
				'conditions' => 'blog_id',
				'key' => 'user_id'
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