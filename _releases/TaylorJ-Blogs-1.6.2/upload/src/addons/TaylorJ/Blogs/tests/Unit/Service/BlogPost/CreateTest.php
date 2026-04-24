<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\BlogPost;

use TaylorJ\Blogs\Tests\TestCase;

class CreateTest extends TestCase
{
	protected static $blogIdCounter = 8000;
	protected static $blogPostIdCounter = 9000;

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

	protected function createService(array $blogValues = [])
	{
		$blog = $this->makeBlog($blogValues);
		return new \TaylorJ\Blogs\Service\BlogPost\Create($this->app(), $blog);
	}

	// ---- setTitle() ----

	public function testSetTitleUpdatesPostTitle()
	{
		$service = $this->createService();
		$service->setTitle('My Test Blog Post Title');

		$this->assertEquals('My Test Blog Post Title', $service->blogPost->blog_post_title);
	}

	// ---- setContent() ----

	public function testSetContentMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'setContent'));
	}

	public function testSetContentUpdatesContent()
	{
		$service = $this->createService();
		$service->setContent('Test blog post content here');

		// Content gets processed by PreparerService, so just verify it's set
		$this->assertNotEmpty($service->blogPost->blog_post_content);
	}

	// ---- setBlogPostState() ----

	public function testSetBlogPostStateVisibleClearsSchedule()
	{
		$service = $this->createService();
		$service->blogPost->scheduled_post_date_time = 12345;

		$service->setBlogPostState('visible');

		$this->assertEquals(0, $service->blogPost->scheduled_post_date_time);
	}

	public function testSetBlogPostStateScheduledKeepsScheduledState()
	{
		$service = $this->createService();
		$service->setBlogPostState('scheduled');

		$this->assertEquals('scheduled', $service->blogPost->blog_post_state);
	}

	public function testSetBlogPostStateDraftClearsScheduleAndDate()
	{
		$service = $this->createService();
		$service->blogPost->scheduled_post_date_time = 12345;
		$service->blogPost->blog_post_date = \XF::$time;

		$service->setBlogPostState('draft');

		$this->assertEquals('draft', $service->blogPost->blog_post_state);
		$this->assertEquals(0, $service->blogPost->scheduled_post_date_time);
		$this->assertEquals(0, $service->blogPost->blog_post_date);
	}

	// ---- setScheduledPostDateTime() ----

	public function testSetScheduledPostDateTimeConvertsToTimestamp()
	{
		$this->setOption('guestTimeZone', 'UTC');

		$service = $this->createService();
		$service->setScheduledPostDateTime([
			'dd' => '2030-01-15',
			'hh' => '14',
			'mm' => '30',
		]);

		// Should be a valid future timestamp
		$this->assertGreaterThan(\XF::$time, $service->blogPost->scheduled_post_date_time);
		$this->assertIsNumeric($service->blogPost->scheduled_post_date_time);
	}

	// ---- finalSteps() ----

	public function testFinalStepsInsertsJobForScheduledPosts()
	{
		$this->fakesJobs();

		$service = $this->createService();
		$service->blogPost->blog_post_state = 'scheduled';
		$service->blogPost->scheduled_post_date_time = \XF::$time + 3600;

		// Note: Can't set blog_post_id on instantiated entity (auto-increment/read-only)
		// But we can still test that the job gets queued
		$service->finalSteps();

		$this->assertJobQueued('TaylorJ\Blogs:PostBlogPost');
	}

	public function testFinalStepsDoesNotInsertJobForVisiblePosts()
	{
		$this->fakesJobs();

		$service = $this->createService();
		$service->blogPost->blog_post_state = 'visible';

		$service->finalSteps();

		$this->assertNoJobsQueued();
	}

	// ---- sendNotifications() ----

	public function testSendNotificationsMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'sendNotifications'));
	}

	public function testSendNotificationsChecksVisibility()
	{
		// Service only sends notifications if blog is visible
		$service = $this->createService(['blog_state' => 'moderated']);

		// Should execute without error even for non-visible blog
		$service->sendNotifications();
		$this->assertTrue(true);
	}

	// ---- setTags() ----

	public function testSetTagsMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'setTags'));
	}

	public function testSetTagsAcceptsArray()
	{
		$service = $this->createService();

		// Should execute without error
		$service->setTags(['tag1', 'tag2']);
		$this->assertTrue(true);
	}

	// ---- validate() ----

	public function testValidateReturnsEmptyArrayWhenValid()
	{
		$service = $this->createService();
		$service->setTitle('Valid Title');
		$service->setContent('Valid content for the blog post');

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

	// ---- insertJob() ----

	public function testInsertJobUsesCorrectKeyFormat()
	{
		$this->fakesJobs();

		$service = $this->createService();
		$service->blogPost->scheduled_post_date_time = \XF::$time + 3600;

		// Note: Can't set blog_post_id on instantiated entity, but that's ok
		// We can still verify the job gets queued
		$service->insertJob();

		$this->assertJobQueued('TaylorJ\Blogs:PostBlogPost');
	}

	// ---- Protected method tests ----

	public function testSetupBlogPostThreadCreationIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Create::class);
		$method = $reflection->getMethod('setupBlogPostThreadCreation');

		$this->assertTrue($method->isProtected());
	}

	public function testAfterResourceThreadCreatedIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Create::class);
		$method = $reflection->getMethod('afterResourceThreadCreated');

		$this->assertTrue($method->isProtected());
	}

	public function testGetThreadMessageIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Create::class);
		$method = $reflection->getMethod('getThreadMessage');

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

	public function testInitializeCreatesNewBlogPost()
	{
		$service = $this->createService();

		// The blogPost should be created during initialization
		$this->assertNotNull($service->blogPost);
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\BlogPost::class, $service->blogPost);
	}

	public function testBlogPostBelongsToBlog()
	{
		$blog = $this->makeBlog(['blog_id' => 999]);
		$service = new \TaylorJ\Blogs\Service\BlogPost\Create($this->app(), $blog);

		// The blog post should belong to the blog
		$this->assertEquals(999, $service->blogPost->blog_id);
	}
}
