# Testing Documentation

This directory contains unit tests for the TaylorJ/Blogs add-on using PHPUnit 12 and hampel/xenforo-test-framework v5 (`^5.41`). Why the suite boots the way it does is in [ADR 0009](../docs/adr/0009-tests-boot-isolated-app-per-test.md).

## Test Structure

```
tests/
├── Feature/
│   └── ClassExtensionsTest.php  # Every class extension this add-on registers still applies
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
│   │   ├── BlogTest.php         # 8 tests for Blog repository (header images via swapFs)
│   │   ├── BlogPostTest.php     # 18 tests for BlogPost repository
│   │   └── BlogWatchTest.php    # 6 tests for BlogWatch repository
│   └── UtilsTest.php            # 65 tests for utility helper methods
├── TestCase.php         # Base test class: XenForo root, add-on isolation, mockVisitor()
└── README.md            # This file
```

## Running Tests

Tests run inside the `xf`-managed Docker container (the database isn't exposed to the host). `xf exec` starts in the XenForo root, so `cd` into the add-on first. The bootstrap resolves XenForo as `../../../..` from the current directory. Xdebug is off by default in the dev container.

### All Tests

```bash
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'
```

**Last run (2026-10-27):** Tests: 273, Assertions: 336, no skips

### Specific Test Suites

```bash
# Entity tests (Blog + BlogPost permissions, state machine)
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit tests/Unit/Entity/'

# Job tests (PostBlogPost scheduled publishing)
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit tests/Unit/Job/'

# Feature suite (class extensions apply)
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit --testsuite Feature'
```

### Single Test File

```bash
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit tests/Unit/Entity/BlogTest.php'
```

### Single Test Method

```bash
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit --filter testCanViewReturnsTrueWhenVisitorHasBothPermissions tests/Unit/Entity/BlogTest.php'
```

## Test Coverage

The per-file counts below are from when each suite was written. See the last run above for the current total.

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

**BlogPost Entity (46 tests)**:
- `getScheduled()` - 3 tests (state machine: scheduled/visible/draft)
- `canView()` - 5 tests (state visibility, permissions for moderated/deleted)
- `canEdit()` - 5 tests (owner vs non-owner, permissions)
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

**PostBlogPost Job**:
- `getStatusMessage()` - 1 test (returns expected string)
- `canCancel()` - 1 test (returns false)
- `canTriggerByChoice()` - 1 test (returns false)
- `run()` - 1 test (completes when blog post not found)

## Test Implementation Notes

### Entity Caching

Entity tests give each entity created via `instantiateEntity()` a unique primary key from a static counter. Each test now gets a fresh app and entity manager, so the counters aren't strictly needed any more. They stay because they're harmless:

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

### XenForo App Per Test

The framework boots a fresh `Hampel\Testing\App` in every test's `setUp()` and destroys it in `tearDown()`. Nothing is shared between tests, so there's no cached app, no reflection to swap it back in, and no entity manager to save and restore. See [ADR 0009](../docs/adr/0009-tests-boot-isolated-app-per-test.md), which supersedes ADR 0007.

`tests/TestCase.php` only sets `$rootDir` and `$addonsToLoad`. There is no `CreatesApplication` trait any more.

### Extension Class Resolution

`$addonsToLoad = ['TaylorJ/Blogs']` loads only this add-on. Class extensions, code event listeners, and Composer autoloaders from other installed add-ons (XFRM and XFMG in the dev container) don't run, so results don't depend on what else is installed. Tests can't catch conflicts with other add-ons as a result. `addon.cache` isn't filtered, so code that checks for another add-on (such as `Job/SimilarBlogPosts` checking for XFES) still sees the real list.

`tests/Feature/ClassExtensionsTest.php` calls `assertAddOnClassExtensionsApply()`, so a XenForo release that renames or removes a class we extend fails the suite instead of silently skipping our code.

Keep a single `$addonsToLoad` in `tests/TestCase.php`. Don't redeclare it in a test class.

### Test Isolation and Mocked State

The base `setUpTraits()` swaps in the framework's testing entity manager for every test, so `mockEntity()`, `mockRepository()`, `mockFinder()`, and `mockService()` work without any `setUp()` of your own. Mocked state dies with the app at the end of the test and can't leak into the next one.

`mockVisitor($permissions, $userId)` calls `actingAsMember()`, which builds a real `XF\Entity\User` in memory and grants the given global permissions in the permission cache. Anything not granted is denied. The framework restores the visitor after each test. The built user is read-only, so set other columns when you build it instead of assigning them afterwards:

```php
$this->actingAsMember(['user_id' => 1, 'timezone' => 'UTC']);
```

### Strict Mode

`phpunit.xml` is the framework's own, with `failOnDeprecation`, `failOnNotice`, `failOnWarning`, `failOnRisky`, and `failOnEmptyTestSuite` on. A new deprecation, notice, warning, or risky test fails the run.

### Filesystem Tests

Blog header image tests call `swapFs('data')`, which mounts an in-memory adapter over `data://` for the rest of the test. Use `assertFsHas()` and `assertFsHasNot()` to check the result. This needs `league/flysystem` 1.x and `league/flysystem-memory` in `require-dev`. `composer.json` ignores Packagist advisory `PKSA-w9tt-7782-78jx`, because XenForo 2.3 ships Flysystem 1.x and there's no patched 1.x release. The exception is dev-only: `build.json` strips `vendor/` and `composer.*` from releases. See [ADR 0009](../docs/adr/0009-tests-boot-isolated-app-per-test.md).

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
4. For service/repository tests: use `mockEntity()`, `mockRepository()`, `mockFinder()`, `mockService()` (no `setUp()` needed)
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
    $service = $this->app()->service('TaylorJ\Blogs:BlogPost\Create', $someBlog);
    $service->setTitle('New Title');
    $this->assertEquals('New Title', $service->blogPost->blog_post_title);
}
```

## Future Improvements

1. **Integration Tests**: Add tests for controller actions (`dispatch()`) and template rendering (`renderTemplate()`)
2. **Coverage Reports**: Generate code coverage with `--coverage-html` flag. `phpunit.xml` already covers the whole add-on
3. **CI Integration**: Set up automated testing on push/PR via GitHub Actions
