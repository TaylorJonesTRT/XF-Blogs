<?php

namespace TaylorJ\Blogs\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Controller for handling a blog instance
 */
class BlogPost extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $blogPost = $this->assertBlogPostExists($params->blog_post_id);
		$blogPostRepo = $this->getBlogPostRepo();
        
        $blogPostContent = $this->finder('TaylorJ\Blogs:BlogPost')
            ->where('blog_post_id', $params->blog_post_id);

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->addAttachmentsToContent($blogPostContent->fetch(), 'taylorj_blogs_blog_post');

		$isPrefetchRequest = $this->request->isPrefetch();

		if (!$isPrefetchRequest)
		{
			$blogPostRepo->logThreadView($blogPost);
		}

        $viewParams = [
            'blogPost' => $blogPost
        ];

        return $this->view('TaylorJ\Blogs:BlogPost\Index', 'taylorj_blogs_blog_post_view', $viewParams);
    }

    public function actionEdit(ParameterBag $params)
    {
        $blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')->where('blog_post_id', $params->blog_post_id)->fetchOne();
        return $this->blogEdit($blogPostFinder);
    }

    protected function blogEdit(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData(
            'taylorj_blogs_blog_post',
            $blogPost,
        );

        $blogId = $blogPost->Blog->blog_id;

        $viewParams = [
            'blogPost' => $blogPost,
            'attachmentData' => $attachmentData,
            'blog_id' => $blogId
        ];

        return $this->view('TaylorJ\Blogs:BlogPost\Edit', 'taylorj_blogs_blog_post_edit', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        $blogPost = $this->finder('TaylorJ\Blogs:BlogPost')->where('blog_post_id', $params->blog_post_id)->fetchOne();

        $this->blogPostSaveProcess($blogPost, $params)->run();

        return $this->redirect($this->buildLink('blogs/post', $blogPost));
    }

    protected function blogPostSaveProcess(\TaylorJ\Blogs\Entity\BlogPost $blogPost, ParameterBag $params)
    {
        $input = $this->filter([
            'blog_post_title' => 'str',
            'blog_id' => 'int'
        ]);
        $message = $this->plugin('XF:Editor')->fromInput('message');
        $input['blog_post_content'] = $message;
        $input['blog_post_last_edit_date'] = 0;
        $input['blog_post_edit_date'] = \XF::$time;

        $form = $this->formAction();
        $form->basicEntitySave($blogPost, $input);

        $hash = $this->filter('attachment_hash', 'str');
        if ($hash && $blogPost->canUploadAndManageAttachments()) {
            $inserter = $this->service('XF:Attachment\Preparer');
        }

        return $form;
    }
    
    public function actionDelete(ParameterBag $params)
    {
        $blogPost = $this->assertBlogPostExists($params->blog_post_id);
        
        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $blogPost,
            $this->buildLink('blogs/post/delete', $blogPost),
            $this->buildLink('blogs/post/edit', $blogPost),
            $this->buildLink('blogs/blog', $blogPost->blog_id),
            $blogPost->blog_post_title
        );
    }

    /**
     * @param \TaylorJ\Blogs\Entity\Blog $blog
     *
     * @return \TaylorJ\Blogs\Service\Blog\Creator
     */
    protected function setupBlogPostCreate(\TaylorJ\Blogs\Entity\Blog $blog)
    {
        $title = $this->filter('title', 'str');
        $message = $this->plugin('XF:Editor')->fromInput('message');

        /** @var \XF\Service\Thread\Creator $creator */
        $creator = $this->service('XF:Thread\Creator', $blog);

        $creator->setContent($title, $message);

        return $creator;
    }

    public function actionAddPreview(ParameterBag $params)
    {
        $message = $this->plugin('XF:Editor')->fromInput('message');
        $blogId = $this->filter('blog_id', 'int');

        /** @var \TaylorJ\Blogs\Entity\Blog $blog */
        $blogPost = $this->assertBlogPostExists($params->blog_post_id);
        $blog = $blogPost->Blog->blog_id;

        $tempHash = $this->filter('attachment_hash', 'str');
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData('taylorj_blogs_post', $blogPost, $tempHash);
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

	/**
	 * @return \XF\Repository\Thread
	 */
	protected function getBlogPostRepo()
	{
		return $this->repository('TaylorJ\Blogs:BlogPost');
	}
}
