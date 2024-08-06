<?php

namespace TaylorJ\Blogs\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Controller for handling a blog instance
 */
class Blog extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        /** @var \TaylorJ\Blogs\Entity\Blog $blog */
        $blog = $this->assertBlogExists($params->blog_id);
        
        if (!$blog->canView() && $blog->user_id == \XF::visitor()->user_id)
        {
            return $this->noPermission(\XF::phrase('permission.blogs_viewOwn'));
        }
        elseif (!$blog->canView())
        {
            return $this->noPermission(\XF::phrase('permission.blogs_viewAny'));
        }

        $blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')
            ->where('blog_id', $params->blog_id)
            ->order('blog_post_date', 'DESC');

        $page = $params->page;
        $perPage = $this->options()->taylorjBlogPostsPerPage;
        $blogPostFinder->limitByPage($page, $perPage);

        $viewParams = [
            'blog' => $blog,
            'blogPosts' => $blogPostFinder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $blogPostFinder->total()
        ];

        return $this->view(
            'TaylorJ\Blogs:Blog\Index',
            'taylorj_blogs_blog_view',
            $viewParams
        );
    }

    public function actionEdit(ParameterBag $params)
    {
        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')->where('blog_id', $params->blog_id)->fetchOne();
        return $this->blogEdit($blogFinder, $params->blog_id);
    }

    public function actionDelete(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        
        /** @var \XF\ControllerPlugin\Delete $plugin */
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
        if (!\XF::visitor()->hasPermission('taylorjBlogPost', 'canPost'))
        {
            return $this->noPermission(\XF::phrase('taylorj_blogs_blog_post_error_new'));
        }
        $blogPost = $this->em()->create('TaylorJ\Blogs:BlogPost');
        return $this->blogPostAdd($blogPost, $params->blog_id);
    }

    protected function blogEdit(\TaylorJ\Blogs\Entity\Blog $blog)
    {
        $viewParams = [
            'blog' => $blog,
        ];

        return $this->view('TaylorJ\Blogs:Blog\Edit', 'taylorj_blogs_blog_edit', $viewParams);
    }

    protected function blogPostAdd(\TaylorJ\Blogs\Entity\BlogPost $blogPost, $blog_id)
    {
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData(
            'taylorj_blogs_blog_post',
            $blogPost,
        );

        $viewParams = [
            'blogPost' => $blogPost,
            'attachmentData' => $attachmentData,
            'blogId' => $blog_id
        ];

        return $this->view('TaylorJ\Blogs:BlogPost\Edit', 'taylorj_blogs_blog_post_new_edit', $viewParams);
    }

    public function actionPostSave(ParameterBag $params)
    {
        $blogPost = $this->em()->create('TaylorJ\Blogs:BlogPost');

        $this->blogPostSaveProcess($blogPost, $params);

        return $this->redirect($this->buildLink('blogs/post', $blogPost));
    }

    protected function blogPostSaveProcess(\TaylorJ\Blogs\Entity\BlogPost $blogPost, ParameterBag $params)
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
        $form->run();

        $hash = $this->filter('attachment_hash', 'str');
        if ($hash && $blogPost->canUploadAndManageAttachments()) {
            /** @var \XF\Service\Attachment\Preparer $inserter */
            $inserter = $this->service('XF:Attachment\Preparer');
            $associated = $inserter->associateAttachmentsWithContent($hash, 'taylorj_blogs_blog_post', $blogPost->blog_post_id);
            if ($associated) {
                $blogPost->fastUpdate('attach_count', $blogPost->attach_count + $associated);
            }
        }

        return $form;
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
        $blog = $this->assertBlogExists($blogId);
        $blogPost = $blog->getNewBlogPost();

        $tempHash = $this->filter('attachment_hash', 'str');
        /** @var \XF\Repository\Attachment $attachmentRepo */
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
        $blog = $this->assertRecordExists('TaylorJ\Blogs:Blog', $id, $with, $phraseKey);
        return $blog;
    }

    protected function assertBlogPostExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('TaylorJ\Blogs:BlogPost', $id, $with, $phraseKey);
    }
}
