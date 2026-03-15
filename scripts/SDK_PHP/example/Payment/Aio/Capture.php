<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithCmvEncodedStrResponseService');

$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => '5fa271cc74e51',
    'TradeNo'         => '2011041718071855',
    'Action'          => 'C',
    'TotalAmount'     => 8685,
];
$url = 'https://payment-stage.ecpay.com.tw/CreditDetail/DoAction';

$response = $postService->post($input, $url);
var_dump($response);
