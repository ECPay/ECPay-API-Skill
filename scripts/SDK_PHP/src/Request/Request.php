<?php

namespace Ecpay\Sdk\Request;

use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;

class Request implements RequestInterface
{
    /**
     * 取得 Request
     *
     * @param  array $source
     * @return mixed
     *
     * @throws RtnException
     */
    public function get($source)
    {
        $sorted = ArrayService::naturalSort($source);
        return $sorted;
    }

    /**
     * 轉陣列
     *
     * @param  mixed $source
     * @return array
     */
    public function toArray($source)
    {
        $request = $this->get($source);
        return $request;
    }
}
