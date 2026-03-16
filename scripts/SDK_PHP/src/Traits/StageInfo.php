<?php

namespace Ecpay\Sdk\Traits;

trait StageInfo
{
    /**
     * Stage OTP 廠商資訊
     */
    protected $stageOtpMerchantId = '2000132';
    protected $stageOtpHashIv = 'v77hoKGq4kWxNNIS';
    protected $stageOtpHashKey = '5294y06JbISpM5x9';

    /**
     * Stage 非 OTP 廠商資訊
     */
    protected $stageNotOtpMerchantId = '2000214';
    protected $stageNotOtpHashIv = 'v77hoKGq4kWxNNIS';
    protected $stageNotOtpHashKey = '5294y06JbISpM5x9';
}
