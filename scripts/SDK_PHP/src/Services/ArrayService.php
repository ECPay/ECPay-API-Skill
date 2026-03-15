<?php

namespace Ecpay\Sdk\Services;

class ArrayService
{
    /**
     * 自然排序
     *
     * @param  array $source
     * @return array
     */
    public static function naturalSort($source)
    {
        uksort($source, function ($first, $second) {

                return strcasecmp($first, $second);
        });
        return $source;
    }
}
