<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
    'hashMethod' => 'md5',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID' => '2000132',
    'AllPayLogisticsID' => '1717876',
];
$action = 'https://logistics-stage.ecpay.com.tw/helper/printTradeDocument';

echo $autoSubmitFormService->generate($input, $action);
