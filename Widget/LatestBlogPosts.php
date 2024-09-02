<?php

namespace TaylorJ\Blogs\Widget;

use XF\Entity\Thread;
use XF\Http\Request;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadRepository;

use TaylorJ\Blogs\Utils;

use function in_array;

class LatestBlogPosts extends AbstractWidget
{
    protected $defaultOptions = [
        'limit' => 5,
        'style' => 'simple',
        'filter' => 'latest',
        'node_ids' => [],
    ];

    protected function getDefaultTemplateParams($context)
    {
        $params = parent::getDefaultTemplateParams($context);
        if ($context == 'options') {
            $nodeRepo = $this->app->repository(NodeRepository::class);
            $params['nodeTree'] = $nodeRepo->createNodeTree($nodeRepo->getFullNodeList());
        }
        return $params;
    }

    public function render()
    {
        $visitor = \XF::visitor();

        $options = $this->options;
        $limit = $options['limit'];
        $filter = $options['filter'];
        $nodeIds = $options['node_ids'];

        if (!$visitor->user_id) {
            $filter = 'latest';
        }

        $router = $this->app->router('public');

        /** @var ThreadRepository $threadRepo */
        $threadRepo = $this->repository(ThreadRepository::class);
        $blogPostRepo = Utils::getBlogPostRepo();

        switch ($filter) {
            default:
            case 'latest':
                $blogPostFinder = $blogPostRepo->findLatestBlogPosts();
                $title = \XF::phrase('widget.taylorj_blogs_latest_blog_posts');
                $link = $router->buildLink('blogs', null);
                break;
        }

        /** @var Thread $thread */
        foreach ($blogPosts = $blogPostFinder->fetch() as $blogPostId => $blogPost) {
            if (
                !$blogPost->canView()
                || $visitor->isIgnoring($blogPost->user_id)
            ) {
                unset($blogPosts[$blogPostId]);
            }
        }
        $total = $blogPosts->count();
        $blogPosts = $blogPosts->slice(0, $limit, true);

        $viewParams = [
            'title' => $this->getTitle() ?: $title,
            'link' => $link,
            'blogPosts' => $blogPosts,
            'style' => $options['style'],
            'filter' => $filter,
            'hasMore' => $total > $blogPosts->count(),
        ];
        return $this->renderer('widget_taylorj_blogs_new_blog_posts', $viewParams);
    }

    public function verifyOptions(Request $request, array &$options, &$error = null)
    {
        $options = $request->filter([
            'limit' => 'uint',
            'style' => 'str',
            'filter' => 'str',
            'node_ids' => 'array-uint',
        ]);
        if (in_array(0, $options['node_ids'])) {
            $options['node_ids'] = [0];
        }
        if ($options['limit'] < 1) {
            $options['limit'] = 1;
        }

        return true;
    }
}
