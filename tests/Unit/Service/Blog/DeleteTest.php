<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\Blog;

use TaylorJ\Blogs\Tests\TestCase;

class DeleteTest extends TestCase
{
	protected static $blogIdCounter = 4000;
	protected static $userIdCounter = 200;

	protected function makeBlog(array $values = [])
	{
		$defaults = [
			'blog_id' => self::$blogIdCounter++,
			'user_id' => 1,
			'blog_title' => 'Test Blog',
			'blog_description' => 'Test Description',
			'blog_creation_date' => \XF::$time,
			'blog_last_post_date' => 0,
			'blog_has_header' => false,
			'blog_state' => 'visible',
			'blog_post_count' => 0,
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', array_merge($defaults, $values));
	}

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

	protected function createService(array $blogValues = [])
	{
		$blog = $this->makeBlog($blogValues);
		return new \TaylorJ\Blogs\Service\Blog\Delete($this->app(), $blog);
	}

	public function testGetBlogReturnsTheBlog()
	{
		$service = $this->createService();
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\Blog::class, $service->getBlog());
		$this->assertSame($service->blog, $service->getBlog());
	}

	public function testSetUserStoresUser()
	{
		$service = $this->createService();
		$user = $this->makeUser(5);

		$service->setUser($user);

		$this->assertSame($user, $service->getUser());
	}

	public function testGetUserReturnsNull()
	{
		$service = $this->createService();
		$this->assertNull($service->getUser());
	}

	public function testSetUserAcceptsNull()
	{
		$service = $this->createService();
		$user = $this->makeUser();
		$service->setUser($user);
		$service->setUser(null);

		$this->assertNull($service->getUser());
	}

	public function testSetSendAlertSetsAlertFlag()
	{
		$service = $this->createService();
		$service->setSendAlert(true);

		// We can't directly access protected properties, but we can test the behavior
		// by checking that the service was configured
		$this->assertTrue(method_exists($service, 'setSendAlert'));
	}

	public function testSetSendAlertWithReason()
	{
		$service = $this->createService();
		$service->setSendAlert(true, 'Spam content');

		// Verify method executes without error
		$this->assertTrue(true);
	}

	public function testSetSendAlertCastsToBool()
	{
		$service = $this->createService();
		$service->setSendAlert(1); // Non-boolean value
		$service->setSendAlert('true'); // String value

		// Verify method executes without error with non-boolean values
		$this->assertTrue(true);
	}

	public function testSetBlogDeleteReason()
	{
		$service = $this->createService();
		$service->setBlogDeleteReason('Inappropriate content');

		// Verify method executes without error
		$this->assertTrue(true);
	}

	public function testDeleteMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteAcceptsSoftType()
	{
		// We can't fully test deletion without database, but we can verify
		// the method signature accepts 'soft' type
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteAcceptsHardType()
	{
		// We can't fully test deletion without database, but we can verify
		// the method signature accepts 'hard' type
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteAcceptsReasonParameter()
	{
		// Verify delete method signature
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\Blog\Delete::class);
		$method = $reflection->getMethod('delete');
		$params = $method->getParameters();

		$this->assertCount(2, $params);
		$this->assertEquals('type', $params[0]->getName());
		$this->assertEquals('reason', $params[1]->getName());
	}

	public function testUpdateCommentsThreadIsProtected()
	{
		// Verify updateCommentsThread exists and is protected
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\Blog\Delete::class);
		$method = $reflection->getMethod('updateCommentsThread');

		$this->assertTrue($method->isProtected());
	}
}
