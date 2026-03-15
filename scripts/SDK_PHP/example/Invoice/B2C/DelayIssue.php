<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$itemCount01 = 3;
$itemPrice01 = 10.5;
$itemAmount01 = round(($itemPrice01 * $itemCount01), 0);

$itemCount02 = 1;
$itemPrice02 = 150.0;
$itemAmount02 = round(($itemPrice02 * $itemCount02), 0);

$data = [
    'MerchantID' => '2000132',
    'RelateNumber' => 'Test' . time(),
    'CustomerName' => '測試消費者',
    'CustomerAddr' => '台北市南港區三重路19-2號6樓',
    'CustomerEmail' => 'test-customer@ecpay.com.tw',
    'Print' => '1',
    'Donation' => '0',
    'TaxType' => '1',
    'SalesAmount' => ($itemAmount01 + $itemAmount02),
    'Items' => [
        [
            'ItemName' => '測試商品01',
            'ItemCount' => $itemCount01,
            'ItemWord' => '個',
            'ItemPrice' => $itemPrice01,
            'ItemTaxType' => '3',
            'ItemAmount' => $itemAmount01,
        ],
        [
            'ItemName' => '測試商品02',
            'ItemCount' => $itemCount02,
            'ItemWord' => '雙',
            'ItemPrice' => $itemPrice02,
            'ItemTaxType' => '3',
            'ItemAmount' => $itemAmount02,
        ],
    ],
    'InvType' => '07',
    'DelayFlag' => '1',
    'DelayDay' => 15,
    'Tsr' => 'tsr' . time(),
    'PayType' => '2',
    'PayAct' => 'ECPAY',

    // 請參考 example/Invoice/B2C/GetInvoicedResponse.php 範例開發
    'NotifyURL' => 'https://www.ecpay.com.tw/example/notify-url',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '3.0.0',
    ],
    'Data' => $data,
];
$url = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/DelayIssue';

$response = $postService->post($input, $url);
var_dump($response);
