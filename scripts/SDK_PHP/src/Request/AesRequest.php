<?php

namespace Ecpay\Sdk\Request;

use Ecpay\Sdk\Services\AesService;
use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;

class AesRequest implements RequestInterface
{
    /**
     * AesService
     *
     * @var AesService
     */
    protected $aesService;
    public function __construct(AesService $aesService)
    {
        $this->aesService = $aesService;
    }

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
        $encrypted = $this->aesService->encryptData($source);
        $sorted = ArrayService::naturalSort($encrypted);
        $encoded = json_encode($sorted);
        return $encoded;
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
        $decoded = json_decode($request, true);
        return $decoded;
    }
}
