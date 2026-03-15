<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$data = [
    'MerchantID' => '2000132',
    'LogisticsID' => '1865733',
    'GoodsAmount' => 100,
    'ServiceType' => '4',
    'SenderName' => '王大明',
    'SenderPhone' => '0911222333',

    // 請參考 example/Logistics/AllInOne/LogisticsStatusNotify.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '1.0.0',
    ],
    'Data' => $data,
];
$url = 'https://logistics-stage.ecpay.com.tw/Express/v2/ReturnHilifeCVS';

$response = $postService->post($input, $url);
var_dump($response);
