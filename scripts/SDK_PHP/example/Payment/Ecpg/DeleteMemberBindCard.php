<?php

use Ecpay\Sdk\Factories\Factory;

require __DIR__ . '/../../../vendor/autoload.php';

$factory = new Factory([
    'hashKey' => 'pwFHCqoQZGmho4w6',
    'hashIv' => 'EkRm7iFT261dpevs',
]);
$postService = $factory->create('PostWithAesJsonResponseService');

// $_POST['BindCardID'] 請帶入綁定信用卡時取得的綁定信用卡代碼 BindCardID
$data = [
    'PlatformID' => '',
    'MerchantID' => '3002607',
    'BindCardID' => $_POST['BindCardID']
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader' => [
        'Timestamp' => time()
    ],
    'Data' => $data
];

$url = 'https://ecpg-stage.ecpay.com.tw/Merchant/DeleteMemberBindCard';

$response = $postService->post($input, $url);
echo(json_encode($response));