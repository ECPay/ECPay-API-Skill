<?php

namespace Ecpay\Sdk\Services;

use Exception;
use Ecpay\Sdk\Traits\HashInfo;
use Ecpay\Sdk\Services\UrlService;
use Ecpay\Sdk\Services\ArrayService;
use Ecpay\Sdk\Exceptions\RtnException;

class CheckMacValueService
{
    use HashInfo;

    /**
     * Hash 方式列舉
     */
    const METHOD_MD5 = 'md5';
    const METHOD_SHA256 = 'sha256';

    /**
     * Hash 方式
     *
     * @var string
     */
    private $method;


    public function __construct($key, $iv, $method)
    {
        $this->setHashKey($key);
        $this->setHashIv($iv);
        $this->setMethod($method);
    }

    /**
     * 附加檢查碼
     *
     * @param  array   $source
     * @param  boolean $isSort
     * @return array
     */
    public function append($source, $isSort = true)
    {
        $source[$this->getFieldName()] = $this->generate($source);
        if ($isSort) {
            $source = $this->sort($source);
        }

        return $source;
    }

    /**
     * 檢查碼參數過濾
     *
     * @param  array $source
     * @return array
     */
    public function filter($source)
    {
        if (isset($source[$this->getFieldName()])) {
            unset($source[$this->getFieldName()]);
        }

        return $source;
    }

    /**
     * 產生檢查碼
     *
     * @param  array $source
     * @return string
     *
     * @throws RtnException
     */
    public function generate($source)
    {
        try {
            $filtered = $this->filter($source);
            $sorted = $this->sort($filtered);
            $combined = $this->toEncodeSourceString($sorted);
            $encoded = UrlService::ecpayUrlEncode($combined);
            $hash = $this->generateHash($encoded);
            $checkMacValue = strtoupper($hash);
        } catch (Exception $e) {
            throw new RtnException(102);
        }

        return $checkMacValue;
    }

    /**
     * 產生雜湊值
     *
     * @param  string $source
     * @return string
     */
    public function generateHash($source)
    {
        $hash = '';
        if ($this->method === self::METHOD_SHA256) {
            $hash = hash('sha256', $source);
        } else {
            $hash = md5($source);
        }
        return $hash;
    }

    /**
     * 取得壓碼欄位名稱
     *
     * @return string
     */
    public function getFieldName()
    {
        return 'CheckMacValue';
    }

    /**
     * 設定雜湊方式
     *
     * @param string $method
     * @return void
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * 排序
     *
     * @param  array $source
     * @return array
     */
    public function sort($source)
    {
        return ArrayService::naturalSort($source);
    }

    /**
     * 轉換為編碼來源字串
     *
     * @param  array $source
     * @return string
     */
    public function toEncodeSourceString($source)
    {
        $combined = 'HashKey=' . $this->getHashKey();
        foreach ($source as $name => $value) {
            $combined .= '&' . $name . '=' . $value;
        }
        $combined .= '&HashIV=' . $this->getHashIv();
        return $combined;
    }

    /**
     * 檢核檢查碼（使用 timing-safe 比較防止 Timing Attack）
     *
     * @param  array $source
     * @return boolean
     */
    public function verify($source)
    {
        $checkMacValue = $this->generate($source);
        return hash_equals($checkMacValue, $source[$this->getFieldName()]);
    }
}
