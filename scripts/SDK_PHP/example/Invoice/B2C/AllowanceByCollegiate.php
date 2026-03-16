<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$itemCount = 1;
$itemPrice = 10.5;
$itemAmount = $itemPrice * $itemCount;
$allowanceAmount = round(($itemAmount), 0);
$data = [
    'MerchantID' => '2000132',
    'InvoiceNo' => 'MJ80009035',
    'InvoiceDate' => '2021-07-28',
    'AllowanceNotify' => 'E',
    'NotifyMail' => 'test-allowance@ecpay.com.tw',
    'AllowanceAmount' => $allowanceAmount,
    'Items' => [
        [
            'ItemSeq' => 1,
            'ItemName' => '測試商品01',
            'ItemCount' => $itemCount,
            'ItemWord' => '個',
            'ItemPrice' => $itemPrice,
            'ItemAmount' => $itemAmount,
        ],
    ],

    // 請參考 example/Invoice/B2C/GetAllowanceByCollegiateResponse.php 範例開發
    'ReturnURL' => 'https://www.ecpay.com.tw/example/return-url',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '3.0.0',
    ],
    'Data' => $data,
];
$url = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/AllowanceByCollegiate';

$response = $postService->post($input, $url);
var_dump($response);
