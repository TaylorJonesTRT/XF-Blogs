# Fix 22 Failing PHPUnit Tests

## Context

The test suite has 22 failing tests caused by **test pollution** from `BlogPostTest` leaving a mocked database adapter in the cached app instance. When tests call `setUpEntityManager()`, it replaces the real entity manager with a mocked version that includes a mocked database adapter. This mocked state persists across test classes because `TestCase::tearDown()` intentionally does NOT destroy the `\XF::$app` instance (due to XenForo's single-app constraint).

### Failing Tests

- **BlogWatchTest** (2 errors): Missing `quote()` expectation - tests call `$repo->setWatchState()` which uses `em->find()` triggering database queries
- **BlogPost/CreateTest** (20 errors): Missing `fetchOne()` expectation - `BlogPost\Create::initialize()` instantiates `Tag\Changer` service which accesses user permissions, triggering permission cache database lookups

### Root Cause Chain

1. `BlogPostTest::setUp()` (line 10-14) calls `setUpEntityManager()` which swaps the real entity manager with a mocked version
2. The mocked entity manager uses a mocked database adapter (from `Hampel\Testing`)
3. After `BlogPostTest` completes, the mocked database remains in the app container (app is cached, not destroyed)
4. Later tests (`BlogWatchTest`, `BlogPost/CreateTest`) run with the mocked database still active
5. These tests trigger database calls (`quote()`, `fetchOne()`) without setting up expectations, causing Mockery exceptions

## Implementation Plan

### Step 1: Add `restoreEntityManager()` helper to TestCase

**File:** `tests/TestCase.php`
**Location:** After line 62 (after `setVisitor()` method)

Add this method:

```php
/**
 * Restore the real entity manager after mocking.
 *
 * Call this in tearDown() if your test called setUpEntityManager() to prevent
 * mocked database state from leaking into subsequent test classes.
 */
protected function restoreEntityManager()
{
    if ($this->app) {
        $this->swap('em', function (\XF\Container $c) {
            return new \XF\Mvc\Entity\Manager(
                $c['db'],
                $c['em.valueFormatter'],
                $c['extension']
            );
        });
    }
}
```

This creates a fresh real entity manager using the app's original database connection, value formatter, and extension services.

### Step 2: Add `tearDown()` to BlogPostTest

**File:** `tests/Unit/Repository/BlogPostTest.php`
**Location:** After line 14 (after `setUp()` method)

Add this method:

```php
protected function tearDown(): void
{
    $this->restoreEntityManager();
    parent::tearDown();
}
```

This ensures that when `BlogPostTest` finishes, the mocked entity manager is replaced with the real one, preventing pollution of subsequent test classes.

### Why This Solution?

1. **Minimal changes**: Only adds one helper method and one tearDown override
2. **No production code changes**: All changes are test infrastructure only
3. **Follows isolation principle**: Each test class leaves the system in a clean state
4. **Matches existing pattern**: The TestCase already has custom tearDown logic (line 113-154)
5. **Prevents cascading failures**: Stops mocked state from leaking between test classes

### Alternative Approaches Rejected

- **Mock `quote()` and `fetchOne()` in affected tests**: Treats symptoms, not root cause; requires many test changes
- **Mock the Tag\Changer service**: Requires significant refactoring and doesn't solve BlogWatchTest issue
- **Refactor services to use lazy initialization**: Requires production code changes just for tests
- **Always call `setUpEntityManager()` everywhere**: Breaks class extension resolution (see TestCase line 77-93)

## Critical Files

- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/TestCase.php` - Add `restoreEntityManager()` helper
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/Unit/Repository/BlogPostTest.php` - Add `tearDown()` to clean up

## Verification

Run the full test suite to confirm all tests pass:

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit
```

Expected output:
- Tests: 253
- Assertions: ~268
- Errors: 0
- Failures: 0
- Skipped: 4

All previously failing tests should now pass:
- `BlogWatchTest::testSetWatchStateWithValidIds`
- `BlogWatchTest::testSetWatchStateAcceptsDifferentBlogAndUserIds`
- All 20 tests in `BlogPost\CreateTest`
