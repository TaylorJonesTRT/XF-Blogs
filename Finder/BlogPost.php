<?php

namespace TaylorJ\Blogs\Finder;

use TaylorJ\Blogs\Entity\Blog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\BlogPost> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\BlogPost> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \TaylorJ\Blogs\Entity\BlogPost|null fetchOne(?int $offset = null)
 * @extends Finder<\TaylorJ\Blogs\Entity\BlogPost>
 */
class BlogPost extends Finder
{
	// -------------------------------------------------------------------------
	// Context methods (mirror XF PostFinder::inThread pattern)
	// -------------------------------------------------------------------------

	/**
	 * Scope to a blog and apply permission-based visibility.
	 * Primary entry-point for listing posts within a specific blog.
	 */
	public function inBlog(Blog $blog, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('blog_id', $blog->blog_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInBlog($limits['allowOwnPending']);
		}

		return $this;
	}

	/**
	 * Per-blog visibility — uses correct taylorjBlogPost permission IDs.
	 * Mirrors XF PostFinder::applyVisibilityChecksInThread().
	 */
	public function applyVisibilityChecksInBlog(bool $allowOwnPending = true)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_post_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_post_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	/**
	 * Global (cross-blog) visibility — used in "What's New", widgets, etc.
	 * Uses correct permission IDs (was broken in original with wrong group name).
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_post_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_post_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	// -------------------------------------------------------------------------
	// State filter methods
	// -------------------------------------------------------------------------

	public function visiblePosts()
	{
		$this->where('blog_post_state', 'visible');
		return $this;
	}

	public function draftPosts()
	{
		$this->where('blog_post_state', 'draft');
		return $this;
	}

	public function scheduledPosts()
	{
		$this->where('blog_post_state', 'scheduled');
		return $this;
	}

	public function deletedPosts()
	{
		$this->where('blog_post_state', 'deleted');
		$this->with('DeletionLog');
		return $this;
	}

	public function moderatedPosts()
	{
		$this->where('blog_post_state', 'moderated');
		return $this;
	}

	// -------------------------------------------------------------------------
	// Context filter methods
	// -------------------------------------------------------------------------

	/** Scope to a blog by ID (when you don't have the entity). */
	public function inBlogId(int $blogId)
	{
		$this->where('blog_id', $blogId);
		return $this;
	}

	public function byUser(int $userId)
	{
		$this->where('user_id', $userId);
		return $this;
	}

	public function forThread(int $threadId)
	{
		$this->where('discussion_thread_id', $threadId);
		return $this;
	}

	// -------------------------------------------------------------------------
	// Ordering methods
	// -------------------------------------------------------------------------

	public function latestFirst()
	{
		$this->order('blog_post_date', 'DESC');
		return $this;
	}

	public function oldestFirst()
	{
		$this->order('blog_post_date', 'ASC');
		return $this;
	}

	public function randomOrder()
	{
		$this->order($this->expression('RAND()'));
		return $this;
	}

	public function useDefaultOrder()
	{
		$this->setDefaultOrder('blog_post_date', 'desc');
		return $this;
	}

	// -------------------------------------------------------------------------
	// Retained from original
	// -------------------------------------------------------------------------

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			return $this;
		}

		$this->whereOr(
			['Watch|' . $userId . '.user_id', '!=', null],
			['BlogPost.Watch|' . $userId . '.user_id', '!=', null]
		);

		return $this;
	}
}
