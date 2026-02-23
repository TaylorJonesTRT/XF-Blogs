<?php

namespace TaylorJ\Blogs\Tests\Unit\Entity;

use TaylorJ\Blogs\Tests\TestCase;

class BlogTest extends TestCase
{
	/**
	 * Counter for generating unique blog IDs to avoid entity caching issues.
	 *
	 * @var int
	 */
	protected static $blogIdCounter = 100;

	/**
	 * Create a Blog entity instance with the given column values.
	 *
	 * @param array $values Column values to set on the entity
	 * @return \TaylorJ\Blogs\Entity\Blog
	 */
	protected function makeBlog(array $values = [])
	{
		$defaults = [
			'blog_id' => self::$blogIdCounter++,
			'user_id' => 1,
			'blog_title' => 'Test Blog Title',
			'blog_description' => 'A test blog description',
			'blog_creation_date' => \XF::$time,
			'blog_last_post_date' => 0,
			'blog_has_header' => false,
			'blog_state' => 'visible',
			'blog_post_count' => 0,
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', array_merge($defaults, $values));
	}

	// ---- canView() ----

	public function testCanViewReturnsTrueWhenVisitorHasBothPermissions()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['viewOwn' => true, 'viewAny' => true],
		]);

		$this->assertTrue($this->makeBlog()->canView());
	}

	public function testCanViewReturnsFalseWhenVisitorLacksViewOwn()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['viewOwn' => false, 'viewAny' => true],
		]);

		$this->assertFalse($this->makeBlog()->canView());
	}

	public function testCanViewReturnsFalseWhenVisitorLacksViewAny()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['viewOwn' => true, 'viewAny' => false],
		]);

		$this->assertFalse($this->makeBlog()->canView());
	}

	// ---- canEdit() ----

	public function testCanEditReturnsTrueForOwnerWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canEditOwn' => true],
		], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canEdit());
	}

	public function testCanEditReturnsFalseForOwnerWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canEditOwn' => false],
		], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canEdit());
	}

	public function testCanEditReturnsFalseForNonOwner()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canEditAny' => false],
		], 999);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canEdit());
	}

	// Note: The current canEdit() logic for non-owners with canEditAny=true
	// returns false with an error message. This documents existing behavior.
	public function testCanEditReturnsFalseForNonOwnerWithEditAnyPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canEditAny' => true],
		], 999);

		$error = null;
		$result = $this->makeBlog(['user_id' => 1])->canEdit($error);
		$this->assertFalse($result);
	}

	// ---- canDelete() ----

	public function testCanSoftDeleteReturnsTrueWhenVisitorHasDeleteAny()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canDeleteAny' => true],
		], 999);

		$this->assertTrue($this->makeBlog()->canDelete('soft'));
	}

	public function testCanSoftDeleteReturnsTrueForOwnerWithDeleteOwn()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canDeleteOwn' => true],
		], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canDelete('soft'));
	}

	public function testCanSoftDeleteReturnsFalseForOwnerWithoutDeleteOwn()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canDeleteOwn' => false],
		], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canDelete('soft'));
	}

	public function testCanHardDeleteReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canHardDeleteAny' => true],
		]);

		$this->assertTrue($this->makeBlog()->canDelete('hard'));
	}

	public function testCanHardDeleteReturnsFalseWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canHardDeleteAny' => false],
		]);

		$this->assertFalse($this->makeBlog()->canDelete('hard'));
	}

	// ---- canUndelete() ----

	public function testCanUndeleteReturnsTrueWithUndeleteAny()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canUndeleteAny' => true],
		], 999);

		$this->assertTrue($this->makeBlog()->canUndelete());
	}

	public function testCanUndeleteReturnsTrueForOwnerWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canUndeleteOwnBlog' => true],
		], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canUndelete());
	}

	public function testCanUndeleteReturnsFalseForOwnerWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canUndeleteOwnBlog' => false],
		], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canUndelete());
	}

	// ---- canPost() ----

	public function testCanPostReturnsTrueForOwnerWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canPost' => true],
		], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canPost());
	}

	public function testCanPostReturnsFalseForOwnerWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canPost' => false],
		], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canPost());
	}

	public function testCanPostReturnsFalseForNonOwner()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canPost' => true],
		], 999);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canPost());
	}

	// ---- canWatch() ----

	public function testCanWatchReturnsFalseForOwner()
	{
		$this->mockVisitor([], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canWatch());
	}

	public function testCanWatchReturnsTrueForNonOwner()
	{
		$this->mockVisitor([], 999);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canWatch());
	}

	// ---- canViewScheduledPosts() ----

	public function testCanViewScheduledPostsReturnsTrueForOwner()
	{
		$this->mockVisitor([], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canViewScheduledPosts());
	}

	public function testCanViewScheduledPostsReturnsFalseForNonOwner()
	{
		$this->mockVisitor([], 999);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canViewScheduledPosts());
	}

	// ---- canEditTags() ----

	public function testCanEditTagsReturnsFalseWhenTaggingDisabled()
	{
		$this->setOption('enableTagging', false);
		$this->mockVisitor([
			'taylorjBlogPost' => ['canTagOwnBlogPost' => true],
		]);

		$this->assertFalse($this->makeBlog()->canEditTags());
	}

	public function testCanEditTagsReturnsTrueWithTagOwnPermission()
	{
		$this->setOption('enableTagging', true);
		$this->mockVisitor([
			'taylorjBlogPost' => ['canTagOwnBlogPost' => true],
		]);

		$this->assertTrue($this->makeBlog()->canEditTags());
	}

	public function testCanEditTagsReturnsTrueWithTagAnyPermission()
	{
		$this->setOption('enableTagging', true);
		$this->mockVisitor([
			'taylorjBlogPost' => ['canTagAnyBlogPost' => true],
		]);

		$this->assertTrue($this->makeBlog()->canEditTags());
	}

	public function testCanEditTagsReturnsTrueWithManageAnyTagPermission()
	{
		$this->setOption('enableTagging', true);
		$this->mockVisitor([
			'taylorjBlogPost' => ['canManageAnyTag' => true],
		]);

		$this->assertTrue($this->makeBlog()->canEditTags());
	}

	public function testCanEditTagsReturnsFalseWithoutAnyPermission()
	{
		$this->setOption('enableTagging', true);
		$this->mockVisitor([
			'taylorjBlogPost' => [
				'canTagOwnBlogPost' => false,
				'canTagAnyBlogPost' => false,
				'canManageAnyTag' => false,
			],
		]);

		$this->assertFalse($this->makeBlog()->canEditTags());
	}

	// ---- canSetPublicDeleteReason() ----

	public function testCanSetPublicDeleteReasonReturnsTrueForNonOwner()
	{
		$this->mockVisitor([], 999);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->canSetPublicDeleteReason());
	}

	public function testCanSetPublicDeleteReasonReturnsFalseForOwner()
	{
		$this->mockVisitor([], 1);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canSetPublicDeleteReason());
	}

	public function testCanSetPublicDeleteReasonReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->canSetPublicDeleteReason());
	}

	// ---- canSendModeratorActionAlert() ----

	public function testCanSendModeratorActionAlertReturnsTrueWhenLoggedInAndVisible()
	{
		$this->mockVisitor([], 1);

		$this->assertTrue($this->makeBlog(['blog_state' => 'visible'])->canSendModeratorActionAlert());
	}

	public function testCanSendModeratorActionAlertReturnsFalseWhenDeleted()
	{
		$this->mockVisitor([], 1);

		$this->assertFalse($this->makeBlog(['blog_state' => 'deleted'])->canSendModeratorActionAlert());
	}

	public function testCanSendModeratorActionAlertReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlog(['blog_state' => 'visible'])->canSendModeratorActionAlert());
	}

	// ---- isVisible() ----

	public function testIsVisibleReturnsTrueWhenStateIsVisible()
	{
		$this->assertTrue($this->makeBlog(['blog_state' => 'visible'])->isVisible());
	}

	public function testIsVisibleReturnsFalseWhenStateIsModerated()
	{
		$this->assertFalse($this->makeBlog(['blog_state' => 'moderated'])->isVisible());
	}

	public function testIsVisibleReturnsFalseWhenStateIsDeleted()
	{
		$this->assertFalse($this->makeBlog(['blog_state' => 'deleted'])->isVisible());
	}

	// ---- isOwner() ----

	public function testIsOwnerReturnsTrueWhenVisitorIsOwner()
	{
		$this->mockVisitor([], 1);

		$this->assertTrue($this->makeBlog(['user_id' => 1])->isOwner());
	}

	public function testIsOwnerReturnsFalseWhenVisitorIsNotOwner()
	{
		$this->mockVisitor([], 999);

		$this->assertFalse($this->makeBlog(['user_id' => 1])->isOwner());
	}

	// ---- canApproveUnapprove() ----

	public function testCanApproveUnapproveReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => true],
		], 1);

		$this->assertTrue($this->makeBlog()->canApproveUnapprove());
	}

	public function testCanApproveUnapproveReturnsFalseWithoutPermission()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
		], 1);

		$this->assertFalse($this->makeBlog()->canApproveUnapprove());
	}

	public function testCanApproveUnapproveReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlog()->canApproveUnapprove());
	}

	// ---- getNewContentState() ----

	public function testGetNewContentStateReturnsVisibleForApprovers()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => true],
		], 1);

		$this->assertEquals('visible', $this->makeBlog()->getNewContentState());
	}

	public function testGetNewContentStateReturnsModeratedWithoutSubmitWithoutApproval()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'general' => ['submitWithoutApproval' => false],
		], 1);

		$this->assertEquals('moderated', $this->makeBlog()->getNewContentState());
	}

	public function testGetNewContentStateReturnsModeratedWhenBlogApprovalEnabled()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'general' => ['submitWithoutApproval' => true],
		], 1);

		$this->setOption('taylorjBlogsBlogApproval', true);

		$this->assertEquals('moderated', $this->makeBlog()->getNewContentState());
	}

	public function testGetNewContentStateReturnsVisibleWhenBlogApprovalDisabled()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'general' => ['submitWithoutApproval' => true],
		], 1);

		$this->setOption('taylorjBlogsBlogApproval', false);

		$this->assertEquals('visible', $this->makeBlog()->getNewContentState());
	}

	// ---- canUploadAndManageAttachments() ----

	public function testCanUploadAndManageAttachmentsAlwaysReturnsTrue()
	{
		$this->assertTrue($this->makeBlog()->canUploadAndManageAttachments());
	}
}
