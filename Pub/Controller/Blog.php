<?php

namespace TaylorJ\UserBlogs\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Controller for handling a blog instance
 */
class Blog extends AbstractController
{
    public function actionBlog(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);

        $viewParams = [
            'blog' => $blog,
        ];

        return $this->view('TaylorJ\UserBlogs:Blog\Index', 'taylorj_userblogs_blog_view', $viewParams);
    }

    public function actionBlogAdd()
    {
        $blogPost = $this->em()->create('TaylorJ\UserBlogs:BlogPost');
        return $this->blogPostAddEdit($blogPost);
    }

    public function actionBlogEdit(ParameterBag $params)
    {
        $blogPostFinder = $this->finder('TaylorJ\UserBlogs:BlogPost')->where('id', $params->id)->fetchOne();
        return $this->blogPostAddEdit($blogPostFinder);
    }

    protected function blogPostAddEdit(\TaylorJ\UserBlogs\Entity\BlogPost $blogPost)
    {
        $viewParams = [
            'blogPost' => $blogPost
        ];

        return $this->view('TaylorJ\UserBlogs:BlogPost\Edit', 'taylorj_userblogs_blog_post_edit', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        if ($params->id) {
            $blog = $this->assertBlogExists($params->id);
        } else {
            $blog = $this->em()->create('TaylorJ\UserBlogs:Blog');
        }

        $this->blogPostSaveProcess($blog)->run();

        return $this->redirect($this->buildLink('userblogs', $blog));
    }

    protected function blogPostSaveProcess(\TaylorJ\UserBlogs\Entity\BlogPost $blogPost)
    {
        $input = $this->filter([
            'blog_post_title' => 'str',
            'blog_post_content' => 'str',
        ]);

        $form = $this->formAction();
        $form->basicEntitySave($blogPost, $input);

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
        $blog = $this->assertBlogExists($params->blog_id ?: $params->blog_name);

        $creator = $this->setupBlogPostCreate($blog);

        return $this->plugin('XF:BbCodePreview')->actionPreview(
            $message,
            'blog_post',
            \XF::visitor()
        );
    }

    protected function assertBlogExists($id, $with = null, $phraseKey = null)
    {
        $blog = $this->assertRecordExists('TaylorJ\UserBlogs:Blog', $id, $with, $phraseKey);
        return $blog;
    }
}
