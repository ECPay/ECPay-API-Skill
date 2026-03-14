<?php

namespace Ecpay\Sdk\Response;

use Ecpay\Sdk\Abstracts\AbstractResponse;

class JsonResponse extends AbstractResponse
{
    /**
     * 轉陣列
     *
     * @param  mixed $response
     * @return array
     */
    public function toArray($response)
    {
        $decoded = json_decode($response, true);

        return $decoded;
    }
}
