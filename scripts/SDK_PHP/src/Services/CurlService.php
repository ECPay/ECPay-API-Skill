<?php

namespace Ecpay\Sdk\Services;

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Interfaces\Services\HttpClientInterface;

class CurlService implements HttpClientInterface
{
    /**
     * Headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * 設定 Header
     *
     * @param  array $headers
     * @return void
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * 執行
     *
     * @param  mixed  $request
     * @param  string $url
     * @return mixed
     *
     * @throws RtnException
     */
    public function run($request, $url)
    {
        if (($ch = curl_init()) === false) {
            throw new RtnException(107);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        if (($rs = curl_exec($ch)) === false) {
            throw new RtnException(108);
        }
        curl_close($ch);
        return $rs;
    }
}
