<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// $_POST['PayToken'] 為取得對應模擬前端發送的 PayToken input 欄位
// $_POST['MerchantTradeNo'] 須與取得 Token 時的 MerchantTradeNo 欄位相同
$data = [
    'MerchantID' => '3002607',
    'PayToken' => $_POST['PayToken'],
    'MerchantTradeNo' => $_POST['MerchantTradeNo']
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment';

$response = $postService->post($input, $url);
echo(json_encode($response));