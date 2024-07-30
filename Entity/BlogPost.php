<?php

namespace TaylorJ\UserBlogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\ParameterBag;

/**
 * COLUMNS
 * @property int $blog_post_id
 * @property int $user_id
 * @property int $blog_id
 * @property string $blog_post_title
 * @property string $blog_post_content
 * @property int $blog_post_creation_date
 * @property int $blog_post_last_edit_date
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \TaylorJ\UserBlogs\Entity\Blog $Blog
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\Attachment[] $Attachments
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

	public function canView(&$error = null)
	{
		$blogPost = $this->BlogPost;

		if (!$blogPost || !$blogPost->canView($error))
		{
			return false;
		}

		$visitor = \XF::visitor();

		if ($this->message_state == 'moderated')
		{
			if (
				!$blogPost->hasPermission('viewModerated')
				&& (!$visitor->user_id)
			)
			{
				return false;
			}
		}
		else if ($this->message_state == 'deleted')
		{
			if (!$blogPost->hasPermission('viewDeleted'))
			{
				return false;
			}
		}

		return true;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();
		$blogPost = $this->BlogPost;

		if (!$visitor->user_id || !$blogPost)
		{
			return false;
		}

		return $blogPost->canEdit($error);
	}
    
    public function isAttachmentEmbedded($attachmentId)
	{
		if (!$this->page_embed)
		{
			return false;
		}

		if ($attachmentId instanceof \XF\Entity\Attachment)
		{
			$attachmentId = $attachmentId->attachment_id;
		}

		return in_array($attachmentId, $this->page_embed);
	}
     
    public function canViewAttachments(&$error = null)
	{
		$visitor = \XF::visitor();
		
		// return ($visitor->hasPermission('EWRcarta', 'viewAttachments'));
        return true;
	}
    
    public function canUploadAndManageAttachments()
	{
		$visitor = \XF::visitor();

		// return ($visitor->user_id && $visitor->hasPermission('EWRcarta', 'manageAttachments'));
        return true;
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_taylorj_userblogs_blog_post';
		$structure->shortName = 'TaylorJ\UserBlogs:BlogPost';
		$structure->contentType = 'taylorj_userblogs_blog_post';
		$structure->primaryKey = 'blog_post_id';
		$structure->columns = [
			'blog_post_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'blog_id' => ['type' => self::UINT],
            'blog_post_title' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'censor' => true],
            'blog_post_content' => ['type' => self::STR, 'required' => true, 'censor' => true],
            'blog_post_date' => ['type' => self::UINT, 'default' => \XF::$time],
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
            ],
			'Attachments' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'taylorj_usersblogs_post'],
					['content_id', '=', '$blog_post_id']
				],
				'with' => 'Data',
				'order' => 'attach_date'
			]
        ];
		$structure->defaultWith = ['User'];
		$structure->getters[] = true;
		$structure->behaviors = [];

		return $structure;
	}

}