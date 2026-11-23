# Upgrade hampel/xenforo-test-framework 3.0.3 → 5.41

Decisions from a grill-with-docs session on 2026-10-27. Rationale is in [ADR 0009](adr/0009-tests-boot-isolated-app-per-test.md).

## Baseline (before any change)

Framework 3.0.3, PHPUnit 10.5.63, PHP 8.5.12 in the container, XenForo `utf8.php` guarded.
`Tests: 271, Assertions: 326, Deprecations: 8, Skipped: 4`, 0 errors. The "5 errors" in `tests/README.md` are stale.

## Stage 1: Bump the toolchain, keep the old boot

- `composer.json` `require-dev`: `hampel/xenforo-test-framework: ^5.41`, `phpunit/phpunit: ^12.0`, `mockery/mockery: ^1.6`.
- Convert the two `@dataProvider` doc-comments in `tests/Unit/Entity/BlogPostTest.php` (~L422, ~L449) to `#[DataProvider]`.
- `composer update` inside the container, then run the suite. Record the result as the new baseline. Leave the `failOn*` flags off for now.

## Stage 2: Adopt the 5.x boot

- Delete `tests/CreatesApplication.php` and its `use` line.
- In `tests/TestCase.php`, set `$rootDir = '../../../..'` and `$addonsToLoad = ['TaylorJ/Blogs']`.
- Remove these overrides and helpers: `setUpTraits()`, `tearDown()`, `storeOriginalDbAndEm()`, `restoreEntityManager()`, `setVisitor()`. Remove their call sites in the Service and Repository tests.
- Keep the `mockVisitor($permissions, $userId)` signature, but reimplement it on `InteractsWithVisitor::actingAsMember()`, which gives a real User entity with permissions granted in memory. Investigate any test that only passed against the Mockery stand-in.
- Keep the incrementing primary key counters in the entity tests.
- Fix the stale `@var \XFRM\XF\Entity\User` docblock at `Pub/Controller/Author.php:16`.
- Run the suite.

## Stage 3: Turn the Flysystem skips into real tests

- Add `league/flysystem: ^1.1.4` and `league/flysystem-memory: ^1.0` to `require-dev`. Add `config.policy.advisories.ignore-id` for `PKSA-w9tt-7782-78jx`, with the reason.
- Rewrite the 4 skipped tests against `swapFs()`:
  - `tests/Unit/UtilsTest.php` `deleteBlogHeaderFiles`
  - `tests/Unit/Repository/BlogTest.php` `deleteBlogHeaderImage`, `setBlogHeaderImagePath`
  - `tests/Unit/Service/Blog/EditTest.php` `deleteBlogHeaderImageFiles`

## Stage 4: Adopt the framework's phpunit.xml and turn on strict mode

- Replace `phpunit.xml` with the framework's version (Feature suite, whole-add-on `<source>`, `failOnDeprecation`, `failOnNotice`, `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite`).
- Add `tests/Feature/ClassExtensionsTest.php`, which calls `assertAddOnClassExtensionsApply()`.
- The suite must be fully green: no deprecations, notices, warnings, or risky tests, and no skips left.

## Docs

- ADR 0009 written. ADR 0007 marked superseded. Done during planning.
- `AGENTS.md` Testing Notes: framework v5, PHPUnit 12, the ADR 0009 link, and no "call `setUpEntityManager()` in `setUp()`" if that stops being needed.
- `tests/README.md`: version header, last-run line, and rewrites of "XenForo App Caching", "Extension Class Resolution", "Test Isolation and Mocked State", and "Future Improvements" (the Job item is done). Add a Feature directory to the tree.

## Out of scope

- No version bump: nothing that ships changes (`build.json` strips vendor, tests, composer files, and phpunit.xml).
- No changes to `CONTEXT.md`.
