# Implementation Plan: Add Composer Support to TaylorJ/Blogs Addon

## Context

The TaylorJ/Blogs addon (v1.6.0) currently has no dependency management system. This implementation adds Composer support following the [official XenForo 2.1+ tutorial](https://xenforo.com/community/resources/using-composer-packages-in-xenforo-2-1-addons-tutorial.7432/) to enable future unit testing with PHPUnit.

**Why this change is needed:**
- Enables adding PHPUnit and testing frameworks as dev dependencies
- Follows XenForo best practices for addon development
- Prepares the addon for comprehensive unit test coverage
- Sets a pattern for other TaylorJ addons to follow (none currently use Composer)

**Current state:**
- No composer.json, composer.lock, or vendor/ directory exists
- No build.json for production release optimization
- addon.json lacks the `composer_autoload` directive
- This will be the first TaylorJ addon with Composer support

## Implementation Steps

### 1. Create composer.json

**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/composer.json`

```json
{
    "name": "taylorj/xf-blogs",
    "description": "A user blogging system to allow your users to express themselves",
    "type": "library",
    "license": "proprietary",
    "version": "1.6.0",
    "require": {
        "php": ">=7.2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "hampel/xenforo-test-framework": "^2.0"
    },
    "autoload-dev": {
        "psr-4": {
            "TaylorJ\\Blogs\\Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

**Key decisions:**
- PHPUnit ^9.5 for PHP 7.2-8.3 compatibility
- hampel/xenforo-test-framework ^2.0 for XenForo 2.3+ testing
- No production dependencies (keeping it lean)
- Dev dependencies only installed during development

### 2. Create build.json

**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/build.json`

```json
{
    "exec": [
        "composer install --working-dir=_build/upload/src/addons/{addon_id}/ --no-dev --optimize-autoloader --no-interaction"
    ]
}
```

**Purpose:** During `xf-addon:build-release`, this strips dev dependencies (PHPUnit) and optimizes the autoloader for production.

### 3. Modify addon.json

**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/addon.json`

**Change:** Add one line before the closing brace (after line 18):

```json
    "icon": "fa-book",
    "composer_autoload": "vendor/composer"
}
```

**Purpose:** Registers Composer's autoloader with XenForo's class resolution system.

### 4. Modify Setup.php

**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/Setup.php`

**Change:** Add this method after line 16 (after the use trait statements):

```php
    use StepRunnerUninstallTrait;

    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        $vendorDirectory = \sprintf("%s/vendor", $this->addOn->getAddOnDirectory());
        if (!\file_exists($vendorDirectory))
        {
            $errors[] = \XF::phrase('taylorj_blogs_vendor_folder_missing');
        }
    }

    public function installStep1()
```

**Purpose:** Prevents installation if vendor/ folder is missing (e.g., GitHub download without running composer install).

### 5. Create phrase for Setup validation

**Via Admin CP or command line:**

Create phrase with:
- **Key:** `taylorj_blogs_vendor_folder_missing`
- **Text:** `The Blogs add-on vendor folder does not exist. Please run 'composer install' in the add-on directory or re-download the add-on.`

Then export:
```bash
php cmd.php xf:dev-export --addon=TaylorJ/Blogs
```

### 6. Update .gitignore

**File:** `/Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs/.gitignore`

**Change from:**
```
./_releases/
```

**To:**
```
./_releases/
vendor/
```

**Purpose:** Excludes Composer dependencies from git (regenerated via composer.lock).

**Note:** Keep composer.lock tracked in git for reproducible builds.

### 7. Create tests directory structure

```bash
mkdir -p tests
touch tests/.gitkeep
```

**Purpose:** Placeholder for future unit tests (referenced in composer.json autoload-dev).

### 8. Initialize Composer

```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
composer install
```

**Expected output:**
- Installs PHPUnit and hampel/xenforo-test-framework
- Generates composer.lock
- Creates vendor/ directory with autoload files

### 9. Update addon CLAUDE.md (optional but recommended)

Add a "Composer & Testing" section documenting:
- How to run `composer install`
- How to add dependencies
- Build process with `xf-addon:build-release`
- Testing commands (for future use)

## Critical Files to Modify

1. **composer.json** (new) - Dependency definitions
2. **addon.json** (modify) - Add `composer_autoload` directive
3. **build.json** (new) - Production build configuration
4. **Setup.php** (modify) - Add vendor folder validation
5. **.gitignore** (modify) - Exclude vendor/

## Verification Plan

### Step 1: Local Development Test
```bash
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
composer install
ls -la vendor/composer/  # Should see autoload files
```

### Step 2: XenForo Integration Test
```bash
cd /Users/taylorjones/Herd/xf232
php cmd.php xf:addon-rebuild TaylorJ/Blogs
```
Expected: No errors, addon rebuilds successfully.

### Step 3: Validation Test
1. Temporarily rename `vendor/` to `vendor-backup/`
2. In Admin CP, try reinstalling/upgrading the addon
3. Should see error: "vendor folder does not exist"
4. Restore: `mv vendor-backup/ vendor/`

### Step 4: Production Build Test
```bash
php cmd.php xf-addon:build-release TaylorJ/Blogs
```

Extract the generated ZIP from `_releases/` and verify:
- ✅ `vendor/composer/` exists (autoload files)
- ✅ `vendor/phpunit/` does NOT exist (stripped by --no-dev)
- ✅ `vendor/hampel/` does NOT exist (stripped by --no-dev)
- ✅ composer.json present
- ✅ build.json present

### Step 5: Fresh Installation Test (Optional)
Install the built ZIP in a clean XenForo instance to confirm it works without development dependencies.

## Expected Outcomes

**After implementation:**
- Composer infrastructure established following XenForo best practices
- PHPUnit and XenForo test framework available for development
- Production builds automatically exclude dev dependencies
- Vendor folder validated before installation
- Foundation ready for writing unit tests
- Pattern established for other TaylorJ addons

**Production impact:**
- Minimal: Only ~10KB of autoloader files added to releases
- No runtime dependencies or external packages
- Zero changes to addon functionality
- Backward compatible (existing installations unaffected)

## Risk Mitigation

**Low risk areas:**
- Composer autoloader integration (well-documented XenForo feature)
- Build process (isolated to this addon)

**Mitigation for Setup.php validation:**
- Clear error message guides users to solution
- Documented in README for GitHub installations
- Only triggers if vendor/ genuinely missing

## Future Extension Path

This implementation is **Phase 1: Infrastructure Only**

**Phase 2 (Future):** Write actual unit tests
- Create Entity tests (Blog, BlogPost)
- Create Service tests (Blog/Create, BlogPost/Create)
- Create Repository tests
- Set up phpunit.xml configuration

**Phase 3 (Future):** CI/CD integration
- GitHub Actions workflow
- Automated test runs
- Code coverage reporting

## References

- [XenForo Composer Tutorial](https://xenforo.com/community/resources/using-composer-packages-in-xenforo-2-1-addons-tutorial.7432/)
- [hampel/xenforo-test-framework](https://packagist.org/packages/hampel/xenforo-test-framework)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
