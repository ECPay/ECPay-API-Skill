<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$itemCount = 3;
$itemPrice = 10;
$itemAmount = ($itemPrice * $itemCount);
$saleAmount = $itemAmount;
$taxAmount = round(($saleAmount * 0.05), 0);
$data = [
    'MerchantID' => '2000132',
    'RelateNumber' => 'test' . time(),
    'CustomerIdentifier' => '23165448',
    'CustomerEmail' => 'test-buyer@ecpay.com.tw',
    'InvType' => '07',
    'TaxType' => '1',
    'Items' => [
        [
            'ItemSeq' => 1,
            'ItemName' => '測試商品01',
            'ItemCount' => $itemCount,
            'ItemPrice' => $itemPrice,
            'ItemTaxType' => '1',
            'ItemAmount' => $itemAmount,
        ],
    ],
    'SalesAmount' => $saleAmount,
    'TaxAmount' => $taxAmount,
    'TotalAmount' => ($saleAmount + $taxAmount),
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
$url = 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/Issue';

$response = $postService->post($input, $url);
var_dump($response);
