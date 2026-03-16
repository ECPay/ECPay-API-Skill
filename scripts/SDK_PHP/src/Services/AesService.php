<?php

namespace Ecpay\Sdk\Services;

use Exception;
use Ecpay\Sdk\Traits\HashInfo;
use Ecpay\Sdk\Exceptions\RtnException;

class AesService
{
    use HashInfo;

    /**
     * Cipher method
     *
     * @var string
     */
    protected $method = 'AES-128-CBC';

    /**
     * Bitwise disjunction flag
     *
     * @var int
     */
    protected $options = OPENSSL_RAW_DATA;

    public function __construct($key, $iv)
    {
        $this->setHashKey($key);
        $this->setHashIv($iv);
    }

    /**
     * AES 解密
     *
     * @param  string $source
     * @return array
     *
     * @throws RtnException
     */
    public function decrypt($source)
    {
        $jsonDecoded = [];
        $base64Decoded = base64_decode($source);
        $decrypted = openssl_decrypt(
            $base64Decoded,
            $this->method,
            $this->getHashKey(),
            $this->options,
            $this->getHashIv()
        );
        if ($decrypted === false) {
            throw new RtnException(109);
        }

        $urlDecoded = urldecode($decrypted);
        $jsonDecoded = json_decode($urlDecoded, true);
        if (is_null($jsonDecoded)) {
            throw new RtnException(111);
        }

        return $jsonDecoded;
    }

    /**
     * 解密資料
     *
     * @param  array $source
     * @return array
     *
     * @throws RtnException
     */
    public function decryptData($source)
    {
        $field = $this->getFieldName();
        if (isset($source[$field])) {
            $source[$field] = $this->decrypt($source[$field]);
        }

        return $source;
    }

    /**
     * AES 加密
     *
     * @param  array $source
     * @return string
     *
     * @throws RtnException
     */
    public function encrypt($source)
    {
        $dataBase64Encode = '';
        try {
            $jsonEncoded = json_encode($source);
            $urlEncoded = urlencode($jsonEncoded);
            $encrypted = openssl_encrypt(
                $urlEncoded,
                $this->method,
                $this->getHashKey(),
                $this->options,
                $this->getHashIv()
            );
            $dataBase64Encode = base64_encode($encrypted);
        } catch (Exception $e) {
            throw new RtnException(110);
        }

        return $dataBase64Encode;
    }

    /**
     * 加密資料
     *
     * @param  array $source
     * @return array
     *
     * @throws RtnException
     */
    public function encryptData($source)
    {
        $field = $this->getFieldName();
        if (isset($source[$field])) {
            $source[$field] = $this->encrypt($source[$field]);
        }

        return $source;
    }

    /**
     * 取得加密欄位名稱
     *
     * @return string
     */
    public function getFieldName()
    {
        return 'Data';
    }
}
