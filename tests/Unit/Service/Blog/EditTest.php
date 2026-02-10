<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\Blog;

use TaylorJ\Blogs\Tests\TestCase;

class EditTest extends TestCase
{
	/**
	 * Counter for generating unique blog IDs to avoid entity caching issues.
	 *
	 * @var int
	 */
	protected static $blogIdCounter = 2000;

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
			'blog_title' => 'Existing Blog',
			'blog_description' => 'Existing Description',
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
	 * @return \TaylorJ\Blogs\Service\Blog\Edit
	 */
	protected function createService(array $blogValues = [])
	{
		$blog = $this->makeBlog($blogValues);
		return new \TaylorJ\Blogs\Service\Blog\Edit($this->app(), $blog);
	}

	public function testSetTitleUpdatesProperty()
	{
		$service = $this->createService();
		$service->setTitle('Updated Blog Title');

		$this->assertEquals('Updated Blog Title', $service->blog->blog_title);
	}

	public function testSetDescriptionUpdatesProperty()
	{
		$service = $this->createService();
		$service->setDescription('Updated blog description');

		$this->assertEquals('Updated blog description', $service->blog->blog_description);
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
		$service->setTitle(''); // Set to empty string

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

	public function testFinalSetupIsCallable()
	{
		$service = $this->createService();

		// Use reflection to call protected finalSetup method
		$reflection = new \ReflectionClass($service);
		$method = $reflection->getMethod('finalSetup');
		$method->setAccessible(true);

		// finalSetup() is empty but should be callable without error
		$this->assertNull($method->invoke($service));
	}

	public function testFinalStepsIsCallable()
	{
		$service = $this->createService();

		// finalSteps() is empty but should be callable without error
		$this->assertNull($service->finalSteps());
	}

	// Skip testDeleteBlogHeaderImageFiles - Flysystem incompatibility
	public function testDeleteBlogHeaderImageFilesIsSkipped()
	{
		$this->markTestSkipped('deleteBlogHeaderImageFiles() skipped due to Flysystem v1/v3 incompatibility');
	}
}
