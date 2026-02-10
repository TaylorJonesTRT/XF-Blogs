<?php

namespace TaylorJ\Blogs\Tests\Unit\Repository;

use TaylorJ\Blogs\Tests\TestCase;
use Mockery;

class BlogTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpEntityManager();
	}

	// ---- findBlogsByUser() ----

	public function testFindBlogsByUserReturnsFinderForUserId()
	{
		$mockFinder = $this->mockFinder('TaylorJ\Blogs:Blog', function ($mock) {
			$mock->shouldReceive('where')->with('user_id', 42)->andReturnSelf();
			$mock->shouldReceive('setDefaultOrder')->with('blog_last_post_date', 'desc')->andReturnSelf();
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:Blog', function ($mock) use ($mockFinder) {
			$mock->shouldReceive('findBlogsByUser')->with(42)->passthru();
			$mock->shouldReceive('finder')->with('TaylorJ\Blogs:Blog')->andReturn($mockFinder);
		});

		$result = $repo->findBlogsByUser(42);
		$this->assertNotNull($result);
	}

	// ---- deleteBlogHeaderImage() ----

	/**
	 * @skip Skipped due to Flysystem version incompatibility between XenForo (v1) and test framework (v3)
	 */
	public function testDeleteBlogHeaderImageCallsDeleteFromAbstractedPath()
	{
		$this->markTestSkipped('Flysystem version incompatibility - XenForo uses v1, framework requires v3');

		$blog = Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('getAbstractedHeaderImagePath')->andReturn('data://taylorj_blogs/blog_header_images/100.jpg');

		$this->swapFs('data');

		$repo = $this->mockRepository('TaylorJ\Blogs:Blog', function ($mock) {
			$mock->shouldReceive('deleteBlogHeaderImage')->passthru();
		});

		// The method calls File::deleteFromAbstractedPath which will use the swapped filesystem
		// No exception means it ran successfully
		$repo->deleteBlogHeaderImage($blog);
		$this->assertTrue(true);
	}

	// ---- batchUpdateBlogPostCounts() ----

	public function testBatchUpdateBlogPostCountsMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:Blog');
		$this->assertTrue(method_exists($repo, 'batchUpdateBlogPostCounts'));
	}

	public function testBatchUpdateBlogPostCountsIsCallable()
	{
		// We can't fully test without database, but verify it's callable
		$repo = $this->app()->repository('TaylorJ\Blogs:Blog');
		$reflection = new \ReflectionMethod($repo, 'batchUpdateBlogPostCounts');

		$this->assertTrue($reflection->isPublic());
		$this->assertCount(0, $reflection->getParameters());
	}

	// ---- setBlogHeaderImagePath() - SKIP (Flysystem) ----

	public function testSetBlogHeaderImagePathIsSkipped()
	{
		$this->markTestSkipped('setBlogHeaderImagePath() skipped due to Flysystem v1/v3 incompatibility');
	}
}
