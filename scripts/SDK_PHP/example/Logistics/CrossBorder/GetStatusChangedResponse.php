<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\AesJsonResponse;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => '5294y06JbISpM5x9',
    'hashIv' => 'v77hoKGq4kWxNNIS',
]);
$aesJsonResponse = $factory->create(AesJsonResponse::class);
$response = file_get_contents('php://input');

var_dump($aesJsonResponse->get($response));
