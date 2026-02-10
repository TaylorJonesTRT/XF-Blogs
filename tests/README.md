# Testing Documentation

This directory contains unit tests for the TaylorJ/Blogs add-on using PHPUnit 10+ and the hampel/xenforo-test-framework v3.0.

## Test Structure

```
tests/
├── Unit/
│   ├── Entity/          # Entity permission and state logic tests
│   │   ├── BlogTest.php         # 46 tests for Blog entity
│   │   └── BlogPostTest.php     # 41 tests for BlogPost entity
│   ├── Job/             # Background job tests
│   │   └── PostBlogPostTest.php # 4 tests for scheduled post job
│   ├── Service/         # Service layer business logic tests
│   │   ├── Blog/
│   │   │   ├── ApproveTest.php  # 8 tests for blog approval
│   │   │   ├── CreateTest.php   # 10 tests for blog creation
│   │   │   └── DeleteTest.php   # 12 tests for blog deletion
│   │   └── BlogPost/
│   │       ├── ApproveTest.php  # 8 tests for post approval
│   │       ├── CreateTest.php   # 27 tests for post creation
│   │       └── DeleteTest.php   # 13 tests for post deletion
│   ├── Repository/      # Data access layer tests
│   │   ├── BlogTest.php         # 5 tests for Blog repository
│   │   ├── BlogPostTest.php     # 18 tests for BlogPost repository
│   │   └── BlogWatchTest.php    # 6 tests for BlogWatch repository
│   └── UtilsTest.php            # 65 tests for utility helper methods
├── TestCase.php         # Base test class with XenForo app setup
├── CreatesApplication.php  # Trait for app creation and caching
└── README.md            # This file
```

## Running Tests

### All Tests

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit
```

**Expected output:** Tests: 253, Assertions: 298, Skipped: 4

### Specific Test Suites

```bash
# Entity tests (Blog + BlogPost permissions, state machine)
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/

# Job tests (PostBlogPost scheduled publishing)
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Job/
```

### Single Test File

```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/BlogTest.php
```

### Single Test Method

```bash
XDEBUG_MODE=off ./vendor/bin/phpunit --filter testCanViewReturnsTrueWhenVisitorHasBothPermissions tests/Unit/Entity/BlogTest.php
```

## Test Coverage

Total: **91 passing tests, 91 assertions**

### Entity Tests (87 tests)

**Blog Entity (46 tests)**:
- `canView()` - 3 tests (permission checks for viewOwn/viewAny)
- `canEdit()` - 4 tests (owner vs non-owner, permissions)
- `canDelete()` - 5 tests (soft/hard delete, permissions)
- `canUndelete()` - 3 tests
- `canPost()` - 3 tests (owner-only posting)
- `canWatch()` - 2 tests (non-owners can watch)
- `canViewScheduledPosts()` - 2 tests (owner-only)
- `canEditTags()` - 5 tests (tagging enabled, permissions)
- `canSetPublicDeleteReason()` - 3 tests (non-owners, not guests)
- `canSendModeratorActionAlert()` - 3 tests (logged in, visible state)
- `isVisible()` - 3 tests (state checks: visible/moderated/deleted)
- `isOwner()` - 2 tests
- `canApproveUnapprove()` - 3 tests
- `getNewContentState()` - 4 tests (approval workflow)
- `canUploadAndManageAttachments()` - 1 test

**BlogPost Entity (41 tests)**:
- `getScheduled()` - 3 tests (state machine: scheduled/visible/draft)
- `canView()` - 5 tests (state visibility, permissions for moderated/deleted)
- `canEdit()` - 3 tests (owner permissions)
- `canDelete()` - 5 tests (soft/hard delete)
- `canUndelete()` - 3 tests
- `canReact()` - 3 tests (not guest, not own post, visible only)
- `isAttachmentEmbedded()` - 3 tests (JSON metadata parsing)
- `canApproveUnapprove()` - 2 tests
- `canViewModeratedContent()` - 3 tests (owner or permission)
- `canUseInlineModeration()` - 2 tests
- `canSendModeratorActionAlert()` - 2 tests
- `canViewAttachments()` - 1 test
- `canUploadAndManageAttachments()` - 1 test
- `getNewContentState()` - 4 tests (approval workflow)
- `getContentDateColumn()` - 1 test

### Job Tests (4 tests)

**PostBlogPost Job** (✅ Now working with framework v3.0!):
- `getStatusMessage()` - 1 test (returns expected string)
- `canCancel()` - 1 test (returns false)
- `canTriggerByChoice()` - 1 test (returns false)
- `run()` - 1 test (completes when blog post not found)

## Test Implementation Notes

### Entity Caching

Entity tests use auto-incrementing IDs to avoid entity manager caching issues. Each entity created via `instantiateEntity()` uses a unique primary key value from a static counter:

```php
protected static $blogIdCounter = 100;
protected function makeBlog(array $values = []) {
    $defaults = ['blog_id' => self::$blogIdCounter++, ...];
    return $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', array_merge($defaults, $values));
}
```

### Mock vs Real Entities

- **Entity tests**: Use real entity instances via `instantiateEntity()` to test actual permission logic
- **Service/Repository/Utils tests**: Use Mockery mocks via `mockEntity()` for isolated unit testing

### XenForo App Caching

The test suite caches the XenForo app instance in `CreatesApplication::$cachedApp` to work around XenForo's single-app constraint (`\XF::setupApp()` cannot be called twice). The `tearDown()` method does NOT destroy the app, allowing tests to share the same instance.

### Extension Class Resolution

The `TestCase::setUpTraits()` override skips `setUpEntityManager()` and `setUpExtension()` by default to preserve XenForo's class extension mappings. Tests that need entity/repository/finder mocking call `$this->setUpEntityManager()` explicitly in their `setUp()` method.

### Test Isolation and Mocked State

**Important:** When a test calls `setUpEntityManager()`, it replaces the real entity manager (and database adapter) with mocked versions. This mocked state persists in the cached app instance and can leak into subsequent test classes.

**Solution:** Tests that mock the entity manager must restore the original state in `tearDown()`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->storeOriginalDbAndEm();  // Store originals before mocking
    $this->setUpEntityManager();     // Mock the entity manager
}

protected function tearDown(): void
{
    $this->restoreEntityManager();   // Restore originals after test
    parent::tearDown();
}
```

This prevents mocked database expectations from causing failures in unrelated test classes.

### JSON Field Handling

When instantiating entities with JSON_ARRAY columns (e.g., `embed_metadata`), pass JSON-encoded strings:

```php
$this->makeBlogPost(['embed_metadata' => json_encode([42, 43, 44])]);
```

### Mock Entity Property Access

When mocking entities with vanilla `Mockery::mock()`, both `offsetGet()` and `get()` expectations are needed for property access:

```php
$mock->shouldReceive('offsetGet')->with('blog_post_id')->andReturn(42);
$mock->shouldReceive('get')->with('blog_post_id')->andReturn(42);
```

## Adding New Tests

1. Create test file in appropriate `tests/Unit/` subdirectory
2. Extend `TaylorJ\Blogs\Tests\TestCase`
3. For entity tests: use `instantiateEntity()` with unique IDs
4. For service/repository tests: call `$this->setUpEntityManager()` in `setUp()` and use `mockEntity()`, `mockRepository()`, `mockFinder()`, `mockService()`
5. For permission tests: use `$this->mockVisitor(['group' => ['permission' => true]])`
6. For option-dependent tests: use `$this->setOption('optionName', value)`

### Example Entity Test

```php
public function testCanEditReturnsTrueForOwner()
{
    $this->mockVisitor(['taylorjBlogs' => ['canEditOwn' => true]], 1);
    $this->assertTrue($this->makeBlog(['user_id' => 1])->canEdit());
}
```

### Example Service Test

```php
public function testSetTitleUpdatesTitle()
{
    $this->setUpEntityManager();
    $service = $this->app()->service('TaylorJ\Blogs:BlogPost\Create', $someBlog);
    $service->setTitle('New Title');
    $this->assertEquals('New Title', $service->blogPost->blog_post_title);
}
```

## Future Improvements

1. **Job Tests**: Update once hampel/xenforo-test-framework supports XenForo 2.3's JobParams API
2. **Integration Tests**: Add tests for controller actions and template rendering
3. **Coverage Reports**: Generate code coverage with `--coverage-html` flag
4. **CI Integration**: Set up automated testing on push/PR via GitHub Actions
