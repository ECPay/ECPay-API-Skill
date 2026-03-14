<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\UrlService;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID'        => '3002607',
    'MerchantTradeNo'   => 'Test' . time(),
    'MerchantTradeDate' => date('Y/m/d H:i:s'),
    'PaymentType'       => 'aio',
    'TotalAmount'       => 100,
    'TradeDesc'         => UrlService::ecpayUrlEncode('交易描述範例'),
    'ItemName'          => '範例商品一批 100 TWD x 1',
    'ChoosePayment'     => 'Credit',
    'EncryptType'       => 1,

    // 請參考 example/Payment/GetCheckoutResponse.php 範例開發
    'ReturnURL'         => 'https://www.ecpay.com.tw/example/receive',
];
$action = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';

echo $autoSubmitFormService->generate($input, $action);
