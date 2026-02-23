# Implementation Plan: Unit Testing for TaylorJ/Blogs Add-on

## Context

The TaylorJ/Blogs add-on currently lacks automated testing, making it difficult to verify that changes don't introduce regressions or bugs. Unit tests will provide confidence when refactoring, adding features, or fixing bugs by ensuring that critical business logic continues to work as expected.

This implementation follows the official XenForo unit testing tutorial (https://xenforo.com/community/resources/unit-testing-xenforo-addons-tutorial.7508/), which provides a comprehensive framework for testing XenForo 2.3 add-ons using PHPUnit and the hampel/xenforo-test-framework package.

**Current State:**
- Composer dependencies already installed (PHPUnit 9.5, hampel/xenforo-test-framework v2.0)
- Empty `tests/` directory exists (only `.gitkeep` file)
- No test infrastructure (phpunit.xml, base test classes, or actual tests)
- Build configuration needs updates to exclude test files from releases

**Why This Matters:**
The add-on has complex business logic including:
- Permission system with multiple `can*()` methods across entities
- State machines for blogs (visible/moderated/deleted) and posts (visible/scheduled/draft/moderated/deleted)
- Scheduled post publishing with timezone handling
- Thread creation workflow for blog post comments
- Notification system with permission filtering
- View count tracking with batch updates
- Similar posts caching via XFES integration

These critical components need test coverage to prevent regressions.

## Implementation Approach

The implementation will be phased, starting with infrastructure setup and then incrementally adding tests for the most critical components. We'll prioritize high-value, high-risk code: entity permissions, state machines, and service business logic.

### Phase 1: Test Infrastructure Setup

**Objective:** Establish the foundation for writing and running tests.

**Files to Create:**

1. **`phpunit.xml`** - PHPUnit configuration
   - Define test suites (Unit, Feature)
   - Configure code coverage for Entity/, Service/, Repository/ directories
   - Exclude vendor, tests, _output, _data from coverage
   - Set bootstrap to vendor/autoload.php

2. **`tests/TestCase.php`** - Base test class for all tests
   - Extend `Hampel\Testing\TestCase`
   - Use `CreatesApplication` trait
   - Set `$rootDir = '../../../..'` (path to XenForo root)
   - Add helper method `getMockData($file)` to load mock data files
   - Add helper method `createTestUser($permissions)` to create users with specific permissions

3. **`tests/CreatesApplication.php`** - Trait for XenForo app bootstrapping
   - Require XF.php and start XenForo
   - Return testing app instance (`Hampel\Testing\App`)
   - Support loading specific add-ons via `$addonsToLoad` property

4. **Directory structure:**
   ```
   tests/
   ├── Unit/
   │   ├── Entity/
   │   ├── Service/
   │   ├── Repository/
   │   └── Utils/
   ├── Feature/
   ├── mock/
   │   └── .gitkeep
   ├── TestCase.php
   └── CreatesApplication.php
   ```

5. **`tests/README.md`** - Testing documentation
   - How to run tests (./vendor/bin/phpunit)
   - Test naming conventions
   - Available test helpers and mocking utilities
   - Example test structure
   - Debugging tips

**Files to Modify:**

1. **`build.json`** - Add exclusions for test files
   - Add `"exclude"` array with test files/directories
   - Exclude: tests/, phpunit.xml, .phpunit.result.cache, vendor testing packages
   - Keep existing `exec` section for composer install --no-dev

2. **`CLAUDE.md`** - Add testing section to documentation
   - Add "Testing" section with commands for running tests
   - Document test structure and locations
   - Reference tests/README.md for detailed docs

**Verification Steps:**
- Run `./vendor/bin/phpunit --list-tests` to confirm PHPUnit discovers test structure
- Verify build process excludes test files (check generated build output)

### Phase 2: Entity Tests - Blog Entity

**Objective:** Test Blog entity's permission system, state management, and validation logic.

**File to Create:** `tests/Unit/Entity/BlogTest.php`

**Test Coverage (approximately 25-30 tests):**

1. **Permission Tests** (highest priority)
   - `canView()` - Test viewOwn/viewAny permissions for owner and non-owner
   - `canEdit()` - Test canEditOwn for owner, canEditAny for moderators
   - `canDelete()` - Test soft/hard delete permissions for owner and moderators
   - `canUndelete()` - Test undelete permissions
   - `canPost()` - Only blog owner can create posts
   - `canWatch()` - Owner cannot watch own blog, others can
   - `canViewScheduledPosts()` - Only owner can view scheduled posts
   - `canEditTags()` - Test tag editing permissions
   - `canSetPublicDeleteReason()` - Non-owner moderators can set delete reasons

2. **State Management Tests**
   - `isVisible()` - Visible state detection
   - `isOwner()` - Owner verification
   - `getNewContentState()` - Content state for approvers vs regular users

3. **Validation Tests**
   - `verifyTitle()` - Reject titles < 10 chars, accept valid titles, test capitalization

4. **Business Logic Tests**
   - Soft delete sets blog_state to 'deleted'
   - Blog approval cascades to all blog posts

**Mocking Strategy:**
- Mock `\XF::visitor()` for permission checks
- Mock User entities with specific permissions
- Use framework's `mockEntity()` helper

### Phase 3: Entity Tests - BlogPost Entity

**Objective:** Test BlogPost entity's state machine, permissions, reactions, and scheduled post logic.

**File to Create:** `tests/Unit/Entity/BlogPostTest.php`

**Test Coverage (approximately 30-35 tests):**

1. **State Machine Tests** (critical)
   - `getScheduled()` - Detect scheduled vs non-scheduled states
   - `verifyScheduledPostDateTime()` - Reject past dates, accept future dates and zero

2. **Permission Tests**
   - `canView()` - Test visibility of moderated/deleted posts based on permissions
   - `canEdit()` - Test canEditOwn for owner, canEditAny for moderators
   - `canDelete()` - Test soft/hard delete permissions
   - `canUndelete()` - Test undelete permissions

3. **Reaction Tests**
   - Cannot react to own post
   - Cannot react when not logged in
   - Cannot react to non-visible posts
   - Can react to visible posts from blog

4. **Thread Integration Tests**
   - `getExpectedThreadTitle()` - Thread title generation with 100 char limit

5. **Attachment Tests**
   - `canUploadAndManageAttachments()` - Attachment permissions
   - `isAttachmentEmbedded()` - Embedded attachment detection

6. **Validation Tests**
   - `verifyTitle()` - Title validation (min 10 chars, capitalization)

**Mocking Strategy:**
- Mock `\XF::visitor()` and `\XF::$time` for time-based logic
- Mock Blog entity for relationship tests
- Mock Attachment entities
- Use specific timestamps for scheduled post tests

### Phase 4: Service Tests - BlogPost/Create

**Objective:** Test the complex BlogPost creation workflow including content preparation, scheduling, thread creation, and notifications.

**File to Create:** `tests/Unit/Service/BlogPost/CreateTest.php`

**Test Coverage (approximately 20-25 tests):**

1. **Content Setting Tests**
   - `setTitle()` - Updates post title
   - `setContent()` - Prepares content and extracts embed metadata
   - `setTags()` - Updates tags when permitted, ignored without permission

2. **State Management Tests**
   - Setting state to 'visible' clears schedule
   - Setting state to 'scheduled' maintains scheduled time
   - Setting state to 'draft' clears schedule and date

3. **Scheduling Tests** (critical)
   - `setScheduledPostDateTime()` - Converts to Unix timestamp, handles timezones
   - `finalSteps()` - Inserts job for scheduled posts, skips for visible posts

4. **Thread Creation Tests**
   - Thread created for visible posts when comments enabled
   - Thread creation skipped for draft/scheduled posts
   - Thread creation skipped when option disabled
   - Thread ID saved to blog post entity

5. **Validation & Save Tests**
   - Validation checks post and tag errors
   - Save persists entity and tags
   - Returns created BlogPost entity

6. **Notification Tests**
   - Notifications sent for visible blogs
   - Notifications skipped for non-visible blogs

**Mocking Strategy:**
- Mock `\XF::service()` for Preparer, Tag\Changer, Thread\Creator services
- Mock `\XF::options()` for feature flags (comments enabled, forum ID)
- Mock `\XF::app()->jobManager()` for job enqueueing
- Use framework's `fakesJobs()` to verify job creation

### Phase 5: Repository Tests

**Objective:** Test repository query methods and data access logic.

**Files to Create:**
1. `tests/Unit/Repository/BlogPostTest.php` (15-20 tests)
2. `tests/Unit/Repository/BlogTest.php` (8-10 tests)

**BlogPost Repository Coverage:**
- View tracking: log view inserts/increments, batch updates
- Job management: update scheduled time, cancel jobs
- Finder methods: latest posts, posts by user, post for thread
- Similar posts: XFES integration, cache staleness detection

**Blog Repository Coverage:**
- Header image: resize/save, upload validation, file deletion
- Finder methods: blogs by user
- Batch update blog post counts

**Mocking Strategy:**
- Mock database with framework's `mockDb()`
- Mock XFES Search service for similar posts
- Mock `\XF::app()->imageManager()` for image processing
- Mock Upload and File utility classes

### Phase 6: Utility & Job Tests

**Objective:** Test static helper methods and background job execution.

**Files to Create:**
1. `tests/Unit/UtilsTest.php` (10-12 tests)
2. `tests/Unit/Job/PostBlogPostTest.php` (6-8 tests)

**Utils Coverage:**
- Time helpers: hours() and minutes() array generation
- Repository shortcuts: getBlogPostRepo(), getBlogRepo()
- Count adjustments: adjustBlogPostCount(), adjustUserBlogPostCount(), adjustUserBlogCount()
- Thread creation: setupBlogPostThreadCreation(), getThreadMessage()

**PostBlogPost Job Coverage:**
- Publishes scheduled post when time reached
- Skips post when time not reached
- Handles missing blog post gracefully
- Exception handling and logging
- Status messages and job properties

**Mocking Strategy:**
- Mock repositories and entities
- Mock `\XF::$time` for time-based logic
- Use framework's `fakesLogger()` for error logging tests

### Phase 7: Additional Service Tests (Optional/Lower Priority)

**Files to Create:**
1. `tests/Unit/Service/Blog/CreateTest.php` (5-8 tests)
2. `tests/Unit/Service/Blog/NotifyTest.php` (8-10 tests)

**Blog/Create Coverage:**
- Title, description, state, header flag setters
- Validation and save logic

**Blog/Notify Coverage:**
- Action type validation (newBlogPost, update, blogPostApproved)
- Permission-based user filtering
- Time-limited notification cycles

## Critical Files Reference

### Files to Read Before Implementation:
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Entity/Blog.php` - Permission methods to test
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Entity/BlogPost.php` - State machine and validation
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/Create.php` - Complex business logic
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/vendor/hampel/xenforo-test-framework/tests/TestCase.php` - Framework reference

### Files to Create:
- `phpunit.xml`
- `tests/TestCase.php`
- `tests/CreatesApplication.php`
- `tests/README.md`
- `tests/Unit/Entity/BlogTest.php`
- `tests/Unit/Entity/BlogPostTest.php`
- `tests/Unit/Service/BlogPost/CreateTest.php`
- `tests/Unit/Repository/BlogPostTest.php`
- `tests/Unit/Repository/BlogTest.php`
- `tests/Unit/UtilsTest.php`
- `tests/Unit/Job/PostBlogPostTest.php`
- Directory structure (Unit/Entity/, Unit/Service/, etc.)

### Files to Modify:
- `build.json` - Add test exclusions
- `CLAUDE.md` - Add testing section

## Testing Best Practices

### Mocking Strategy
- **Mock** external dependencies (database, file system, HTTP, mail)
- **Mock** XenForo framework components (app, visitor, options, repositories)
- **Mock** services with side effects (jobs, notifications)
- **Don't mock** the class under test or simple value objects

### Naming Conventions
- Test files: `{ClassName}Test.php`
- Test methods: `test{Behavior}{Scenario}()` or `test{MethodName}{Condition}{ExpectedResult}()`
- Examples: `testCanViewReturnsTrueWhenVisitorHasPermission()`, `testVerifyTitleFailsForShortTitle()`

### Test Structure (AAA Pattern)
```php
public function testExample()
{
    // Arrange - Set up test data and mocks
    $blog = $this->mockEntity('TaylorJ\Blogs:Blog');

    // Act - Execute the code being tested
    $result = $blog->canView();

    // Assert - Verify the result
    $this->assertTrue($result);
}
```

### Coverage Goals
- Entities: 80%+ (focus on permissions, validation, state changes)
- Services: 75%+ (focus on business logic workflows)
- Repositories: 60%+ (complex queries and data manipulation)
- Utils: 80%+ (high-value, straightforward coverage)
- Jobs: 70%+ (execution paths and error handling)

**Note:** Skip trivial getters/setters, XenForo framework methods, and simple pass-through methods. Focus on business logic and decision points.

## Known Challenges & Solutions

1. **Permission Testing**: Mock `\XF::visitor()` with specific permissions using helper method `createTestUser($permissions)` in base TestCase

2. **Time-Dependent Logic**: Mock `\XF::$time` for scheduled posts, use specific timestamps for predictable behavior

3. **Thread Creation**: Mock `\XF::service()` to return mocked ThreadCreator, verify setup calls rather than actual creation

4. **XFES Integration**: Mock XFES Search service for similar posts feature

5. **Database Operations**: Use framework's `mockDb()`, verify queries without executing them

## Verification & Testing

### Running Tests
```bash
# From add-on directory
./vendor/bin/phpunit

# Specific test suite
./vendor/bin/phpunit --testsuite Unit

# Specific test file
./vendor/bin/phpunit tests/Unit/Entity/BlogTest.php

# With coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Specific test method
./vendor/bin/phpunit --filter testCanViewReturnsTrueWhenVisitorHasPermission
```

### Verification Checklist
- [ ] PHPUnit discovers all tests (`./vendor/bin/phpunit --list-tests`)
- [ ] All tests pass on initial run
- [ ] Tests complete in under 60 seconds (unit tests should be fast)
- [ ] Code coverage report generates successfully
- [ ] Build process excludes test files (verify with test build)
- [ ] No database modifications during test execution
- [ ] No external API calls during tests
- [ ] Documentation is clear with examples

## Implementation Priority

### Minimum Viable Testing (4-6 hours)
If time is limited, implement in this order:
1. Phase 1 (Infrastructure) - 1 hour
2. Phase 2 (Blog Entity Tests) - 2 hours
3. Phase 3 (BlogPost Entity Tests) - 2 hours
4. Documentation update - 30 minutes

This provides foundational infrastructure and tests for the most critical business logic (permissions and state machines).

### Comprehensive Testing (18-25 hours)
Implement all phases sequentially for full coverage of entities, services, repositories, utilities, and jobs.

### Incremental Rollout
After Phase 1 infrastructure is complete, subsequent phases can be implemented incrementally as time allows, with each phase adding value independently.
