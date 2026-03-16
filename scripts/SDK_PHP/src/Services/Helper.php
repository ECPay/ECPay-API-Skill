<?php

namespace Ecpay\Sdk\Services;

class Helper
{
    /**
     * Dump 並結束
     *
     * @param  mixed  $content
     * @param  string $desc
     * @return void
     */
    public static function dd($content, $desc = '')
    {
        self::dump($content, $desc);
        exit;
    }

    /**
     * Dump
     *
     * @param  mixed  $content
     * @param  string $desc
     * @return void
     */
    public static function dump($content, $desc = '')
    {
        if (!empty($desc)) {
            echo $desc . PHP_EOL;
        }

        var_dump($content);
    }

    /**
     * 判斷是否為 Json 格式
     *
     * @param  string $string
     * @return boolean
     */
    public static function isJson($string)
    {
        $isString = is_string($string);
        $isArray = is_array(json_decode($string, true));
        $isJsonError = (json_last_error() == JSON_ERROR_NONE);
        return $isString && $isArray && $isJsonError;
    }

    /**
     * 以可讀方式印出
     *
     * @param  mixed  $content
     * @param  string $desc
     * @return void
     */
    public static function printReadable($content, $desc = '')
    {
        if (!empty($desc)) {
            echo $desc . '<br>' . PHP_EOL;
        }

        echo '<pre>' . print_r($content, true) . '</pre><br>' . PHP_EOL;
    }
}
