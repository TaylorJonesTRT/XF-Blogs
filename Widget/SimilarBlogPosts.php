<?php

namespace TaylorJ\Blogs\Widget;

use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Entity\BlogPostSimilar;
use XF\Http\Request;
use XF\Phrase;
use XF\Repository\NodeRepository;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

use function array_slice, count;

class SimilarBlogPosts extends AbstractWidget
{
    /**
     * @var array
     */
    protected $defaultOptions = [
        'limit' => 5,
        'style' => 'simple',
        'node_ids' => [],
        'date_limit_days' => 0,
    ];

    /**
     * @param string $context
     *
     * @return array
     */
    protected function getDefaultTemplateParams($context)
    {
        $params = parent::getDefaultTemplateParams($context);

        if ($context == 'options')
        {
            $nodeRepo = $this->app->repository(NodeRepository::class);
            $params['nodeTree'] = $nodeRepo->createNodeTree(
                $nodeRepo->getFullNodeList()
            );
        }

        return $params;
    }

    /**
     * @return WidgetRenderer|string
     */
    public function render()
    {
        $options = $this->app->options();
        $xfesEnabled = $options->xfesEnabled;
        $similarBlogPostOptions = $options->xfesSimilarThreads;
        if (!$xfesEnabled || !$similarBlogPostOptions['widgetEnabled'])
        {
            return '';
        }

        $blogPost = $this->contextParams['blogPost'] ?? null;
        if (!$blogPost)
        {
            return '';
        }

        $cache = $this->getSimilarBlogPostCache($blogPost);
        if (!$cache || !$cache->similar_blog_post_ids)
        {
            return '';
        }

        $visitor = \XF::visitor();

        $similarBlogPostIds = array_slice(
            $cache->similar_blog_post_ids,
            0,
            max($this->options['limit'] * 4, 20)
        );

        $finder = $this->finder('TaylorJ\Blogs:BlogPost')
            ->with('User')
            ->with('Blog')
            ->whereIds($similarBlogPostIds)
            ->where('blog_post_state', 'visible')
            ->order('blog_post_id');

        $blogPosts = $finder
            ->fetch()
            ->sortByList($similarBlogPostIds);

        foreach ($blogPosts AS $blogPostId => $blogPost)
        {
            /** @var BlogPost $blogPost */
            if (!$blogPost->canView())
            {
                unset($blogPosts[$blogPostId]);
            }
        }
        $blogPosts = $blogPosts->slice(0, $this->options['limit'], true);

        if (!count($blogPosts))
        {
            return '';
        }

        $viewParams = [
            'title' => $this->getTitle(),
            'style' => $this->options['style'],
            'blogPosts' => $blogPosts,
        ];
        return $this->renderer('taylorj_blogs_widget_similar_blog_posts', $viewParams);
    }

    /**
     * @param BlogPost $blogPost
     *
     * @return BlogPostSimilar|null
     */
    protected function getSimilarBlogPostCache(BlogPost $blogPost)
    {
        $cache = $blogPost->SimilarBlogPosts;

        $isRobot = $this->app->request()->getRobotName() ? true : false;
        if ($isRobot)
        {
            return $cache;
        }

        /** @var \TaylorJ\Blogs\Repository\BlogPost $blogPostRepo */
        $blogPostRepo = $this->repository('TaylorJ\Blogs:BlogPost');

        if (!$cache)
        {
            try
            {
                $cache = $blogPostRepo->rebuildSimilarBlogPostsCache($blogPost);
            }
            catch (\Exception $e)
            {
                // Intentionally not logging this as it could flood the logs. The rebuild job will trigger
                // some logs if this keep happening.

                /** @var BlogPostSimilar $cache */
                $cache = $blogPost->getRelationOrDefault('SimilarBlogPosts');
                $cache->reset(); // possible our error occurred during save so wipe out the pending values

                if (!$cache->exists())
                {
                    $cache->blog_post_id = $blogPost->blog_post_id;
                }

                $cache->pending_rebuild = true;

                try
                {
                    $cache->save(false);
                }
                catch (\Exception $e)
                {
                    // we got another error, just move on
                    return null;
                }
            }

            return $cache;
        }

        $blogPostRepo->flagIfSimilarBlogPostsCacheNeedsRebuild($blogPost);
        return $cache;
    }

    /**
     * @param Request $request
     * @param array            $options
     * @param Phrase|null  $error
     *
     * @return bool
     */
    public function verifyOptions(
        Request $request,
        array &$options,
        &$error = null
    )
    {
        $options = $request->filter([
            'limit' => 'posint',
            'style' => 'str',
            'date_limit_days' => 'uint',
        ]);

        return true;
    }
}
