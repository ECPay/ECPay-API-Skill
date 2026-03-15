<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$postService = $factory->create('PostWithAesStrResponseService');

$data = [
    'TempLogisticsID' => '0',
    'GoodsAmount' => 100,
    'GoodsName' => '範例商品',
    'SenderName' => '陳大明',
    'SenderZipCode' => '11560',
    'SenderAddress' => '台北市南港區三重路19-2號6樓',
    'Temperature' => '0003',

    // 請參考 example/Logistics/AllInOne/LogisticsStatusNotify.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',

    // 請參考 example/Logistics/AllInOne/TempTradeEstablishedResponse.php 範例開發
    'ClientReplyURL' => 'https://www.ecpay.com.tw/example/client-reply',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '1.0.0',
    ],
    'Data' => $data,
];
$url = 'https://logistics-stage.ecpay.com.tw/Express/v2/RedirectToLogisticsSelection';

$response = $postService->post($input, $url);
echo $response['body'];
