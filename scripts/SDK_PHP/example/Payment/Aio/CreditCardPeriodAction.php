<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithCmvEncodedStrResponseService');

$input = [
    'MerchantID'        => '3002607',
    'MerchantTradeNo'   => 'Test1748833369', // 訂單產生時傳送給綠界的特店交易編號
    'Action'            => 'Cancel',         // ReAuth(授權失敗交易)、Cancel(終止定期定額後續交易)
    'TimeStamp'         => time(),
];
$url = 'https://payment-stage.ecpay.com.tw/Cashier/CreditCardPeriodAction';

$response = $postService->post($input, $url);
var_dump($response);
