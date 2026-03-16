<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AesService;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$AesService = $factory->create(AesService::class);

// 模擬綠界綁定信用卡結果通知 OrderResultURL，完整回傳內容請參閱 API 文件
// (參考開發文件: https://developers.ecpay.com.tw/?p=35606)
// $data = [
//     'PlatformID' => '',
//     'MerchantID' => '3002607',
//     'MerchantMemberID' => 'testphpsdk3002607',
//     'BindCardID' => '9814a32ed10c4dcea2209b786146ddeb61bb92d2bc3d49b8b7c9f1333f5c2236',
//     'IsSameCard' => true,
//     'CardInfo' => [
//         'Card6No' => '431195',
//         'Card4No' => '2222',
//         'CardValidYY' => '25',
//         'CardValidMM' => '12',
//         'AuthCode' => '777777',
//         'Gwsr' => 13118888,
//         'ProcessDate' => '2024/03/04 16:36:54',
//         'Amount' => 100,
//         'Eci' => 0,
//         'Stage' => 0,
//         'Stast' => 0,
//         'Staed' => 0
//     ],
//     'CustomField' => '',
//     'OrderInfo' => [
//         'MerchantTradeNo' => 'test1709541383',
//         'TradeNo' => '2403041636445569',
//         'PaymentDate' => '2024/03/04 16:36:54',
//         'TradeAmt' => 100,
//         'PaymentType' => 'Credit',
//         'TradeDate' => '2024/03/04 16:36:44',
//         'ChargeFee' => 2.45,
//         'TradeStatus' => '1'
//     ],
//     'RtnCode' => 1,
//     'RtnMsg' => 'Success'
// ];

// $resultData = [
//     'MerchantID' => '3002607',
//     'RpHeader' => null,
//     'TransCode' => 1,
//     'TransMsg' => 'Success!',
//     'Data' => $AesService->encrypt($data)
// ];

// $_POST = [
//     'ResultData' => json_encode($resultData)
// ];

// 處理綠界回傳綁定信用卡結果
$resultData = $_POST['ResultData'];
$resultData = json_decode($resultData, true);

// AES 解密
$decrypt = $AesService->decrypt($resultData['Data']);

echo(json_encode($decrypt));
