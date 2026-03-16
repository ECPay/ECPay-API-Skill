<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\VerifiedArrayResponse;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$checkoutResponse = $factory->create(VerifiedArrayResponse::class);

// 模擬綠界付款結果回傳格式範例，非真實付款結果
$_POST = [
    'MerchantID'           => '3002607',
    'MerchantTradeNo'      => 'WPLL4E341E122DB44D62',
    'PaymentDate'          => '2019/05/09 00:01:21',
    'PaymentType'          => 'Credit_CreditCard',
    'PaymentTypeChargeFee' => '1',
    'RtnCode'              => '1',
    'RtnMsg'               => '交易成功',
    'SimulatePaid'         => '0',
    'TradeAmt'             => '500',
    'TradeDate'            => '2019/05/09 00:00:18',
    'TradeNo'              => '1905090000188278',
    'CheckMacValue'        => '6E7F053EF215FC851A050A2FF01D72CBE440EA138DC3E905647985DDF236FD25',
];

var_dump($checkoutResponse->get($_POST));
