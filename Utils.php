<?php

namespace TaylorJ\Blogs;

class Utils
{
    
    public static function hours()
    {
        $hours = [];
        for ($i = 0; $i < 24; $i++)
        {
            $hh = str_pad($i, 2, '0', STR_PAD_LEFT);
            $hours[$hh] = $hh;
        }

        return $hours;
    }

    public static function minutes()
    {
        $minutes = [];
        for ($i = 0; $i < 60; $i += 5)
        {
            $mm = str_pad($i, 2, '0', STR_PAD_LEFT);
            $minutes[$mm] = $mm;
        }

        return $minutes;
    }

    public static function repo($class)
    {
        return \XF::app()->repository($class);
    }

    public static function log($msg)
    {
        \XF::logError('[TaylorJ\Blogs] --> '.$msg);
    }
}