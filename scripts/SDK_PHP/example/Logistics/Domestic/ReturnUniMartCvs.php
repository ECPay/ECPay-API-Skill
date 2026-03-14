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
    'GoodsAmount' => 1000,
    'ServiceType' => '4',
    'SenderName' => '陳大明',

    // 請參考 example/Logistics/Domestic/GetReturnResponse.php 範例開發
    'ServerReplyURL' => 'https://www.ecpay.com.tw/example/server-reply',
];
$url = 'https://logistics-stage.ecpay.com.tw/express/ReturnUniMartCVS';

$response = $postService->post($input, $url);
var_dump($response);
