<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// API 參數設定
// (完整參數列表請參閱 API 文件: https://developers.ecpay.com.tw/?p=35591)
$merchantTradeNo = 'test' . time();
$protocol = 'http';
if (isset($_SERVER['HTTPS'])){
    $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
}
$orderResultURL = $protocol . '://' . $_SERVER['SERVER_NAME'] . '/example/Payment/Ecpg/GetCreateBindCardResponse.php';

$data = [
    'PlatformID' => '',
    'MerchantID' => '3002607',
    'ConsumerInfo' => [
        'MerchantMemberID' => 'testphpsdk3002607',
        'Email' => 'customer@email.com',
        'Phone' => '0912345678',
        'Name' => 'Test',
        'CountryCode' => '158'
    ],
    'OrderInfo' => [
        'MerchantTradeDate' => date('Y/m/d H:i:s'),
        'MerchantTradeNo' => $merchantTradeNo,
        'TotalAmount' => '100',
        'TradeDesc' => '綁卡交易描述',
        'ItemName' => '交易名稱',
        'ReturnURL' => 'https://www.ecpay.com.tw/example/receive'
    ],

    // 請參考 example/Payment/Ecpg/GetCreateBindCardResponse.php 範例開發
    'OrderResultURL' => $orderResultURL,
    'CustomField' => '自訂欄位',
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyBindingCard';

$response = $postService->post($input, $url);

// 回傳前端畫面所需資訊
$result = [
    'Token' => $response['Data']['Token'],
    'MerchantTradeNo' => $merchantTradeNo
];

echo(json_encode($result));