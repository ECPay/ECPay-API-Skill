<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'XBERn1YOvpM9nfZc',
    'hashIv' => 'h1ONHk4P4yqbl5LK',
    'hashMethod' => 'md5',
]);
$autoSubmitFormService = $factory->create('AutoSubmitFormWithCmvService');

$input = [
    'MerchantID' => '2000933',
    'AllPayLogisticsID' => '1717858',
    'CVSPaymentNo' => 'W5254579754',
];
$action = 'https://logistics-stage.ecpay.com.tw/Express/PrintOKMARTC2COrderInfo';

echo $autoSubmitFormService->generate($input, $action);
