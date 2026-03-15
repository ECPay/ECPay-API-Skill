<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv'  => 'EkRm7iFT261dpevs',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID'  => '3002607',
    'PayDateType' => 'close',
    'StartDate'   => '2015-02-12',
    'EndDate'     => '2015-02-12',
];
$action = 'https://payment-stage.ecpay.com.tw/CreditDetail/FundingReconDetail';

echo $autoSubmitFormService->generate($input, $action);
