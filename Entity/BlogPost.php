<?php

namespace TaylorJ\Blogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\ParameterBag;
use XF\BbCode\RenderableContentInterface;

/**
 * COLUMNS
 * @property int $blog_post_id
 * @property int $user_id
 * @property int $blog_id
 * @property string $blog_post_title
 * @property string $blog_post_content
 * @property int $blog_post_creation_date
 * @property int $blog_post_last_edit_date
 * @property int $attach_count
 * @property array|null $embed_metadata
 * @property int $view_count
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \TaylorJ\Blogs\Entity\Blog $Blog
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Attachment[] $Attachments
 */
class BlogPost extends Entity implements RenderableContentInterface
{

	public function getBreadcrumbs($includeSelf = true)
	{
		$breadcrumbs = $this->Blog ? $this->Blog->getBreadcrumbs() : [];
		if ($includeSelf)
		{
			$breadcrumbs[] = [
				'href' => $this->app()->router()->buildLink('blogs/post', $this),
				'value' => $this->blog_post_title,
				'blog_post_id' => $this->blog_post_id
			];
		}

		return $breadcrumbs;
	}

    protected function verifyTitle(&$value)
    {
        if (strlen($value) < 10)
        {
//          the error below needs to be changed to use a phrase rather than hard coded text
            $this->error(\XF::phrase('taylorj_blogs_blog_post_title_verification_error'), 'title');
            return false;
        }

        $value = utf8_ucwords($value);

        return true;
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
            if (!$visitor->hasPermission('blogPost', 'canEditOwnPost'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_post_error_edit');
                return false;
            }
		}
        else
        {
            if ($visitor->hasPermission('blogs', 'canEditAny'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_post_error_edit');
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
            if (!$visitor->hasPermission('blogPost', 'canDeleteOwnPost'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_post_error_delete');
                return false;
            }
		}
        else
        {
            if (!$visitor->hasPermission('blogPost', 'deleteAny'))
            {
                $error = \XF::phrase('taylorj_blogs_blog_post_error_delete');
                return false;
            }
        }

		return true;	
	}
    
    public function isAttachmentEmbedded($attachmentId)
	{
		if (!$this->embed_metadata)
		{
			return false;
		}

		if ($attachmentId instanceof \XF\Entity\Attachment)
		{
			$attachmentId = $attachmentId->attachment_id;
		}

		return in_array($attachmentId, $this->embed_metadata);
	}
     
    public function canViewAttachments(&$error = null)
	{
		$visitor = \XF::visitor();
		
        return true;
	}
    
    public function canUploadAndManageAttachments()
	{
		$visitor = \XF::visitor();

		return ($visitor->user_id && $visitor->hasPermission('blogs', 'manageAttachments'));
        return true;
	}

	public function getBbCodeRenderOptions($context, $type)
	{
		return [
			'entity' => $this,
			'user' => $this->User,
			'attachments' => $this->Attachments,
			'viewAttachments' => $this->canViewAttachments()
		];
	}
	
	protected function adjustBlogPostCount($amount)
	{
		if ($this->user_id
			&& $this->User
		)
		{
			$this->Blog->fastUpdate('blog_post_count', max(0, $this->Blog->blog_post_count + $amount));
		}
	}
    
    protected function _postSave()
    {
        $this->adjustBlogPostCount(1);
    }

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_taylorj_blogs_blog_post';
		$structure->shortName = 'TaylorJ\Blogs:BlogPost';
		$structure->contentType = 'taylorj_blogs_blog_post';
		$structure->primaryKey = 'blog_post_id';
		$structure->columns = [
			'blog_post_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'blog_id' => ['type' => self::UINT],
            'blog_post_title' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'censor' => true],
            'blog_post_content' => ['type' => self::STR, 'required' => true, 'censor' => true],
            'blog_post_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'blog_post_last_edit_date' => ['type' => self::UINT, 'default' => 0],
			'attach_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0, 'api' => true],
			'embed_metadata' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
			'view_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
		];
		$structure->relations = [
            'User' => [
            	'entity'     => 'XF:User',
            	'type'       => self::TO_ONE,
            	'conditions' => 'user_id',
            	'primary'    => true
            ],
            'Blog' => [
                'entity'    => 'TaylorJ\Blogs:Blog',
                'type'      => self::TO_ONE,
                'conditions'=> 'blog_id',
                'primary'   => true
            ],
			'Attachments' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'taylorj_blogs_blog_post'],
					['content_id', '=', '$blog_post_id']
				],
				'with' => 'Data',
				'order' => 'attach_date'
			]
        ];
		$structure->defaultWith = ['User'];
		$structure->getters[] = true;
		$structure->behaviors = [
			'XF:Indexable' => [
				'checkForUpdates' => ['blog_post_title', 'blog_post_id', 'blog_id', 'user_id']
			],
		];

		return $structure;
	}

}