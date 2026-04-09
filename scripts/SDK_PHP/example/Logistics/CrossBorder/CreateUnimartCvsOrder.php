<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

$data = [
    'MerchantID' => '2000132',
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'MerchantTradeNo' => 'Test' . time(),
    'LogisticsType' => 'CB',
    'LogisticsSubType' => 'UNIMARTCBCVS',
    'GoodsAmount' => 1000,
    'GoodsWeight' => 5.0,
    'GoodsEnglishName' => 'Test goods',
    'ReceiverCountry' => 'SG',
    'ReceiverName' => 'Test Receiver',
    'ReceiverCellPhone' => '65212345678',
    'ReceiverStoreID' => '711_1',
    'ReceiverZipCode' => '419701',
    'ReceiverAddress' => 'address 23424 -fr 13-2',
    'ReceiverEmail' => 'test-receiver@ecpay.com.tw',
    'SenderName' => 'Test Sender',
    'SenderCellPhone' => '886987654321',
    'SenderAddress' => 'address 23424 -fr 13-2, Nangang Dist., Taipei City 115, Taiwan (R.O.C.)',
    'SenderEmail' => 'test-sender@ecpay.com.tw',
    'Remark' => 'Test Remark',

    // 請參考 example/Logistics/CrossBorder/GetStatusChangedResponse.php 範例開發
    'ServerReplyURL' => 'https://logistics-stage.ecpay.com.tw/MockMerchant/NoticsTestRtn',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
        'Revision' => '1.0.0',
    ],
    'Data' => $data,
];
$url = 'https://logistics-stage.ecpay.com.tw/CrossBorder/Create';

$response = $postService->post($input, $url);
var_dump($response);
