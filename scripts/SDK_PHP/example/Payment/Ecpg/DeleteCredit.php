<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$data = [
    'MerchantID' => '3002607',
    'ConsumerInfo' => [
        'MerchantMemberID' => 'test123456',
        'Email' => 'customer@email.com',
        'Phone' => '0912345678',
        'Name' => 'Test',
        'CountryCode' => '158'
    ]
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyUser';

$response = $postService->post($input, $url);
var_dump($response);