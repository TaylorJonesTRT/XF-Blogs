<?php

namespace TaylorJ\Blogs\Widget;

use XF\Entity\Thread;
use XF\Http\Request;
use XF\Phrase;
use XF\Repository\NodeRepository;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;
use XFES\Entity\ThreadSimilar;

use function array_slice, count, in_array;

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

		$blogPost = $this->contextParams['taylorj_blogs_blog_post'] ?? null;
		if (!$blogPost)
		{
			return '';
		}

		$cache = $this->getSimilarBlogPostCache($blogPost);
		if (!$cache || !$cache->similar_thread_ids)
		{
			return '';
		}

		$visitor = \XF::visitor();

		$similarThreadIds = array_slice(
			$cache->similar_thread_ids,
			0,
			max($this->options['limit'] * 4, 20)
		);

		$finder = $this->finder('XF:Thread')
			->with('User')
			->with('Forum')
			->with("Forum.Node.Permissions|{$visitor->permission_combination_id}")
			->whereIds($similarThreadIds)
			->where('discussion_state', 'visible')
			->where('discussion_type', '<>', 'redirect')
			->order('thread_id');

		if ($this->options['style'] == 'full')
		{
			$finder->with('fullForum');
		}

		if (
			$this->options['node_ids'] &&
			!in_array(0, $this->options['node_ids'])
		)
		{
			$finder->where('node_id', $this->options['node_ids']);
		}

		if ($this->options['date_limit_days'])
		{
			$finder->where(
				'post_date',
				'>=',
				\XF::$time - ($this->options['date_limit_days'] * 86400)
			);
		}

		$threads = $finder
			->fetch()
			->sortByList($similarThreadIds);

		foreach ($threads AS $threadId => $blogPost)
		{
			/** @var Thread $blogPost */
			if (!$blogPost->canView() || $blogPost->isIgnored())
			{
				unset($threads[$threadId]);
			}
		}
		$threads = $threads->slice(0, $this->options['limit'], true);

		if (!count($threads))
		{
			return '';
		}

		$viewParams = [
			'title' => $this->getTitle(),
			'style' => $this->options['style'],
			'threads' => $threads,
		];
		return $this->renderer('xfes_widget_similar_threads', $viewParams);
	}

	/**
	 * @param Thread $thread
	 *
	 * @return ThreadSimilar|null
	 */
	protected function getSimilarBlogPostCache(Thread $thread)
	{
		$cache = $thread->XFES_SimilarThreads;

		$isRobot = $this->app->request()->getRobotName() ? true : false;
		if ($isRobot)
		{
			return $cache;
		}

		/** @var \XFES\XF\Repository\Thread $threadRepo */
		$threadRepo = $this->repository('XF:Thread');

		if (!$cache)
		{
			try
			{
				$cache = $threadRepo->rebuildSimilarThreadsCache($thread);
			}
			catch (\Exception $e)
			{
				// Intentionally not logging this as it could flood the logs. The rebuild job will trigger
				// some logs if this keep happening.

				/** @var ThreadSimilar $cache */
				$cache = $thread->getRelationOrDefault('XFES_SimilarThreads');
				$cache->reset(); // possible our error occurred during save so wipe out the pending values

				if (!$cache->exists())
				{
					$cache->thread_id = $thread->thread_id;
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

		$threadRepo->flagIfSimilarThreadsCacheNeedsRebuild($thread);
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
			'node_ids' => 'array-uint',
			'date_limit_days' => 'uint',
		]);

		if (in_array(0, $options['node_ids']))
		{
			$options['node_ids'] = [0];
		}

		return true;
	}
}
