<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID'    => '3002607',
    'DateType'      => '2',
    'BeginDate'     => '2015-02-12',
    'EndDate'       => '2015-02-12',
    'MediaFormated' => '0',
];
$action = 'https://vendor-stage.ecpay.com.tw/PaymentMedia/TradeNoAio';

echo $autoSubmitFormService->generate($input, $action);
