# Unit Test Expansion Plan for TaylorJ/Blogs

## Context

The TaylorJ/Blogs add-on currently has strong unit test coverage for **Entity** (87 tests) and **Job** (4 tests) layers, but lacks comprehensive testing for the **Service**, **Repository**, and **Utils** layers. There are 36 existing but non-working tests for these layers that need fixing, and many components remain completely untested.

**Problem:** Without comprehensive unit test coverage across all layers, we risk:
- Regressions during refactoring or feature additions
- Bugs in business logic (services) and data access (repositories)
- Confidence gaps when making changes to critical components like blog post creation, deletion, and notification systems

**Goal:** Expand unit test coverage to include all Service, Repository, and Utils components, providing confidence for future development and ensuring the add-on maintains high code quality standards.

**Current State:**
- ✅ 91 passing tests (Entity: 87, Job: 4)
- ⚠️ 36 non-working tests (Service: 11, Repository: 9, Utils: 16)
- ❌ 10 service classes mostly untested (BlogPost/Create partially tested)
- ❌ 3 repository classes partially tested or untested
- ❌ Utils class partially tested

**Target State:**
- 🎯 ~280-320 total tests with comprehensive coverage across all layers
- All service business logic validated
- All repository data access patterns tested
- Utils helper methods fully covered

---

## Implementation Strategy

### Phase 1: Fix Existing Tests & Build Confidence (Priority: CRITICAL)

**Start with easy wins to establish patterns and build confidence.**

#### 1.1 Blog/Create Service (NEW - 8-10 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/Blog/Create.php`

**Why Start Here:** Simplest service class (72 lines), straightforward setters, no complex dependencies. Perfect for establishing service testing patterns.

**Test Coverage:**
- Property setters: `setTitle()`, `setDescription()`, `setHasBlogHeader()`
- State management: `setState()` calls `blog.getNewContentState()`
- Validation: `_validate()` calls `blog.preSave()` and collects errors
- Save flow: `_save()` calls `blog.save(true, false)` and returns blog
- Lifecycle: `finalSteps()` is called (empty method)

**Testing Pattern:**
```php
class BlogCreateTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->setUpEntityManager(); // CRITICAL: Enable mocking
    }

    protected function createService() {
        $mockBlog = $this->mockEntity('TaylorJ\Blogs:Blog', true, function($mock) {
            $mock->shouldReceive('offsetSet')->byDefault();
            $mock->shouldReceive('offsetGet')->byDefault()->andReturn(null);
        });
        return new \TaylorJ\Blogs\Service\Blog\Create($this->app(), $mockBlog);
    }

    public function testSetTitleUpdatesProperty() {
        $service = $this->createService();
        $service->blog->shouldReceive('offsetSet')
            ->with('blog_title', 'Test Title')
            ->once();
        $service->setTitle('Test Title');
    }
}
```

**New Test File:** `tests/Unit/Service/Blog/CreateTest.php`

---

#### 1.2 Blog/Edit Service (NEW - 9-11 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/Blog/Edit.php`

**Why Next:** Very similar to Blog/Create (establishes consistency), adds one complexity (file deletion - skip due to Flysystem).

**Test Coverage:**
- Property setters: `setTitle()`, `setDescription()`, `setHasBlogHeader()`
- Validation: `_validate()` calls `blog.preSave()` and collects errors
- Save flow: `_save()` calls `blog.save()` and returns blog
- Lifecycle hooks: `finalSteps()`, `finalSetup()`
- File management: `deleteBlogHeaderImageFiles()` - **SKIP** (Flysystem incompatibility)

**Pattern:** Nearly identical to CreateTest, use as template.

**New Test File:** `tests/Unit/Service/Blog/EditTest.php`

---

#### 1.3 BlogWatch Repository (NEW - 6-8 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Repository/BlogWatch.php`

**Why Include:** Simple toggle logic, introduces repository testing pattern (entity manager find/create), quick win.

**Test Coverage:**
- Watch toggle: `setWatchState()` creates watch if none exists
- Unwatch toggle: `setWatchState()` deletes watch if exists
- Race condition: Handles `DuplicateKeyException` gracefully
- Validation: Throws `InvalidArgumentException` for invalid IDs (blog_id=0, user_id=0)
- Composite key: Finds watch by (blog_id + user_id)

**Testing Pattern:**
```php
public function testSetWatchStateCreatesWatchIfNoneExists() {
    $mockWatch = $this->mockEntity('TaylorJ\Blogs:BlogWatch', false, function($mock) {
        $mock->shouldReceive('offsetSet')->byDefault();
        $mock->shouldReceive('save')->once();
    });

    $mockEm = $this->app()->em();
    $mockEm->shouldReceive('find')
        ->with('TaylorJ\Blogs:BlogWatch', [1, 1])
        ->andReturn(null);
    $mockEm->shouldReceive('create')
        ->with('TaylorJ\Blogs:BlogWatch')
        ->andReturn($mockWatch);

    $repo = $this->repository('TaylorJ\Blogs:BlogWatch');
    $repo->setWatchState($mockBlog, $mockUser);
}
```

**New Test File:** `tests/Unit/Repository/BlogWatchTest.php`

---

#### 1.4 Utils Class (FIX EXISTING - 16 tests + ADD 6-9 = 22-25 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Utils.php`

**Why Include:** Existing 16 tests need fixing, static helpers are straightforward to test, quick confidence boost.

**Existing Tests to Fix:**
- `hours()` returns 24 entries (4 tests)
- `minutes()` returns 60 entries (4 tests)
- `repo()` / `getBlogPostRepo()` / `getBlogRepo()` (3 tests)
- Count adjustment methods: `adjustBlogPostCount()`, `adjustUserBlogPostCount()`, `adjustUserBlogCount()` (6 tests)

**New Tests to Add (6-9):**
- `log()` calls `XF::logError()` with prefixed message (1 test)
- `getThreadMessage()` renders phrase with snippet and link (2 tests - normal + truncation)
- `setupBlogPostThreadCreation()` creates ThreadCreator with correct forum (2 tests - with config + fallback)
- `afterResourceThreadCreated()` marks thread read and auto-watches (2 tests)
- `getPostRepo()` returns PostRepository (1 test)
- `deleteBlogHeaderFiles()` - **SKIP** (Flysystem)

**Existing Test File:** `tests/Unit/UtilsTest.php` (fix + expand)

---

**Phase 1 Summary:** 45-54 tests across 4 components, establishes all core patterns (service, repository, static utility testing).

---

### Phase 2: Medium Complexity Components (Priority: HIGH)

#### 2.1 Blog/Delete Service (NEW - 12-15 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/Blog/Delete.php`

**Test Coverage:**
- Basic deletion: `delete('soft')` vs `delete('hard')`
- Cascading: Calls `BlogPost/Delete` for each blog post
- Alert management: `setSendAlert()` and notification triggering
- User tracking: `setUser()` / `getUser()` for moderator actions
- Reason recording: `setBlogDeleteReason()`

**Key Complexity:** Mock Blog with BlogPosts collection, mock BlogPost/Delete service in loop.

**New Test File:** `tests/Unit/Service/Blog/DeleteTest.php`

---

#### 2.2 BlogPost/Delete Service (NEW - 15-18 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/Delete.php`

**Test Coverage:**
- Basic deletion: `delete('soft')` vs `delete('hard')`
- Thread updates: `updateCommentsThread()` adds deletion reply if `addPost=true`
- Reply creation: `setupCommentsThreadReply()` creates Replier with message
- Message generation: `getThreadReplyMessage()` with/without reason
- Alert management: `setSendAlert()` triggers alerts for visible posts
- Configuration: `setAddPost()` overrides default from options

**Key Complexity:** Mock Thread, Forum, Replier service; test conditional thread reply creation.

**New Test File:** `tests/Unit/Service/BlogPost/DeleteTest.php`

---

#### 2.3 Blog Repository (FIX 2 EXISTING + ADD 2-3 = 4-5 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Repository/Blog.php`

**Existing Tests to Fix:**
- `findBlogsByUser()` returns finder with user filter and ordering (1 test)
- `deleteBlogHeaderImage()` - **SKIP** (Flysystem incompatibility)

**New Tests to Add (2-3):**
- `batchUpdateBlogPostCounts()` iterates all blogs (1 test)
- `batchUpdateBlogPostCounts()` calls `fastUpdate()` with correct count (1 test)
- Image upload: `setBlogHeaderImagePath()` - **SKIP** (Flysystem)

**Existing Test File:** `tests/Unit/Repository/BlogTest.php` (fix + minimal expansion)

---

#### 2.4 BlogPost/Approve Service (NEW - 6-8 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/Approve.php`

**Test Coverage:**
- Approval flow: Transitions from 'moderated' to 'visible'
- Date setting: Sets `blog_post_date` to current time
- Save execution: Calls `blogPost.save()`
- Thread creation: `onApprove()` creates thread if comments enabled
- Validation: Only approves moderated posts
- Return values: Returns true on success, false on invalid state

**New Test File:** `tests/Unit/Service/BlogPost/ApproveTest.php`

---

#### 2.5 Blog/Approve Service (NEW - 6-8 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/Blog/Approve.php`

**Test Coverage:**
- Approval flow: Transitions from 'moderated' to 'visible'
- Save execution: Calls `blog.save()`
- Notification config: `setNotifyRunTime()` sets timeout
- Validation: Only approves moderated blogs
- Return values: Returns true on success, false on invalid state
- Lifecycle: `onApprove()` hook (currently commented out)

**New Test File:** `tests/Unit/Service/Blog/ApproveTest.php`

---

**Phase 2 Summary:** 41-54 tests, covers deletion workflows and approval logic.

---

### Phase 3: High Complexity Components (Priority: HIGH)

#### 3.1 BlogPost/Create Service (FIX 11 EXISTING + ADD 14-19 = 25-30 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/Create.php`

**Why Critical:** Most complex and important service. Orchestrates blog post creation including content preparation, state management, scheduling, thread creation, tagging, and notifications.

**Existing Tests to Fix (11):**
- `setTitle()` updates title (1 test) ✓
- `setBlogPostState()` handles visible/scheduled/draft (3 tests) ✓
- `setScheduledPostDateTime()` converts date array to timestamp (1 test) ✓
- `finalSteps()` inserts job for scheduled posts (2 tests) ✓
- `sendNotifications()` triggers Notify service when visible (2 tests) ✓
- `setTags()` calls TagChanger when permissions allow (2 tests) ✓

**New Tests to Add (14-19):**
- Content preparation: `setContent()` calls PreparerService and extracts embed metadata (2-3 tests)
- Thread creation on save: `_save()` creates thread when visible + comments enabled (4-5 tests)
  - Thread creation when post is visible
  - Skips thread when state not visible
  - Skips thread when comments disabled
  - Updates `discussion_thread_id` on success
  - Thread state matches post state
- Thread setup: `setupBlogPostThreadCreation()` configures ThreadCreator correctly (2-3 tests)
  - Uses configured forum or falls back to first forum
  - Sets content with title + message
  - Sets discussion type to 'blogPost'
- Thread lifecycle: `afterResourceThreadCreated()` marks read and auto-watches (2 tests)
- Message generation: `getThreadMessage()` renders snippet correctly (2 tests)
- Validation: `_validate()` collects errors from entity + tags (2 tests)
- Job creation: `insertJob()` uses correct unique key format (1 test)

**Testing Challenges:**
- Mock PreparerService for content rendering
- Mock ThreadCreator for conditional thread creation
- Mock TagChanger with full method chain
- Use `fakesJobs()` for job assertions
- Mock Notify service for notification triggering

**Existing Test File:** `tests/Unit/Service/BlogPost/CreateTest.php` (fix + major expansion)

---

#### 3.2 BlogPost/Edit Service (NEW - 18-22 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/Edit.php`

**Test Coverage:**
- Basic updates: `setTitle()`, `setBlogPostContent()` (2-3 tests)
- Date management: Updates `blog_post_date` vs `blog_post_last_edit_date` based on state (3-4 tests)
- Scheduling updates: `setScheduledPostDateTime()` handles state transitions (4 tests)
- Thread creation on edit: `finalSteps()` creates thread when draft/scheduled → visible (4-5 tests)
- State transitions: `handlePostStateChange()` manages approval workflow (2-3 tests)
- Spam checking: `checkForSpam()` integrates with spam checker (3 tests)
- Validation: `_validate()` collects errors (2 tests)

**Key Complexity:** State transitions affect date field updates, conditional thread creation on state change, spam checking integration.

**New Test File:** `tests/Unit/Service/BlogPost/EditTest.php`

---

#### 3.3 BlogPost Repository (FIX 7 EXISTING + ADD 18-23 = 25-30 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Repository/BlogPost.php`

**Existing Tests to Fix (7):**
- Finder methods: `findLatestBlogPosts()`, `findBlogPostsByUser()`, `findBlogPostForThread()` (3 tests)
- Job management: `updateJob()` executes query (1 test)
- View tracking: `logThreadView()`, `batchUpdateThreadViews()` (2 tests)
- Count queries: `getUserBlogPostCount()` (1 test)

**New Tests to Add (18-23):**
- Finder methods: `findOtherPostsByOwnerRandom()`, `findBlogPostAuthor()` (2 tests)
- Job management: `removeJob()` cancels job with correct key (1-2 tests)
- Alert sending: `sendModeratorActionAlert()` creates alert with metadata (5 tests)
  - Creates alert for user
  - Includes title, link, reason in extras
  - Uses forceUser if provided
  - Returns false if no user
  - Sets dependsOnAddOnId
- XFES similar posts: Cache rebuild, query building, flag checking (6-8 tests)
  - `rebuildSimilarBlogPostsCache()` calls XFES and updates cache
  - `getSimilarBlogPostIds()` calls moreLikeThis with correct query
  - `getSimilarBlogPostsMltQuery()` sets up MLT query
  - `flagIfSimilarBlogPostsCacheNeedsRebuild()` creates cache if not exists
  - Returns true if pending rebuild
  - Checks isRebuildRequired based on age
- Additional finders and count queries (2-3 tests)

**Testing Challenges:**
- Mock database with `mockDatabase()` for raw SQL queries
- Mock XFES Search service (check if XFES installed, skip if not available)
- Mock finder with method chain assertions
- Mock BlogPostSimilar entity for cache operations

**Existing Test File:** `tests/Unit/Repository/BlogPostTest.php` (fix + major expansion)

---

**Phase 3 Summary:** 68-82 tests, covers most complex business logic.

---

### Phase 4: Optional/Low Priority Components

#### 4.1 Blog/Notify Service (SKIP or 8-10 tests)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/Blog/Notify.php`

**Recommendation:** **SKIP** - Extends `AbstractNotifier` with mostly inherited behavior. BlogPost/Create already tests that Notify is called correctly.

**If Time Permits - Minimal Coverage (8-10 tests):**
- Notifier loading: `loadNotifiers()` returns blogWatch notifier (1-2 tests)
- Permission checking: `canUserViewContent()` checks `blog.canView()` (2 tests)
- Action types: Constructor accepts valid action types ('update', 'newBlogPost', 'blogPostApproved') (3 tests)
- Job creation: `createForJob()` factory for job resumption (1-2 tests)

**New Test File:** `tests/Unit/Service/Blog/NotifyTest.php` (optional)

---

#### 4.2 BlogPost/ThreadCreator Service (SKIP)
**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Service/BlogPost/ThreadCreator.php`

**Recommendation:** **SKIP COMPLETELY** - 537 lines, essentially XF's Thread\Creator adapted for blog posts. Requires mocking dozens of XF core components. Better tested through integration tests or by relying on XF's core Thread\Creator tests.

**Alternative Coverage:** BlogPost/Create and BlogPost/Edit services already test the integration with ThreadCreator.

---

**Phase 4 Summary:** 0-10 tests (optional/skip)

---

## Testing Patterns & Utilities

### Service Testing Template

**File Pattern:** `tests/Unit/Service/{Namespace}/{ClassName}Test.php`

```php
<?php

namespace TaylorJ\Blogs\Tests\Unit\Service\Blog;

use TaylorJ\Blogs\Tests\TestCase;
use Mockery;

class CreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEntityManager(); // CRITICAL: Must call for mocking
    }

    protected function createService()
    {
        $mockBlog = $this->mockEntity('TaylorJ\Blogs:Blog', true, function($mock) {
            $mock->shouldReceive('offsetSet')->byDefault();
            $mock->shouldReceive('offsetGet')->byDefault()->andReturn(null);
            $mock->shouldReceive('get')->byDefault()->andReturn(null);
        });

        return new \TaylorJ\Blogs\Service\Blog\Create($this->app(), $mockBlog);
    }

    public function testSetTitleUpdatesProperty()
    {
        $service = $this->createService();
        $service->blog->shouldReceive('offsetSet')
            ->with('blog_title', 'Test Title')
            ->once();

        $service->setTitle('Test Title');
    }
}
```

**Key Points:**
- Always call `$this->setUpEntityManager()` in `setUp()` for service/repository tests
- Mock entities need both `offsetGet()` and `get()` expectations
- Use `byDefault()` for flexible default mocking
- Use `once()` or exact parameter matching for specific assertions

---

### Repository Testing Template

**File Pattern:** `tests/Unit/Repository/{ClassName}Test.php`

```php
<?php

namespace TaylorJ\Blogs\Tests\Unit\Repository;

use TaylorJ\Blogs\Tests\TestCase;

class BlogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEntityManager();
    }

    public function testFindBlogsByUserReturnsFinderWithCorrectConditions()
    {
        $mockFinder = $this->mockFinder('TaylorJ\Blogs:Blog', function($mock) {
            $mock->shouldReceive('where')->with('user_id', 1)->andReturnSelf();
            $mock->shouldReceive('order')->with('last_blog_post_date', 'DESC')->andReturnSelf();
        });

        $repo = $this->repository('TaylorJ\Blogs:Blog');
        $result = $repo->findBlogsByUser(1);

        $this->assertNotNull($result);
    }
}
```

**Key Points:**
- Use `mockFinder()` for query builder assertions
- Chain methods with `andReturnSelf()`
- Use `mockDatabase()` for raw SQL queries
- Test query construction, not execution

---

### Utilities for Testing

**From TestCase.php:**
- `mockVisitor(array $permissions, int $userId)` - Mock visitor with permissions
- `setVisitor($user)` - Set global XF visitor
- `getMockData($file)` - Load JSON fixtures from `tests/mock/`

**From hampel/xenforo-test-framework:**
- `$this->mockEntity($identifier, $isSingleton, $callback)` - Mock entities
- `$this->mockRepository($identifier, $callback)` - Mock repositories
- `$this->mockFinder($identifier, $callback)` - Mock finders
- `$this->mockService($identifier, $callback)` - Mock services
- `$this->mockDatabase($callback)` - Mock database adapter
- `$this->fakesJobs()` - Enable job faking
- `$this->assertJobQueued($jobClass)` - Assert job enqueued
- `$this->assertNoJobsQueued()` - Assert no jobs enqueued
- `$this->setOption($name, $value)` - Set XF options for test
- `$this->fakesErrors()` - Suppress error logging

---

## Critical Files to Modify

### Base Infrastructure (Already Exists)
1. `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/TestCase.php` - Base test class (no changes needed)
2. `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/CreatesApplication.php` - App caching trait (no changes needed)
3. `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/phpunit.xml` - PHPUnit configuration (no changes needed)

### Service Tests (Create New + Fix Existing)
4. `tests/Unit/Service/Blog/CreateTest.php` - **NEW** (8-10 tests)
5. `tests/Unit/Service/Blog/EditTest.php` - **NEW** (9-11 tests)
6. `tests/Unit/Service/Blog/DeleteTest.php` - **NEW** (12-15 tests)
7. `tests/Unit/Service/Blog/ApproveTest.php` - **NEW** (6-8 tests)
8. `tests/Unit/Service/BlogPost/CreateTest.php` - **FIX + EXPAND** (11 → 25-30 tests)
9. `tests/Unit/Service/BlogPost/EditTest.php` - **NEW** (18-22 tests)
10. `tests/Unit/Service/BlogPost/DeleteTest.php` - **NEW** (15-18 tests)
11. `tests/Unit/Service/BlogPost/ApproveTest.php` - **NEW** (6-8 tests)

### Repository Tests (Create New + Fix Existing)
12. `tests/Unit/Repository/BlogTest.php` - **FIX + EXPAND** (2 → 4-5 tests)
13. `tests/Unit/Repository/BlogPostTest.php` - **FIX + EXPAND** (7 → 25-30 tests)
14. `tests/Unit/Repository/BlogWatchTest.php` - **NEW** (6-8 tests)

### Utility Tests (Fix Existing)
15. `tests/Unit/UtilsTest.php` - **FIX + EXPAND** (16 → 22-25 tests)

---

## Testing Constraints & Limitations

### Framework Constraints
1. **Single App Instance:** XenForo can only create one app instance per process. App caching via `CreatesApplication` trait is mandatory.
2. **Entity Manager Setup:** Must call `$this->setUpEntityManager()` explicitly in `setUp()` for service/repository tests. Do NOT call in base `TestCase` (breaks class extension resolution).
3. **Flysystem Incompatibility:** XF uses Flysystem v1, test framework expects v3. **Skip all filesystem tests** using `$this->markTestSkipped()`.
4. **No App Destruction:** Cannot destroy `\XF::$app` in `tearDown()`. Tests share app instance.

### Testing Limitations
1. **Unit Tests Only:** No integration tests, no controller tests, no end-to-end tests in this plan.
2. **XFES Dependency:** Similar posts tests require XFES add-on. Check if installed, skip if unavailable.
3. **Mocking Complexity:** Complex services (ThreadCreator, Notify) may have brittle mocks. Consider skipping or minimal coverage.
4. **Database Queries:** Raw SQL queries require `mockDatabase()` which can be fragile. Test query construction, not execution.

### Components to Skip
1. **BlogPost/ThreadCreator Service** - Too complex (537 lines), tested via integration
2. **Blog/Notify Service** - Optional (inherited behavior from AbstractNotifier)
3. **All file operations** - Flysystem version incompatibility
4. **Controller tests** - Out of scope (integration tests)

---

## Expected Outcomes

### Test Count Summary

| Layer | Current | Target | New Tests |
|-------|---------|--------|-----------|
| **Services** | 11 (broken) | 126-156 | 115-145 |
| **Repositories** | 9 (broken) | 35-43 | 26-34 |
| **Utils** | 16 (broken) | 22-25 | 6-9 |
| **Subtotal** | **36** | **183-224** | **147-188** |
| **Entity (existing)** | 87 | 87 | 0 |
| **Job (existing)** | 4 | 4 | 0 |
| **GRAND TOTAL** | **127** | **274-315** | **147-188** |

### Coverage Targets
- **Service Layer:** 10 services tested (8 comprehensive, 2 skipped)
- **Repository Layer:** 3 repositories tested (BlogPost comprehensive, Blog minimal, BlogWatch complete)
- **Utils Layer:** All static helpers tested
- **Overall:** ~220-280 total tests with comprehensive unit coverage

### Quality Metrics
- 100% pass rate (all tests passing)
- No skipped tests except documented Flysystem incompatibilities
- Minimum 1 assertion per test
- Full mocking isolation (no database calls, no file operations)
- All tests run in <30 seconds

---

## Verification & Testing

### Running Tests

```bash
# Change to add-on directory
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs

# Run all working tests (Entity + Job + new Service/Repository/Utils)
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/

# Run specific test suites
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Service/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Repository/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/UtilsTest.php

# Run single test file
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Service/Blog/CreateTest.php

# Run single test method
XDEBUG_MODE=off ./vendor/bin/phpunit --filter testSetTitleUpdatesProperty tests/Unit/Service/Blog/CreateTest.php
```

### Verification Checklist

**Phase 1 Complete (45-54 tests):**
- [ ] Blog/Create service - 8-10 tests passing
- [ ] Blog/Edit service - 9-11 tests passing
- [ ] BlogWatch repository - 6-8 tests passing
- [ ] Utils class - 22-25 tests passing (16 fixed + 6-9 new)

**Phase 2 Complete (41-54 tests):**
- [ ] Blog/Delete service - 12-15 tests passing
- [ ] BlogPost/Delete service - 15-18 tests passing
- [ ] Blog repository - 4-5 tests passing
- [ ] BlogPost/Approve service - 6-8 tests passing
- [ ] Blog/Approve service - 6-8 tests passing

**Phase 3 Complete (68-82 tests):**
- [ ] BlogPost/Create service - 25-30 tests passing (11 fixed + 14-19 new)
- [ ] BlogPost/Edit service - 18-22 tests passing
- [ ] BlogPost repository - 25-30 tests passing (7 fixed + 18-23 new)

**Final Verification:**
- [ ] All test suites pass: `XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/`
- [ ] No skipped tests except documented Flysystem tests
- [ ] Test count: 274-315 total tests (Entity: 87, Job: 4, Service: 126-156, Repository: 35-43, Utils: 22-25)
- [ ] Code coverage: Service/Repository/Utils layers comprehensively tested
- [ ] Documentation: Update `tests/README.md` with new testing guidance

---

## Implementation Timeline Estimate

**Assuming ~2-3 hours per component:**

| Phase | Components | Tests | Estimated Hours |
|-------|------------|-------|-----------------|
| Phase 1 | 4 components | 45-54 | 8-12 hours |
| Phase 2 | 5 components | 41-54 | 10-15 hours |
| Phase 3 | 3 components | 68-82 | 15-20 hours |
| Phase 4 | 0-1 components | 0-10 | 0-5 hours (optional) |
| **TOTAL** | **12-13 components** | **154-200 tests** | **33-52 hours** |

**Recommended Approach:**
- **Week 1:** Phase 1 (Easy Wins) - Build confidence with simple components
- **Week 2:** Phase 2 (Medium) - Tackle deletion and approval logic
- **Week 3:** Phase 3 (High Complexity) - Critical service and repository expansion
- **Week 4:** Bug fixes, optimization, documentation updates

---

## Success Criteria

1. **All existing tests fixed:** 36 broken tests now passing
2. **Comprehensive service coverage:** All critical services tested (Create, Edit, Delete, Approve for Blog and BlogPost)
3. **Repository validation:** All repository methods tested except file operations
4. **Utils coverage:** All static helpers and count adjustment methods tested
5. **Quality standards:** 100% pass rate, proper mocking isolation, fast execution (<30s)
6. **Documentation:** Testing patterns documented for future development
7. **Maintainability:** Clear test organization, reusable helper methods, consistent naming

**Final Target:** ~274-315 passing unit tests providing comprehensive coverage across Entity (87), Job (4), Service (126-156), Repository (35-43), and Utils (22-25) layers.
