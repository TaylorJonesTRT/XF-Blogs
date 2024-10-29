<?php

namespace TaylorJ\Blogs\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XFRM\Entity\Category;

/**
 * @method AbstractCollection<\XFRM\Entity\ResourceItem> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\ResourceItem> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\ResourceItem|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\ResourceItem>
 */
class BlogPost extends Finder
{
	public function applyGlobalVisibilityChecks($allowOwnPending = false)
	{
		$conditions = $this->getGlobalVisibilityConditions($allowOwnPending);

		$this->whereOr($conditions);

		return $this;
	}

	public function getGlobalVisibilityConditions(bool $allowOwnPending = false): array
	{
		$visitor = \XF::visitor();
		$conditions = [];

		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogsBlogPost', 'viewDeleted'))
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('taylorjBlogsBlogPost', 'viewModerated'))
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

		return $conditions;
	}

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, just ignore
			return $this;
		}

		$this->whereOr(
			['Watch|' . $userId . '.user_id', '!=', null],
			['BlogPost.Watch|' . $userId . '.user_id', '!=', null]
		);

		return $this;
	}

	/**
	 * @deprecated Use with('full') or with('fullCategory') instead
	 *
	 * @param bool $includeCategory
	 * @return $this
	 */
	public function forFullView($includeCategory = true)
	{
		$this->with($includeCategory ? 'fullCategory' : 'full');

		return $this;
	}

	public function useDefaultOrder()
	{
		$defaultOrder = 'last_update';
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder($defaultOrder, $defaultDir);

		return $this;
	}
}
