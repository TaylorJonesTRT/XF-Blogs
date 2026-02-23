<?php

namespace TaylorJ\Blogs\Pub\Controller;

use TaylorJ\Blogs\Entity\Blog as BlogEntity;
use TaylorJ\Blogs\Entity\BlogPost as BlogPostEntity;
use TaylorJ\Blogs\Repository\BlogWatch;
use TaylorJ\Blogs\Service\Blog\Delete as BlogDelete;
use TaylorJ\Blogs\Service\BlogPost\Create;
use TaylorJ\Blogs\Utils;
use XF\ControllerPlugin\UndeletePlugin;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\Attachment;
use XF\Repository\AttachmentRepository;
use XF\Service\Attachment\Preparer;

class Blog extends AbstractController
{
    public function preDispatchController($action, ParameterBag $params)
    {
        $visitor = \XF::visitor();

        /** @var BlogEntity $blog */
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

    public function actionIndex(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
            $this->renderBlogPostList($blog, 'visible'));
    }

    public function actionScheduledPosts(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        if (!$blog->canViewScheduledPosts())
        {
            return $this->noPermission();
        }
        return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
            $this->renderBlogPostList($blog, 'scheduled'));
    }

    public function actionDraftPosts(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        // Drafts are private — only the blog owner can see them
        if ($blog->user_id !== \XF::visitor()->user_id)
        {
            return $this->noPermission();
        }
        return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
            $this->renderBlogPostList($blog, 'draft'));
    }

    public function actionDeletedPosts(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        if (!\XF::visitor()->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
        {
            return $this->noPermission();
        }
        return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
            $this->renderBlogPostList($blog, 'deleted'));
    }

    protected function renderBlogPostList(BlogEntity $blog, string $viewType)
    {
        /** @var \TaylorJ\Blogs\Finder\BlogPost $blogPostFinder */
        $blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost');

        switch ($viewType)
        {
            case 'visible':
                // inBlog() applies permission-based visibility checks
                $blogPostFinder->inBlog($blog);
                break;
            case 'scheduled':
                $blogPostFinder->inBlogId($blog->blog_id)->scheduledPosts();
                break;
            case 'draft':
                $blogPostFinder->inBlogId($blog->blog_id)->draftPosts();
                break;
            case 'deleted':
                $blogPostFinder->inBlogId($blog->blog_id)->deletedPosts();
                break;
        }

        $blogPostFinder->latestFirst();

        $page = $this->filterPage();
        $perPage = $this->options()->taylorjBlogPostsPerPage;
        $blogPostFinder->limitByPage($page, $perPage);

        /** @var AttachmentRepository $attachmentRepo */
        $attachmentRepo = \XF::repository(AttachmentRepository::class);
        $attachmentRepo->addAttachmentsToContent($blogPostFinder, 'taylorj_blogs_blog_post');

        $allBlogPosts = $blogPostFinder->fetch();

        $canInlineMod = false;
        foreach ($allBlogPosts AS $blogPost)
        {
            if ($blogPost->canUseInlineModeration())
            {
                $canInlineMod = true;
                break;
            }
        }

        return [
            'blog'           => $blog,
            'blogPosts'      => $allBlogPosts,
            'page'           => $page,
            'perPage'        => $perPage,
            'total'          => $blogPostFinder->total(),
            'allowInlineMod' => !$this->request->get('_xfDisableInlineMod'),
            'canInlineMod'   => $canInlineMod,
            'viewType'       => $viewType,
        ];
    }

    public function actionEdit(ParameterBag $params)
    {
        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')->where('blog_id', $params->blog_id)->fetchOne();

        return $this->blogEdit($blogFinder);
    }

    public function actionDelete(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        /** @var BlogEntity $blog */
        $blog = $this->assertBlogExists($params->blog_id);

        $type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
        $reason = $this->filter('reason', 'str');

        if (!$blog->canDelete($type, $error))
        {
            return $this->noPermission($error);
        }

        if ($this->isPost())
        {
            if ($visitor->user_id == $blog->user_id)
            {
                $type = 'soft';
            }
            else
            {
                $type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
            }

            $reason = $this->filter('reason', 'str');

            if (!$blog->canDelete($type, $error))
            {
                return $this->noPermission($error);
            }

            /** @var BlogDelete $deleter */
            $deleter = $this->service('TaylorJ\Blogs:Blog\Delete', $blog);

            if ($this->filter('author_alert', 'bool'))
            {
                $deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
            }

            if ($blog->canSetPublicDeleteReason())
            {
                $deleter->setBlogDeleteReason($this->filter('public_delete_reason', 'str'));
            }

            $deleter->delete($type, $reason);

            $this->plugin('XF:InlineMod')->clearIdFromCookie('taylorj_blogs_blog', $blog->blog_id);

            return $this->redirect($this->buildLink('blogs'));
        }
        else
        {
            $viewParams = [
                'blog' => $blog,
            ];

            return $this->view('TaylorJ\Blogs:Blog\Delete', 'taylorj_blogs_blog_delete', $viewParams);
        }
    }

    public function actionUndelete(ParameterBag $params)
    {
        /** @var BlogEntity $blog */
        $blog = $this->assertBlogExists($params->blog_id);

        /** @var UndeletePlugin $plugin */
        $plugin = $this->plugin('XF:Undelete');
        return $plugin->actionUndelete(
            $blog,
            $this->buildLink('blogs/blog/undelete', $blog),
            $this->buildLink('blogs/blog', $blog),
            $blog->blog_title,
            'blog_state'
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

    protected function blogEdit(BlogEntity $blog)
    {
        $viewParams = [
            'blog' => $blog,
        ];

        return $this->view('TaylorJ\Blogs:Blog\Edit', 'taylorj_blogs_blog_edit', $viewParams);
    }

    protected function blogPostAdd(BlogPostEntity $blogPost, $blog_id)
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
        /** @var BlogEntity $blog */
        $blog = $this->assertBlogExists($blogId);
        $blogPost = $blog->getNewBlogPost();

        $tempHash = $this->filter('attachment_hash', 'str');
        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentData = $attachmentRepo->getEditorData('taylorj_blogs_blog_post', $blogPost, $tempHash);
        $attachments = $attachmentData['attachments'];

        return $this->plugin('XF:BbCodePreview')->actionPreview(
            $message,
            'taylorj_blogs_blog_post',
            \XF::visitor(),
            $attachments
        );
    }

    protected function assertBlogExists($id, $with = null, $phraseKey = null)
    {
        /** @var BlogEntity $blog */
        $blog = $this->assertRecordExists('TaylorJ\Blogs:Blog', $id, $with, $phraseKey);
        return $blog;
    }

    /**
     * @param BlogEntity $blog
     *
     * @return Create
     */
    protected function blogPostCreate(BlogEntity $blog)
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
