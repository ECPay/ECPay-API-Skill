<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',
]);
$postService = $factory->create('PostWithCmvStrResponseService');

$input = [
    'MerchantID' => '2000132',
    'AllPayLogisticsID' => '1718550',
    'ShipmentDate' => date('Y/m/d'),
];
$url = 'https://logistics-stage.ecpay.com.tw/Helper/UpdateShipmentInfo';

$response = $postService->post($input, $url);
var_dump($response);
