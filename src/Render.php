<?php
declare(strict_types=1);

namespace Shuiping\Postception;
/**
 * Class Render
 * @package swagpostception
 */
class Render
{

    public const REQUEST_GET = 'GET';
    public const REQUEST_POST = 'POST';
    public const REQUEST_PUT = 'PUT';
    public const REQUEST_DELETE = 'DELETE';

    protected const PARAM_NAME = 'name';
    protected const PARAM_DESCRIPTION = 'description';
    protected const PARAM_PARAMS = 'params';
    protected const PARAM_URL = 'url';

    protected const PARAM_CEPT_CEPTNAME = 'ceptname';
    protected const PARAM_CEPT_METHODS = 'methods';

    protected const TEMPLATE_FILE = '/templates/template_';
    protected const TEMPLATE_POST = '/templates/template_post.txt';
    protected const TEMPLATE_CEPT = '/templates/template_cept.txt';
    protected const REQUEST_PARAM_MODE = 'formdata';

    /**
     * @var
     */
    protected $template;

    /**
     * generate cept file
     * @param object $item
     * @param array $function_codes
     * @return string
     */
    public function renderCept($item, array $function_codes): string
    {
        static $var = 0;
        if ($var < 1) {
            $this->template = file_get_contents(__DIR__ . self::TEMPLATE_CEPT);
        }
        $this->renderParam(self::TEMPLATE_POST, $item->name);
        $this->renderParam(self::PARAM_CEPT_METHODS, implode('', $function_codes));

        $var++;
        return $this->template;
    }

    /**
     * generate cept code
     * @param object $request
     * @return string
     * @throws \Exception
     */
    public function renderFunction($request): string
    {
        if (empty($request->request->method)) {
            throw new \Exception(sprintf('Empty %s method type', $request->request->method));
        }
        if (empty($this->getMethodTemplate($request->request->method))) {
            throw new \Exception(sprintf('Dont have %s type template', $request->request->method));
        }

        $this->template = file_get_contents($this->getMethodTemplate($request->request->method ?? ''));
        $this->renderParam(self::PARAM_NAME, $request->name ?? '');
        $this->renderParam(self::PARAM_DESCRIPTION, $request->description ?? '');
        $this->renderParam(self::PARAM_URL, $request->request->url->raw ?? '');

        // need to handle some kind of params
        switch ($request->request->body->mode) {
            case 'raw':
                if (!empty($request->request->body->raw)) {
                    $this->renderParam(self::PARAM_PARAMS, (array)$request->request->body->raw);
                }
                break;
            case 'formdata':
            case 'urlencoded':
                if (!$request->request->body->formdata && !$request->request->body->urlencoded) {
                    $this->renderParam(self::PARAM_PARAMS, []);
                    break;
                }
                $formdata = $request->request->body->formdata ?? $request->request->body->urlencoded;
                $params = [];
                foreach ($formdata as $formdatum) {
                    $params[$formdatum->key] = $formdatum->value;
                }
                $this->renderParam(self::PARAM_PARAMS, $params);
                break;
        }
        return $this->template;
    }

    /**
     * @param string $name
     * @param $value
     */
    protected function renderParam(string $name, $value)
    {
        if (is_array($value)) {
            $value = var_export($value, true);
        }
        $this->template = str_replace("%$name%", $value, $this->template);
    }

    /**
     * @param string $methodType
     * @return string
     * @throws \Exception
     */
    protected function getMethodTemplate(string $methodType): string
    {
        $template = __DIR__ . self::TEMPLATE_FILE . strtolower($methodType) . '.txt';
        if (!file_exists($template)) {
            throw new \Exception(sprintf('Dont have %s type template', $methodType));
        }
        return $template;
    }

}