<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$postService = $factory->create('AutoSubmitFormService');

$input = [
    'MerchantID' => '2000132',
    'MerchantTradeNo' => 'Test' . time(),
    'LogisticsType' => 'CB',
    'LogisticsSubType' => 'UNIMARTCBCVS',
    'Destination' => 'SG',

    // 請參考 example/Logistics/CrossBorder/GetMapResponse.php 範例開發
    'ServerReplyURL' => 'https://logistics-stage.ecpay.com.tw/MockMerchant/NoticsTestRtn',
];
$action = 'https://logistics-stage.ecpay.com.tw/CrossBorder/Map';

echo $postService->generate($input, $action);
