# Tests reuse a single cached XenForo app instance

**Status:** superseded by [0009](0009-tests-boot-isolated-app-per-test.md)

XenForo supports only one app instance per process, but `hampel/xenforo-test-framework` builds one per test. `tests/CreatesApplication.php` keeps the first `Hampel\Testing\App` in a static property and injects it back into `\XF::$app` through reflection for later tests. The cost is that state can leak between tests, which is why entity tests use incrementing IDs with `instantiateEntity()`.
