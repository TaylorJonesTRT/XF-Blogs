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
        if (!\XF::visitor()->hasPermission('taylorjBlogs', 'viewOwn'))
        {
            return $this->noPermission(\XF::phrase('permission.taylorjBlogs_viewOwn'));
        }

        $blogFinder = $this->finder('TaylorJ\Blogs:Blog');
        
        if (!\XF::visitor()->hasPermission('taylorjBlogs', 'viewAny'))
        {
            $blogFinder->where('user_id', \XF::visitor()->user_id);
        }

        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')
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
        if (!\XF::visitor()->hasPermission('taylorjBlogs', 'canCreate'))
        {
            return $this->noPermission(\XF::phrase('permission.taylorjBlogs_canCreate'));
        }

        $blog = $this->em()->create('TaylorJ\Blogs:Blog');
        return $this->blogAddEdit($blog);
    }
	
	public function actionEdit(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        
        if (!$blog->canEdit($error))
        {
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
        if ($params->blog_id)
        {
            $blog = $this->assertBlogExists($params->blog_id);

            if (!$blog->canEdit($error))
            {
                return $this->noPermission($error);
            }
        }
        else
        {
            $blog = $this->em()->create('TaylorJ\Blogs:Blog');
        }

        $this->blogSaveProcess($blog)->run();

        if ($upload = $this->request->getFile('upload', false, false)) {
            $this->getBlogRepo()->setBlogHeaderImagePath($blog->blog_id, $upload);
        }

        return $this->redirect($this->buildLink('blogs', $blog));
    }

    protected function blogSaveProcess(\TaylorJ\Blogs\Entity\Blog $blog)
    {
        $input = $this->filter([
            'blog_title' => 'str',
            'blog_description' => 'str',
        ]);

        if ($this->request->getFile('upload', false, false))
        {
            $input['blog_has_header'] = true;
        }

        $form = $this->formAction();
        $form->basicEntitySave($blog, $input);

        return $form;
    }

    public function actionThreadPreview(ParameterBag $params)
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');
		return $this->plugin('XF:BbCodePreview')->actionPreview(
			$message, 'blog-post', \XF::visitor()
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


}