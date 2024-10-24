<?php

namespace TaylorJ\Blogs\Repository;

use XF\Mvc\Entity\Repository;
use XF\Util\File;

class Blog extends Repository
{

    public function setBlogHeaderImagePath($blog_id, $upload)
    {
        $upload->requireImage();

        if (!$upload->isValid($errors)) {
            throw new \XF\PrintableException(reset($errors));
        }

        $dataDir = \XF::app()->config('externalDataPath');
        $dataDir .= "://taylorj_blogs/blog_header_images/" . $blog_id . ".jpg";

        try {
            $image = \XF::app()->imageManager->imageFromFile($upload->getTempFile());

            $tempFile = \XF\Util\File::getTempFile();
            if ($tempFile && $image->save($tempFile)) {
                $output = $tempFile;
            }
            unset($image);

            \XF\Util\File::copyFileToAbstractedPath($output, $dataDir);
        } catch (Exception $e) {
            throw new \XF\PrintableException(\XF::phrase('unexpected_error_occurred'));
        }
    }

    public function findBlogsByUser($userId)
    {
        $blogFinder = $this->finder('TaylorJ\Blogs:Blog')
            ->where('user_id', $userId)
            ->setDefaultOrder('blog_last_post_date', 'desc');

        return $blogFinder;
    }
}

