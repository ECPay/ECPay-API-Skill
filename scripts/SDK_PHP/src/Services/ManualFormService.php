<?php

namespace Ecpay\Sdk\Services;

use Ecpay\Sdk\Interfaces\Services\HtmlInterface;
use Ecpay\Sdk\Interfaces\Request\RequestInterface;

class ManualFormService
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
     * 產生表單
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
        $html .= '</body>';
        $html .= $this->htmlService->footer();
        return $html;
    }
}
