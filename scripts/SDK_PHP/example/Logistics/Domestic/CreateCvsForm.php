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
    'LogisticsType' => 'CVS',
    'LogisticsSubType' => 'FAMI',
    'GoodsAmount' => 1000,
    'GoodsName' => '綠界 SDK 範例商品',
    'SenderName' => '陳大明',
    'SenderCellPhone' => '0911222333',
    'ReceiverName' => '王小美',
    'ReceiverCellPhone' => '0933222111',

    // 請參考 example/Logistics/Domestic/GetLogisticStatueResponse.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',
    'ClientReplyURL' => 'https://www.ecpay.com.tw/example/client-reply',

    // 請參考 example/Logistics/Domestic/GetMapResponse.php 範例取得
    'ReceiverStoreID' => '006598'
];
$action = 'https://logistics-stage.ecpay.com.tw/Express/Create';

echo $autoSubmitFormService->generate($input, $action);
