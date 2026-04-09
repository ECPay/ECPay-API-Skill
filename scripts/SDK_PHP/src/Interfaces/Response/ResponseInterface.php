<?php

namespace Ecpay\Sdk\Interfaces\Response;

use Ecpay\Sdk\Exceptions\RtnException;

interface ResponseInterface
{
    /**
     * 取得 Response
     *
     * @param  mixed $source
     * @return array
     *
     * @throws RtnException
     */
    public function get($source);

    /**
     * 轉陣列
     *
     * @param  mixed $response
     * @return array
     */
    public function toArray($response);
}
