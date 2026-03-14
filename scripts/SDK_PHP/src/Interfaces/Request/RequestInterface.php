<?php

namespace Ecpay\Sdk\Interfaces\Request;

interface RequestInterface
{
    /**
     * 取得 Request
     *
     * @param  array $source
     * @return mixed
     *
     * @throws RtnException
     */
    public function get($source);

    /**
     * 轉陣列
     *
     * @param  mixed $source
     * @return array
     */
    public function toArray($source);
}
