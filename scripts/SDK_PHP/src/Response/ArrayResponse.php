<?php

namespace Ecpay\Sdk\Response;

use Ecpay\Sdk\Abstracts\AbstractResponse;

class ArrayResponse extends AbstractResponse
{
    /**
     * 轉陣列
     *
     * @param  mixed $response
     * @return array
     */
    public function toArray($response)
    {
        return $response;
    }
}
