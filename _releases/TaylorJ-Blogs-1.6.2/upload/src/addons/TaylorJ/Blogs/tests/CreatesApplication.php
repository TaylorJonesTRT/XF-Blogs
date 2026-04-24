<?php

namespace TaylorJ\Blogs\Tests;

trait CreatesApplication
{
	/**
	 * @var string Path to XenForo root directory relative to addon root
	 */
	protected $rootDir = '../../../..';

	/**
	 * @var array Add-on IDs to load (empty = all)
	 */
	protected $addonsToLoad = [];

	/**
	 * @var \XF\App|null Cached app instance to avoid re-creation
	 */
	protected static $cachedApp = null;

	/**
	 * Creates the application for testing.
	 * XenForo does not support creating multiple app instances, so we cache it.
	 *
	 * @return \XF\App
	 */
	public function createApplication()
	{
		if (static::$cachedApp !== null) {
			// Re-inject the cached app into XF
			$reflection = new \ReflectionProperty(\XF::class, 'app');
			$reflection->setAccessible(true);

			if ($reflection->getValue() === null) {
				$reflection->setValue(null, static::$cachedApp);
			}

			return static::$cachedApp;
		}

		require_once("{$this->rootDir}/src/XF.php");

		\XF::start($this->rootDir);

		$options = [];
		$options['xf-addons'] = $this->addonsToLoad;

		static::$cachedApp = \XF::setupApp('Hampel\Testing\App', $options);

		return static::$cachedApp;
	}
}
