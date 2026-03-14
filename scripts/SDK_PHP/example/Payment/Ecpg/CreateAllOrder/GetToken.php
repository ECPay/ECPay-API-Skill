<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// 請依照平台商提供的付款方式參照 API 文件調整 $data 中的參數
// (參考開發文件: https://developers.ecpay.com.tw/?p=9040)
$merchantTradeNo = 'test' . time();
$data = [
    'MerchantID' => '3002607',
    'RememberCard' => 1,
    'PaymentUIType' => 2,
    'ChoosePaymentList' => "0",
    'OrderInfo' => [
        'MerchantTradeDate' => date('Y/m/d H:i:s'),
        'MerchantTradeNo' => $merchantTradeNo,
        'TotalAmount' => '100',
        'ReturnURL' => 'https://www.ecpay.com.tw/example/receive',
        'TradeDesc' => 'DESC',
        'ItemName' => 'Test'
    ],
    'CardInfo' => [
        'Redeem' => 0,
        'OrderResultURL' => 'https://www.ecpay.com.tw/example/receive',
        'CreditInstallment' => '3,6,12',
        'FlexibleInstallment' => 30
    ],
    'UnionPayInfo' => [
        'OrderResultURL' => 'https://www.ecpay.com.tw/example/receive'
    ],
    'ATMInfo' => [
        'ExpireDate' => 3
    ],
    'CVSInfo' => [
        'StoreExpireDate' => 10080
    ],
    'BarcodeInfo' => [
        'StoreExpireDate' => 7
    ],
    'ConsumerInfo' => [
        'MerchantMemberID' => 'test123456',
        'Email' => 'customer@email.com',
        'Phone' => '0912345678',
        'Name' => 'Test',
        'CountryCode' => '158'
    ]
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade';
$response = $postService->post($input, $url);

// 回傳前端畫面所需資訊
$result = [
    'Token' => $response['Data']['Token'],
    'MerchantTradeNo' => $merchantTradeNo
];

echo(json_encode($result));
