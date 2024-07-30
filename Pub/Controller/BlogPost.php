<?php

namespace TaylorJ\UserBlogs\Pub\Controller;

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

        $viewParams = [
            'blogPost' => $blogPost
        ];

        return $this->view('TaylorJ\UserBlogs:BlogPost\Index', 'taylorj_userblogs_blog_post_view', $viewParams);
    }

    public function actionBlogPostEdit(ParameterBag $params)
    {
        $blogPostFinder = $this->finder('TaylorJ\UserBlogs:BlogPost')->where('blog_post_id', $params->id)->fetchOne();
        return $this->blogEdit($blogPostFinder, $params->blog_id);
    }

    protected function blogEdit(\TaylorJ\UserBlogs\Entity\BlogPost $blogPost, $blogId)
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData(
            'taylorj_userblogs_post',
            $blogPost,
        );

        $viewParams = [
            'blogPost' => $blogPost,
            'attachmentData' => $attachmentData,
            'blogId' => $blogId
        ];

        return $this->view('TaylorJ\UserBlogs:BlogPost\Edit', 'taylorj_userblogs_blog_post_edit', $viewParams);
    }

    public function actionBlogPostSave(ParameterBag $params)
    {
        $blogPost = $this->finder('TaylorJ\UserBlogs:BlogPost')->where('blog_post_id', $params->id)->fetchOne();

        $this->blogPostSaveProcess($blogPost, $params)->run();

        return $this->redirect($this->buildLink('userblogs/blog/post', $blogPost));
    }

    protected function blogPostSaveProcess(\TaylorJ\UserBlogs\Entity\BlogPost $blogPost, ParameterBag $params)
    {
        $input = $this->filter([
            'blog_post_title' => 'str',
            'blog_id' => 'int'
        ]);
        $blog = $this->assertBlogExists($input['blog_id']);
        $message = $this->plugin('XF:Editor')->fromInput('message');
        $input['blog_post_content'] = $message;
        $input['blog_post_last_edit_date'] = 0;

        $form = $this->formAction();
        $form->basicEntitySave($blogPost, $input);

        $hash = $this->filter('attachment_hash', 'str');
        if ($hash && $blogPost->canUploadAndManageAttachments()) {
            $inserter = $this->service('XF:Attachment\Preparer');
        }

        return $form;
    }

    /**
     * @param \TaylorJ\UserBlogs\Entity\Blog $blog
     *
     * @return \TaylorJ\UserBlogs\Service\Blog\Creator
     */
    protected function setupBlogPostCreate(\TaylorJ\UserBlogs\Entity\Blog $blog)
    {
        $title = $this->filter('title', 'str');
        $message = $this->plugin('XF:Editor')->fromInput('message');

        /** @var \XF\Service\Thread\Creator $creator */
        $creator = $this->service('XF:Thread\Creator', $blog);

        $creator->setContent($title, $message);

        // attachments aren't supported in pre-reg actions
        // if ($forum->canUploadAndManageAttachments())
        // {
        // 	$creator->setAttachmentHash($this->filter('attachment_hash', 'str'));
        // }

        return $creator;
    }

    public function actionBlogAddPreview(ParameterBag $params)
    {

        // $creator = $this->setupBlogPostCreate($blog);
        $message = $this->plugin('XF:Editor')->fromInput('message');
        $blogId = $this->filter('blog_id', 'int');
        /** @var \TaylorJ\UserBlogs\Entity\Blog $blog */
        $blog = $this->assertBlogExists($blogId);
        $blogPost = $blog->getNewBlogPost();

        $tempHash = $this->filter('attachment_hash', 'str');
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData('taylorj_userblogs_post', $blogPost, $tempHash);
        $attachments = $attachmentData['attachments'];

        return $this->plugin('XF:BbCodePreview')->actionPreview(
            $message,
            'blog_post',
            \XF::visitor(),
            $attachments
        );
    }

    // protected function assertBlogExists($id, $with = null, $phraseKey = null)
    // {
    //     $blog = $this->assertRecordExists('TaylorJ\UserBlogs:Blog', $id, $with, $phraseKey);
    //     return $blog;
    // }

    protected function assertBlogPostExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('TaylorJ\UserBlogs:BlogPost', $id, $with, $phraseKey);
    }
}
