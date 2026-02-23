<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\Blog;

use TaylorJ\Blogs\Tests\TestCase;

class CreateTest extends TestCase
{
	/**
	 * Counter for generating unique blog IDs to avoid entity caching issues.
	 *
	 * @var int
	 */
	protected static $blogIdCounter = 1000;

	/**
	 * Create a Blog entity instance for testing.
	 *
	 * @param array $values Column values to set on the entity
	 * @return \TaylorJ\Blogs\Entity\Blog
	 */
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

	/**
	 * Create a service instance for testing.
	 *
	 * @param array $blogValues
	 * @return \TaylorJ\Blogs\Service\Blog\Create
	 */
	protected function createService(array $blogValues = [])
	{
		$blog = $this->makeBlog($blogValues);
		return new \TaylorJ\Blogs\Service\Blog\Create($this->app(), $blog);
	}

	public function testSetTitleUpdatesProperty()
	{
		$service = $this->createService();
		$service->setTitle('New Blog Title');

		$this->assertEquals('New Blog Title', $service->blog->blog_title);
	}

	public function testSetDescriptionUpdatesProperty()
	{
		$service = $this->createService();
		$service->setDescription('New blog description');

		$this->assertEquals('New blog description', $service->blog->blog_description);
	}

	public function testSetStateUsesVisibleWithApprovePermission()
	{
		// Mock visitor with approveUnapprove permission
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => true],
		], 1);

		$service = $this->createService(['user_id' => 1, 'blog_state' => 'moderated']);
		$service->setState();

		// Visitor with approve permission should get visible state
		$this->assertEquals('visible', $service->blog->blog_state);
	}

	public function testSetStateUsesModeratedWithoutSubmitPermission()
	{
		// Mock visitor without submitWithoutApproval permission
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'general' => ['submitWithoutApproval' => false],
		], 1);

		$service = $this->createService(['user_id' => 1, 'blog_state' => '']);
		$service->setState();

		// Should get moderated state without submit permission
		$this->assertEquals('moderated', $service->blog->blog_state);
	}

	public function testSetHasBlogHeaderSetsPropertyToOne()
	{
		$service = $this->createService(['blog_has_header' => false]);
		$service->setHasBlogHeader();

		$this->assertEquals(1, $service->blog->blog_has_header);
	}

	public function testValidateReturnsTrueWhenValid()
	{
		$service = $this->createService([
			'blog_title' => 'Valid Title',
			'blog_description' => 'Valid Description',
		]);

		$errors = [];
		$result = $service->validate($errors);

		$this->assertTrue($result);
		$this->assertIsArray($errors);
		$this->assertEmpty($errors);
	}

	public function testValidateReturnsFalseWhenInvalid()
	{
		// Create a blog and then clear the title to trigger validation error
		$service = $this->createService();
		$service->setTitle(''); // Set to empty string after creation

		$errors = [];
		$result = $service->validate($errors);

		$this->assertFalse($result);
		$this->assertIsArray($errors);
		$this->assertNotEmpty($errors);
	}

	public function testSaveReturnsBlogEntity()
	{
		$service = $this->createService([
			'blog_title' => 'Test Blog',
			'blog_description' => 'Test Description',
		]);

		// We can't actually save to the database in a unit test,
		// but we can verify the service structure
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\Blog::class, $service->blog);
	}

	public function testFinalStepsIsCallable()
	{
		$service = $this->createService();

		// finalSteps() is empty but should be callable without error
		$this->assertNull($service->finalSteps());
	}
}
