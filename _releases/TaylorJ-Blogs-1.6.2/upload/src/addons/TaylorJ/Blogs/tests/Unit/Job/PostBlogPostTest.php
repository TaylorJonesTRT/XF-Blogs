<?php

namespace TaylorJ\Blogs\Tests\Unit\Job;

use TaylorJ\Blogs\Tests\TestCase;
use Mockery;

class PostBlogPostTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->setUpEntityManager();
	}

	// ---- getStatusMessage() ----

	public function testGetStatusMessageReturnsExpectedString()
	{
		$job = $this->createJobInstance(['blog_post_id' => 1]);

		$this->assertEquals('Posting blog...', $job->getStatusMessage());
	}

	// ---- canCancel() ----

	public function testCanCancelReturnsFalse()
	{
		$job = $this->createJobInstance(['blog_post_id' => 1]);

		$this->assertFalse($job->canCancel());
	}

	// ---- canTriggerByChoice() ----

	public function testCanTriggerByChoiceReturnsFalse()
	{
		$job = $this->createJobInstance(['blog_post_id' => 1]);

		$this->assertFalse($job->canTriggerByChoice());
	}

	// ---- run() ----

	public function testRunCompletesWhenBlogPostNotFound()
	{
		$this->fakesErrors();

		$mockFinder = $this->mockFinder('TaylorJ\Blogs:BlogPost', function ($mock) {
			$mock->shouldReceive('where')->with('blog_post_id', 999)->andReturnSelf();
			$mock->shouldReceive('fetchOne')->andReturn(null);
		});

		$job = $this->createJobInstance(['blog_post_id' => 999]);
		$result = $job->run(30);

		$this->assertNotNull($result);
	}

	/**
	 * Helper to create a PostBlogPost job instance.
	 *
	 * @param array $data
	 * @return \TaylorJ\Blogs\Job\PostBlogPost
	 */
	protected function createJobInstance(array $data = [])
	{
		return new \TaylorJ\Blogs\Job\PostBlogPost(
			$this->app(),
			1, // job ID
			$data
		);
	}
}
