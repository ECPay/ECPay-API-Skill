<?php

namespace Ecpay\Sdk\Abstracts;

use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Exceptions\TransException;
use Ecpay\Sdk\Interfaces\Response\ResponseInterface;

abstract class AbstractAesResponse implements ResponseInterface
{
    /**
     * AesService
     *
     * @var AesService
     */
    protected $aesService;

    public function __construct($aesService)
    {
        $this->aesService = $aesService;
    }

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
        $this->hasTransError($source);
        $parsed = $this->toArray($source);
        $sorted = ArrayService::naturalSort($parsed);

        return $sorted;
    }

    /**
     * Has transaction errors
     *
     * @param  string $response
     * @return void
     *
     * @throws TransException
     */
    protected function hasTransError($response)
    {
        $decoded = json_decode($response, true);
        if (isset($decoded['TransCode'])) {
            $code = intval($decoded['TransCode']);
            $message = $decoded['TransMsg'];
            if ($code !== 1) {
                throw new TransException($code, $message);
            }
        }
    }
}
