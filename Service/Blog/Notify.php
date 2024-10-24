<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use TaylorJ\Blogs\Entity\BlogPost;
use XF\Service\AbstractNotifier;

class Notify extends AbstractNotifier
{
	/**
	 * @var Blog
	 */
	protected $update;

	/**
	 * @var BlogPost
	 */
	protected $blogPost;

	protected $actionType;

	public function __construct(\XF\App $app, Blog $update, BlogPost $blogPost, $actionType)
	{
		parent::__construct($app);

		switch ($actionType) {
			case 'update':
			case 'newBlogPost':
				break;
			case 'blogPostApproved':
				break;
			default:
				throw new \InvalidArgumentException("Unknown action type '$actionType'");
		}

		$this->actionType = $actionType;
		$this->update = $update;
		$this->blogPost = $blogPost;
	}

	public static function createForJob(array $extraData)
	{
		$update = \XF::app()->find('TaylorJ\Blogs:Blog', $extraData['updateId'], ['Blog']);
		if (!$update) {
			return null;
		}

		return \XF::service('TaylorJ\Blogs:Blog\Notify', $update, $extraData['actionType']);
	}

	protected function getExtraJobData()
	{
		return [
			'updateId' => $this->update->resource_update_id,
			'actionType' => $this->actionType
		];
	}

	protected function loadNotifiers()
	{
		return [
			'blogWatch' => $this->app->notifier('TaylorJ\Blogs:Blog\BlogWatch', $this->update, $this->actionType),
		];
	}

	protected function loadExtraUserData(array $users)
	{
		$permCombinationIds = [];
		foreach ($users as $user) {
			$id = $user->permission_combination_id;
			$permCombinationIds[$id] = $id;
		}

		$this->app->permissionCache()->cacheMultipleContentPermsForContent(
			$permCombinationIds,
			'taylorj_blogs_blog_post',
			$this->update->blog_id
		);
	}

	protected function canUserViewContent(\XF\Entity\User $user)
	{
		return \XF::asVisitor(
			$user,
			function () {
				return $this->update->canView();
			}
		);
	}

	public function notify($timeLimit = null)
	{
		$this->ensureDataLoaded();

		$endTime = $timeLimit > 0 ? microtime(true) + $timeLimit : null;

		foreach ($this->getNotifiers() as $type => $notifier) {
			$data = $this->notifyData[$type];
			if (!$data) {
				// already processed or nothing to do
				continue;
			}

			$newData = $this->notifyType($notifier, $data, $endTime);
			$this->notifyData[$type] = $newData;

			if ($endTime && microtime(true) >= $endTime) {
				break;
			}
		}
	}

	public function notifyAndEnqueue($timeLimit = null)
	{
		$this->notify($timeLimit);
		return $this->enqueueJobIfNeeded();
	}

	protected function notifyType(\XF\Notifier\AbstractNotifier $notifier, array $data, $endTime = null)
	{
		do {
			$notifyUsers = array_slice($data, 0, self::USERS_PER_CYCLE, true);
			$users = $notifier->getUserData(array_keys($notifyUsers));

			$this->loadExtraUserData($users);

			foreach ($notifyUsers as $userId => $notify) {
				unset($data[$userId]);

				if (!isset($users[$userId])) {
					continue;
				}

				$user = $users[$userId];

				if (!$this->canUserViewContent($user) || !$notifier->canNotify($user)) {
					continue;
				}

				$alert = ($notify['alert'] && empty($this->alerted[$userId]));
				if ($alert && $notifier->sendAlert($user)) {
					$this->alerted[$userId] = true;
				}

				if ($endTime && microtime(true) >= $endTime) {
					return $data;
				}
			}
		} while ($data);

		return $data;
	}
}

