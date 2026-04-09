<?php

namespace Ecpay\Sdk\Exceptions;

use Exception;

class TransException extends Exception
{
    public function __construct($code, $message)
    {
        parent::__construct($message, $code);
    }
}
