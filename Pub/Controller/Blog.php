<?php

namespace TaylorJ\Blogs\Pub\Controller;

use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Repository\BlogWatch;
use TaylorJ\Blogs\Service\BlogPost\Create;
use TaylorJ\Blogs\Utils;
use XF\ControllerPlugin\Delete;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\Attachment;
use XF\Repository\AttachmentRepository;
use XF\Service\Attachment\Preparer;

/**
 * Controller for handling a blog instance
 */
class Blog extends AbstractController
{
	public function preDispatchController($action, ParameterBag $params)
	{
		$visitor = \XF::visitor();

		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($params->blog_id);

		if (!$blog->canView() && $blog->user_id == \XF::visitor()->user_id)
		{
			throw $this->exception($this->noPermission(\XF::phrase('permission.taylorjBlogs_viewOwn')));
		}
		else if (!$blog->canView())
		{
			throw $this->exception($this->noPermission(\XF::phrase('permission.taylorjBlogs_viewAny')));
		}
	}

	public function actionIndex(ParameterBag $params, $postType = 'visible')
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($params->blog_id);

		$conditions[] = [
			'blog_post_state' => ['moderated', 'visible'],
		];
		$blogPostFinder = $this->getBlogPosts($params->blog_id, $conditions, 'DESC');

		$page = $params->page;
		$perPage = $this->options()->taylorjBlogPostsPerPage;
		$blogPostFinder->limitByPage($page, $perPage);

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($blogPostFinder, 'taylorj_blogs_blog_post');

		$viewParams = [
			'blog' => $blog,
			'blogPosts' => $blogPostFinder->fetch(),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $blogPostFinder->total(),
			'viewType' => 'visible',
		];

		return $this->view(
			'TaylorJ\Blogs:Blog\Index',
			'taylorj_blogs_blog_view',
			$viewParams
		);
	}

	public function actionScheduledPosts(ParameterBag $params)
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($params->blog_id);

		$conditions[] = [
			'blog_post_state' => ['scheduled'],
		];
		$blogPostFinder = $this->getBlogPosts($params->blog_id, $conditions, 'DESC');

		$page = $params->page;
		$perPage = $this->options()->taylorjBlogPostsPerPage;
		$blogPostFinder->limitByPage($page, $perPage);

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($blogPostFinder, 'post');

		$viewParams = [
			'blog' => $blog,
			'blogPosts' => $blogPostFinder->fetch(),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $blogPostFinder->total(),
			'viewType' => 'scheduled',
		];

		return $this->view(
			'TaylorJ\Blogs:Blog\Index',
			'taylorj_blogs_blog_view',
			$viewParams
		);
	}

	public function actionDraftPosts(ParameterBag $params)
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($params->blog_id);

		$conditions[] = [
			'blog_post_state' => ['draft'],
		];
		$blogPostFinder = $this->getBlogPosts($params->blog_id, $conditions, 'DESC');

		$page = $params->page;
		$perPage = $this->options()->taylorjBlogPostsPerPage;
		$blogPostFinder->limitByPage($page, $perPage);

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($blogPostFinder, 'taylorj_blogs_blog_post');

		$viewParams = [
			'blog' => $blog,
			'blogPosts' => $blogPostFinder->fetch(),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $blogPostFinder->total(),
			'viewType' => 'draft',
		];

		return $this->view(
			'TaylorJ\Blogs:Blog\Index',
			'taylorj_blogs_blog_view',
			$viewParams
		);
	}

	public function actionDeletedPosts(ParameterBag $params)
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($params->blog_id);

		$conditions[] = [
			'blog_post_state' => ['deleted'],
		];
		$blogPostFinder = $this->getBlogPosts($params->blog_id, $conditions, 'DESC');

		$page = $params->page;
		$perPage = $this->options()->taylorjBlogPostsPerPage;
		$blogPostFinder->limitByPage($page, $perPage);

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($blogPostFinder, 'taylorj_blogs_blog_post');

		$viewParams = [
			'blog' => $blog,
			'blogPosts' => $blogPostFinder->fetch(),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $blogPostFinder->total(),
			'viewType' => 'deleted',
		];

		return $this->view(
			'TaylorJ\Blogs:Blog\Index',
			'taylorj_blogs_blog_view',
			$viewParams
		);
	}

	public function actionEdit(ParameterBag $params)
	{
		$blogFinder = $this->finder('TaylorJ\Blogs:Blog')
			->where('blog_id', $params->blog_id)->fetchOne();
		return $this->blogEdit($blogFinder);
	}

	public function actionDelete(ParameterBag $params)
	{
		$blog = $this->assertBlogExists($params->blog_id);


		if (!$blog->canDelete($error))
		{
			return $this->noPermission($error);
		}

		/** @var Delete $plugin */
		$plugin = $this->plugin('XF:Delete');

		return $plugin->actionDelete(
			$blog,
			$this->buildLink('blogs/blog/delete', $blog),
			$this->buildLink('blogs/blog/edit', $blog),
			$this->buildLink('blogs'),
			$blog->blog_title
		);
	}

	public function actionAddPost(ParameterBag $params)
	{
		$visitor = \XF::visitor();
		$blog = $this->assertBlogExists($params->blog_id);

		if ($blog->user_id === $visitor->user_id)
		{
			if (!$visitor->hasPermission('taylorjBlogPost', 'canPost'))
			{
				return $this->noPermission(\XF::phrase('taylorj_blogs_blog_post_error_new'));
			}
			else
			{
				$blogPost = $this->em()->create('TaylorJ\Blogs:BlogPost');
				return $this->blogPostAdd($blogPost, $params->blog_id);
			}
		}
	}

	protected function blogEdit(\TaylorJ\Blogs\Entity\Blog $blog)
	{
		$viewParams = [
			'blog' => $blog,
		];

		return $this->view('TaylorJ\Blogs:Blog\Edit', 'taylorj_blogs_blog_edit', $viewParams);
	}

	protected function blogPostAdd(BlogPost $blogPost, $blog_id)
	{
		/** @var Attachment $attachmentRepo */
		$attachmentRepo = $this->repository('XF:Attachment');
		$attachmentData = $attachmentRepo->getEditorData(
			'taylorj_blogs_blog_post',
			$blogPost,
		);

		$dt = new \DateTime();
		$dt->setTimezone(new \DateTimeZone(\XF::visitor()->timezone));
		$hh_value = $dt->format('H');
		$mm_value = $dt->format('i');

		$hours = Utils::hours();
		$minutes = Utils::minutes();

		$blog = $this->assertBlogExists($blog_id);

		$viewParams = [
			'blogPost' => $blogPost,
			'blog' => $blog,
			'attachmentData' => $attachmentData,
			'blogId' => $blog_id,
			'hours' => $hours,
			'minutes' => $minutes,
			'dt' => $dt,
			'hh_value' => $hh_value,
			'mm_value' => $mm_value,
		];

		return $this->view('TaylorJ\Blogs:BlogPost\Edit', 'taylorj_blogs_blog_post_new_edit', $viewParams);
	}

	public function actionPostSave(ParameterBag $params)
	{
		$blogPost = $this->em()->create('TaylorJ\Blogs:BlogPost');

		return $this->blogPostSaveProcess($params);
	}

	protected function blogPostSaveProcess(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		$input = $this->filter([
			'blog_post_title' => 'str',
			'blog_id' => 'int',
		]);
		$blog = $this->assertBlogExists($input['blog_id']);

		$creator = $this->blogPostCreate($blog);
		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$this->assertNotFlooding('post');

		if ($blog->canEditTags())
		{
			$creator->setTags($this->filter('tags', 'str'));
		}

		/** @var BlogPost $blogPost */
		$blogPost = $creator->save();

		if ($visitor->user_id)
		{
			if ($blogPost->blog_post_state == 'moderated')
			{
				$this->session()->setHasContentPendingApproval();
			}
		}

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

		return $this->redirect($this->buildLink('blogs/post', $blogPost), \XF::phrase('taylorj_blogs_post_successful'));
	}

	public function actionWatch(ParameterBag $params)
	{

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->noPermission();
		}

		$blog = $this->assertBlogExists($params->blog_id);

		if (!$blog->canWatch($error))
		{
			return $this->noPermission($error);
		}

		/** @var BlogWatch $blogWatchRepo */
		$blogWatchRepo = $this->repository('TaylorJ\Blogs:BlogWatch');
		$blogWatchRepo->setWatchState($blog, $visitor);

		$redirect = $this->redirect($this->buildLink('blogs/blog', $blog));
		return $redirect;
	}

	public function actionAddPreview(ParameterBag $params)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');
		$blogId = $this->filter('blog_id', 'int');
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertBlogExists($blogId);
		$blogPost = $blog->getNewBlogPost();

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

	protected function assertBlogExists($id, $with = null, $phraseKey = null)
	{
		/** @var \TaylorJ\Blogs\Entity\Blog $blog */
		$blog = $this->assertRecordExists('TaylorJ\Blogs:Blog', $id, $with, $phraseKey);
		return $blog;
	}

	/**
	 * @param $blodId
	 * @param $conditions[]
	 * @param $order
	 *
	 * @return array|ArrayCollection
	 */
	protected function getBlogPosts($blogId, $conditions, $order)
	{
		$blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')
			->where('blog_id', $blogId)
			->whereOr($conditions)
			->order('blog_post_state', $order);

		return $blogPostFinder;
	}

	/**
	 * @param \TaylorJ\Blogs\Entity\Blog $blog
	 *
	 * @return Create
	 */
	protected function blogPostCreate(\TaylorJ\Blogs\Entity\Blog $blog)
	{
		/** @var Create $creator */
		$creator = $this->service('TaylorJ\Blogs:BlogPost\Create', $blog);

		$title = $this->filter('blog_post_title', 'str');
		$creator->setTitle($title);

		$message = $this->plugin('XF:Editor')->fromInput('message');
		$creator->setContent($message);

		$blogPostState = $this->filter('blog_post_schedule', 'str');
		$creator->setBlogPostState($blogPostState);

		if ($blogPostState == 'scheduled')
		{
			$scheduledPostDateTime = $this->filter([
				'dd' => 'str',
				'hh' => 'int',
				'mm' => 'int',
			]);
			$creator->setScheduledPostDateTime($scheduledPostDateTime);
		}

		if ($blogPostState == 'visible')
		{
			$creator->sendNotifications(3);
		}

		return $creator;
	}
}
