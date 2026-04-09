<?php

namespace Ecpay\Sdk\Interfaces\Services\Test;

interface TestInterface
{
    /**
     * 執行
     *
     * @param  mixed $input
     * @return mixed
     */
    public function execute($input);

    /**
     * 產生預期結果
     *
     * @return mixed
     */
    public function generateExpected();

    /**
     * 產生輸入
     *
     * @return mixed
     */
    public function generateInput();

    /**
     * 取得預期結果來源
     *
     * @return mixed
     */
    public function getExpectedSource();

    /**
     * 取得輸入來源
     *
     * @return mixed
     */
    public function getInputSource();
}
