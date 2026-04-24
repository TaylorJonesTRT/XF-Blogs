<?php

namespace TaylorJ\Blogs\Tests\Unit\Repository;

use TaylorJ\Blogs\Tests\TestCase;

class BlogWatchTest extends TestCase
{
	protected static $blogIdCounter = 3000;
	protected static $userIdCounter = 100;

	/**
	 * Create a real Blog entity for testing
	 */
	protected function makeBlog($blogId = null)
	{
		if ($blogId === null) {
			$blogId = self::$blogIdCounter++;
		}

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', [
			'blog_id' => $blogId,
			'user_id' => 1,
			'blog_title' => 'Test Blog',
			'blog_description' => 'Test Description',
			'blog_creation_date' => \XF::$time,
			'blog_last_post_date' => 0,
			'blog_has_header' => false,
			'blog_state' => 'visible',
			'blog_post_count' => 0,
		]);
	}

	/**
	 * Create a real User entity for testing
	 */
	protected function makeUser($userId = null)
	{
		if ($userId === null) {
			$userId = self::$userIdCounter++;
		}

		return $this->app()->em()->instantiateEntity('XF:User', [
			'user_id' => $userId,
			'username' => 'TestUser' . $userId,
		]);
	}

	public function testSetWatchStateThrowsExceptionForInvalidBlogId()
	{
		$blog = $this->makeBlog(0); // Invalid blog_id
		$user = $this->makeUser(1);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid blog or user');

		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');
		$repo->setWatchState($blog, $user);
	}

	public function testSetWatchStateThrowsExceptionForInvalidUserId()
	{
		$blog = $this->makeBlog(1);
		$user = $this->makeUser(0); // Invalid user_id

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid blog or user');

		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');
		$repo->setWatchState($blog, $user);
	}

	public function testSetWatchStateWithValidIds()
	{
		$blog = $this->makeBlog(1);
		$user = $this->makeUser(1);

		// This test verifies that setWatchState doesn't throw an exception with valid IDs
		// We can't fully test the database interaction in a unit test, but we can verify
		// the method executes without error
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');

		// The method doesn't return anything, so we just verify it doesn't throw
		$repo->setWatchState($blog, $user);

		// If we get here, the method didn't throw an exception
		$this->assertTrue(true);
	}

	public function testSetWatchStateAcceptsDifferentBlogAndUserIds()
	{
		$blog = $this->makeBlog(5);
		$user = $this->makeUser(10);

		// Verify the method works with different valid IDs
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');
		$repo->setWatchState($blog, $user);

		// If we get here, the method accepted the composite key
		$this->assertTrue(true);
	}

	public function testRepositoryExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');
		$this->assertInstanceOf(\TaylorJ\Blogs\Repository\BlogWatch::class, $repo);
	}

	public function testSetWatchStateMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogWatch');
		$this->assertTrue(method_exists($repo, 'setWatchState'));
	}
}
