<?php

namespace Ecpay\Sdk\Response;

use Ecpay\Sdk\Services\Helper;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Abstracts\AbstractAesResponse;

class AesJsonResponse extends AbstractAesResponse
{
    /**
     * 轉陣列
     *
     * @param  mixed $response
     * @return array
     *
     * @throws RtnException
     */
    public function toArray($response)
    {
        if (!Helper::isJson($response)) {
            throw new RtnException(103);
        }
        $decode = json_decode($response, true);
        return $this->aesService->decryptData($decode);
    }
}
