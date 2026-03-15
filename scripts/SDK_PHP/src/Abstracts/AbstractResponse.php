<?php

namespace Ecpay\Sdk\Abstracts;

use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Interfaces\Response\ResponseInterface;

abstract class AbstractResponse implements ResponseInterface
{
    /**
     * 取得 Response
     *
     * @param  mixed $source
     * @return array
     *
     * @throws RtnException
     */
    public function get($source)
    {
        $parsed = $this->toArray($source);
        $sorted = ArrayService::naturalSort($parsed);

        return $sorted;
    }
}
