# Fix BlogPost Service Tests

## Context

The `tests/Unit/Service/BlogPost/` suite has **24 failures**: 23 parse errors in `CreateTest` (all caused by unresolved merge conflicts in `Create.php`) and 1 assertion failure in `EditTest::testSetTitleUpdatesPostTitle`.

## Problem 1: `Service/BlogPost/Create.php` — Unresolved Merge Conflict (23 errors)

Git shows `UU Service/BlogPost/Create.php`. The file contains 5 pairs of `<<<<<<<`/`=======`/`>>>>>>>` markers, making it unparseable by PHP. Every `CreateTest` test fails with `ParseError` on line 79.

**Resolution strategy — take the space-indented (98efb66) version for all duplicated blocks:**

The conflict is between a tab-indented HEAD branch and a space-indented 98efb66 branch. The non-conflicted code throughout the file already uses spaces, so we'll use spaces consistently.

Key decisions per conflict region:

| Lines | HEAD | 98efb66 | Choose | Reason |
|-------|------|---------|--------|--------|
| 79–141 | `setTags`, `setBlogPostState`, `setScheduledPostDateTime` with `\XF::$time` timezone | Same methods with `\XF::options()->guestTimeZone` | **98efb66** | `\XF::$time` is a Unix timestamp (int) — invalid as a timezone string. Test also uses `setOption('guestTimeZone', 'UTC')`. |
| 152–168 | `finalSteps()` (tabs) | `finalSteps()` (spaces) | **98efb66** | Identical logic, consistent indentation |
| 175–193 | Tag error handling (tabs) | Tag error handling (spaces) | **98efb66** | Identical logic |
| 204–237 | Thread creation without `afterResourceThreadCreated` | Thread creation with `afterResourceThreadCreated` + unused `$commentThread` var | **98efb66** (without `$commentThread`) | Edit.php calls `afterResourceThreadCreated`, so Create should too. Drop unused variable. |
| 249–266 | Duplicate `sendNotifications()` | `insertJob()` method | **98efb66** | `insertJob()` is needed (tested + called by `finalSteps`). `sendNotifications()` already exists at line 268. |

### File to modify
- `Service/BlogPost/Create.php` — Remove all conflict markers, keep the resolved code

## Problem 2: `EditTest::testSetTitleUpdatesPostTitle` — Title Not Updating (1 failure)

**Root cause:** The `BlogPost` entity has a `verifyBlogPostTitle()` method (line 82 of `Entity/BlogPost.php`) that checks:
```php
if (strlen($value) > \XF::options()->taylorjBlogsBlogPostTitleLength)
```

The option `taylorjBlogsBlogPostTitleLength` has a default of `200`, but in the test environment it's not set, so it returns `null`/`0`. Since `strlen('Updated Blog Post Title') > 0` is true, the verify rejects the title and the entity keeps its old value `'Existing Blog Post'`.

**Fix:** Add `$this->setOption('taylorjBlogsBlogPostTitleLength', 200);` before calling `setTitle()` in `EditTest::testSetTitleUpdatesPostTitle`.

The same fix will also be needed in `CreateTest::testSetTitleUpdatesPostTitle` once the merge conflict is resolved, since it has the same issue (calls `setTitle` without setting the option).

### Files to modify
- `tests/Unit/Service/BlogPost/EditTest.php:41` — add `setOption` call
- `tests/Unit/Service/BlogPost/CreateTest.php:38` — add `setOption` call

## Verification

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Service/BlogPost/
```

All 43 tests (23 Create + 20 Edit) should pass with 0 errors and 0 failures.
