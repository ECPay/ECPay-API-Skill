<?php

namespace Ecpay\Sdk\Response;

use Ecpay\Sdk\Abstracts\AbstractVerifiedResponse;

class VerifiedArrayResponse extends AbstractVerifiedResponse
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
