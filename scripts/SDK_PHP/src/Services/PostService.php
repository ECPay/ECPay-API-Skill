<?php

namespace Ecpay\Sdk\Services;

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;
use Ecpay\Sdk\Interfaces\Response\ResponseInterface;
use Ecpay\Sdk\Interfaces\Services\HttpClientInterface;

class PostService
{
    /**
     * Request
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * Response
     *
     * @var ResponseInterface
     */
    protected $response;

    /**
     * ServerPostService
     *
     * @var HttpClientInterface
     */
    protected $serverPostService;

    public function __construct(
        RequestInterface $request,
        HttpClientInterface $httpClientService,
        ResponseInterface $response
    ) {
        $this->request = $request;
        $this->serverPostService = $httpClientService;
        $this->response = $response;
    }

    /**
     * Server Post
     *
     * @param  array  $request
     * @param  string $url
     * @return array
     *
     * @throws RtnException
     */
    public function post($request, $url)
    {
        $reqeust = $this->request->get($request);
        $source = $this->serverPostService->run($reqeust, $url);
        return $this->response->get($source);
    }
}
