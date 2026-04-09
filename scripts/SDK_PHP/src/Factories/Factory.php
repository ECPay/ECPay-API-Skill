<?php

namespace Ecpay\Sdk\Factories;

use ReflectionClass;
use Ecpay\Sdk\Request\Request;
use Ecpay\Sdk\Request\AesRequest;
use Ecpay\Sdk\Services\AesService;
use Ecpay\Sdk\Response\StrResponse;
use Ecpay\Sdk\Services\CurlService;
use Ecpay\Sdk\Services\HtmlService;
use Ecpay\Sdk\Services\PostService;
use Ecpay\Sdk\Response\JsonResponse;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Response\AesStrResponse;
use Ecpay\Sdk\Response\AesJsonResponse;
use Ecpay\Sdk\Services\ManualFormService;
use Ecpay\Sdk\Response\EncodedStrResponse;
use Ecpay\Sdk\Request\CheckMacValueRequest;
use Ecpay\Sdk\Abstracts\AbstractAesResponse;
use Ecpay\Sdk\Services\CheckMacValueService;
use Ecpay\Sdk\Services\AutoSubmitFormService;
use Ecpay\Sdk\Abstracts\AbstractVerifiedResponse;
use Ecpay\Sdk\Response\VerifiedEncodedStrResponse;

class Factory
{
    /**
     * Hash options
     *
     * @var array
     */
    private $options = [
        'hashKey' => '',
        'hashIv' => '',
        'hashMethod' => CheckMacValueService::METHOD_SHA256,
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * 類別工廠
     *
     * @param  string $class
     * @return mixed
     *
     * @throws RtnException
     */
    public function create($class)
    {
        $instance = null;
        switch (true) {
            case $this->isClassOrAlias($class, 'JsonCurlService'):
                $instance = $this->create(CurlService::class);
                $headers = [
                    'Content-Type:application/json',
                ];
                $instance->setHeaders($headers);
                break;
            case $this->isClassOrAlias($class, 'AutoSubmitFormService'):
                $request = $this->create(Request::class);
                $htmlService = $this->create(HtmlService::class);
                $instance = new AutoSubmitFormService($request, $htmlService);
                break;

            // CheckMacValue 應用
            case $this->isClassOrAlias($class, CheckMacValueService::class):
                $instance = new $class(
                    $this->options['hashKey'],
                    $this->options['hashIv'],
                    $this->options['hashMethod']
                );
                break;
            case $this->isClassOrAlias($class, CheckMacValueRequest::class):
            case $this->isClassOrAlias($class, AbstractVerifiedResponse::class):
                $checkMacValueService = $this->create(CheckMacValueService::class);
                $instance = new $class($checkMacValueService);
                break;
            case $this->isClassOrAlias($class, 'PostWithCmvVerifiedEncodedStrResponseService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $curlService = $this->create(CurlService::class);
                $response = $this->create(VerifiedEncodedStrResponse::class);
                $instance = new PostService($checkMacValueRequest, $curlService, $response);
                break;
            case $this->isClassOrAlias($class, 'PostWithCmvEncodedStrResponseService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $curlService = $this->create(CurlService::class);
                $response = $this->create(EncodedStrResponse::class);
                $instance = new PostService($checkMacValueRequest, $curlService, $response);
                break;
            case $this->isClassOrAlias($class, 'PostWithCmvJsonResponseService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $curlService = $this->create(CurlService::class);
                $response = $this->create(JsonResponse::class);
                $instance = new PostService($checkMacValueRequest, $curlService, $response);
                break;
            case $this->isClassOrAlias($class, 'PostWithCmvStrResponseService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $curlService = $this->create(CurlService::class);
                $response = $this->create(StrResponse::class);
                $instance = new PostService($checkMacValueRequest, $curlService, $response);

                break;
            case $this->isClassOrAlias($class, 'AutoSubmitFormWithCmvService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $htmlService = $this->create(HtmlService::class);
                $instance = new AutoSubmitFormService($checkMacValueRequest, $htmlService);
                break;
            case $this->isClassOrAlias($class, 'FormWithCmvService'):
                $checkMacValueRequest = $this->create(CheckMacValueRequest::class);
                $htmlService = $this->create(HtmlService::class);
                $instance = new ManualFormService($checkMacValueRequest, $htmlService);
                break;

            // AES 應用
            case $this->isClassOrAlias($class, AesService::class):
                $instance = new $class($this->options['hashKey'], $this->options['hashIv']);
                break;
            case $this->isClassOrAlias($class, AesRequest::class):
            case $this->isClassOrAlias($class, AbstractAesResponse::class):
                $aesService = $this->create(AesService::class);
                $instance = new $class($aesService);
                break;
            case $this->isClassOrAlias($class, 'PostWithAesJsonResponseService'):
                $aesRequest = $this->create(AesRequest::class);
                $jsonCurlService = $this->create('JsonCurlService');
                $response = $this->create(AesJsonResponse::class);
                $instance = new PostService($aesRequest, $jsonCurlService, $response);
                break;
            case $this->isClassOrAlias($class, 'PostWithAesStrResponseService'):
                $aesRequest = $this->create(AesRequest::class);
                $jsonCurlService = $this->create('JsonCurlService');
                $response = $this->create(AesStrResponse::class);
                $instance = new PostService($aesRequest, $jsonCurlService, $response);
                break;

            default:
                $instance = new $class();
        }

        return $instance;
    }

    /**
     * 含 Hash 資訊類別工廠
     *
     * @deprecated since version v1.0.2105270, 請改用 create 方法
     * @param  string $class
     * @param  string $key
     * @param  string $iv
     * @return mixed
     *
     * @throws RtnException
     */
    public function createWithHash($class, $key, $iv)
    {
        $this->options['hashKey'] = $key;
        $this->options['hashIv'] = $iv;

        return $this->create($class);
    }

    /**
     * 是否為某類別或別名
     *
     * @param  string $class
     * @param  string $target
     * @return boolean
     */
    private function isClassOrAlias($class, $target)
    {
        $result = false;
        if ($class === $target) {
            $result = true;
        } else {
            if (class_exists($class) && class_exists($target)) {
                $reflection = new ReflectionClass($class);
                if ($reflection->isSubclassOf($target)) {
                    $result = true;
                }
            }
        }

        return $result;
    }
}
