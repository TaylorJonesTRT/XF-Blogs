<?php

namespace TaylorJ\Blogs\Tests;

use Hampel\Testing\TestCase as BaseTestCase;
use XF\Entity\User;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var string Path to the XenForo root directory, relative to the add-on directory
     */
    protected $rootDir = '../../../..';

    /**
     * @var array Only this add-on is loaded, so other installed add-ons' class extensions,
     *            listeners and Composer autoloaders stay out of the tests (see ADR 0009)
     */
    protected $addonsToLoad = ['TaylorJ/Blogs'];

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
     * Act as a member with specific global permissions.
     *
     * The visitor is a real User entity built in memory. Anything not granted is denied.
     *
     * @param array $permissions Key-value pairs of [group => [permission => value]]
     * @param int $userId The user ID for the visitor
     * @return User
     */
    protected function mockVisitor(array $permissions = [], $userId = 1)
    {
        return $this->actingAsMember(['user_id' => $userId], $permissions);
    }
}
