<?php

namespace TaylorJ\Blogs\Tests\Unit\Repository;

use TaylorJ\Blogs\Tests\TestCase;
use Mockery;

class BlogPostTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->storeOriginalDbAndEm();
		$this->setUpEntityManager();
	}

	protected function tearDown(): void
	{
		$this->restoreEntityManager();
		parent::tearDown();
	}

	// ---- findLatestBlogPosts() ----

	public function testFindLatestBlogPostsReturnsFinderForVisiblePosts()
	{
		$mockFinder = $this->mockFinder('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('where')->with('blog_post_state', 'visible')->andReturnSelf();
			$mock->shouldReceive('order')->with('blog_post_date', 'DESC')->andReturnSelf();
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) use ($mockFinder) {
			$mock->shouldReceive('findLatestBlogPosts')->passthru();
			$mock->shouldReceive('finder')->andReturn($mockFinder);
		});

		$result = $repo->findLatestBlogPosts();
		$this->assertNotNull($result);
	}

	// ---- findBlogPostsByUser() ----

	public function testFindBlogPostsByUserReturnsFinderForUserId()
	{
		$mockFinder = $this->mockFinder('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('where')->with('user_id', 42)->andReturnSelf();
			$mock->shouldReceive('setDefaultOrder')->with('blog_post_date', 'desc')->andReturnSelf();
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) use ($mockFinder) {
			$mock->shouldReceive('findBlogPostsByUser')->with(42)->passthru();
			$mock->shouldReceive('finder')->with('TaylorJ\Blogs:BlogPost')->andReturn($mockFinder);
		});

		$result = $repo->findBlogPostsByUser(42);
		$this->assertNotNull($result);
	}

	// ---- findBlogPostForThread() ----

	public function testFindBlogPostForThreadReturnsFinderForThread()
	{
		$thread = Mockery::mock('XF\Entity\Thread');
		$thread->shouldReceive('offsetGet')->with('thread_id')->andReturn(500);
		$thread->shouldReceive('get')->with('thread_id')->andReturn(500);

		$mockFinder = $this->mockFinder('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('where')->with('discussion_thread_id', 500)->andReturnSelf();
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) use ($mockFinder) {
			$mock->shouldReceive('findBlogPostForThread')->passthru();
			$mock->shouldReceive('finder')->with('TaylorJ\Blogs:BlogPost')->andReturn($mockFinder);
		});

		$result = $repo->findBlogPostForThread($thread);
		$this->assertNotNull($result);
	}

	// ---- updateJob() ----

	public function testUpdateJobExecutesQueryWithCorrectParameters()
	{
		$blogPost = Mockery::mock('TaylorJ\Blogs\Entity\BlogPost');
		$blogPost->shouldReceive('offsetGet')->with('blog_post_id')->andReturn(42);
		$blogPost->shouldReceive('get')->with('blog_post_id')->andReturn(42);
		$blogPost->shouldReceive('offsetGet')->with('scheduled_post_date_time')->andReturn(1700000000);
		$blogPost->shouldReceive('get')->with('scheduled_post_date_time')->andReturn(1700000000);

		$this->mockDatabase(function ($mock) {
			$mock->shouldReceive('query')
				->once()
				->withArgs(function ($query, $params) {
					return strpos($query, 'UPDATE xf_job') !== false
						&& $params[0] === 1700000000
						&& $params[1] === 'taylorjblogs_scheduledpost_42';
				});
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('updateJob')->passthru();
			$mock->shouldReceive('db')->andReturn($this->app()->db());
		});

		$repo->updateJob($blogPost);
	}

	// ---- logThreadView() ----

	public function testLogThreadViewInsertsOrUpdatesView()
	{
		$blogPost = Mockery::mock('TaylorJ\Blogs\Entity\BlogPost');
		$blogPost->shouldReceive('offsetGet')->with('blog_post_id')->andReturn(42);
		$blogPost->shouldReceive('get')->with('blog_post_id')->andReturn(42);

		$this->mockDatabase(function ($mock) {
			$mock->shouldReceive('query')
				->once()
				->withArgs(function ($query, $blogPostId) {
					return strpos($query, 'INSERT INTO xf_taylorj_blogs_blog_post_view') !== false
						&& $blogPostId === 42;
				});
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('logThreadView')->passthru();
			$mock->shouldReceive('db')->andReturn($this->app()->db());
		});

		$repo->logThreadView($blogPost);
	}

	// ---- batchUpdateThreadViews() ----

	public function testBatchUpdateThreadViewsExecutesUpdateAndEmptyTable()
	{
		$this->mockDatabase(function ($mock) {
			$mock->shouldReceive('query')
				->once()
				->withArgs(function ($query) {
					return strpos($query, 'UPDATE xf_taylorj_blogs_blog_post') !== false;
				});
			$mock->shouldReceive('emptyTable')
				->with('xf_taylorj_blogs_blog_post_view')
				->once();
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('batchUpdateThreadViews')->passthru();
			$mock->shouldReceive('db')->andReturn($this->app()->db());
		});

		$repo->batchUpdateThreadViews();
	}

	// ---- getUserBlogPostCount() ----

	public function testGetUserBlogPostCountQueriesDatabase()
	{
		$this->mockDatabase(function ($mock) {
			$mock->shouldReceive('fetchOne')
				->once()
				->withArgs(function ($query, $userId) {
					return strpos($query, 'SELECT COUNT(*)') !== false
						&& $userId === 42;
				})
				->andReturn(5);
		});

		$repo = $this->mockRepository('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('getUserBlogPostCount')->passthru();
			$mock->shouldReceive('db')->andReturn($this->app()->db());
		});

		$result = $repo->getUserBlogPostCount(42);
		$this->assertEquals(5, $result);
	}

	// ---- Additional finder methods ----

	public function testFindOtherPostsByOwnerRandomMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'findOtherPostsByOwnerRandom'));
	}

	public function testFindBlogPostAuthorMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'findBlogPostAuthor'));
	}

	// ---- removeJob() ----

	public function testRemoveJobMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'removeJob'));
	}

	public function testRemoveJobAcceptsBlogPost()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$reflection = new \ReflectionMethod($repo, 'removeJob');
		$params = $reflection->getParameters();

		$this->assertCount(1, $params);
	}

	// ---- sendModeratorActionAlert() ----

	public function testSendModeratorActionAlertMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'sendModeratorActionAlert'));
	}

	public function testSendModeratorActionAlertAcceptsThreeParameters()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$reflection = new \ReflectionMethod($repo, 'sendModeratorActionAlert');
		$params = $reflection->getParameters();

		$this->assertGreaterThanOrEqual(2, count($params));
	}

	// ---- XFES Similar Posts Methods ----

	public function testRebuildSimilarBlogPostsCacheMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'rebuildSimilarBlogPostsCache'));
	}

	public function testGetSimilarBlogPostIdsMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'getSimilarBlogPostIds'));
	}

	public function testGetSimilarBlogPostsMltQueryMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'getSimilarBlogPostsMltQuery'));
	}

	public function testFlagIfSimilarBlogPostsCacheNeedsRebuildMethodExists()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertTrue(method_exists($repo, 'flagIfSimilarBlogPostsCacheNeedsRebuild'));
	}

	// ---- Repository structure tests ----

	public function testRepositoryExtendsBaseRepository()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertInstanceOf(\XF\Mvc\Entity\Repository::class, $repo);
	}

	public function testRepositoryIdentifier()
	{
		$repo = $this->app()->repository('TaylorJ\Blogs:BlogPost');
		$this->assertInstanceOf(\TaylorJ\Blogs\Repository\BlogPost::class, $repo);
	}
}
