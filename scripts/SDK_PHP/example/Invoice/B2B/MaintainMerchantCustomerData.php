<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'ejCk326UnaZWKisg',
    'hashIv' => 'q9jcZX8Ib9LM8wYk',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$data = [
    'MerchantID' => '2000132',
    'Action' => 'Add',
    'Identifier' => '53538851',
    'type' => '2',
    'CompanyName' => '綠界科技',
    'TradingSlang' => 'Testing Slang',
    'ExchangeMode' => '0',
    'EmailAddress' => 'test-company@ecpay.com.tw',
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
$url = 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/MaintainMerchantCustomerData';

$response = $postService->post($input, $url);
var_dump($response);
