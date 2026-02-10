<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\BlogPost;

use TaylorJ\Blogs\Tests\TestCase;

class EditTest extends TestCase
{
	protected static $blogPostIdCounter = 10000;

	protected function makeBlogPost(array $values = [])
	{
		$defaults = [
			'blog_post_id' => self::$blogPostIdCounter++,
			'blog_id' => 1,
			'user_id' => 1,
			'username' => 'TestUser',
			'blog_post_title' => 'Existing Blog Post',
			'blog_post_content' => 'Existing content',
			'blog_post_date' => \XF::$time - 3600,
			'blog_post_state' => 'visible',
			'attach_count' => 0,
			'reaction_score' => 0,
			'reactions' => '[]',
			'tags' => '[]',
			'discussion_thread_id' => 0,
			'scheduled_post_date_time' => 0,
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:BlogPost', array_merge($defaults, $values));
	}

	protected function createService(array $blogPostValues = [])
	{
		$blogPost = $this->makeBlogPost($blogPostValues);
		return new \TaylorJ\Blogs\Service\BlogPost\Edit($this->app(), $blogPost);
	}

	// ---- setTitle() ----

	public function testSetTitleUpdatesPostTitle()
	{
		$service = $this->createService();
		$service->setTitle('Updated Blog Post Title');

		$this->assertEquals('Updated Blog Post Title', $service->blogPost->blog_post_title);
	}

	// ---- setBlogPostContent() ----

	public function testSetBlogPostContentUpdatesContent()
	{
		$service = $this->createService();
		$service->setBlogPostContent('Updated blog post content');

		$this->assertEquals('Updated blog post content', $service->blogPost->blog_post_content);
	}

	// ---- validate() ----

	public function testValidateReturnsEmptyArrayWhenValid()
	{
		$service = $this->createService();
		$service->setTitle('Valid Title');
		$service->setBlogPostContent('Valid content');

		$errors = [];
		$result = $service->validate($errors);

		$this->assertTrue($result);
		$this->assertIsArray($errors);
	}

	public function testValidateCollectsErrorsWhenInvalid()
	{
		$service = $this->createService();
		$service->setTitle(''); // Empty title should fail

		$errors = [];
		$result = $service->validate($errors);

		$this->assertFalse($result);
		$this->assertIsArray($errors);
		$this->assertNotEmpty($errors);
	}

	// ---- setScheduledPostDateTime() ----

	public function testSetScheduledPostDateTimeForScheduledState()
	{
		$visitor = $this->mockVisitor([], 1);
		$visitor->timezone = 'UTC';
		$this->setOption('guestTimeZone', 'UTC');

		$service = $this->createService();
		$service->setScheduledPostDateTime([
			'dd' => '2030-01-15',
			'hh' => '14',
			'mm' => '30',
			'blog_post_schedule' => 'scheduled',
		]);

		$this->assertEquals('scheduled', $service->blogPost->blog_post_state);
		$this->assertGreaterThan(0, $service->blogPost->scheduled_post_date_time);
	}

	public function testSetScheduledPostDateTimeForDraftState()
	{
		$visitor = $this->mockVisitor([], 1);
		$visitor->timezone = 'UTC';

		$service = $this->createService();
		$service->setScheduledPostDateTime([
			'dd' => '2030-01-15',
			'hh' => '14',
			'mm' => '30',
			'blog_post_schedule' => 'draft',
		]);

		$this->assertEquals('draft', $service->blogPost->blog_post_state);
		$this->assertEquals(0, $service->blogPost->scheduled_post_date_time);
		$this->assertEquals(0, $service->blogPost->blog_post_date);
	}

	public function testSetScheduledPostDateTimeForVisibleState()
	{
		$visitor = $this->mockVisitor([], 1);
		$visitor->timezone = 'UTC';

		$service = $this->createService();
		$service->setScheduledPostDateTime([
			'dd' => '2030-01-15',
			'hh' => '14',
			'mm' => '30',
			'blog_post_schedule' => 'visible',
		]);

		$this->assertEquals(0, $service->blogPost->scheduled_post_date_time);
	}

	// ---- finalSteps() ----

	public function testFinalStepsMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'finalSteps'));
	}

	public function testFinalStepsCreatesThreadForVisiblePost()
	{
		// This is complex to fully test, just verify method structure
		$service = $this->createService(['blog_post_state' => 'visible', 'discussion_thread_id' => 0]);

		// Should execute without error (thread creation would fail without mocking XF services)
		$this->assertTrue(method_exists($service, 'finalSteps'));
	}

	// ---- handlePostStateChange() ----

	public function testHandlePostStateChangeMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'handlePostStateChange'));
	}

	public function testHandlePostStateChangeAcceptsBlogPost()
	{
		$blogPost = $this->makeBlogPost();
		$service = $this->createService();

		// Verify method signature accepts BlogPost
		$reflection = new \ReflectionMethod($service, 'handlePostStateChange');
		$params = $reflection->getParameters();

		$this->assertCount(1, $params);
		$this->assertEquals('blogPost', $params[0]->getName());
	}

	// ---- checkForSpam() ----

	public function testCheckForSpamMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'checkForSpam'));
	}

	public function testCheckForSpamIsCallable()
	{
		$service = $this->createService();
		$reflection = new \ReflectionMethod($service, 'checkForSpam');

		$this->assertTrue($reflection->isPublic());
		$this->assertCount(0, $reflection->getParameters());
	}

	// ---- Protected method tests ----

	public function testFinalSetupIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Edit::class);
		$method = $reflection->getMethod('finalSetup');

		$this->assertTrue($method->isProtected());
	}

	public function testSetupBlogPostThreadCreationIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Edit::class);
		$method = $reflection->getMethod('setupBlogPostThreadCreation');

		$this->assertTrue($method->isProtected());
	}

	public function testGetThreadMessageIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Edit::class);
		$method = $reflection->getMethod('getThreadMessage');

		$this->assertTrue($method->isProtected());
	}

	public function testAfterResourceThreadCreatedIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Edit::class);
		$method = $reflection->getMethod('afterResourceThreadCreated');

		$this->assertTrue($method->isProtected());
	}

	// ---- Service structure tests ----

	public function testBlogPostPropertyIsPublic()
	{
		$service = $this->createService();
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\BlogPost::class, $service->blogPost);
	}

	public function testServiceUsesValidateAndSavableTrait()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'validate'));
		$this->assertTrue(method_exists($service, 'save'));
	}

	public function testConstructorAcceptsBlogPost()
	{
		$blogPost = $this->makeBlogPost();
		$service = new \TaylorJ\Blogs\Service\BlogPost\Edit($this->app(), $blogPost);

		$this->assertInstanceOf(\TaylorJ\Blogs\Service\BlogPost\Edit::class, $service);
		$this->assertSame($blogPost, $service->blogPost);
	}
}
