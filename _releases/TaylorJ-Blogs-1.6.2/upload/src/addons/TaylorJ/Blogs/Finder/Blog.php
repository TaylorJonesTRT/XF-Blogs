<?php

namespace TaylorJ\Blogs\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\Blog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\Blog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \TaylorJ\Blogs\Entity\Blog|null fetchOne(?int $offset = null)
 * @extends Finder<\TaylorJ\Blogs\Entity\Blog>
 */
class Blog extends Finder
{
	public function visibleBlogs()
	{
		$this->where('blog_state', 'visible');
		return $this;
	}

	public function deletedBlogs()
	{
		$this->where('blog_state', 'deleted');
		$this->with('DeletionLog');
		return $this;
	}

	public function moderatedBlogs()
	{
		$this->where('blog_state', 'moderated');
		return $this;
	}

	/**
	 * Permission-based visibility for blogs.
	 * NOTE: taylorjBlogs has no dedicated viewModerated permission; only deleted
	 * is gated (via canDeleteAny as proxy). Moderated blogs are not surfaced here
	 * — they are handled by the approval queue, not the public listing.
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogs', 'canDeleteAny'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		// No taylorjBlogs.viewModerated permission exists. Moderated blogs visible
		// to the owner only via allowOwnPending.
		if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	/**
	 * If the visitor lacks viewAny, restrict to their own blogs only.
	 * Used in the blog index listing.
	 */
	public function applyOwnershipVisibility()
	{
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('taylorjBlogs', 'viewAny'))
		{
			if ($visitor->user_id)
			{
				$this->where('user_id', $visitor->user_id);
			}
			else
			{
				$this->whereImpossible();
			}
		}
		return $this;
	}

	public function byUser(int $userId)
	{
		$this->where('user_id', $userId);
		return $this;
	}

	public function latestFirst()
	{
		$this->order('blog_last_post_date', 'DESC');
		return $this;
	}

	public function newestFirst()
	{
		$this->order('blog_creation_date', 'DESC');
		return $this;
	}

	public function useDefaultOrder()
	{
		$this->setDefaultOrder('blog_last_post_date', 'desc');
		return $this;
	}
}
