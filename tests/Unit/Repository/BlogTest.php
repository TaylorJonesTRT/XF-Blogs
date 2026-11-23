<?php

namespace TaylorJ\Blogs\Tests\Unit\Repository;

use TaylorJ\Blogs\Entity\Blog as BlogEntity;
use TaylorJ\Blogs\Finder\Blog as BlogFinder;
use TaylorJ\Blogs\Tests\TestCase;
use XF\Http\Upload;
use XF\PrintableException;

class BlogTest extends TestCase
{
    /**
     * Write a real JPEG of the given size and wrap it as an upload.
     *
     * @return Upload
     */
    protected function makeImageUpload(int $width, int $height)
    {
        $path = $this->createTempDirectory() . '/header.jpg';

        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);

        return new Upload($path, 'header.jpg');
    }

    // ---- findBlogsByUser() ----

    public function testFindBlogsByUserReturnsFinderForUserId()
    {
        $mockFinder = $this->mockFinder('TaylorJ\Blogs:Blog', function ($mock)
        {
            $mock->shouldReceive('byUser')->with(42)->once()->andReturnSelf();
            $mock->shouldReceive('latestFirst')->once()->andReturnSelf();
        });

        $repo = $this->mockRepository('TaylorJ\Blogs:Blog', function ($mock) use ($mockFinder)
        {
            $mock->shouldReceive('findBlogsByUser')->with(42)->passthru();
            $mock->shouldReceive('finder')->with(BlogFinder::class)->andReturn($mockFinder);
        });

        $result = $repo->findBlogsByUser(42);
        $this->assertNotNull($result);
    }

    // ---- deleteBlogHeaderImage() ----

    public function testDeleteBlogHeaderImageRemovesHeaderFile()
    {
        $this->swapFs('data');
        $this->app()->fs()->write('data://taylorj_blogs/blog_header_images/100.jpg', 'header');
        $this->assertFsHas('data://taylorj_blogs/blog_header_images/100.jpg');

        /** @var BlogEntity $blog */
        $blog = $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', ['blog_id' => 100]);

        $this->app()->repository('TaylorJ\Blogs:Blog')->deleteBlogHeaderImage($blog);

        $this->assertFsHasNot('data://taylorj_blogs/blog_header_images/100.jpg');
    }

    public function testDeleteBlogHeaderImageIgnoresMissingFile()
    {
        $this->swapFs('data');

        /** @var BlogEntity $blog */
        $blog = $this->app()->em()->instantiateEntity('TaylorJ\Blogs:Blog', ['blog_id' => 101]);

        $this->app()->repository('TaylorJ\Blogs:Blog')->deleteBlogHeaderImage($blog);

        $this->assertFsHasNot('data://taylorj_blogs/blog_header_images/101.jpg');
    }

    // ---- handleHeaderImageFromInput() ----

    public function testHandleHeaderImageFromInputDeleteHeaderRemovesFileAndClearsFlag()
    {
        $this->swapFs('data');
        $this->app()->fs()->write('data://taylorj_blogs/blog_header_images/102.jpg', 'header');
        $this->assertFsHas('data://taylorj_blogs/blog_header_images/102.jpg');

        $blog = \Mockery::mock(BlogEntity::class);
        $blog->shouldReceive('getAbstractedHeaderImagePath')
            ->andReturn('data://taylorj_blogs/blog_header_images/102.jpg');
        $blog->shouldReceive('fastUpdate')->with('blog_has_header', '0')->once();

        $this->app()->repository('TaylorJ\Blogs:Blog')->handleHeaderImageFromInput($blog, 'delete_header', null);

        $this->assertFsHasNot('data://taylorj_blogs/blog_header_images/102.jpg');
    }

    // ---- batchUpdateBlogPostCounts() ----

    public function testBatchUpdateBlogPostCountsMethodExists()
    {
        $repo = $this->app()->repository('TaylorJ\Blogs:Blog');
        $this->assertTrue(method_exists($repo, 'batchUpdateBlogPostCounts'));
    }

    public function testBatchUpdateBlogPostCountsIsCallable()
    {
        // We can't fully test without database, but verify it's callable
        $repo = $this->app()->repository('TaylorJ\Blogs:Blog');
        $reflection = new \ReflectionMethod($repo, 'batchUpdateBlogPostCounts');

        $this->assertTrue($reflection->isPublic());
        $this->assertCount(0, $reflection->getParameters());
    }

    // ---- setBlogHeaderImagePath() ----

    public function testSetBlogHeaderImagePathWritesCroppedJpeg()
    {
        $this->swapFs('data');

        $this->app()->repository('TaylorJ\Blogs:Blog')->setBlogHeaderImagePath(7, $this->makeImageUpload(800, 600));

        $this->assertFsHas('data://taylorj_blogs/blog_header_images/7.jpg');

        $size = getimagesizefromstring($this->app()->fs()->read('data://taylorj_blogs/blog_header_images/7.jpg'));
        $this->assertSame([500, 500], [$size[0], $size[1]]);
    }

    public function testSetBlogHeaderImagePathRejectsNonImage()
    {
        $this->swapFs('data');

        $path = $this->createTempDirectory() . '/header.jpg';
        file_put_contents($path, 'not an image');

        try
        {
            $this->app()->repository('TaylorJ\Blogs:Blog')->setBlogHeaderImagePath(8, new Upload($path, 'header.jpg'));
            $this->fail('Expected a PrintableException for a non-image upload');
        }
        catch (PrintableException $e)
        {
            $this->assertFsHasNot('data://taylorj_blogs/blog_header_images/8.jpg');
        }
    }
}
