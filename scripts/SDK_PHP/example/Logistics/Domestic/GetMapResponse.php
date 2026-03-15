<?php

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\ArrayResponse;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory();
$response = $factory->create(ArrayResponse::class);

var_dump($response->get($_POST));
