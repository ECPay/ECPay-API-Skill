<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// $_POST['MerchantTradeNo'] 須與綁定信用卡交易時的 MerchantTradeNo 欄位相同
$data = [
    'PlatformID' => '',
    'MerchantID' => '3002607',
    'MerchantMemberID' => 'testphpsdk3002607',
    'MerchantTradeNo' => $_POST['MerchantTradeNo']
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/GetMemberBindCard';

$response = $postService->post($input, $url);
echo(json_encode($response));