<?php

namespace TaylorJ\Blogs\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Blogs extends AbstractController
{
   protected function preDispatchController($action, ParameterBag $params)
   {
    $this->setSectionContext('blogs');
   } 
   
   public function actionIndex(ParameterBag $params)
   {
       if ($params['blog_id'])
       {
        return $this->rerouteController(__CLASS__, 'blog', $params);
       }
       
       $this->setSectionContext('blogs_list');
       
       $page = $this->filterPage();
       $perPage = 20;
       
       $blogFinder = $this->finder('TaylorJ\Blogs:Blog')
           ->setDefaultOrder('blog_last_post_date')
           ->limitByPage($page, $perPage);
       
        $viewParams = [
            'blogs' => $blogFinder->fetch(),
            'total' => $blogFinder->total(),
            'page' => $page,
            'perPage' => $perPage
        ];

       return $this->view('TaylorJ\Blogs:Blogs\Admin', 'taylorj_blogs_admin_index', $viewParams);
   }
   
   public function actionBlog(ParameterBag $params)
   {
       $this->setSectionContext('blogs_blog');

       $page = $this->filterPage();
       $perPage = 20;
       
       $blogPostsFinder = $this->finder('TaylorJ\Blogs:BlogPost')
            ->where('blog_id', $params->blog_id)
            ->limitByPage($page, $perPage);
       $blogFinder = $this->finder('TaylorJ\Blogs:Blog')->where('blog_id', $params->blog_id);

       $viewParams = [
            'blogPosts' => $blogPostsFinder->fetch(),
            'blog' => $blogFinder->fetchOne()
       ];
       return $this->view('TaylorJ\Blogs:Blogs\Admin', 'taylorj_blogs_admin_blog_view', $viewParams);
   }
   
    public function actionBlogDelete(ParameterBag $params)
    {
        // $blog = $this->finder('TaylorJ\Blogs:Blog')
        //     ->where('blog_id', $params->blog_id)
        //     ->fetchOne();
        
        // /** @var \XF\ControllerPlugin\Delete $plugin */
        // $plugin = $this->plugin('XF:Delete');
        // return $plugin->actionDelete(
        //     $blog,
        //     $this->buildLink('blogs/blog', $blog),
        //     $this->buildLink('blogs/blog/edit', $blog),
        //     $this->buildLink('blogs/blog', $blog->blog_id),
        //     $blog->blog_title
        // );

		return $this->getBlogsPlugin()->actionDelete($params);
    }
    
	protected function getBlogsPlugin()
	{
		return $this->plugin('TaylorJ\Blogs:Blog');
	}

}