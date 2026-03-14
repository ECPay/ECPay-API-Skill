<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithCmvJsonResponseService');

$parameters = [
    'MerchantID'      => '3002607',
    'CreditRefundId'  => 13475885,
    'CreditAmount'    => 100,
    'CreditCheckCode' => 62861749,
];
$url = 'https://payment-stage.ecPay.com.tw/CreditDetail/QueryTrade/V2';

$response = $postService->post($parameters, $url);
var_dump($response);
