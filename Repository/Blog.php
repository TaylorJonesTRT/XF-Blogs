<?php

namespace TaylorJ\Blogs\Repository;

use TaylorJ\Blogs\Entity\Blog as BlogEntity;
use XF\Http\Upload;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Util\File;

class Blog extends Repository
{
    public function setBlogHeaderImagePath($blog_id, Upload $upload)
    {
        $upload->requireImage();

        if (!$upload->isValid($errors))
        {
            throw new PrintableException(reset($errors));
        }

        $abstractedPath = sprintf('data://taylorj_blogs/blog_header_images/%d.jpg', $blog_id);

        try
        {
            $image = \XF::app()->imageManager()->imageFromFile($upload->getTempFile());
            $image->resizeAndCrop(500, 500);

            $tempFile = File::getTempFile();
            if ($tempFile && $image->save($tempFile))
            {
                $output = $tempFile;
            }
            unset($image);

            File::copyFileToAbstractedPath($output, $abstractedPath);
        }
        catch (\Exception $e)
        {
            throw new PrintableException(\XF::phrase('unexpected_error_occurred'));
        }
    }

    public function deleteBlogHeaderImage(BlogEntity $blog)
    {
        File::deleteFromAbstractedPath($blog->getAbstractedHeaderImagePath());
    }

    public function findBlogsByUser($userId)
    {
        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')
            ->where('user_id', $userId)
            ->setDefaultOrder('blog_last_post_date', 'desc');

        return $blogFinder;
    }

    public function batchUpdateBlogPostCounts()
    {
        $blogs = $this->finder('TaylorJ\Blogs:Blog')->fetch();

        /** @var BlogEntity $blog */
        foreach ($blogs AS $blog)
        {
            $blog->fastUpdate('blog_post_count', $blog->getBlogPostCount());
        }
    }
}
