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
    'LogisticsID' => '1769541',
    'GoodsAmount' => 130,
    'ServiceType' => '4',
    'SenderName' => '王大明',

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
$url = 'https://logistics-stage.ecpay.com.tw/Express/v2/ReturnUniMartCVS';

$response = $postService->post($input, $url);
var_dump($response);
