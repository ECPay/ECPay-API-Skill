<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithCmvJsonResponseService');

$input = [
    'MerchantID'      => '3002607',
    'MerchantTradeNo' => 'Test1732694169',
    'TimeStamp'       => time(),
];
$url = 'https://payment-stage.ecpay.com.tw/Cashier/QueryCreditCardPeriodInfo';

$response = $postService->post($input, $url);
var_dump($response);
