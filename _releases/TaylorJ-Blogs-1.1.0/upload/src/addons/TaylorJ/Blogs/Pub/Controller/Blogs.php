<?php

namespace TaylorJ\Blogs\Pub\Controller;

use XF\Mvc\ParameterBag;

use XF\Pub\Controller\AbstractController;

/**
 * Controller for handling the blogs addon instance
 */
class Blogs extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('taylorjBlogs', 'viewBlogs')) {
            return $this->noPermission(\XF::phrase('permission.taylorjBlogs_viewBlogs'));
        }

        if (!$visitor->hasPermission('taylorjBlogs', 'viewOwn')) {
            return $this->noPermission(\XF::phrase('permission.taylorjBlogs_viewOwn'));
        }

        $blogFinder = $this->finder('TaylorJ\Blogs:Blog');

        if (!$visitor->hasPermission('taylorjBlogs', 'viewAny')) {
            $blogFinder
                ->where('user_id', \XF::visitor()->user_id);
        }

        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')
            ->where('blog_state', 'visible')
            ->order('blog_creation_date', 'DESC');

        $page = $params->page;
        $perPage = $this->options()->taylorjBlogsPerPage;
        $blogFinder->limitByPage($page, $perPage);

        $viewParams = [
            'blogs' => $blogFinder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $blogFinder->total()
        ];

        return $this->view('TaylorJ\Blogs:Blogs\Index', 'taylorj_blogs_index', $viewParams);
    }

    public function actionAdd()
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('taylorjBlogs', 'canCreate')) {
            return $this->noPermission(\XF::phrase('permission.taylorjBlogs_canCreate'));
        }

        if ($visitor->taylorj_blogs_blog_count >= $this->options()->taylorjBlogsBlogLimit) {
            return $this->noPermission(\XF::phrase('taylorj_blogs_blog_limit_reached'));
        }

        $blog = $this->em()->create('TaylorJ\Blogs:Blog');
        return $this->blogAddEdit($blog);
    }

    public function actionEdit(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);

        if (!$blog->canEdit($error)) {
            return $this->noPermission($error);
        }

        return $this->blogAddEdit($blog);
    }

    protected function blogAddEdit(\TaylorJ\Blogs\Entity\Blog $blog)
    {
        $viewParams = [
            'blog' => $blog
        ];

        return $this->view('TaylorJ\Blogs:Blog\Edit', 'taylorj_blogs_blog_edit', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        if ($params->blog_id) {
            $blog = $this->assertBlogExists($params->blog_id);

            if (!$blog->canEdit($error)) {
                return $this->noPermission($error);
            }
        } else {
            $blog = $this->em()->create('TaylorJ\Blogs:Blog');
        }


        return $this->blogSaveProcess($blog);
    }

    protected function blogSaveProcess(\TaylorJ\Blogs\Entity\Blog $newBlog)
    {
        $visitor = \XF::visitor();

        $input = $this->filter([
            'blog_title' => 'str',
            'blog_description' => 'str',
        ]);

        /** @var \TaylorJ\Blogs\Service\Blog\Create $creator */
        $creator = $this->blogCreate($newBlog);
        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $this->assertNotFlooding('post');

        /** @var \TaylorJ\Blogs\Entity\Blog $blog */
        $blog = $creator->save();

        if ($upload = $this->request->getFile('upload', false, false)) {
            $this->getBlogRepo()->setBlogHeaderImagePath($blog->blog_id, $upload);
            $blog->fastUpdate('blog_has_header', '1');
        }

        if ($visitor->user_id) {
            if ($blog->blog_state == 'moderated') {
                $this->session()->setHasContentPendingApproval();
            }
        }

        $creator->finalSteps();

        return $this->redirect($this->buildLink('blogs/blog', $blog));
    }

    public function actionThreadPreview(ParameterBag $params)
    {
        $message = $this->plugin('XF:Editor')->fromInput('message');
        return $this->plugin('XF:BbCodePreview')->actionPreview(
            $message,
            'blog-post',
            \XF::visitor()
        );
    }

    /**
     * @param $id
     * @param $with
     * @param $phraseKey
     * @return \TaylorJ\Blogs\Entity\Blog
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertBlogExists($blog_id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('TaylorJ\Blogs:Blog', $blog_id, $with, $phraseKey);
    }

    protected function getBlogRepo()
    {
        /** @var \TaylorJ\Blogs\Repository\Blog $repo */
        $repo = $this->repository('TaylorJ\Blogs:Blog');

        return $repo;
    }

    /**
     * @return \TaylorJ\Blogs\Service\Blog\Create
     */
    protected function blogCreate(\TaylorJ\Blogs\Entity\Blog $newBlog)
    {
        /** @var \TaylorJ\Blogs\Service\Blog\Create $creator */
        $creator = $this->service('TaylorJ\Blogs:Blog\Create', $newBlog);

        $title = $this->filter('blog_title', 'str');
        $blog_description = $this->filter('blog_description', 'str');

        $creator->setTitle($title);
        $creator->setDescription($blog_description);
        $creator->setState();

        return $creator;
    }
}
