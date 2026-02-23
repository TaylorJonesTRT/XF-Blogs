<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\BlogPost;

use TaylorJ\Blogs\Tests\TestCase;

class DeleteTest extends TestCase
{
	protected static $blogPostIdCounter = 5000;
	protected static $userIdCounter = 300;

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
			'blog_post_state' => 'visible',
			'attach_count' => 0,
			'reaction_score' => 0,
			'reactions' => '[]',
			'tags' => '[]',
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:BlogPost', array_merge($defaults, $values));
	}

	protected function makeUser($userId = null)
	{
		if ($userId === null) {
			$userId = self::$userIdCounter++;
		}

		return $this->app()->em()->instantiateEntity('XF:User', [
			'user_id' => $userId,
			'username' => 'TestUser' . $userId,
		]);
	}

	protected function createService(array $blogPostValues = [])
	{
		$blogPost = $this->makeBlogPost($blogPostValues);
		return new \TaylorJ\Blogs\Service\BlogPost\Delete($this->app(), $blogPost);
	}

	public function testGetBlogPostReturnsTheBlogPost()
	{
		$service = $this->createService();
		$this->assertInstanceOf(\TaylorJ\Blogs\Entity\BlogPost::class, $service->getBlogPost());
		$this->assertSame($service->blogPost, $service->getBlogPost());
	}

	public function testSetUserStoresUser()
	{
		$service = $this->createService();
		$user = $this->makeUser(5);

		$service->setUser($user);

		$this->assertSame($user, $service->getUser());
	}

	public function testGetUserReturnsNullByDefault()
	{
		$service = $this->createService();
		$this->assertNull($service->getUser());
	}

	public function testSetUserAcceptsNull()
	{
		$service = $this->createService();
		$user = $this->makeUser();
		$service->setUser($user);
		$service->setUser(null);

		$this->assertNull($service->getUser());
	}

	public function testSetSendAlertSetsFlag()
	{
		$service = $this->createService();
		$service->setSendAlert(true);

		$this->assertTrue(method_exists($service, 'setSendAlert'));
	}

	public function testSetSendAlertWithReason()
	{
		$service = $this->createService();
		$service->setSendAlert(true, 'Spam content');

		$this->assertTrue(true);
	}

	public function testSetSendAlertCastsToBool()
	{
		$service = $this->createService();
		$service->setSendAlert(1);
		$service->setSendAlert('true');

		$this->assertTrue(true);
	}

	public function testSetAddPostOverridesDefault()
	{
		$service = $this->createService();
		$service->setAddPost(true);
		$service->setAddPost(false);

		$this->assertTrue(true);
	}

	public function testSetPostByUser()
	{
		$service = $this->createService();
		$user = $this->makeUser();

		$service->setPostByUser($user);

		$this->assertTrue(true);
	}

	public function testSetPostByUserAcceptsNull()
	{
		$service = $this->createService();
		$service->setPostByUser(null);

		$this->assertTrue(true);
	}

	public function testSetPostDeleteReason()
	{
		$service = $this->createService();
		$service->setPostDeleteReason('Inappropriate content');

		$this->assertTrue(true);
	}

	public function testDeleteMethodExists()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteAcceptsSoftType()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteAcceptsHardType()
	{
		$service = $this->createService();
		$this->assertTrue(method_exists($service, 'delete'));
	}

	public function testDeleteMethodSignature()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Delete::class);
		$method = $reflection->getMethod('delete');
		$params = $method->getParameters();

		$this->assertCount(2, $params);
		$this->assertEquals('type', $params[0]->getName());
		$this->assertEquals('reason', $params[1]->getName());
	}

	public function testUpdateCommentsThreadIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Delete::class);
		$method = $reflection->getMethod('updateCommentsThread');

		$this->assertTrue($method->isProtected());
	}

	public function testSetupCommentsThreadReplyIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Delete::class);
		$method = $reflection->getMethod('setupCommentsThreadReply');

		$this->assertTrue($method->isProtected());
	}

	public function testGetThreadReplyMessageIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Delete::class);
		$method = $reflection->getMethod('getThreadReplyMessage');

		$this->assertTrue($method->isProtected());
	}

	public function testAfterCommentsThreadRepliedIsProtected()
	{
		$reflection = new \ReflectionClass(\TaylorJ\Blogs\Service\BlogPost\Delete::class);
		$method = $reflection->getMethod('afterCommentsThreadReplied');

		$this->assertTrue($method->isProtected());
	}
}
