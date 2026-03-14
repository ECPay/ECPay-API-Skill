<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'XBERn1YOvpM9nfZc',
    'hashIv' => 'h1ONHk4P4yqbl5LK',
    'hashMethod' => 'md5',
]);
$postService = $factory->create('PostWithCmvStrResponseService');

$input = [
    'MerchantID' => '2000933',
    'AllPayLogisticsID' => '1718552',
    'CVSPaymentNo' => 'C9681067',
    'CVSValidationNo' => '2448',
];
$url = 'https://logistics-stage.ecpay.com.tw/Express/CancelC2COrder';

$response = $postService->post($input, $url);
var_dump($response);
