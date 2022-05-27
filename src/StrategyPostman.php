<?php
declare(strict_types=1);

namespace Shuiping\Postception;

use Shuiping\Postception\interfaces\Generateable;

/**
 * Class StrategyPostman
 * @package swagpostception
 */
class StrategyPostman extends StrategyBase implements Generateable
{

    /**
     * From postman to codeception strategy
     */
    public const STRATEGY = 'postman';

    /**
     * @return string
     */
    public function getProjectName(): string
    {
        return $this->data->info->name;
    }

    /**
     * @param object $data
     * @return bool
     * @throws \Exception
     */
    public function generate($data): bool
    {
        parent::generate($data);
        /** @todo replace StdClass by Structs */
        $result = [];
        foreach ($this->data->item as $item) {
            $path = parse_url($item->request->url->raw, PHP_URL_PATH);
            $module = explode('/', $path)[2];
            // 如果没有example
            if (empty($item->response)) {
                echo PHP_EOL . 'List: ' . $item->name;
                $item->request->name = $item->name;
                $result[$module][] = $this->processCollectionFolder($item->request);
            } else {
                // 如果有多个response，分别做匹配
                $responses = $item->response;
                // 把每个example都加入到方法
                foreach ($responses as $response) {
                    echo PHP_EOL . 'List: ' . $response->name;
                    $response->originalRequest->name = $response->name;
                    $response->originalRequest->response_body = $response->body;
                    $result[$module][] = $this->processCollectionFolder($response->originalRequest);
                }
            }

        }

        foreach ($result as $key => $res) {
            $filename = $this->directory . $key . 'Cest.php';
            // 匹配修改{{host}}
            $pattern = '/[\{].*[\}]/';
            $replacement = Render::REQUEST_URL;
            $result = preg_replace($pattern, $replacement, implode(PHP_EOL, $res) . '}');
            // 把所有结果输入到文件中
            echo PHP_EOL . 'Result: ' . file_put_contents($filename, $result);
            echo PHP_EOL . 'END';
        }
        // 全部内容都写到这个文件中

        return true;
    }

    /**
     * generate cept file
     * @param object $item
     * @throws \Exception
     */
    protected function processCollectionFolder($item): string
    {
        $function_codes = [];
        echo PHP_EOL . 'Request: ' . $item->name;
        $function_codes[] = $this->render->renderFunction($item);
        $path = parse_url($item->url->raw, PHP_URL_PATH) ?? '';

        return $this->render->renderCept($function_codes, $path);
    }

}