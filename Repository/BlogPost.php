<?php

namespace TaylorJ\Blogs\Repository;

use TaylorJ\Blogs\Entity\BlogPost as BlogPostEntity;
use TaylorJ\Blogs\Entity\BlogPostSimilar;
use TaylorJ\Blogs\Finder\BlogPost as BlogPostFinder;
use XF\Entity\Thread;
use XF\Mvc\Entity\Repository;
use XFES\Search\Query\FunctionOrder;
use XFES\Search\Query\MoreLikeThisQuery;

class BlogPost extends Repository
{
	public function logThreadView(BlogPostEntity $blogPost)
	{
		$this->db()->query("
			INSERT INTO xf_taylorj_blogs_blog_post_view
				(blog_post_id, total)
			VALUES
				(? , 1)
			ON DUPLICATE KEY UPDATE
				total = total + 1
		", $blogPost->blog_post_id);
	}

	public function batchUpdateThreadViews()
	{
		$db = $this->db();
		$db->query("
			UPDATE xf_taylorj_blogs_blog_post AS t
			INNER JOIN xf_taylorj_blogs_blog_post_view AS tv ON (t.blog_post_id = tv.blog_post_id)
			SET t.view_count = t.view_count + tv.total
		");
		$db->emptyTable('xf_taylorj_blogs_blog_post_view');
	}

	public function findBlogPostForThread(Thread $thread)
	{
		/** @var BlogPostFinder $finder */
		$finder = $this->finder('TaylorJ\Blogs:BlogPost');

		$finder->where('discussion_thread_id', $thread->thread_id);

		return $finder;
	}

	public function updateJob($blogPost)
	{
		$jobId = 'taylorjblogs_scheduledpost_' . $blogPost->blog_post_id;
		$db = $this->db();

		$db->query("
			UPDATE xf_job
			SET trigger_date = ?
			WHERE unique_key = ?
		", [$blogPost->scheduled_post_date_time, $jobId]);
	}

	public function removeJob($blogPost)
	{
		$jobId = 'taylorjblogs_scheduledpost_' . $blogPost->blog_post_id;
		/*$db = $this->db();*/
		/**/
		/*$db->query("DELETE FROM xf_job WHERE unique_key = ?", [$jobId]);*/
		$this->app()->jobManager()->cancelUniqueJob($jobId);
	}

	/**
	 * @return ThreadFinder
	 */
	public function findLatestBlogPosts()
	{
		return $this->finder(BlogPostFinder::class)
			->where('blog_post_state', 'visible')
			->order('blog_post_date', 'DESC');
	}

	public function findBlogPostsByUser($userId)
	{
		$blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost')
			->where('user_id', $userId)
			->setDefaultOrder('blog_post_date', 'desc');

		return $blogPostFinder;
	}

	public function findOtherPostsByOwnerRandom($userId)
	{
		/** @var BlogPostFinder $finder */
		$finder = $this->finder('TaylorJ\Blogs:BlogPost');

		$randomBlogPosts = $finder
			->where('user_id', $userId)
			->order($finder->expression('RAND()'));

		return $randomBlogPosts;
	}

	public function getUserBlogPostCount($userId)
	{
		return $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_taylorj_blogs_blog_post
			WHERE user_id = ?
				AND blog_post_state = 'visible'
		", $userId);
	}

	/**
	 * @param BlogPostEntity $blogPost
	 *
	 * @return BlogPostSimilar
	 */
	public function rebuildSimilarBlogPostsCache(BlogPostEntity $blogPost): BlogPostSimilar
	{
		$blogPostIds = $this->getSimilarBlogPostIds(
			$blogPost,
			BlogPostSimilar::MAX_RESULTS,
			false
		);

		/** @var BlogPostSimilar $cache */
		$cache = $blogPost->getRelationOrDefault('SimilarBlogPosts');
		$cache->pending_rebuild = false;
		$cache->last_update_date = \XF::$time;
		$cache->similar_blog_post_ids = $blogPostIds;
		$cache->save(false);

		return $cache;
	}

	/**
	 * @param BlogPostEntity $blogPost
	 * @param int|null          $maxResults
	 * @param bool              $applyVisitorPermissions
	 *
	 * @return int[]
	 */
	public function getSimilarBlogPostIds(
		BlogPostEntity $blogPost,
		$maxResults = null,
		bool $applyVisitorPermissions = true
	): array
	{
		/** @var Search $searcher */
		$searcher = $this->app()->search();

		$results = $searcher->moreLikeThis(
			$this->getSimilarBlogPostsMltQuery($blogPost),
			$maxResults,
			$applyVisitorPermissions
		);

		$threadIds = [];
		foreach ($results AS $result)
		{
			$threadIds[] = $result[1];
		}

		return $threadIds;
	}

	/**
	 * @param BlogPostEntity $blogPost
	 *
	 * @return bool
	 */
	public function flagIfSimilarBlogPostsCacheNeedsRebuild(
		BlogPostEntity $blogPost
	): bool
	{
		/** @var ThreadSimilar $cache */
		$cache = $blogPost->getRelationOrDefault('SimilarBlogPosts');
		if (!$cache->exists())
		{
			$cache->pending_rebuild = true;
			$cache->save();
			return true;
		}

		if ($cache->pending_rebuild)
		{
			return true;
		}

		if ($cache->isRebuildRequired())
		{
			$cache->fastUpdate('pending_rebuild', 1);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param BlogPostEntity $blogPost
	 *
	 * @return MoreLikeThisQuery
	 */
	public function getSimilarBlogPostsMltQuery(
		BlogPostEntity $blogPost
	): MoreLikeThisQuery
	{
		/** @var Search $searcher */
		$searcher = $this->app()->search();

		$query = $searcher->getMoreLikeThisQuery();
		$query
			->like($blogPost)
			->inType('taylorj_blogs_blog_post')
			->allowHidden(false);

		$boost = floatval($this->app()->options()->xfesSimilarThreads['forumBoost']);
		if ($boost > 1)
		{
			$query->orderedBy(new FunctionOrder([
				'filter' => ['term' => ['blogPost' => $blogPost->blog_post_id]],
				'weight' => $boost,
			]));
		}

		return $query;
	}
}
