<?php

namespace Ecpay\Sdk\Traits;

trait HashInfo
{
    /**
     * Hash IV
     *
     * @var string
     */
    protected $hashIv;

    /**
     * Hash Key
     *
     * @var string
     */
    protected $hashKey;

    /**
     * 取得 Hash IV
     *
     * @return string
     */
    public function getHashIv()
    {
        return $this->hashIv;
    }

    /**
     * 取得 Hash Key
     *
     * @return string
     */
    public function getHashKey()
    {
        return $this->hashKey;
    }

    /**
     * 設定 Hash IV
     *
     * @param  string $iv
     * @return void
     */
    public function setHashIv($iv)
    {
        $this->hashIv = $iv;
    }

    /**
     * 設定 Hash Key
     *
     * @param  string $key
     * @return void
     */
    public function setHashKey($key)
    {
        $this->hashKey = $key;
    }
}
