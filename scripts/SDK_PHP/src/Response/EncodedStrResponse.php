<?php

namespace Ecpay\Sdk\Response;

use Ecpay\Sdk\Abstracts\AbstractResponse;

class EncodedStrResponse extends AbstractResponse
{
    /**
     * 轉陣列
     *
     * @param  mixed $response
     * @return array
     */
    public function toArray($response)
    {
        parse_str($response, $parsed);

        return $parsed;
    }
}
