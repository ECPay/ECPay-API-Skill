<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\AesJsonResponse;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$response = $factory->create(AesJsonResponse::class);
$parsed = [];
$parsed['ResultData'] = $response->get($_POST['ResultData']);
var_dump($parsed);
