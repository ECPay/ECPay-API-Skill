<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// $_POST['BindCardID'] 請帶入綁定信用卡時取得的綁定信用卡代碼 BindCardID
$merchantTradeNo = 'test' . time();
$data = [
    'PlatformID' => '',
    'MerchantID' => '3002607',
    'BindCardID' => $_POST['BindCardID'],
    'OrderInfo' => [
        'MerchantTradeDate' => date('Y/m/d H:i:s'),
        'MerchantTradeNo' => $merchantTradeNo,
        'TotalAmount' => '100',
        'ReturnURL' => 'https://www.ecpay.com.tw/example/receive',
        'TradeDesc' => 'DESC',
        'ItemName' => 'Test'
    ],
    'ConsumerInfo' => [
        'MerchantMemberID' => 'testphpsdk3002607',
        'Email' => 'customer@email.com',
        'Phone' => '0912345678',
        'Name' => 'Test',
        'CountryCode' => '158',
        'Address' => '',
    ],
    'CustomField' => '',
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePaymentWithCardID';

$response = $postService->post($input, $url);
echo(json_encode($response));