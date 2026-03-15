<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Services\AesService;
use Ecpay\Sdk\Services\CurlService;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$AesService = $factory->create(AesService::class);
$CurlService = $factory->create(CurlService::class);

$data = [
    'MerchantID' => '3002607',
    'DateType' => '2',
    'BeginDate' => '2022-07-01',
    'EndDate' => '2022-07-30',
    'PaymentType' => '01'
];
$encryptData = $AesService->encrypt($data);

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $encryptData
];

$url = 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTradeMedia';
$filepath = '../../../QueryTradeMedia' . time() . '.csv';

$CurlService->setHeaders(array('Content-Type:application/json'));
$result = $CurlService->run(json_encode($input), $url);

file_put_contents($filepath, $result);
echo($result);