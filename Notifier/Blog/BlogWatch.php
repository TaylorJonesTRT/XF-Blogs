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
		$finder = $this->app()->finder('TaylorJ\Blogs:BlogWatch');

		$finder->where('blog_id', $blog->blog_id)
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

	public function sendAlert(\XF\Entity\User $user)
	{
		$update = $this->update;
		// $resource = $update->Resource;

		if ($user->user_id == $update->User->user_id) {
			$senderId = $update->User->user_id;
			$senderName = $update->User->username;
		} else {
			$senderId = $update->User->user_id;
			$senderName = $update->User->username;
		}
		
		$blogPost = \XF::finder('TaylorJ\Blogs:BlogPost')
			->where('blog_id', $update->blog_id)
			->order('blog_post_date', 'DESC')
			->fetchOne();

		return $this->basicAlert(
			$user,
			$senderId,
			$senderName,
			'taylorj_blogs_blog',
			$update->blog_id,
			'insert'
		);
	}

}