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
    'InvoiceYear' => '109',
    'InvoiceTerm' => 0,
    'UseStatus' => 0,
    'InvoiceCategory' => 2,
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
$url = 'https://einvoice-stage.ecpay.com.tw/B2BInvoice/GetInvoiceWordSetting';

$response = $postService->post($input, $url);
var_dump($response);
