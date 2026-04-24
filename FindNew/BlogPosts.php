<?php

namespace TaylorJ\Blogs\FindNew;

use TaylorJ\Blogs\Finder\BlogPost;
use TaylorJ\Blogs\XF\Entity\User;
use XF\Entity\FindNew;
use XF\FindNew\AbstractHandler;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;

class BlogPosts extends AbstractHandler
{
    public function getRoute()
    {
        return 'whats-new/blog-posts';
    }

    public function getPageReply(Controller $controller, FindNew $findNew, array $results, $page, $perPage)
    {
        $canInlineMod = false;

        $viewParams = [
            'findNew' => $findNew,

            'page' => $page,
            'perPage' => $perPage,

            'blogPosts' => $results,
            'canInlineMod' => $canInlineMod,
        ];
        return $controller->view('TaylorJ\Blogs:WhatsNew\BlogPosts', 'taylorj_blogs_whats_new_blog_posts', $viewParams);
    }

    public function getFiltersFromInput(Request $request)
    {
        $filters = [];

        $visitor = \XF::visitor();

        $watched = $request->filter('watched', 'bool');
        if ($watched && $visitor->user_id)
        {
            $filters['watched'] = true;
        }

        return $filters;
    }

    public function getDefaultFilters()
    {
        return [];
    }

    public function getResultIds(array $filters, $maxResults)
    {
        $visitor = \XF::visitor();

        /** @var BlogPost $finder */
        $finder = \XF::finder('TaylorJ\Blogs:BlogPost');
        $finder
            ->applyGlobalVisibilityChecks()
            ->latestFirst();

        $this->applyFilters($finder, $filters);

        $blogPosts = $finder->fetch($maxResults);
        $blogPosts = $this->filterResults($blogPosts);

        // TODO: consider overfetching or some other permission limits within the query

        return $blogPosts->keys();
    }

    public function getPageResultsEntities(array $ids)
    {
        $visitor = \XF::visitor();

        $ids = array_map('intval', $ids);

        /** @var BlogPost $finder */
        $finder = \XF::finder('TaylorJ\Blogs:BlogPost')
            ->where('blog_post_id', $ids);

        return $finder->fetch();
    }

    protected function filterResults(AbstractCollection $results)
    {
        $visitor = \XF::visitor();

        return $results->filter(function (\TaylorJ\Blogs\Entity\BlogPost $blogPost) use ($visitor)
        {
            return ($blogPost->canView() && !$visitor->isIgnoring($blogPost->user_id));
        });
    }

    protected function applyFilters(BlogPost $finder, array $filters)
    {
        $visitor = \XF::visitor();
        if (!empty($filters['watched']))
        {
            $finder->watchedOnly($visitor->user_id);
        }
    }

    public function getResultsPerPage()
    {
        return \XF::options()->taylorjBlogPostsPerPage;
    }

    public function isAvailable()
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();
        return $visitor->canViewBlogs();
    }
}
