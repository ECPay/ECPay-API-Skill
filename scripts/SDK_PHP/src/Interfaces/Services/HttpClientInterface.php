<?php

namespace Ecpay\Sdk\Interfaces\Services;

interface HttpClientInterface
{
    /**
     * 執行
     *
     * @param  mixed  $request
     * @param  string $url
     * @return mixed
     *
     * @throws RtnException
     */
    public function run($request, $url);
}
