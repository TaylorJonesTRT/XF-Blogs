<?php

namespace TaylorJ\Blogs\Tests\Unit\Entity;

use TaylorJ\Blogs\Tests\TestCase;
use Mockery;

class BlogPostTest extends TestCase
{
	/**
	 * Counter for generating unique blog post IDs to avoid entity caching issues.
	 *
	 * @var int
	 */
	protected static $blogPostIdCounter = 200;

	/**
	 * Create a BlogPost entity instance with the given column values.
	 *
	 * @param array $values Column values to set on the entity
	 * @return \TaylorJ\Blogs\Entity\BlogPost
	 */
	protected function makeBlogPost(array $values = [])
	{
		$defaults = [
			'blog_post_id' => self::$blogPostIdCounter++,
			'user_id' => 1,
			'blog_id' => 100,
			'blog_post_title' => 'A Test Blog Post Title',
			'blog_post_content' => 'Test content for the blog post.',
			'blog_post_date' => \XF::$time,
			'blog_post_last_edit_date' => 0,
			'attach_count' => 0,
			'embed_metadata' => null,
			'view_count' => 0,
			'blog_post_state' => 'visible',
			'scheduled_post_date_time' => null,
			'discussion_thread_id' => 0,
			'tags' => [],
			'reaction_score' => 0,
			'reactions' => [],
			'reaction_users' => [],
		];

		return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:BlogPost', array_merge($defaults, $values));
	}

	// ---- getScheduled() ----

	public function testGetScheduledReturnsTrueWhenStateIsScheduled()
	{
		$this->assertTrue($this->makeBlogPost(['blog_post_state' => 'scheduled'])->getScheduled());
	}

	public function testGetScheduledReturnsFalseWhenStateIsVisible()
	{
		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'visible'])->getScheduled());
	}

	public function testGetScheduledReturnsFalseWhenStateIsDraft()
	{
		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'draft'])->getScheduled());
	}

	// ---- canView() ----

	public function testCanViewReturnsTrueForVisiblePost()
	{
		$this->mockVisitor([], 999);

		$this->assertTrue($this->makeBlogPost(['blog_post_state' => 'visible'])->canView());
	}

	public function testCanViewReturnsFalseForModeratedPostWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canViewModeratedBlogPosts' => false],
		], 999);

		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'moderated'])->canView());
	}

	public function testCanViewReturnsTrueForModeratedPostWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canViewModeratedBlogPosts' => true],
		], 999);

		$this->assertTrue($this->makeBlogPost(['blog_post_state' => 'moderated'])->canView());
	}

	public function testCanViewReturnsFalseForDeletedPostWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canViewDeletedBlogPosts' => false],
		], 999);

		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'deleted'])->canView());
	}

	public function testCanViewReturnsTrueForDeletedPostWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canViewDeletedBlogPosts' => true],
		], 999);

		$this->assertTrue($this->makeBlogPost(['blog_post_state' => 'deleted'])->canView());
	}

	// ---- canEdit() ----

	public function testCanEditReturnsTrueForOwnerWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canEditOwnPost' => true],
		], 1);

		$this->assertTrue($this->makeBlogPost(['user_id' => 1])->canEdit());
	}

	public function testCanEditReturnsFalseForOwnerWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canEditOwnPost' => false],
		], 1);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1])->canEdit());
	}

	public function testCanEditReturnsFalseForNonOwner()
	{
		$this->mockVisitor([
			'taylorjBlogs' => ['canEditAny' => false],
		], 999);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1])->canEdit());
	}

	// ---- canDelete() ----

	public function testCanSoftDeleteReturnsTrueWithDeleteAny()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canDeleteAny' => true],
		], 999);

		$this->assertTrue($this->makeBlogPost()->canDelete('soft'));
	}

	public function testCanSoftDeleteReturnsTrueForOwnerWithDeleteOwn()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canDeleteOwnPost' => true],
		], 1);

		$this->assertTrue($this->makeBlogPost(['user_id' => 1])->canDelete('soft'));
	}

	public function testCanSoftDeleteReturnsFalseForOwnerWithoutDeleteOwn()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canDeleteOwnPost' => false],
		], 1);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1])->canDelete('soft'));
	}

	public function testCanHardDeleteReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canHardDeleteAny' => true],
		]);

		$this->assertTrue($this->makeBlogPost()->canDelete('hard'));
	}

	public function testCanHardDeleteReturnsFalseWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canHardDeleteAny' => false],
		]);

		$this->assertFalse($this->makeBlogPost()->canDelete('hard'));
	}

	// ---- canUndelete() ----

	public function testCanUndeleteReturnsTrueWithUndeleteAny()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canUndeleteAny' => true],
		], 999);

		$this->assertTrue($this->makeBlogPost()->canUndelete());
	}

	public function testCanUndeleteReturnsTrueForOwnerWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['canUndeleteOwnPost' => true],
		], 1);

		$this->assertTrue($this->makeBlogPost(['user_id' => 1])->canUndelete());
	}

	public function testCanUndeleteReturnsFalseWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => [
				'canUndeleteAny' => false,
				'canUndeleteOwnPost' => false,
			],
		], 1);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1])->canUndelete());
	}

	// ---- canReact() ----

	public function testCanReactReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlogPost()->canReact());
	}

	public function testCanReactReturnsFalseForNonVisiblePost()
	{
		$this->mockVisitor([], 999);

		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'draft'])->canReact());
	}

	public function testCanReactReturnsFalseForOwnPost()
	{
		$this->mockVisitor([], 1);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1, 'blog_post_state' => 'visible'])->canReact());
	}

	// ---- isAttachmentEmbedded() ----

	public function testIsAttachmentEmbeddedReturnsFalseWhenNoMetadata()
	{
		$this->assertFalse($this->makeBlogPost(['embed_metadata' => null])->isAttachmentEmbedded(42));
	}

	public function testIsAttachmentEmbeddedReturnsTrueWhenIdInMetadata()
	{
		$this->assertTrue($this->makeBlogPost(['embed_metadata' => json_encode([42, 43, 44])])->isAttachmentEmbedded(42));
	}

	public function testIsAttachmentEmbeddedReturnsFalseWhenIdNotInMetadata()
	{
		$this->assertFalse($this->makeBlogPost(['embed_metadata' => json_encode([42, 43, 44])])->isAttachmentEmbedded(99));
	}

	// ---- canApproveUnapprove() ----

	public function testCanApproveUnapproveReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => true],
		], 1);

		$this->assertTrue($this->makeBlogPost()->canApproveUnapprove());
	}

	public function testCanApproveUnapproveReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlogPost()->canApproveUnapprove());
	}

	// ---- canViewModeratedContent() ----

	public function testCanViewModeratedContentReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['viewModerated' => true],
		], 999);

		$this->assertTrue($this->makeBlogPost()->canViewModeratedContent());
	}

	public function testCanViewModeratedContentReturnsTrueForOwner()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['viewModerated' => false],
		], 1);

		$this->assertTrue($this->makeBlogPost(['user_id' => 1])->canViewModeratedContent());
	}

	public function testCanViewModeratedContentReturnsFalseForNonOwnerWithoutPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['viewModerated' => false],
		], 999);

		$this->assertFalse($this->makeBlogPost(['user_id' => 1])->canViewModeratedContent());
	}

	// ---- canUseInlineModeration() ----

	public function testCanUseInlineModerationReturnsTrueWithPermission()
	{
		$this->mockVisitor([
			'taylorjBlogPost' => ['inlineMod' => true],
		], 1);

		$this->assertTrue($this->makeBlogPost()->canUseInlineModeration());
	}

	public function testCanUseInlineModerationReturnsFalseForGuest()
	{
		$this->mockVisitor([], 0);

		$this->assertFalse($this->makeBlogPost()->canUseInlineModeration());
	}

	// ---- canSendModeratorActionAlert() ----

	public function testCanSendModeratorActionAlertReturnsTrueWhenLoggedInAndVisible()
	{
		$this->mockVisitor([], 1);

		$this->assertTrue($this->makeBlogPost(['blog_post_state' => 'visible'])->canSendModeratorActionAlert());
	}

	public function testCanSendModeratorActionAlertReturnsFalseWhenDeleted()
	{
		$this->mockVisitor([], 1);

		$this->assertFalse($this->makeBlogPost(['blog_post_state' => 'deleted'])->canSendModeratorActionAlert());
	}

	// ---- canViewAttachments() ----

	public function testCanViewAttachmentsAlwaysReturnsTrue()
	{
		$this->assertTrue($this->makeBlogPost()->canViewAttachments());
	}

	// ---- canUploadAndManageAttachments() ----

	public function testCanUploadAndManageAttachmentsAlwaysReturnsTrue()
	{
		$this->assertTrue($this->makeBlogPost()->canUploadAndManageAttachments());
	}

	// ---- getNewContentState() ----

	public function testGetNewContentStateReturnsVisibleForApprovers()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => true],
		], 1);

		$this->assertEquals('visible', $this->makeBlogPost()->getNewContentState());
	}

	public function testGetNewContentStateReturnsModeratedWithoutSubmitWithoutApproval()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'taylorjBlogPost' => ['submitWithoutApproval' => false],
		], 1);

		$this->assertEquals('moderated', $this->makeBlogPost()->getNewContentState());
	}

	public function testGetNewContentStateReturnsModeratedWhenApprovalEnabled()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'taylorjBlogPost' => ['submitWithoutApproval' => true],
		], 1);

		$this->setOption('taylorjBlogsBlogPostApproval', true);

		$this->assertEquals('moderated', $this->makeBlogPost()->getNewContentState());
	}

	public function testGetNewContentStateReturnsVisibleWhenApprovalDisabled()
	{
		$this->mockVisitor([
			'forum' => ['approveUnapprove' => false],
			'taylorjBlogPost' => ['submitWithoutApproval' => true],
		], 1);

		$this->setOption('taylorjBlogsBlogPostApproval', false);

		$this->assertEquals('visible', $this->makeBlogPost()->getNewContentState());
	}

	// ---- getContentDateColumn() ----

	public function testGetContentDateColumnReturnsBlogPostDate()
	{
		$this->assertEquals('blog_post_date', $this->makeBlogPost()->getContentDateColumn());
	}
}
