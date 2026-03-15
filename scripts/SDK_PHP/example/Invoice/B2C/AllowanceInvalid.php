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
    'InvoiceNo' => 'MJ80009358',
    'AllowanceNo' => '2021072813260868',
    'Reason' => 'Testing reason',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '3.0.0',
    ],
    'Data' => $data,
];
$url = 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/AllowanceInvalid';

$response = $postService->post($input, $url);
var_dump($response);
