<?php

namespace Ecpay\Sdk\Interfaces\Services;

interface HtmlInterface
{
    /**
     * 跳脫 HTML 特殊字元
     *
     * @param  string $value
     * @return string
     */
    public function escapeHtml($value);

    /**
     * 產生 Footer
     *
     * @return string
     */
    public function footer();

    /**
     * 產生表單
     *
     * @param  array  $input
     * @param  string $action
     * @param  string $target
     * @param  string $id
     * @param  string $submitText
     * @return string
     */
    public function form($input, $action, $target, $id, $submitText);

    /**
     * 產生 Header
     *
     * @return string
     */
    public function header();
}
