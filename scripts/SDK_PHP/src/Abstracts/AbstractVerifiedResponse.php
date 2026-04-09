<?php

namespace Ecpay\Sdk\Abstracts;

use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Services\CheckMacValueService;
use Ecpay\Sdk\Interfaces\Response\ResponseInterface;

abstract class AbstractVerifiedResponse implements ResponseInterface
{
    /**
     * 驗證 Service
     *
     * @var CheckMacValueService
     */
    protected $checkMacValueService;

    /**
     * 注入驗證 Service
     *
     * @param CheckMacValueService $checkMacValueService
     */
    public function __construct(CheckMacValueService $checkMacValueService)
    {
        $this->checkMacValueService = $checkMacValueService;
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
        $parsed = $this->toArray($source);
        if (!$this->verify($parsed)) {
            throw new RtnException(106);
        }
        $sorted = ArrayService::naturalSort($parsed);
        return $sorted;
    }

    /**
     * 驗證回應
     *
     * @param  array $source
     * @return boolean
     */
    public function verify($source)
    {
        return $this->checkMacValueService->verify($source);
    }
}
