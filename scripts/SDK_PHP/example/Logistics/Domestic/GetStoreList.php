<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',
]);
$postService = $factory->create('PostWithCmvJsonResponseService');

$input = [
    'MerchantID' => '2000132',

    // 超商類別(最新類別請參考開發文件: https://developers.ecpay.com.tw/?p=47496)
    // 1. All
    // 2. FAMI: 全家
    // 3. UNIMART: 7-ELEVEN超商(常溫)
    // 4. HILIFE: 萊爾富
    // 5. OKMART: OK超商
    // 6. UNIMARTFREEZE: 7-ELEVEN超商(冷鏈)
    'CvsType' => 'All',
];
$url = 'https://logistics-stage.ecpay.com.tw/Helper/GetStoreList';

$response = $postService->post($input, $url);
var_dump($response);
