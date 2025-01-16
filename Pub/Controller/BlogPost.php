<?php

namespace TaylorJ\Blogs\Pub\Controller;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Entity\BlogPost as BlogPostEntity;
use TaylorJ\Blogs\Service\BlogPost\Create;
use TaylorJ\Blogs\Service\BlogPost\Delete as BlogPostDelete;
use TaylorJ\Blogs\Service\BlogPost\Edit;
use TaylorJ\Blogs\Utils;
use XF\ControllerPlugin\Reaction;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\SharePlugin;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\Attachment;
use XF\Repository\AttachmentRepository;
use XF\Repository\PostRepository;
use XF\Service\Attachment\Preparer;
use XF\Service\Tag\ChangerService;
use XF\Service\Thread\Creator;

/**
 * Controller for handling a blog instance
 */
class BlogPost extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		$blogPost = $this->assertBlogPostExists($params->blog_post_id);
		$blogPostRepo = (new Utils())->getBlogPostRepo();

		$blogPostContent = $this->finder('TaylorJ\Blogs:BlogPost')
			->where('blog_post_id', $params->blog_post_id);

		/** @var Attachment $attachmentRepo */
		$attachmentRepo = $this->repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($blogPostContent->fetch(), 'taylorj_blogs_blog_post');

		if ($blogPost->blog_post_state === 'visible')
		{
			$discussionThread = $this->finder('XF:Thread')->where('thread_id', $blogPost->discussion_thread_id)->fetchOne();
		}
		else
		{
			$discussionThread = null;
		}

		if ($discussionThread)
		{
			/** @var PostRepository $postRepo */
			$postRepo = $this->getPostRepo();
			$comments = $postRepo->findPostsForThreadView($discussionThread)
				->order('post_date', 'DESC');
		}
		else
		{
			$comments = null;
		}

		$isPrefetchRequest = $this->request->isPrefetch();
		if (!$isPrefetchRequest)
		{
			$blogPostRepo->logThreadView($blogPost);
		}

		$blogPostWordCount = str_word_count(strip_tags($blogPost->blog_post_content));
		$readTime = ceil($blogPostWordCount / 225);

		$ownerOtherPosts = $blogPostRepo->findOtherPostsByOwnerRandom($blogPost->user_id);

		$viewParams = [
			'blogPost' => $blogPost,
			'comments' => $comments ? $comments->fetch(5) : null,
			'discussionThread' => $discussionThread,
			'blogPostReadTime' => $readTime,
			'pendingApproval' => $this->filter('pending_approval', 'bool'),
			'ownerOtherPosts' => $ownerOtherPosts->fetch(4),
		];

		return $this->view('TaylorJ\Blogs:BlogPost\Index', 'taylorj_blogs_blog_post_view', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')->where('blog_post_id', $params->blog_post_id)->fetchOne();
		return $this->blogEdit($blogPostFinder);
	}

	protected function blogEdit(BlogPostEntity $blogPost)
	{
		/** @var Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentData = $attachmentRepo->getEditorData(
			'taylorj_blogs_blog_post',
			$blogPost,
		);

		$tz = new \DateTimeZone(\XF::visitor()->timezone);
		$dt = new \DateTime();
		$dt->setTimezone(new \DateTimeZone(\XF::visitor()->timezone));
		$dt->setTimestamp($blogPost->scheduled_post_date_time);
		/*$dt = new \DateTime($blogPost->scheduled_post_date_time);*/
		$hh_value = $dt->format('H');
		$mm_value = $dt->format('i');

		$hours = Utils::hours();
		$minutes = Utils::minutes();

		$blogId = $blogPost->Blog->blog_id;

		$viewParams = [
			'blogPost' => $blogPost,
			'attachmentData' => $attachmentData,
			'blog_id' => $blogId,
			'hours' => $hours,
			'minutes' => $minutes,
			'dt' => $dt,
			'hh_value' => $hh_value,
			'mm_value' => $mm_value,
		];

		return $this->view('TaylorJ\Blogs:BlogPost\Edit', 'taylorj_blogs_blog_post_edit', $viewParams);
	}

	public function actionSave(ParameterBag $params)
	{
		$blogPost = $this->finder('TaylorJ\Blogs:BlogPost')->where('blog_post_id', $params->blog_post_id)->fetchOne();

		return $this->blogPostSaveProcess($blogPost, $params);
	}

	protected function blogPostSaveProcess(BlogPostEntity $blogPost, ParameterBag $params)
	{
		$input = $this->filter([
			'blog_post_title' => 'str',
			'blog_id' => 'int',
		]);

		// uncomment the below lines if this ends up breaking something
		// i do not remember why i had a isPost check on a blogPost creation
		// if ($this->isPost()) {
		$creator = $this->blogPostEdit($blogPost);
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$this->assertNotFlooding('post');

		/** @var BlogPostEntity $blogPost */
		$blogPost = $creator->save();

		$hash = $this->filter('attachment_hash', 'str');
		if ($hash && $blogPost->canUploadAndManageAttachments())
		{
			/** @var Preparer $inserter */
			$inserter = $this->service('XF:Attachment\Preparer');
			$associated = $inserter->associateAttachmentsWithContent($hash, 'taylorj_blogs_blog_post', $blogPost->blog_post_id);
			if ($associated)
			{
				$blogPost->fastUpdate('attach_count', $blogPost->attach_count + $associated);
			}
		}
		$creator->finalSteps();

		return $this->redirect($this->buildLink('blogs/post', $blogPost), \XF::phrase('taylorj_blogs_post_edit_successful'));
		// }
	}

	public function actionDelete(ParameterBag $params)
	{
		/** @var BlogPostEntity $blogPost */
		$blogPost = $this->assertBlogPostExists($params->blog_post_id);
		$blog = $blogPost->Blog;

		if (!$blogPost->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$blogPost->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var BlogPostDelete $deleter */
			$deleter = $this->service('TaylorJ\Blogs:BlogPost\Delete', $blogPost);

			if ($this->filter('author_alert', 'bool'))
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			if ($blogPost->canSetPublicDeleteReason())
			{
				$deleter->setPostDeleteReason($this->filter('public_delete_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin('XF:InlineMod')->clearIdFromCookie('taylorj_blogs_blog_post', $blogPost->blog_post_id);

			return $this->redirect($this->buildLink('blogs/blog', $blogPost->Blog));
		}
		else
		{
			$viewParams = [
				'blogPost' => $blogPost,
				'blog' => $blogPost->Blog,
			];
			return $this->view('TaylorJ\Blogs:BlogPost\Delete', 'taylorj_blogs_blog_post_delete', $viewParams);
		}
	}

	public function actionReact(ParameterBag $params)
	{
		$blogPost = $this->assertViewablePost($params->blog_post_id);

		/** @var Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');

		return $reactionPlugin->actionReactSimple($blogPost, 'blogs/post');
	}

	public function actionReactions(ParameterBag $params)
	{
		$blogPost = $this->assertViewablePost($params->blog_post_id);

		/** @var Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		$breadCrumbs = $blogPost->getBreadcrumbs();

		return $reactionPlugin->actionReactions(
			$blogPost,
			'blogs/post/reactions',
			null,
			$breadCrumbs
		);
	}

	public function actionShare(ParameterBag $params)
	{
		$blogPost = $this->assertViewablePost($params->blog_post_id);
		$blog = $blogPost->Blog;

		/** @var SharePlugin $sharePlugin */
		$sharePlugin = $this->plugin(SharePlugin::class);
		return $sharePlugin->actionTooltipWithEmbed(
			$this->buildLink('canonical:blogs/post', $blogPost),
			\XF::phrase('taylorj_blogs_blog_post_in_x', ['title' => $blogPost->blog_post_title]),
			\XF::phrase('taylorj_blogs_blog_post_share_this'),
			null,
			$blogPost->getEmbedCodeHtml()
		);
	}

	public function actionReport(ParameterBag $params)
	{
		$blogPost = $this->assertViewablePost($params->blog_post_id);
		if (!$blogPost->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var ReportPlugin $reportPlugin */
		$reportPlugin = $this->plugin(ReportPlugin::class);
		return $reportPlugin->actionReport(
			'taylorj_blogs_blog_post',
			$blogPost,
			$this->buildLink('blogs/post/report', $blogPost),
			$this->buildLink('blogs/post', $blogPost)
		);
	}

	public function actionTags(ParameterBag $params)
	{
		$blogPost = $this->assertViewablePost($params->blog_post_id);

		if (!$blogPost->canEditTags($error))
		{
			return $this->noPermission($error);
		}

		/** @var ChangerService $tagger */
		$tagger = $this->service(ChangerService::class, 'taylorj_blogs_blog_post', $blogPost);

		if ($this->isPost())
		{
			$tagger->setEditableTags($this->filter('tags', 'str'));
			if ($tagger->hasErrors())
			{
				return $this->error($tagger->getErrors());
			}

			$tagger->save();

			if ($this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'blogPost' => $blogPost,
				];
				$reply = $this->view('TaylorJ\Blogs:BlogPost\TagsInline', 'taylorj_blogs_blog_post_tags_list', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('blogs/post', $blogPost));
			}
		}
		else
		{
			$grouped = $tagger->getExistingTagsByEditability();

			$viewParams = [
				'blogPost'         => $blogPost,
				'blog'          => $blogPost->Blog,
				'editableTags'   => $grouped['editable'],
				'uneditableTags' => $grouped['uneditable'],
			];

			return $this->view('TaylorJ\Blogs:BlogPost\Tags', 'taylorj_blogs_blog_post_tags', $viewParams);
		}
	}

	/**
	 * @param Blog $blog
	 *
	 * @return \TaylorJ\Blogs\Service\Blog\Creator
	 */
	protected function setupBlogPostCreate(Blog $blog)
	{
		$title = $this->filter('title', 'str');
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var Creator $creator */
		$creator = $this->service('XF:Thread\Creator', $blog);

		$creator->setContent($title, $message);

		return $creator;
	}

	public function actionAddPreview(ParameterBag $params)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');
		$blogId = $this->filter('blog_id', 'int');

		/** @var Blog $blog */
		$blogPost = $this->assertBlogPostExists($params->blog_post_id);
		$blog = $blogPost->Blog->blog_id;

		$tempHash = $this->filter('attachment_hash', 'str');
		/** @var Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentData = $attachmentRepo->getEditorData('taylorj_blogs_blog_post', $blogPost, $tempHash);
		$attachments = $attachmentData['attachments'];

		return $this->plugin('XF:BbCodePreview')->actionPreview(
			$message,
			'blog_post',
			\XF::visitor(),
			$attachments
		);
	}

	protected function assertBlogPostExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('TaylorJ\Blogs:BlogPost', $id, $with, $phraseKey);
	}

	protected function assertViewablePost($id, $with = null, $phraseKey = null)
	{
		/** @var BlogPostEntity $blogPost */
		$blogPost = $this->assertBlogPostExists($id, $with, $phraseKey);

		if (!$blogPost->canView($error))
		{
			throw $this->exception(
				$this->noPermission($error)
			);
		}

		return $blogPost;
	}

	/**
	 * @return PostRepository
	 */
	protected function getPostRepo()
	{
		return $this->repository(PostRepository::class);
	}

	/**
	 * @param Blog $blog
	 *
	 * @return Create
	 */
	protected function blogPostEdit(BlogPostEntity $blogPost)
	{
		/** @var Edit $creator */
		$creator = $this->service('TaylorJ\Blogs:BlogPost\Edit', $blogPost);

		$title = $this->filter('blog_post_title', 'str');
		$creator->setTitle($title);

		$message = $this->plugin('XF:Editor')->fromInput('message');
		$creator->setContent($message);

		$scheduledPostDateTime = $this->filter([
			'blog_post_schedule' => 'string',
			'dd' => 'str',
			'hh' => 'int',
			'mm' => 'int',
		]);

		$creator->setScheduledPostDateTime($scheduledPostDateTime);

		return $creator;
	}
}
