<?php

namespace TaylorJ\Blogs\Pub\Controller;

use TaylorJ\Blogs\Repository\Blog;
use TaylorJ\Blogs\Repository\BlogPost;
use TaylorJ\Blogs\Utils;
use XF\Entity\MemberStat;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Author extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		/** @var \XFRM\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewBlogs($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}

	public function actionIndex(ParameterBag $params)
	{
		if ($params->user_id)
		{
			return $this->rerouteController('TaylorJ\Blogs:Author', 'Author', $params);
		}

		/** @var MemberStat $memberStat */
		$memberStat = $this->em()->findOne('XF:MemberStat', ['member_stat_key' => 'taylorj_blogs_most_blog_posts']);

		if ($memberStat && $memberStat->canView())
		{
			return $this->redirectPermanently(
				$this->buildLink('members', null, ['key' => $memberStat->member_stat_key])
			);
		}
		else
		{
			return $this->redirect($this->buildLink('blogs'));
		}
	}

	public function actionAuthor(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		/** @var User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var BlogPost $blogPostRepo */
		$blogPostRepo = Utils::getBlogPostRepo();
		$finder = $blogPostRepo->findBlogPostsByUser(
			$user->user_id,
		);

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->taylorjBlogPostsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'blogs/authors', $user);
		$this->assertCanonicalUrl($this->buildLink('blogs/authors', $user, ['page' => $page]));

		$blogPosts = $finder->limitByPage($page, $perPage)->fetch();
		$blogPosts = $blogPosts->filterViewable();

		$viewParams = [
			'user' => $user,
			'blogPosts' => $blogPosts,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view('TaylorJ\Blogs:Author\View', 'taylorj_blogs_author_view', $viewParams);
	}

	public function actionOwner(ParameterBag $params)
	{
		$this->assertNotEmbeddedImageRequest();

		/** @var User $user */
		$user = $this->assertRecordExists('XF:User', $params->user_id);

		/** @var Blog $blogRepo */
		$blogRepo = Utils::getBlogRepo();

		$finder = $blogRepo->findBlogsByUser($user->user_id);

		$total = $finder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->taylorjBlogPostsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'blogs/authors/owner', $user);
		$this->assertCanonicalUrl($this->buildLink('blogs/authors/owner', $user, ['page' => $page]));

		$blogs = $finder->limitByPage($page, $perPage)->fetch();

		$viewParams = [
			'user' => $user,
			'blogs' => $blogs,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view('TaylorJ\Blogs:Author\OwnerView', 'taylorj_blogs_author_owner_view', $viewParams);
	}

	public function actionBlogs(ParameterBag $params)
	{
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('xfrm_viewing_resources');
	}
}
