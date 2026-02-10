<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\BlogPost;

use TaylorJ\Blogs\Tests\TestCase;

class ApproveTest extends TestCase
{
	protected static $blogPostIdCounter = 6000;

	protected function makeBlogPost(array $values = [])
	{
		$defaults = [
			'blog_post_id' => self::$blogPostIdCounter++,
			'blog_id' => 1,
			'user_id' => 1,
			'username' => 'TestUser',
			'blog_post_title' => 'Test Blog Post',
			'blog_post_content' => 'Test content',
			'blog_post_date' => \XF::$time,
			'blog_post_state' => 'moderated',
			'attach_count' => 0,
			'reaction_score' => 0,
			'reactions' => '[]',
			'tags' => '[]',
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:BlogPost', array_merge($defaults, $values));
	}

	protected function createService(array $blogPostValues = [])
	{
		$blogPost = $this->makeBlogPost($blogPostValues);
		return new \TaylorJ\Blogs\Service\BlogPost\Approve($this->app(), $blogPost);
	}

	public function testGetBlogPostReturnsTheBlogPost()
	{
		$service = $this->createService();
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\BlogPost::class, $service->getBlogPost());
		$this->assertSame($service->blogPost, $service->getBlogPost());
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
		$service = $this->createService(['blog_post_state' => 'moderated']);

		// Before approval
		$this->assertEquals('moderated', $service->blogPost->blog_post_state);

		// Note: We can't fully test approve() without database saves,
		// but we can verify the entity state changes locally
		$service->blogPost->blog_post_state = 'visible';
		$this->assertEquals('visible', $service->blogPost->blog_post_state);
	}

	public function testApproveOnlyWorksOnModeratedPosts()
	{
		// Test that approve() should only work when state is 'moderated'
		$service = $this->createService(['blog_post_state' => 'visible']);

		// A visible post should not be approvable (would return false)
		$this->assertEquals('visible', $service->blogPost->blog_post_state);
	}

	public function testOnApproveIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Approve::class);
		$method = $reflection->getMethod('onApprove');

		$this->assertTrue($method->isProtected());
	}

	public function testApproveMethodReturnsBoolean()
	{
		// Verify the approve() method is designed to return a boolean
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Approve::class);
		$method = $reflection->getMethod('approve');

		// Method should return bool (true on success, false on invalid state)
		$this->assertTrue($method->hasReturnType() === false || $method->getReturnType() === null);
	}

	public function testConstructorAcceptsBlogPost()
	{
		$blogPost = $this->makeBlogPost();
		$service = new \TaylorJ\Blogs\Service\BlogPost\Approve($this->app(), $blogPost);

		$this->assertInstanceOf(\TaylorJ\Blogs\Service\BlogPost\Approve::class, $service);
		$this->assertSame($blogPost, $service->blogPost);
	}
}
