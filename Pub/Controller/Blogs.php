<?php

namespace TaylorJ\UserBlogs\Pub\Controller;

use XF\Mvc\ParameterBag;

use XF\Pub\Controller\AbstractController;

/**
    * Controller for handling the blogs addon instance
*/
class Blogs extends AbstractController
{
    public function actionIndex()
    {
        $blogFinder = $this->finder('TaylorJ\UserBlogs:Blog')
            ->order('blog_creation_date', 'DESC');

        $viewParams = [
            'blogs' => $blogFinder->fetch()
        ];

        return $this->view('TaylorJ\UserBlogs:Blogs\Index', 'taylorj_userblogs_blogs_index', $viewParams);
    }

	public function actionAdd()
    {
        $blog = $this->em()->create('TaylorJ\UserBlogs:Blog');
        return $this->blogAddEdit($blog);
    }
	
	public function actionEdit(ParameterBag $params)
    {
        $blog = $this->assertBlogExists($params->blog_id);
        return $this->blogAddEdit($blog);
    }
	
	protected function blogAddEdit(\TaylorJ\UserBlogs\Entity\Blog $blog)
    {
        $viewParams = [
            'blog' => $blog
        ];

        return $this->view('TaylorJ\UserBlogs:Blog\Edit', 'taylorj_userblogs_blog_edit', $viewParams);
    }
	
	public function actionSave(ParameterBag $params)
    {
        if ($params->id)
        {
            $blog = $this->assertBlogExists($params->blog_id);
        }
        else
        {
            $blog = $this->em()->create('TaylorJ\UserBlogs:Blog');
        }

        $this->blogSaveProcess($blog)->run();

        if ($upload = $this->request->getFile('upload', false, false)) {
            $this->getBlogRepo()->setBlogHeaderImagePath($blog->blog_id, $upload);
        }

        return $this->redirect($this->buildLink('userblogs', $blog));
    }

    protected function blogSaveProcess(\TaylorJ\UserBlogs\Entity\Blog $blog)
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
     * @return \TaylorJ\UserBlogs\Entity\Blog
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertBlogExists($blog_id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('TaylorJ\UserBlogs:Blog', $blog_id, $with, $phraseKey);
    }

    protected function getBlogRepo()
    {
        /** @var \TaylorJ\UserBlogs\Repository\Blog $repo */
        $repo = $this->repository('TaylorJ\UserBlogs:Blog');

        return $repo;
    }


}