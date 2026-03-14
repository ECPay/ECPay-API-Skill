<?php

namespace Ecpay\Sdk\Services;

use Ecpay\Sdk\Interfaces\Services\HtmlInterface;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;

class AutoSubmitFormService
{
    /**
     * Html Service
     *
     * @var HtmlInterface
     */
    protected $htmlService;

    /**
     * RequestInterface
     *
     * @var request
     */
    protected $request;

    public function __construct(RequestInterface $request, HtmlInterface $htmlService)
    {
        $this->request = $request;
        $this->htmlService = $htmlService;
    }

    /**
     * 產生自動送出表單 JS
     *
     * @param  string $formId
     * @return string
     */
    public function autoSubmitJs($formId)
    {
        $js = '<script type="text/javascript">';
        $js .= 'document.getElementById("' . $this->htmlService->escapeHtml($formId) . '").submit();';
        $js .= '</script>';
        return $js;
    }

    /**
     * 產生自動送出表單
     *
     * @param  array  $input
     * @param  string $action
     * @param  string $id
     * @param  string $target
     * @param  string $submitText
     * @return string
     */
    public function generate($input, $action, $target = '_self', $id = 'ecpay-form', $submitText = 'ecpay-button')
    {
        $request = $this->request->toArray($input);
        $html = $this->htmlService->header();
        $html .= '<body>';
        $html .= $this->htmlService->form($request, $action, $target, $id, $submitText);
        $html .= $this->autoSubmitJs($id);
        $html .= '</body>';
        $html .= $this->htmlService->footer();
        return $html;
    }
}
