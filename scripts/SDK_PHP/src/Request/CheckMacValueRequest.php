<?php

namespace Ecpay\Sdk\Request;

use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Services\CheckMacValueService;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;

class CheckMacValueRequest implements RequestInterface
{
    /**
     * CheckMacValueService
     *
     * @var CheckMacValueService
     */
    protected $checkMacValueService;
    public function __construct(CheckMacValueService $checkMacValueService)
    {
        $this->checkMacValueService = $checkMacValueService;
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
        $appened = $this->checkMacValueService->append($source);
        $sorted = ArrayService::naturalSort($appened);
        return $sorted;
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
        return $request;
    }
}
