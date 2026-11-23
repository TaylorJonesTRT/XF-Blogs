<?php

namespace TaylorJ\Blogs\Tests\Feature;

use TaylorJ\Blogs\Tests\TestCase;

class ClassExtensionsTest extends TestCase
{
    /**
     * Every class extension this add-on registers still resolves. A XenForo release that renames
     * or removes an extended class fails here instead of silently skipping our code.
     */
    public function testAddOnClassExtensionsApply()
    {
        $this->assertAddOnClassExtensionsApply();
    }
}
