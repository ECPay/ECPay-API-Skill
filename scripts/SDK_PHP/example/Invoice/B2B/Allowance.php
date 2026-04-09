<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$itemCount = 1;
$itemPrice = 10;
$itemAmount = ($itemPrice * $itemCount);
$totalAmount = $itemAmount;
$taxAmount = round($totalAmount * 0.05, 0);
$data = [
    'MerchantID' => '2000132',
    'TaxAmount' => $taxAmount,
    'TotalAmount' => $totalAmount,
    'Details' => [
        [
            'OriginalInvoiceNumber' => 'MJ20001246',
            'OriginalInvoiceDate' => '2021-07-30',
            'ItemName' => '測試商品01',
            'OriginalSequenceNumber' => 1,
            'ItemName' => '測試商品01',
            'ItemCount' => $itemCount,
            'ItemPrice' => $itemPrice,
            'ItemAmount' => $itemAmount,
        ],
    ],
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'RqID' => '701b3264-a538-437e-ad45-2505eb7dde39',
        'Revision' => '1.0.0',
    ],
    'Data' => $data,
];
$url = 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Allowance';

$response = $postService->post($input, $url);
var_dump($response);
