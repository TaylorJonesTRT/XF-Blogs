<?php

namespace TaylorJ\Blogs\Entity;

use TaylorJ\Blogs\Utils;
use XF\Entity\ApprovalQueue;
use XF\Entity\DatableInterface;
use XF\Entity\DatableTrait;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Router;
use XF\Util\File;

/**
 * COLUMNS
 * @property int $blog_id
 * @property int $user_id
 * @property string $blog_title
 * @property string $blog_title_
 * @property string $blog_description
 * @property string $blog_description_
 * @property int $blog_creation_date
 * @property int $blog_last_post_date
 * @property bool $blog_has_header
 * @property int $blog_post_count
 * @property string $blog_state
 *
 * GETTERS
 * @property-read string $blog_header_image
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read \XF\Mvc\Entity\AbstractCollection<\TaylorJ\Blogs\Entity\BlogPost> $BlogPost
 * @property-read \XF\Mvc\Entity\AbstractCollection<\TaylorJ\Blogs\Entity\BlogWatch> $BlogWatch
 * @property-read ApprovalQueue|null $ApprovalQueue
 */
class Blog extends Entity implements DatableInterface
{
	use DatableTrait;

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
			if (!$visitor->hasPermission('taylorjBlogs', 'canDeleteAny'))
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

	public function canEditTags(?BlogPost $blogPost = null, &$error = null)
	{
		$visitor = \XF::visitor();

		if (!$this->app()->options()->enableTagging)
		{
			return false;
		}

		// if no blog post, assume will be owned by this person
		if ($visitor->hasPermission('taylorjBlogPost', 'canTagOwnBlogPost'))
		{
			return true;
		}

		return (
			$visitor->hasPermission('taylorjBlogPost', 'canTagAnyBlogPost')
			|| $visitor->hasPermission('forum', 'canManageAnyTag')
		);
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

	public function getIconUrl($sizeCode = null, $canonical = false)
	{
		$app = $this->app();

		if ($this->blog_header_image)
		{
			$group = floor($this->blog_id);
			return $app->applyExternalDataUrl(
				"taylorj_blogs/blog_header_images/{$this->blog_id}.jpg?{$this->blog_creation_date}",
				$canonical
			);
		}
		else
		{
			return null;
		}
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

	public function canApproveUnapprove(&$error = null)
	{
		return (
			\XF::visitor()->user_id
			&& \XF::visitor()->hasPermission('forum', 'approveUnapprove')
		);
	}

	public function getNewBlogPost()
	{
		$blogPost = $this->_em->create('TaylorJ\Blogs:BlogPost');
		$blogPost->blog_id = $this->blog_id;

		return $blogPost;
	}

	public function getNewContentState(?Blog $blog = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $visitor->hasPermission('forum', 'approveUnapprove'))
		{
			return 'visible';
		}

		if (!$visitor->hasPermission('general', 'submitWithoutApproval'))
		{
			return 'moderated';
		}

		return \XF::app()->options()->taylorjBlogsBlogApproval ? 'moderated' : 'visible';
	}

	public function getContentDateColumn(): string
	{
		return 'blog_creation_date';
	}

	public function isVisible()
	{
		return true;
	}

	public function isOwner()
	{
		if (\XF::visitor()->user_id != $this->user_id)
		{
			return false;
		}

		return true;
	}

	protected function submitHamData()
	{
		/** @var ContentChecker $submitter */
		$submitter = $this->app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('taylorj_blogs_blog', $this->blog_id);
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('blog_state', 'visible');
		$approvalChange = $this->isStateChanged('blog_state', 'moderated');
		$deletionChange = $this->isStateChanged('blog_post_state', 'deleted');

		if (!$this->isUpdate())
		{
			(new Utils())->adjustUserBlogCount($this, 1);
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->blog_creation_date;
			$approvalQueue->save();
		}

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				(new Utils())->adjustUserBlogCount($this, 1);
				if ($approvalChange)
				{
					$this->submitHamData();
				}
			}
			else if ($deletionChange == 'enter' && !$this->DeletionLog)
			{
				$delLog = $this->getRelationOrDefault('DeletionLog', false);
				$delLog->setFromVisitor();
				$delLog->save();
			}

			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}
	}

	protected function _preDelete()
	{
		foreach ($this->BlogPosts AS $blogPost)
		{
			$blogPost->delete();
		}

	}

	protected function _postDelete()
	{
		(new Utils())->adjustUserBlogCount($this, -1);
		(new Utils())->adjustUserBlogPostCount($this, -$this->blog_post_count);

		$dataDir = \XF::app()->config('externalDataPath');
		$dataDir .= "://taylorj_blogs/blog_header_images/" . $this->blog_id . ".jpg";
		File::deleteFromAbstractedPath($dataDir);
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
			'blog_state' => [
				'type' => self::STR,
				'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'],
			],
		];
		$structure->relations = [
			'User' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true,
			],
			'BlogPosts' => [
				'entity'	=> 'TaylorJ\Blogs:BlogPost',
				'type'		=> self::TO_MANY,
				'conditions' => 'blog_id',
				'primary'	=> true,
			],
			'BlogWatch' => [
				'entity' => 'TaylorJ\Blogs:BlogWatch',
				'type' => self::TO_MANY,
				'conditions' => 'blog_id',
				'key' => 'user_id',
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'taylorj_blogs_blog'],
					['content_id', '=', '$blog_id'],
				],
				'primary' => true,
			],
		];
		$structure->defaultWith = ['User'];
		$structure->getters['blog_header_image'] = true;
		$structure->behaviors = [
			'XF:Taggable' => ['stateField' => 'blog_post_state'],
			'XF:Indexable' => [
				'checkForUpdates' => ['blog_title', 'blog_description', 'blog_id', 'blog_last_post_date', 'user_id'],
			],
		];

		return $structure;
	}

	protected function _getBreadcrumbs($includeSelf, $linkType, $link)
	{
		/** @var Router $router */
		$router = $this->app()->container('router.' . $linkType);
		$structure = $this->structure();

		$output = [];
		if ($includeSelf)
		{
			$output[] = [
				'value' => $this->blog_title,
				'href' => $router->buildLink($link, $this),
				$structure->primaryKey => $this->{$structure->primaryKey},
			];
		}

		return $output;
	}
}
