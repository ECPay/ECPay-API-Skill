<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\ArrayResponse;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory();
$arrayResponse = $factory->create(ArrayResponse::class);

var_dump($arrayResponse->get($_POST));
