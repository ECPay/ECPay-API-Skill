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
    'LogisticsType' => 'CVS',
    'LogisticsSubType' => 'FAMI',
    'IsCollection' => 'N',

    // 請參考 example/Logistics/Domestic/GetMapResponse.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',
];
$action = 'https://logistics-stage.ecpay.com.tw/Express/map';

echo $autoSubmitFormService->generate($input, $action);
