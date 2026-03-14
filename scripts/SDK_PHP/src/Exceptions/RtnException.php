<?php

namespace Ecpay\Sdk\Exceptions;

use Exception;

class RtnException extends Exception
{
    public function __construct($code)
    {
        $message = $this->toRtnMessage($code);
        parent::__construct($message, $code);
    }

    /**
     * 轉換為回應訊息
     *
     * @param  int $code
     * @return string
     */
    public function toRtnMessage($code)
    {
        $messageList = include __DIR__ . '/../Config/RtnException.php';
        $message = 'Undefined error';
        if (isset($messageList[$code])) {
            $message = $messageList[$code];
        }

        return $message;
    }
}
