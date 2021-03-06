<?php
declare(strict_types=1);

namespace Shuiping\Postception;
/**
 * Class Render
 * @package swagpostception
 */
class Render
{
    protected const PARAM_NAME = 'name';
    protected const PARAM_DESCRIPTION = 'description';
    protected const PARAM_PARAMS = 'params';
    protected const PARAM_URL = 'url';

    protected const PARAM_CEPT_CEPTNAME = 'ceptname';
    protected const PARAM_CEPT_METHODS = 'methods';

    protected const TEMPLATE_FILE = '/templates/template_';
    protected const TEMPLATE_CEPT = '/templates/template_cept.txt';
    // postman里面可能存在全局host变量，使用下面或者替换
    const REQUEST_URL = 'https://tool.oschina.net/regex';
    const REQUEST_METHOD = 'request_method';
    const RESPONSE_CHACK = 'response_check';
    // 返回的状态码字段名称
    const RESPONSE_CODE = 'flag';
    // 返回的数据字段名称
    const RESPONSE_DATA = 'msg';
    // 默认的json返回结果检查规则
    const DEFAULT_RULES = [
        'flag' => 'integer:=1',
        'msg' => 'array',
        '__token__' => 'string',
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
    public function renderCept(array $function_codes, $path = ''): string
    {
        static $modules = [];
        $module = explode('/', $path)[2];
        if (!in_array($module, $modules)) {
            $this->template = file_get_contents(__DIR__ . self::TEMPLATE_CEPT);
            $this->renderParam(self::PARAM_CEPT_CEPTNAME, $module);
            $modules[] = $module;
        }

        $this->renderParam(self::PARAM_CEPT_METHODS, implode('', $function_codes));

        return $this->template;
    }

    /**
     * generate cept code
     * @param object $request
     * @return string|null
     * @throws \Exception
     */
    public function renderFunction($request_item): ?string
    {
        // 过滤相同的请求
        static $exists_methods = [];
        // 记录请求的名称，如果存在多个example，需要修改后续方法名称，避免方法名称重复
        static $exists_names = [];
        static $exists_name_index = 10000;
        if (empty($request_item->method)) {
            throw new \Exception(sprintf('Empty %s method type', $request_item->method));
        }
        if (empty($this->getMethodTemplate($request_item->method))) {
            throw new \Exception(sprintf('Dont have %s type template', $request_item->method));
        }

        $this->template = file_get_contents($this->getMethodTemplate($request_item->method ?? ''));

        // 替换名称中的斜杠字符,包括模块，控制器，方法名，例如：api/Log/getWeb
        $url_path = $request_item->url->path;
        $name = implode('/', $url_path);
        $this->renderParam(self::PARAM_URL, $name);
        // 将保存的示例解析出来做判断，如果不存在示例，那就使用默认规则
        if (empty($request_item->response_body)) {
            $this->renderParam(self::RESPONSE_CHACK, self::DEFAULT_RULES);
        } else {
            $response_body = json_decode($request_item->response_body, true);
            // 保存的example的结果
            $rules = $this->getValueType($response_body);
            // 检查返回结果里面的字段规则，并替换
            $this->renderParam(self::RESPONSE_CHACK, var_export($rules, true) ?? '');
        }

        // 防止url和body体都有参数，且参数不相同
        $url_params = $request_item->url->query ?? [];
        $url_params = $url_params ? json_decode(urldecode(json_encode($url_params, 320)), true) : [];
        if ($url_params && is_array($url_params)) {
            $url_params = $this->changeParams($url_params);
        }

        $body_params = $request_item->body->raw ?? $request_item->body->formdata ?? $request_item->body->urlencoded ?? [];
        $body_params = $body_params ? json_decode(json_encode($body_params, 320), true) : [];
        if ($body_params && is_array($body_params)) {
            $body_params = $this->changeParams($url_params);
        }

        $real_params = array_merge((array)$url_params, (array)$body_params);
        // 如果已经存在同样的请求信息，直接过滤
        $exist = md5(json_encode($real_params) . $name . $request_item->method);
        if (in_array($exist, $exists_methods)) {
            return $this->template = null;
        }

        // 替换方法名称
        $exists_methods[] = $exist;
        // 如果存在同名，也就是就要加上方法序数
        if (in_array($name, $exists_names)) {
            $exists_name_index++;
            $name .= $exists_name_index;
        }
        // ddd(file_put_contents('test.txt',json_encode($request_item,320)));
        $exists_names[] = $name;
        $this->renderParam(self::PARAM_DESCRIPTION, $request_item->description ?? 'Test ' . $name);
        $value = explode('/', $name);
        $this->renderParam(self::PARAM_NAME, end($value));
        $this->renderParam(self::REQUEST_METHOD, ucfirst(strtolower($request_item->method)));
        // 替换参数信息
        $this->renderParam(self::PARAM_PARAMS, var_export($real_params, true));
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
        $rules = [];
        foreach ($arr as $key => $item) {
            if (is_array($item) && !in_array($key, self::NOT_PARSE)) {
                // 特殊处理msg里面没有key的数据
                if (isset($item[0]) || empty($item)) {
                    $rules[$key] = gettype($item);
                } else {
                    // 正常数组
                    $rules[$key] = $this->getValueType($item);
                }
            } else {
                if ($key === self::RESPONSE_CODE) {
                    $rules[$key] = gettype($item) . ':=' . $item;
                } else {
                    $rules[$key] = gettype($item);
                }
            }
        }
        return $rules;
    }

    /**
     * changeParams
     * @author Bruce 2022/5/12
     * @param array $params
     * @return array
     */
    public function changeParams(array $params)
    {
        $new_params = [];
        foreach ($params as $param) {
            // 如果是数字类型的字符串，且第一个数字不是0，那么就需要转成数字参数
            if (is_string($param['value']) && is_numeric($param['value']) && strpos($param['value'], '0') !== 0) {
                $new_params[$param['key']] = $param['value'] + 0;
            } else {
                $new_params[$param['key']] = $param['value'];
            }
        }
        return $new_params;
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

        return $this->template = str_replace("%$name%", $value, $this->template);

        // if ($name === 'name') {
        //     // dumps(str_replace("%$name%", $value, $this->template),1111111111);
        //     ddd("%$name%", $value, $this->template);
        // }
    }

    /**
     * @param string $methodType
     * @return string
     * @throws \Exception
     */
    protected function getMethodTemplate(string $methodType): string
    {
        $template = __DIR__ . self::TEMPLATE_FILE . strtolower($methodType) . '.txt';
        // if template is not exist,use post template as default
        if (!file_exists($template)) {
            $template = __DIR__ . self::TEMPLATE_FILE . strtolower('post') . '.txt';
        }
        return $template;
    }

}