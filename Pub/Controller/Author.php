<?php

namespace TaylorJ\Blogs\Pub\Controller;

use XF\Entity\MemberStat;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use TaylorJ\Blogs\Repository\BlogPost;
use TaylorJ\Blogs\Utils;

class Author extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var \XFRM\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        if (!$visitor->canViewBlogs($error)) {
            throw $this->exception($this->noPermission($error));
        }
    }

    public function actionIndex(ParameterBag $params)
    {
        if ($params->user_id) {
            return $this->rerouteController('TaylorJ\Blogs:Author', 'Author', $params);
        }

        /** @var MemberStat $memberStat */
        $memberStat = $this->em()->findOne('XF:MemberStat', ['member_stat_key' => 'taylorj_blogs_most_blog_posts']);

        if ($memberStat && $memberStat->canView()) {
            return $this->redirectPermanently(
                $this->buildLink('members', null, ['key' => $memberStat->member_stat_key])
            );
        } else {
            return $this->redirect($this->buildLink('blogs'));
        }
    }

    public function actionAuthor(ParameterBag $params)
    {
        $this->assertNotEmbeddedImageRequest();

        /** @var User $user */
        $user = $this->assertRecordExists('XF:User', $params->user_id);

        /** @var ResourceItem $blogPostRepo */
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

        $viewParams = [
            'user' => $user,
            'blogPosts' => $blogPosts,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
        return $this->view('TaylorJ\Blogs:Author\View', 'taylorj_blogs_author_view', $viewParams);
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('xfrm_viewing_resources');
    }
}
