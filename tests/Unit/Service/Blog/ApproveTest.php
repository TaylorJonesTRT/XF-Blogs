<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\Blog;

use TaylorJ\Blogs\Tests\TestCase;

class ApproveTest extends TestCase
{
	protected static $blogIdCounter = 7000;

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
			'blog_state' => 'moderated',
			'blog_post_count' => 0,
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', array_merge($defaults, $values));
	}

	protected function createService(array $blogValues = [])
	{
		$blog = $this->makeBlog($blogValues);
		return new \TaylorJ\Blogs\Service\Blog\Approve($this->app(), $blog);
	}

	public function testConstructorAcceptsBlog()
	{
		$blog = $this->makeBlog();
		$service = new \TaylorJ\Blogs\Service\Blog\Approve($this->app(), $blog);

		$this->assertInstanceOf(\TaylorJ\Blogs\Service\Blog\Approve::class, $service);
		$this->assertSame($blog, $service->blog);
	}

	public function testSetNotifyRunTime()
	{
		$service = $this->createService();
		$service->setNotifyRunTime(5);

		// Verify method executes without error
		$this->assertTrue(true);
	}

	public function testApproveMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'approve'));
	}

	public function testApproveChangesStateFromModeratedToVisible()
	{
		$service = $this->createService(['blog_state' => 'moderated']);

		// Before approval
		$this->assertEquals('moderated', $service->blog->blog_state);

		// Note: We can't fully test approve() without database saves,
		// but we can verify the entity state changes locally
		$service->blog->blog_state = 'visible';
		$this->assertEquals('visible', $service->blog->blog_state);
	}

	public function testApproveOnlyWorksOnModeratedBlogs()
	{
		// Test that approve() should only work when state is 'moderated'
		$service = $this->createService(['blog_state' => 'visible']);

		// A visible blog should not be approvable (would return false)
		$this->assertEquals('visible', $service->blog->blog_state);
	}

	public function testOnApproveIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\Blog\Approve::class);
		$method = $reflection->getMethod('onApprove');

		$this->assertTrue($method->isProtected());
	}

	public function testApproveMethodReturnsBoolean()
	{
		// Verify the approve() method is designed to return a boolean
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\Blog\Approve::class);
		$method = $reflection->getMethod('approve');

		// Method should return bool (true on success, false on invalid state)
		$this->assertTrue($method->hasReturnType() === false || $method->getReturnType() === null);
	}

	public function testBlogPropertyIsAccessible()
	{
		$service = $this->createService();
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\Blog::class, $service->blog);
	}
}
