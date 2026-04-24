<?php

namespace TaylorJ\Blogs\Repository;

use XF\Mvc\Entity\Repository;

class BlogWatch extends Repository
{
	public function setWatchState(\TaylorJ\Blogs\Entity\Blog $blog, \XF\Entity\User $user)
	{
		if (!$blog->blog_id || !$user->user_id)
		{
			throw new \InvalidArgumentException("Invalid blog or user");
		}

		$watch = $this->em->find('TaylorJ\Blogs:BlogWatch', [
			'blog_id' => $blog->blog_id,
			'user_id' => $user->user_id
		]);

        if ($watch)
        {
            return $watch->delete();
        }
        
        /** @var \TaylorJ\Blogs\Entity\BlogWatch $watch */
        $watch = $this->em->create('TaylorJ\Blogs:BlogWatch');
        $watch->blog_id = $blog->blog_id;
        $watch->user_id = $user->user_id;
        
        try
        {
            $watch->save();
        }
        catch (\XF\Db\DuplicateKeyException $e) {}
	}
}