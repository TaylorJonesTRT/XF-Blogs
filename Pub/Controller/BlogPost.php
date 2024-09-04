<?php

namespace TaylorJ\Blogs\Pub\Controller;

use TaylorJ\Blogs\Entity\BlogPost as BlogPostEntity;
use TaylorJ\Blogs\XF\ForumType\Discussion;
use XF\Pub\Controller\AbstractController;
use XF\Repository\PostRepository;
use XF\Mvc\ParameterBag;
use XF\Repository\AttachmentRepository;
use XF\ControllerPlugin\SharePlugin;
use XF\ControllerPlugin\ReportPlugin;
use TaylorJ\Blogs\Utils;

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
        $attachmentRepo = $this->repository(AttachmentRepository::class);
        $attachmentRepo->addAttachmentsToContent($blogPostContent->fetch(), 'taylorj_blogs_blog_post');

        if ($blogPost->blog_post_state === 'visible') {
            $discussionThread = $this->finder('XF:Thread')->where('thread_id', $blogPost->discussion_thread_id)->fetchOne();
            /** @var \XF\Repository\PostRepository $postRepo */
            $postRepo = $this->getPostRepo();
            $postList = $postRepo->findPostsForThreadView($discussionThread)
                ->order('post_date', 'DESC')
                ->fetch(5);
        } else {
            $discussionThread = null;
            $postList = null;
        }
        $isPrefetchRequest = $this->request->isPrefetch();

        if (!$isPrefetchRequest) {
            $blogPostRepo->logThreadView($blogPost);
        }

        $blogPostWordCount = str_word_count(strip_tags($blogPost->blog_post_content));
        $readTime = ceil($blogPostWordCount / 225);

        $viewParams = [
            'blogPost' => $blogPost,
            'comments' => $postList,
            'discussionThread' => $discussionThread,
            'blogPostReadTime' => $readTime
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
            'mm_value' => $mm_value
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
            'blog_id' => 'int'
        ]);

        // uncomment the below lines if this ends up breaking something
        // i do not remember why i had a isPost check on a blogPost creation
        // if ($this->isPost()) {
        $creator = $this->blogPostEdit($blogPost);
        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $this->assertNotFlooding('post');

        /** @var \TaylorJ\Blogs\Entity\BlogPost $blogPost */
        $blogPost = $creator->save();

        $hash = $this->filter('attachment_hash', 'str');
        if ($hash && $blogPost->canUploadAndManageAttachments()) {
            /** @var \XF\Service\Attachment\Preparer $inserter */
            $inserter = $this->service('XF:Attachment\Preparer');
            $associated = $inserter->associateAttachmentsWithContent($hash, 'taylorj_blogs_blog_post', $blogPost->blog_post_id);
            if ($associated) {
                $blogPost->fastUpdate('attach_count', $blogPost->attach_count + $associated);
            }
        }
        $creator->finalSteps();

        return $this->redirect($this->buildLink('blogs/post', $blogPost), \XF::phrase('taylorj_blogs_post_edit_successful'));
        // }
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

    public function actionReact(ParameterBag $params)
    {
        $blogPost = $this->assertViewablePost($params->blog_post_id);

        /** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('XF:Reaction');

        return $reactionPlugin->actionReactSimple($blogPost, 'blogs/post');
    }

    public function actionReactions(ParameterBag $params)
    {
        $blogPost = $this->assertViewablePost($params->blog_post_id);

        /** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('XF:Reaction');

        return $reactionPlugin->actionReactions(
            $blogPost,
            'blogs/post/reactions',
            null,
            []
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
        if (!$blogPost->canReport($error)) {
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
        /** @var \TaylorJ\Blogs\Entity\BlogPost $blogPost */
        $blogPost = $this->assertBlogPostExists($id, $with, $phraseKey);

        if (!$blogPost->canView($error)) {
            throw $this->exception(
                $this->noPermission($error)
            );
        }

        return $blogPost;
    }

    /**
     * @return \XF\Repository\Thread
     */
    protected function getBlogPostRepo()
    {
        return $this->repository('TaylorJ\Blogs:BlogPost');
    }

    /**
     * @return PostRepository
     */
    protected function getPostRepo()
    {
        return $this->repository(PostRepository::class);
    }

    public function insertJob(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
    {
        $jobid = 'taylorjblogs_scheduledpost_' . $blogPost->blog_post_id . '_' . \XF::$time;
        $app = \XF::app();
        $app->jobManager()->enqueueLater($jobid, $blogPost->scheduled_post_date_time, 'TaylorJ\Blogs:PostBlogPost', ['blog_post_id' => $blogPost->blog_post_id]);
    }

    /**
     * @param \TaylorJ\Blogs\Entity\Blog $blog
     *
     * @return \TaylorJ\Blogs\Service\BlogPost\Create
     */
    protected function blogPostEdit(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
    {
        /** @var \TaylorJ\Blogs\Service\BlogPost\Edit $creator */
        $creator = $this->service('TaylorJ\Blogs:BlogPost\Edit', $blogPost);

        $title = $this->filter('blog_post_title', 'str');
        $creator->setTitle($title);

        $message = $this->plugin('XF:Editor')->fromInput('message');
        $creator->setContent($message);

        $scheduledPostDateTime = $this->filter([
            'blog_post_schedule' => 'bool',
            'dd' => 'str',
            'hh' => 'int',
            'mm' => 'int'
        ]);

        $creator->setScheduledPostDateTime($scheduledPostDateTime);

        /*if ($blogPost->isChanged('blog_post_state')) {*/
        /*    $test = 'hello';*/
        /*}*/

        return $creator;
    }
}
