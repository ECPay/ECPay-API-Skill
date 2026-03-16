<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$itemCount = 3;
$itemPriceIncludeTax = 10.5;
$itemAmount = round(($itemPriceIncludeTax * $itemCount), 0);
$saleAmount = $itemAmount;
$data = [
    'MerchantID' => '2000132',
    'RelateNumber' => 'Test' . time(),
    'CustomerPhone' => '0911222333',
    'Print' => '0',
    'Donation' => '0',
    'CarrierType' => '1',
    'TaxType' => '1',
    'SalesAmount' => $saleAmount,
    'Items' => [
        [
            'ItemName' => '測試商品01',
            'ItemCount' => $itemCount,
            'ItemWord' => '個',
            'ItemPrice' => $itemPriceIncludeTax,
            'ItemTaxType' => '1',
            'ItemAmount' => $itemAmount,
        ],
    ],
    'InvType' => '07'
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '3.0.0',
    ],
    'Data' => $data,
];
$url = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue';

$response = $postService->post($input, $url);
var_dump($response);
