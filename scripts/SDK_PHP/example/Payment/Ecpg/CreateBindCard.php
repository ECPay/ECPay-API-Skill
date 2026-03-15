<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// $_POST['BindCardPayToken'] 請帶入前端 Web 取得的綁定信用卡代碼 BindCardPayToken
$data = [
    'MerchantID' => '3002607',
    'BindCardPayToken' => $_POST['BindCardPayToken'],
    'MerchantMemberID' => 'testphpsdk3002607'
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/CreateBindCard';

$response = $postService->post($input, $url);
echo(json_encode($response));