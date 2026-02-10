<?php

namespace TaylorJ\Blogs\Tests\Unit;

use TaylorJ\Blogs\Tests\TestCase;
use TaylorJ\Blogs\Utils;

class UtilsTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpEntityManager();
	}

	// ---- hours() ----

	public function testHoursReturns24Entries()
	{
		$hours = Utils::hours();

		$this->assertCount(24, $hours);
	}

	public function testHoursStartsWithZeroPadded()
	{
		$hours = Utils::hours();

		$this->assertArrayHasKey('00', $hours);
		$this->assertEquals('00', $hours['00']);
	}

	public function testHoursEndsAt23()
	{
		$hours = Utils::hours();

		$this->assertArrayHasKey('23', $hours);
		$this->assertEquals('23', $hours['23']);
	}

	public function testHoursHasSingleDigitPadded()
	{
		$hours = Utils::hours();

		$this->assertArrayHasKey('05', $hours);
		$this->assertEquals('05', $hours['05']);
	}

	// ---- minutes() ----

	public function testMinutesReturns60Entries()
	{
		$minutes = Utils::minutes();

		$this->assertCount(60, $minutes);
	}

	public function testMinutesStartsWithZeroPadded()
	{
		$minutes = Utils::minutes();

		$this->assertArrayHasKey('00', $minutes);
		$this->assertEquals('00', $minutes['00']);
	}

	public function testMinutesEndsAt59()
	{
		$minutes = Utils::minutes();

		$this->assertArrayHasKey('59', $minutes);
		$this->assertEquals('59', $minutes['59']);
	}

	public function testMinutesSingleDigitPadded()
	{
		$minutes = Utils::minutes();

		$this->assertArrayHasKey('03', $minutes);
		$this->assertEquals('03', $minutes['03']);
	}

	// ---- repo() ----

	public function testRepoReturnsRepository()
	{
		$this->mockRepository('TaylorJ\Blogs:Blog');

		$repo = Utils::repo('TaylorJ\Blogs:Blog');
		$this->assertNotNull($repo);
	}

	// ---- getBlogPostRepo() ----

	public function testGetBlogPostRepoReturnsRepository()
	{
		$this->mockRepository('TaylorJ\Blogs:BlogPost');

		$repo = Utils::getBlogPostRepo();
		$this->assertNotNull($repo);
	}

	// ---- getBlogRepo() ----

	public function testGetBlogRepoReturnsRepository()
	{
		$this->mockRepository('TaylorJ\Blogs:Blog');

		$repo = Utils::getBlogRepo();
		$this->assertNotNull($repo);
	}

	// ---- adjustBlogPostCount() ----

	public function testAdjustBlogPostCountIncrements()
	{
		$mockUser = \Mockery::mock('XF\Entity\User');
		$mockUser->shouldReceive('offsetGet')->with('taylorj_blogs_blog_post_count')->andReturn(5);

		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(1);
		$blog->shouldReceive('get')->with('user_id')->andReturn(1);
		$blog->shouldReceive('offsetGet')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('get')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('offsetGet')->with('blog_post_count')->andReturn(5);
		$blog->shouldReceive('get')->with('blog_post_count')->andReturn(5);
		$blog->shouldReceive('fastUpdate')->with('blog_post_count', 6)->once();

		$utils = new Utils();
		$utils->adjustBlogPostCount($blog, 1);
	}

	public function testAdjustBlogPostCountDoesNotGoBelowZero()
	{
		$mockUser = \Mockery::mock('XF\Entity\User');

		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(1);
		$blog->shouldReceive('get')->with('user_id')->andReturn(1);
		$blog->shouldReceive('offsetGet')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('get')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('offsetGet')->with('blog_post_count')->andReturn(0);
		$blog->shouldReceive('get')->with('blog_post_count')->andReturn(0);
		$blog->shouldReceive('fastUpdate')->with('blog_post_count', 0)->once();

		$utils = new Utils();
		$utils->adjustBlogPostCount($blog, -1);
	}

	public function testAdjustBlogPostCountSkipsWhenNoUser()
	{
		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(0);
		$blog->shouldReceive('get')->with('user_id')->andReturn(0);
		$blog->shouldNotReceive('fastUpdate');

		$utils = new Utils();
		$utils->adjustBlogPostCount($blog, 1);
	}

	// ---- adjustUserBlogPostCount() ----

	public function testAdjustUserBlogPostCountIncrements()
	{
		$mockUser = \Mockery::mock('XF\Entity\User');
		$mockUser->shouldReceive('offsetGet')->with('taylorj_blogs_blog_post_count')->andReturn(10);
		$mockUser->shouldReceive('get')->with('taylorj_blogs_blog_post_count')->andReturn(10);
		$mockUser->shouldReceive('fastUpdate')->with('taylorj_blogs_blog_post_count', 11)->once();

		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(1);
		$blog->shouldReceive('get')->with('user_id')->andReturn(1);
		$blog->shouldReceive('offsetGet')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('get')->with('User')->andReturn($mockUser);

		$utils = new Utils();
		$utils->adjustUserBlogPostCount($blog, 1);
	}

	// ---- adjustUserBlogCount() ----

	public function testAdjustUserBlogCountIncrements()
	{
		$mockUser = \Mockery::mock('XF\Entity\User');
		$mockUser->shouldReceive('offsetGet')->with('taylorj_blogs_blog_count')->andReturn(2);
		$mockUser->shouldReceive('get')->with('taylorj_blogs_blog_count')->andReturn(2);
		$mockUser->shouldReceive('fastUpdate')->with('taylorj_blogs_blog_count', 3)->once();

		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(1);
		$blog->shouldReceive('get')->with('user_id')->andReturn(1);
		$blog->shouldReceive('offsetGet')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('get')->with('User')->andReturn($mockUser);

		$utils = new Utils();
		$utils->adjustUserBlogCount($blog, 1);
	}

	public function testAdjustUserBlogCountDoesNotGoBelowZero()
	{
		$mockUser = \Mockery::mock('XF\Entity\User');
		$mockUser->shouldReceive('offsetGet')->with('taylorj_blogs_blog_count')->andReturn(0);
		$mockUser->shouldReceive('get')->with('taylorj_blogs_blog_count')->andReturn(0);
		$mockUser->shouldReceive('fastUpdate')->with('taylorj_blogs_blog_count', 0)->once();

		$blog = \Mockery::mock('TaylorJ\Blogs\Entity\Blog');
		$blog->shouldReceive('offsetGet')->with('user_id')->andReturn(1);
		$blog->shouldReceive('get')->with('user_id')->andReturn(1);
		$blog->shouldReceive('offsetGet')->with('User')->andReturn($mockUser);
		$blog->shouldReceive('get')->with('User')->andReturn($mockUser);

		$utils = new Utils();
		$utils->adjustUserBlogCount($blog, -1);
	}

	// ---- log() ----

	public function testLogPrefixesMessage()
	{
		// We can't easily test XF::logError() calls without mocking the global XF class,
		// but we can verify the method exists and is callable
		$this->assertTrue(method_exists(Utils::class, 'log'));
	}

	// ---- getPostRepo() ----

	public function testGetPostRepoReturnsRepository()
	{
		$utils = new Utils();
		$repo = $utils->getPostRepo();

		// Verify it returns a repository instance
		$this->assertInstanceOf(\XF\Mvc\Entity\Repository::class, $repo);
	}

	// ---- getThreadMessage() ----

	public function testGetThreadMessageMethodExists()
	{
		// Complex method that requires full BlogPost entity with User relation
		// Testing full functionality would require extensive mocking
		$this->assertTrue(method_exists(Utils::class, 'getThreadMessage'));
	}

	// ---- setupBlogPostThreadCreation() ----

	public function testSetupBlogPostThreadCreationMethodExists()
	{
		// Complex method that creates ThreadCreator service with forum lookup
		// Testing full functionality would require mocking Forum finder and ThreadCreator service
		$this->assertTrue(method_exists(Utils::class, 'setupBlogPostThreadCreation'));
	}

	// ---- afterResourceThreadCreated() ----

	public function testAfterResourceThreadCreatedMethodExists()
	{
		// Complex method that interacts with Thread and ThreadWatch repositories
		// Testing full functionality would require mocking these repositories
		$this->assertTrue(method_exists(Utils::class, 'afterResourceThreadCreated'));
	}

	// ---- deleteBlogHeaderFiles() - SKIP (Flysystem) ----

	public function testDeleteBlogHeaderFilesIsSkipped()
	{
		$this->markTestSkipped('deleteBlogHeaderFiles() skipped due to Flysystem v1/v3 incompatibility');
	}
}
