<?php

namespace TaylorJ\Blogs\Notifier\Blog;

class BlogWatch extends AbstractWatch
{
	protected function getApplicableActionTypes()
	{
		return ['update', 'blogPostWatch', 'newBlogPost'];
	}

	protected function getDefaultWatchNotifyData()
	{
		$update = $this->update;
		$blog = $this->update;

		// Look at any records watching this category or any parent. This will match if the user is watching
		// a parent category with include_children > 0 or if they're watching this category (first whereOr condition).
		$finder = $this->app()->finder('TaylorJ\Blogs:BlogWatch')
			->where('User.user_state', '=', 'valid')
			->where('User.is_banned', '=', 0);

		$activeLimit = $this->app()->options()->watchAlertActiveOnly;
		if (!empty($activeLimit['enabled']))
		{
			$finder->where('User.last_activity', '>=', \XF::$time - 86400 * $activeLimit['days']);
		}

		$notifyData = [];
		foreach ($finder->fetchColumns(['user_id']) AS $watch)
		{
			$notifyData[$watch['user_id']] = [
				'alert' => true,
			];
		}

		return $notifyData;
	}

	protected function getWatchEmailTemplateName()
	{
		return 'xfrm_watched_category_' . ($this->actionType == 'resource' ? 'resource' : 'update');
	}
}