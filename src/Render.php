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
    // postman里面可能存在全局host变量，使用下面或者替换
    const REQUEST_URL = 'https://tool.oschina.net/regex';
    const RESPONSE_CHACK = 'response_check';
    // 返回的状态码字段名称
    const RESPONSE_CODE = 'flag';
    // 返回的数据字段名称
    const RESPONSE_DATA = 'msg';
    // 默认的json返回结果检查规则
    const DEFAULT_RULES = [
        'code' => 'integer:=200',
        'message' => 'string',
        'data' => 'array',
    ];
    // 不解析的字段
    const NOT_PARSE = ['info', 'list'];

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
        $slices = explode('/', $item->name);
        if ($var < 1) {
            $this->template = file_get_contents(__DIR__ . self::TEMPLATE_CEPT);
            $this->renderParam(self::PARAM_CEPT_CEPTNAME, 'Api');
            $var++;
        }
        $this->renderParam(self::PARAM_CEPT_METHODS, implode('', $function_codes));

        return $this->template;
    }

    /**
     * generate cept code
     * @param object $request
     * @return string
     * @throws \Exception
     */
    public function renderFunction($request_item): string
    {
        if (empty($request_item->method)) {
            throw new \Exception(sprintf('Empty %s method type', $request_item->method));
        }
        if (empty($this->getMethodTemplate($request_item->method))) {
            throw new \Exception(sprintf('Dont have %s type template', $request_item->method));
        }

        $this->template = file_get_contents($this->getMethodTemplate($request_item->method ?? ''));
        if (strlen($request_item->name) > 20) {
            $request_item->name = parse_url($request_item->name, PHP_URL_PATH);
        }
        $slices = explode('/', $request_item->name);

        $this->renderParam(self::PARAM_NAME, $slices[3] ?? $slices[1] ?? $slices[0]);
        $this->renderParam(self::PARAM_DESCRIPTION, $request_item->description ?? 'Test ' . $request_item->name);
        $this->renderParam(self::PARAM_URL, $request_item->url->raw ?? '');
        // 将保存的示例解析出来做判断，如果不存在示例，那就使用默认规则
        if (empty($request_item->response_body)) {
            $this->renderParam(self::RESPONSE_CHACK, self::DEFAULT_RULES);
        } else {
            $response_body = json_decode($request_item->response_body, true);
            foreach ($response_body as $key => $value) {
                if ($key !== self::RESPONSE_DATA) {
                    $rules[$key] = gettype($value);
                }
                if ($key === self::RESPONSE_CODE) {
                    $rules[$key] = gettype($value) . ':=' . $value;
                }
                if (is_array($value)) {
                    $rules[$key] = $this->getValueType($value);
                }
            }
            // 检查返回结果里面的字段规则，并替换
            $this->renderParam(self::RESPONSE_CHACK, var_export($rules, true) ?? '');
        }

        // 如果没有参数，就直接替换为空数组
        if (empty($request_item->body)) {
            $this->renderParam(self::PARAM_PARAMS, var_export([], true));
            return $this->template;
        }

        switch ($request_item->body->mode) {
            case 'raw':
                if (!empty($request_item->body->raw)) {
                    $this->renderParam(self::PARAM_PARAMS, $this->changeParams(json_decode($request_item->body->raw, true)));
                }
                break;
            case 'formdata':
            case 'urlencoded':
                // 如果没有param，直接使用空数组代替
                if (empty($request_item->body->formdata) && empty($request_item->body->urlencoded)) {
                    $this->renderParam(self::PARAM_PARAMS, []);
                    break;
                }
                $formdata = $request_item->body->formdata ?? $request_item->body->urlencoded;
                $params = [];
                foreach ($formdata as $formdatum) {
                    $params[$formdatum->key] = $formdatum->value;
                }
                $this->renderParam(self::PARAM_PARAMS, $this->changeParams($params));
                break;
            default:
                $this->renderParam(self::PARAM_PARAMS, var_export([], true));
                break;
        }
        return $this->template;
    }

    /**
     * 递归获取值类型，并保持结构不变
     * @author Bruce 2022/5/13
     * @param $arr
     * @return mixed
     */
    public function getValueType($arr)
    {
        foreach ($arr as $k => $v) {
            if (is_array($arr[$k])) {
                if (in_array($k, self::NOT_PARSE)) {
                    $arr[$k] = 'array';
                } else {
                    $arr[$k] = $this->getValueType($arr[$k]);
                }
            } else {
                $gettype = gettype($arr[$k]);
                $arr[$k] = $gettype === 'double' ? 'float' : $gettype;
            }
        }
        return $arr;
    }

    /**
     * changeParams
     * @author Bruce 2022/5/12
     * @param array $params
     * @return array
     */
    public function changeParams(array $params)
    {
        foreach ($params as $key => $param) {
            // 如果是数字类型的字符串，且第一个数字不是0，那么就需要转成数字参数
            if (is_string($param) && is_numeric($param) && strpos($param, '0') !== 0) {
                $params[$key] = $param + 0;
            }
        }
        return $params;
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