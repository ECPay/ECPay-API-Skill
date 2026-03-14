<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Request\AesRequest as AesGenerater;
use Ecpay\Sdk\Response\AesJsonResponse as AesParser;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$aesParser = $factory->create(AesParser::class);
$parsedRequest = $aesParser->get(file_get_contents('php://input'));

// 物流狀態(貨態) or 物流狀態(逆物流)通知相關處理

$aesGenerater = $factory->create(AesGenerater::class);
$data = [
    'RtnCode' => '1',
    'RtnMsg' => '',
];
$input = [
    'MerchantID' => '2000132',
    'RqHeader' => [
        'Timestamp' => time(),
    ],
    'TransCode' => '1',
    'TransMsg' => '',
    'Data' => $data,
];
$response = $aesGenerater->get($input);
var_dump($response);
