<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$data = [
    'PlatformID' => '3002607',
    'MerchantID' => '3002607',
    'MerchantTradeNo' => '20180914001',
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade';

$response = $postService->post($input, $url);
var_dump($response);
