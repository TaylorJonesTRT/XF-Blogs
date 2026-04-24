<?php

namespace TaylorJ\Blogs\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Hampel\Testing\TestCase as BaseTestCase;
use Hampel\Testing\Mvc\Entity\Manager as TestingManager;
use Mockery;

abstract class TestCase extends BaseTestCase
{
	use CreatesApplication;

	/**
	 * @var TestingManager|null Cached testing entity manager
	 */
	protected static $testingEm = null;

	/**
	 * @var \XF\Db\AbstractAdapter|null Original database adapter before mocking
	 */
	protected $originalDb = null;

	/**
	 * @var \XF\Mvc\Entity\Manager|null Original entity manager before mocking
	 */
	protected $originalEm = null;

	/**
	 * Load mock data from a JSON file in the mock directory.
	 *
	 * @param string $file Filename relative to tests/mock/
	 * @return array
	 */
	protected function getMockData($file)
	{
		$path = __DIR__ . '/mock/' . $file;
		return json_decode(file_get_contents($path), true);
	}

	/**
	 * Create a mock visitor with specific permissions.
	 *
	 * @param array $permissions Key-value pairs of [group => [permission => value]]
	 * @param int $userId The user ID for the mock visitor
	 * @return \Mockery\MockInterface
	 */
	protected function mockVisitor(array $permissions = [], $userId = 1)
	{
		$visitor = Mockery::mock('MockVisitor');
		$visitor->user_id = $userId;

		$visitor->shouldReceive('offsetGet')->with('user_id')->andReturn($userId)->byDefault();
		$visitor->shouldReceive('__get')->with('user_id')->andReturn($userId)->byDefault();
		$visitor->shouldReceive('__isset')->andReturn(true)->byDefault();
		$visitor->shouldReceive('offsetExists')->andReturn(true)->byDefault();

		$visitor->shouldReceive('hasPermission')->andReturnUsing(
			function ($group, $permission) use ($permissions) {
				return isset($permissions[$group][$permission])
					? $permissions[$group][$permission]
					: false;
			}
		);

		$visitor->shouldReceive('canReport')->andReturn(true)->byDefault();

		$this->setVisitor($visitor);

		return $visitor;
	}

	/**
	 * Set the XF visitor to a mock user entity.
	 *
	 * @param \XF\Entity\User|\Mockery\MockInterface $user
	 */
	protected function setVisitor($user)
	{
		$property = new \ReflectionProperty(\XF::class, 'visitor');
		$property->setAccessible(true);
		$property->setValue(null, $user);
	}

	/**
	 * Store the current database and entity manager before mocking.
	 *
	 * Call this BEFORE calling setUpEntityManager() to preserve the real instances.
	 */
	protected function storeOriginalDbAndEm()
	{
		if ($this->app && !$this->originalDb && !$this->originalEm) {
			$this->originalDb = $this->app->db();
			$this->originalEm = $this->app->em();
		}
	}

	/**
	 * Restore the real database and entity manager after mocking.
	 *
	 * Call this in tearDown() if your test called setUpEntityManager() to prevent
	 * mocked database/entity manager state from leaking into subsequent test classes.
	 */
	protected function restoreEntityManager()
	{
		if ($this->app && $this->originalDb && $this->originalEm) {
			$this->swap('db', $this->originalDb);
			$this->swap('em', $this->originalEm);
			$this->originalDb = null;
			$this->originalEm = null;
		}
	}

	/**
	 * Override setUpTraits to skip setUpEntityManager.
	 * The testing framework's setUpEntityManager creates a new Manager that breaks
	 * XenForo's Extension class resolution. Instead, we skip it entirely and use
	 * the standard entity manager for instantiateEntity calls. Test classes that
	 * need mockEntity/mockFinder/mockRepository should call setUpEntityManager()
	 * explicitly in their setUp method.
	 */
	protected function setUpTraits()
	{
		$uses = array_flip($this->classUsesRecursive(static::class));

		// SKIP setUpEntityManager - it breaks class extension resolution.
		// Tests that need mocking can call setUpEntityManager() explicitly.

		// SKIP setUpExtension - swapping the extension creates a new instance with empty
		// inverseExtensionMap, causing instantiateEntity to fail. We use the extension
		// that was created during app setup.

		if (isset($uses[\Hampel\Testing\Concerns\InteractsWithLanguage::class])) {
			$this->setUpLanguage();
		}

		if (isset($uses[\Hampel\Testing\Concerns\InteractsWithOptions::class])) {
			$this->setUpOptions();
		}

		if (isset($uses[\Hampel\Testing\Concerns\InteractsWithTime::class])) {
			$this->setUpTime();
		}

		return $uses;
	}

	/**
	 * Override tearDown to prevent destroying the shared app instance.
	 */
	protected function tearDown(): void
	{
		// Restore visitor
		try {
			$property = new \ReflectionProperty(\XF::class, 'visitor');
			$property->setAccessible(true);
			$property->setValue(null, null);
		} catch (\Throwable $e) {
			// visitor property may not exist
		}

		// Run beforeApplicationDestroyed callbacks (options restoration etc.)
		if ($this->app) {
			foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
				call_user_func($callback);
			}
		}

		$this->setUpHasRun = false;

		// Clean up Mockery
		if (class_exists('Mockery')) {
			if ($container = Mockery::getContainer()) {
				$this->addToAssertionCount($container->mockery_getExpectationCount());
			}
			Mockery::close();
		}

		// Reset Carbon test time
		if (class_exists(Carbon::class)) {
			Carbon::setTestNow();
		}
		if (class_exists(CarbonImmutable::class)) {
			CarbonImmutable::setTestNow();
		}

		$this->afterApplicationCreatedCallbacks = [];
		$this->beforeApplicationDestroyedCallbacks = [];

		// NOTE: We intentionally do NOT call parent::tearDown() and do NOT
		// destroy \XF::$app, because XenForo cannot re-create the app instance.
	}
}
