<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID' => '2000132',
    'MerchantTradeNo' => 'Test' . time(),
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'LogisticsType' => 'HOME',
    'LogisticsSubType' => 'TCAT',
    'GoodsAmount' => 1000,
    'GoodsName' => '綠界 SDK 範例商品',
    'SenderName' => '陳大明',
    'SenderCellPhone' => '0911222333',
    'SenderZipCode' => '11560',
    'SenderAddress' => '台北市南港區三重路19-2號6樓',
    'ReceiverName' => '王小美',
    'ReceiverCellPhone' => '0933222111',
    'ReceiverZipCode' => '11560',
    'ReceiverAddress' => '台北市南港區三重路19-2號6樓',
    'Temperature' => '0001',
    'Distance' => '00',
    'Specification' => '0001',
    'ScheduledPickupTime' => '4',
    'ScheduledDeliveryTime' => '4',

    // 請參考 example/Logistics/Domestic/GetLogisticStatueResponse.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',
    'ClientReplyURL' => 'https://www.ecpay.com.tw/example/client-reply',
];
$action = 'https://logistics-stage.ecpay.com.tw/Express/Create';

echo $autoSubmitFormService->generate($input, $action);
