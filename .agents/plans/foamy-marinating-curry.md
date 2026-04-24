# Upgrade PHPUnit to v10 and Test Framework to v3.0

## Context

The TaylorJ/Blogs addon currently uses PHPUnit 9.6 and hampel/xenforo-test-framework v2.1, which has a known incompatibility with XenForo 2.3's JobParams API. This prevents 4 Job tests from running. The framework v3.0 was specifically released to support XenForo 2.3 and fixes this compatibility issue.

**Current State:**
- PHPUnit 9.6.34 with hampel/xenforo-test-framework 2.1.0
- 87 tests passing (Entity: 83, Service: 11, Repository: 9, Utils: 16)
- 4 Job tests excluded due to framework incompatibility
- PHP 8.3.30 available (meets v3.0 requirement of PHP 8.1+)

**Goals:**
- Upgrade to PHPUnit 10 and test framework v3.0
- Enable the 4 Job tests that are currently failing
- Maintain all existing test coverage
- Update documentation to reflect the changes

## Implementation Steps

### 1. Create Backups

Create backups of critical files before making changes:

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
cp composer.json composer.json.backup
cp composer.lock composer.lock.backup
cp phpunit.xml phpunit.xml.backup
cp tests/TestCase.php tests/TestCase.php.backup
cp tests/CreatesApplication.php tests/CreatesApplication.php.backup
```

Run current tests and save baseline:
```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity tests/Unit/Repository tests/Unit/Utils tests/Unit/Service > test-results-before.txt 2>&1
```

### 2. Update Dependencies

**Edit composer.json** to update the require-dev section:

```json
"require-dev": {
    "phpunit/phpunit": "^10.0",
    "hampel/xenforo-test-framework": "^3.0"
}
```

**Run composer update:**
```bash
composer update phpunit/phpunit hampel/xenforo-test-framework --with-all-dependencies
```

**Verify versions:**
```bash
./vendor/bin/phpunit --version  # Should show PHPUnit 10.x
composer show hampel/xenforo-test-framework  # Should show 3.0.3
```

### 3. Migrate PHPUnit Configuration

Run PHPUnit's built-in migration tool:
```bash
./vendor/bin/phpunit --migrate-configuration
```

**Note:** The phpunit.xml already uses the PHPUnit 10 schema URL, so this may not make changes. Review the output and compare with the backup to verify.

### 4. Update Test Infrastructure

**Check for framework v3.0 stub files:**
```bash
ls -la vendor/hampel/xenforo-test-framework/stubs/
cat vendor/hampel/xenforo-test-framework/README.md
```

**Compare with our custom test files:**
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/TestCase.php`
- `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/tests/CreatesApplication.php`

**If v3.0 stubs exist, selectively merge changes while keeping our customizations:**
- Keep app caching logic in CreatesApplication
- Keep setUpTraits() override that skips setUpEntityManager/setUpExtension
- Keep custom tearDown() that preserves app instance
- Keep mockVisitor() and getMockData() helpers
- Adopt any new v3.0 trait methods or signatures
- Adopt extension class handling updates (v3.0.2 introduced new extension class logic)

**Key areas to review:**
- Extension class compatibility (v3.0.2 expanded extension class with global static maps)
- Mail transport changes (v3.0 reimplemented as TestTransport)
- Job\Manager updates for JobParams support

### 5. Test Execution

**Start with a single test file:**
```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/BlogTest.php
```

**Run all non-Job test suites:**
```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Service/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Repository/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/UtilsTest.php
```

Expected: 87 tests pass with same assertions as before.

**Critical test - Enable Job tests:**
```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Job/PostBlogPostTest.php
```

Expected: 4 Job tests pass without the previous JobParams fatal error.

**Run complete test suite:**
```bash
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/
```

Expected: 123 tests (87 existing + 4 Job + potentially more) all pass.

### 6. Update Documentation

**Update tests/README.md:**
- Change header to mention PHPUnit 10+ and framework v3.0
- Remove "(currently incompatible with XenForo 2.3)" note from Job tests section
- Update test run commands to include Job tests directory
- Update total test count to 123 tests
- Remove entire "Known Issues" section about Job test incompatibility
- Update "Future Improvements" section to remove Job test item

**Update CLAUDE.md:**
- Update "Testing" section header to mention PHPUnit 10+ and framework v3.0
- Update test coverage to show 123 tests including Job tests
- Remove "Known Issues" section about Job test incompatibility
- Update "Running Tests" commands to include Job tests
- Add note to Background Jobs section that Job tests are now working

### 7. Final Verification

**Clean test run with verbose output:**
```bash
composer dump-autoload
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/ --testdox
```

**Verify coverage still works:**
```bash
XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/Unit/ --coverage-text
```

**Test XenForo integration:**
```bash
cd /Users/taylorjones/Herd/xf232
php cmd.php xf:addon-rebuild TaylorJ/Blogs
```

### 8. Remove Backup Files

Once everything is confirmed working:
```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
rm -f composer.json.backup composer.lock.backup phpunit.xml.backup
rm -f tests/TestCase.php.backup tests/CreatesApplication.php.backup
rm -f test-results-before.txt
```

## Critical Files

- **composer.json** - Update dependency versions
- **composer.lock** - Will be regenerated by composer update
- **phpunit.xml** - May need schema migration (though already uses v10 schema)
- **tests/TestCase.php** - May need updates from framework v3.0 stubs
- **tests/CreatesApplication.php** - May need updates from framework v3.0 stubs
- **tests/Unit/Job/PostBlogPostTest.php** - Primary validation target for JobParams fix
- **tests/README.md** - Documentation updates
- **CLAUDE.md** - Documentation updates

## Verification Checklist

- [ ] PHPUnit version is 10.x
- [ ] Framework version is 3.0.3
- [ ] All 87 existing tests still pass
- [ ] All 4 Job tests now pass (no JobParams errors)
- [ ] Total test count is 123 tests
- [ ] No deprecation warnings in test output
- [ ] Code coverage reporting still works
- [ ] XenForo addon still loads (xf:addon-rebuild succeeds)
- [ ] Documentation updated (README.md, CLAUDE.md)
- [ ] Backup files removed

## Rollback Plan

If critical issues arise:

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
cp composer.json.backup composer.json
cp composer.lock.backup composer.lock
cp phpunit.xml.backup phpunit.xml
cp tests/TestCase.php.backup tests/TestCase.php
cp tests/CreatesApplication.php.backup tests/CreatesApplication.php
composer install
```

## Troubleshooting

**If Job tests still fail with JobParams error:**
- Verify framework version is actually 3.0.x: `composer show hampel/xenforo-test-framework`
- Try: `rm composer.lock && composer install`

**If tests fail with setUp/tearDown signature errors:**
- Add return type hints: `protected function setUp(): void`

**If extension class resolution breaks:**
- Review framework v3.0.2 Extension.php changes
- May need to adjust setUpTraits() override

**If PHPUnit 10 not found:**
- Try: `rm -rf vendor/ && composer install`
