<?php

namespace TaylorJ\Blogs\Entity;

use TaylorJ\Blogs\Utils;
use XF\BbCode\RenderableContentInterface;
use XF\Entity\ApprovalQueue;
use XF\Entity\Attachment;
use XF\Entity\CoverImageTrait;
use XF\Entity\DatableInterface;
use XF\Entity\DatableTrait;
use XF\Entity\DeletionLog;
use XF\Entity\EmbedRendererTrait;
use XF\Entity\EmbedResolverTrait;
use XF\Entity\ReactionTrait;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Repository\AttachmentRepository;

/**
 * COLUMNS
 * @property int $blog_post_id
 * @property int $user_id
 * @property int $blog_id
 * @property string $blog_post_title
 * @property string $blog_post_title_
 * @property string $blog_post_content
 * @property string $blog_post_content_
 * @property int $blog_post_date
 * @property int $blog_post_last_edit_date
 * @property int $attach_count
 * @property array|null $embed_metadata
 * @property int $view_count
 * @property string $blog_post_state
 * @property int|null $scheduled_post_date_time
 * @property int $discussion_thread_id
 * @property int $reaction_score
 * @property array $reactions_
 * @property array $reaction_users_
 *
 * GETTERS
 * @property-read mixed $Unfurls
 * @property-read bool $scheduled
 * @property-read string|null $cover_image
 * @property mixed $reactions
 * @property mixed $reaction_users
 * @property-read array $Embeds
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read Blog|null $Blog
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Attachment> $Attachments
 * @property-read Thread|null $Discussion
 * @property-read ApprovalQueue|null $ApprovalQueue
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ReactionContent> $Reactions
 */
class BlogPost extends Entity implements RenderableContentInterface, DatableInterface
{
	use ReactionTrait;
	use CoverImageTrait;
	use EmbedRendererTrait;
	use EmbedResolverTrait;
	use DatableTrait;

	public function getBreadcrumbs($includeSelf = true)
	{
		$breadcrumbs = $this->Blog ? $this->Blog->getBreadcrumbs() : [];
		if ($includeSelf)
		{
			$breadcrumbs[] = [
				'href' => $this->app()->router()->buildLink('blogs/post', $this),
				'value' => $this->blog_post_title,
				'blog_post_id' => $this->blog_post_id,
			];
		}

		return $breadcrumbs;
	}

	protected function verifyTitle(&$value)
	{
		if (strlen($value) < 10)
		{
			$this->error(\XF::phrase('taylorj_blogs_blog_post_title_verification_error'), 'title');
			return false;
		}

		$value = utf8_ucwords($value);

		return true;
	}

	protected function verifyScheduledPostDateTime($value)
	{
		if ($value != 0)
		{
			$dateTime = new \DateTime();
			$dateTime->setTimestamp($value);

			if ($dateTime->getTimestamp() <= \XF::$time)
			{
				$this->error(\XF::phrase('taylorj_blogs_blog_post_scheduled_time_error'));
				return false;
			}
			else
			{
				return true;
			}
		}
		return true;
	}

	public function canView(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($this->blog_post_state == 'moderated')
		{
			if (
				!$visitor->hasPermission('taylorjBlogPost', 'canViewModeratedBlogPosts')
			)
			{
				return false;
			}
		}
		else if ($this->blog_post_state == 'deleted')
		{
			if (!$visitor->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
			{
				return false;
			}
		}
		return true;
	}

	public function canEdit(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id == $this->user_id)
		{
			if (!$visitor->hasPermission('taylorjBlogPost', 'canEditOwnPost'))
			{
				$error = \XF::phrase('taylorj_blogs_blog_post_error_edit');
				return false;
			}
		}
		else
		{
			if ($visitor->hasPermission('taylorjBlogs', 'canEditAny'))
			{
				$error = \XF::phrase('taylorj_blogs_blog_post_error_edit');
				return false;
			}
			else
			{
				return false;
			}
		}

		return true;
	}

	public function canDelete($type = 'soft', &$error = null)
	{
		$visitor = \XF::visitor();

		if ($type != 'soft')
		{
			return $visitor->hasPermission('taylorjBlogPost', 'canHardDeleteAny');
		}

		if ($visitor->hasPermission('taylorjBlogPost', 'canDeleteAny'))
		{
			return true;
		}

		return (
			$this->user_id == $visitor->user_id
				&& $visitor->hasPermission('taylorjBlogPost', 'canDeleteOwnPost')
		);
	}

	public function canUndelete(&$error = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->hasPermission('taylorjBlogPost', 'canUndeleteAny'))
		{
			return true;
		}

		return (
			$this->user_id == $visitor->user_id
				&& $visitor->hasPermission('taylorjBlogPost', 'canUndeleteOwnPost')
		);
	}

	public function isAttachmentEmbedded($attachmentId)
	{
		if (!$this->embed_metadata)
		{
			return false;
		}

		if ($attachmentId instanceof Attachment)
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

		// return ($visitor->user_id && $visitor->hasPermission('taylorjBlogs', 'manageAttachments'));
		return true;
	}

	public function canReact(&$error = null)
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->blog_post_state != 'visible')
		{
			return false;
		}

		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}

		if (!$this->Blog)
		{
			return false;
		}

		return true;
	}

	public function canReport(&$error = null, ?User $asUser = null)
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	public function canApproveUnapprove(&$error = null)
	{
		return (
			\XF::visitor()->user_id
				&& \XF::visitor()->hasPermission('forum', 'approveUnapprove')
		);
	}

	public function canViewModeratedContent()
	{
		$visitor = \XF::visitor();
		if ($visitor->hasPermission('taylorjBlogPost', 'viewModerated'))
		{
			return true;
		}
		else if ($this->user_id == $visitor->user_id)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function canEditTags(&$error = null)
	{
		$blog = $this->Blog;

		return $blog ? $blog->canEditTags($this, $error) : false;
	}

	public function canUseInlineModeration(&$error = null)
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $visitor->hasPermission('taylorjBlogPost', 'inlineMod'));
	}

	public function canSetPublicDeleteReason()
	{
		$visitor = \XF::visitor();

		return (
			$this->app()->options()->taylorjBlogsBlogPostDeleteThreadAction['add_post']
				&& $this->Discussion
				&& $visitor->user_id
				&& $visitor->user_id != $this->user_id
			// technically can allow this for own blog post owners, but may be somewhat confusing
		);
	}

	public function canSendModeratorActionAlert()
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
				&& $this->blog_post_state == 'visible'
		);
	}

	public function getBbCodeRenderOptions($context, $type)
	{
		$renderOptions = [
			'entity' => $this,
			'user' => $this->User,
			'attachments' => $this->Attachments,
			'viewAttachments' => $this->canViewAttachments(),
			'unfurls' => $this->Unfurls ?: [],
		];

		$this->addEmbedRendererBbCodeOptions($renderOptions, $context, $type);

		return $renderOptions;
	}

	public function getScheduled(): bool
	{
		if ($this->blog_post_state === 'scheduled')
		{
			return true;
		}

		return false;
	}

	public function isVisible()
	{
		return true;
	}

	public function getExpectedThreadTitle($currentValues = true)
	{
		$title = $currentValues ? $this->getValue('blog_post_title') : $this->getExistingValue('blog_post_title');
		$state = $currentValues ? $this->getValue('blog_post_state') : $this->getExistingValue('blog_post_state');

		$template = '';
		$options = $this->app()->options();

		if (!$template)
		{
			$template = '{title}';
		}

		$threadTitle = str_replace('{title}', $title, $template);
		return $this->app()->stringFormatter()->wholeWordTrim($threadTitle, 100);
	}

	/**
	 * @return string|null
	 */
	public function getCoverImage()
	{
		$attachments = $this->attach_count
		? $this->Attachments
		: $this->_em->getEmptyCollection();

		return $this->getCoverImageInternal(
			$attachments,
			$this->canViewAttachments(),
			$this->embed_metadata,
			$this->blog_post_content
		);
	}

	public function getUnfurls()
	{
		return $this->_getterCache['Unfurls'] ?? [];
	}

	public function getNewContentState(?BlogPost $blogPost = null)
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $visitor->hasPermission('forum', 'approveUnapprove'))
		{
			return 'visible';
		}

		if (!$visitor->hasPermission('taylorjBlogPost', 'submitWithoutApproval'))
		{
			return 'moderated';
		}

		return \XF::app()->options()->taylorjBlogsBlogPostApproval ? 'moderated' : 'visible';
	}

	public function getContentDateColumn(): string
	{
		return 'blog_post_date';
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$route = ($canonical ? 'canonical:' : '') . 'blogs/post';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentTitle()
	{
		return $this->blog_post_title;
	}

	public function setUnfurls($unfurls)
	{
		$this->_getterCache['Unfurls'] = $unfurls;
	}

	public function createCommentThreadsForOldBlogs()
	{
		$blogPosts = \XF::app()->finder('TaylorJ\Blogs:BlogPost')->fetch();

		foreach ($blogPosts AS $blogPost)
		{
			if ($blogPost->discussion_thread_id == 0)
			{
				$creator = Utils::setupBlogPostThreadCreation($blogPost);
				if ($creator && $creator->validate())
				{
					$thread = $creator->save();
					$blogPost->fastUpdate('discussion_thread_id', $thread->thread_id);
					Utils::afterResourceThreadCreated($thread);
				}
			}
		}
	}

	protected function submitHamData()
	{
		/** @var ContentChecker $submitter */
		$submitter = $this->app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('taylorj_blogs_blog_post', $this->blog_post_id);
	}

	public function softDelete($reason = '', ?User $byUser = null)
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->blog_post_state == 'deleted')
		{
			return false;
		}

		$this->blog_post_state = 'deleted';

		/** @var DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	protected function _postDelete()
	{
		(new Utils())->adjustUserBlogPostCount($this->Blog, -1);
		(new Utils())->adjustBlogPostCount($this->Blog, -1);

		/** @var AttachmentRepository $attachRepo */
		$attachRepo = $this->repository(AttachmentRepository::class);
		$attachRepo->fastDeleteContentAttachments('taylorj_blogs_blog_post', $this->blog_post_id);
	}

	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('blog_post_state', 'visible');
		$approvalChange = $this->isStateChanged('blog_post_state', 'moderated');
		$deletionChange = $this->isStateChanged('blog_post_state', 'deleted');

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->blog_post_date;
			$approvalQueue->save();
		}

		$blogPostRepo = Utils::getBlogPostRepo();

		if (!$this->isUpdate())
		{
			(new Utils())->adjustBlogPostCount($this->Blog, 1);
			(new Utils())->adjustUserBlogPostCount($this->Blog, 1);
			$this->Blog->fastUpdate('blog_last_post_date', \XF::$time);
		}
		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				(new Utils())->adjustBlogPostCount($this->Blog, 1);
				(new Utils())->adjustUserBlogPostCount($this->Blog, 1);
				$this->Blog->fastUpdate('blog_last_post_date', \XF::$time);
				if ($approvalChange)
				{
					$this->fastUpdate('blog_post_date', \XF::$time);
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
			if ($this->isChanged('scheduled_post_date_time') && $this->blog_post_state == 'scheduled')
			{
				$blogPostRepo->updateJob($this);
			}
			if ($this->isChanged('blog_post_state') && $this->blog_post_state == 'visible')
			{
				$blogPostRepo->removeJob($this);
			}
		}
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
			'blog_post_state' => [
				'type' => self::STR,
				'default' => 'visible',
				'allowedValues' => ['visible', 'scheduled', 'draft', 'moderated', 'deleted'],
			],
			'scheduled_post_date_time' => ['type' => self::UINT, 'nullable' => true],
			'discussion_thread_id' => ['type' => self::UINT, 'default' => 0],
			'tags' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->relations = [
			'User' => [
				'entity'     => 'XF:User',
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true,
			],
			'Blog' => [
				'entity'    => 'TaylorJ\Blogs:Blog',
				'type'      => self::TO_ONE,
				'conditions' => 'blog_id',
				'primary'   => true,
			],
			'Attachments' => [
				'entity' => 'XF:Attachment',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'taylorj_blogs_blog_post'],
					['content_id', '=', '$blog_post_id'],
				],
				'with' => 'Data',
				'order' => 'attach_date',
			],
			'Discussion' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => [['thread_id', '=', '$discussion_thread_id']],
				'primary' => true,
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'taylorj_blogs_blog_post'],
					['content_id', '=', '$blog_post_id'],
				],
				'primary' => true,
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'taylorj_blogs_blog_post'],
					['content_id', '=', '$blog_post_id'],
				],
				'primary' => true,
			],
			'SimilarBlogPosts' => [
				'entity' => 'TaylorJ\Blogs:BlogPostSimilar',
				'type' => self::TO_ONE,
				'conditions' => 'blog_post_id',
				'primary' => true,
				'cascadeDelete' => true,
			],
		];
		$structure->defaultWith = ['User', 'Blog'];
		$structure->getters = [
			'Unfurls' => true,
			'scheduled' => true,
			'cover_image' => true,
		];
		$structure->behaviors = [
			'XF:Taggable' => ['stateField' => 'blog_post_state'],
			'XF:Indexable' => [
				'checkForUpdates' => ['blog_post_title', 'blog_post_id', 'blog_id', 'user_id', 'blog_post_content'],
			],
			'XF:Reactable' => [
				'stateField' => 'blog_post_state',
			],
		];

		$structure->withAliases = [
			'full' => [
				function ()
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'Reactions|' . $userId,
						];
					}

					return null;
				},
			],
		];
		static::addReactableStructureElements($structure);
		static::addEmbedRendererStructureElements($structure);
		static::addEmbedResolverStructureElements($structure);

		return $structure;
	}
}
