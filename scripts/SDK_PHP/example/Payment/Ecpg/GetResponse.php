<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AesService;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$AesService = $factory->create(AesService::class);

// 模擬對送綠界回傳付款資訊的加密 Data 進行解密，實際串接請參閱 API 文件
// (參考開發文件: https://developers.ecpay.com.tw/?p=9058#)
$data = [
    'RtnCode' => 1,
    'RtnMsg' => 'Success',
    'PlatformID' => '3002607',
    'MerchantID' => '3002607',
    'Token' => 'm12dae4846446sq',
    'TokenExpireDate' => date('Y/m/d H:i:s')
];

// 模擬界回傳付款資訊的加密資料，AES 加密
$encryptData = $AesService->encrypt($data);

$input = [
    'MerchantID' => '3002607',
    'RpHeader' => [
        'Timestamp' => time()
    ],
    'TransCode' => 1,
    'TransMsg' => 'Success',
    'Data' => $encryptData
];

// AES 解密
$decrypt = $AesService->decrypt($input['Data']);

echo(json_encode($decrypt));
