<?php

namespace TaylorJ\Blogs\Widget;

use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\Utils;
use XF\Http\Request;
use XF\Repository\NodeRepository;

use function in_array;

class OtherAuthorBlogPosts extends AbstractWidget
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
		if ($context == 'options')
		{
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

		/** @var BlogPost $blogPost */
		$blogPost = $this->contextParams['blogPost'] ?? null;
		if (!$blogPost)
		{
			return '';
		}

		$router = $this->app->router('public');

		$blogPostRepo = Utils::getBlogPostRepo();

		switch ($filter)
		{
			default:
			case 'latest':
				$blogPostFinder = $blogPostRepo->findOtherPostsByOwnerRandom($blogPost->user_id);
				$title = \XF::phrase('widget.taylorj_blogs_other_author_blog_posts');
				break;
		}

		foreach ($blogPosts = $blogPostFinder->fetch() AS $blogPostId => $blogPost)
		{
			if (
				!$blogPost->canView()
				|| $visitor->isIgnoring($blogPost->user_id)
			)
			{
				unset($blogPosts[$blogPostId]);
			}
		}
		$total = $blogPosts->count();
		$blogPosts = $blogPosts->slice(0, $limit, true);

		$viewParams = [
			'title' => $this->getTitle() ?: $title,
			'blogPosts' => $blogPosts,
			'style' => $options['style'],
			'filter' => $filter,
			'hasMore' => $total > $blogPosts->count(),
		];
		return $this->renderer('widget_taylorj_blogs_other_blog_posts', $viewParams);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'style' => 'str',
			'filter' => 'str',
			'node_ids' => 'array-uint',
		]);
		if (in_array(0, $options['node_ids']))
		{
			$options['node_ids'] = [0];
		}
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}

		return true;
	}
}
