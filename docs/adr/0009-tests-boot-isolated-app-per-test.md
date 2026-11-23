# Tests boot an isolated XenForo app per test

Supersedes [0007](0007-tests-cache-xenforo-app.md).

Starting with `hampel/xenforo-test-framework` 5.x, the framework boots a fresh `Hampel\Testing\App` for every test, so the suite no longer caches one app and swaps it back in by reflection. The `CreatesApplication` trait, the `setUpTraits()`/`tearDown()` overrides, and the save-and-restore of the entity manager are gone. With nothing shared between tests, mocked state can't leak from one to the next.

Tests load only this add-on: `$addonsToLoad = ['TaylorJ/Blogs']`. Class extensions, code event listeners, and Composer autoloaders from other installed add-ons (XFRM and XFMG in the dev container) don't run. That keeps results independent of whatever else is installed on the dev forum. It also stops another add-on that bundles its own PHPUnit from breaking the run at boot. The trade-off is that tests can't catch conflicts with other add-ons. A Feature test calls `assertAddOnClassExtensionsApply()` so our own class extensions are still checked. `addon.cache` is not filtered, so `Job/SimilarBlogPosts` still sees the real list of installed add-ons when it checks for XFES.

## Consequences

- `composer.json` accepts Packagist advisory `PKSA-w9tt-7782-78jx` on `league/flysystem` 1.x. XenForo 2.3 ships Flysystem 1.x, which has no patched release, and the framework's `swapFs()` needs `league/flysystem-memory` 1.x to test the blog header image code against an in-memory filesystem. The exception covers dev only: `build.json` strips `vendor/` and `composer.*` from releases.
- The framework's `phpunit.xml` is used as is, including `failOnDeprecation`, `failOnNotice`, `failOnWarning`, and `failOnRisky`. A new deprecation fails the suite.
- The `$blogIdCounter`-style primary key counters in entity tests aren't strictly needed any more. They stay because they're harmless.
