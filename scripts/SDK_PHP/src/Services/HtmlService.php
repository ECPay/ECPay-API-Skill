<?php

namespace Ecpay\Sdk\Services;

use Ecpay\Sdk\Interfaces\Services\HtmlInterface;

class HtmlService implements HtmlInterface
{
    /**
     * 跳脫 HTML 特殊字元
     *
     * @param  string $value
     * @return string
     */
    public function escapeHtml($value)
    {
        $escaped = $value;
        $doubleEncode = true;
        if (!empty($escaped)) {
            $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $doubleEncode);
        }

        return $escaped;
    }

    /**
     * 產生 Footer
     *
     * @return string
     */
    public function footer()
    {
        return '</html>';
    }

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
    public function form($input, $action, $target, $id, $submitText)
    {
        $formTarget = $this->escapeHtml($target);
        $formAction = $this->escapeHtml($action);
        $form = '<form id="' . $id . '" method="POST" target="' . $formTarget . '" action="' . $formAction . '">';
        foreach ($input as $name => $value) {
            $inputName = $this->escapeHtml($name);
            $inputValue = $this->escapeHtml($value);
            $form .= '<input type="hidden" name="' . $inputName . '" value="' . $inputValue . '">';
        }
        $form .= '</form>';
        return $form;
    }

    /**
     * 產生 Header
     *
     * @return string
     */
    public function header()
    {
        $header = '<!DOCTYPE html>';
        $header .= '<html>';
        $header .= '<head>';
        $header .= '<meta charset="utf-8">';
        $header .= '</head>';
        return $header;
    }
}
