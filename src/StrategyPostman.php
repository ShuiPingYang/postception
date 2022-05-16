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
            // 如果没有example
            if (empty($item->response)) {
                echo PHP_EOL . 'List: ' . $item->name;
                $item->request->name = $item->name;
                $result[] = $this->processCollectionFolder($item->request);
            } else {
                // 如果有多个response，分别做匹配
                $responses = $item->response;
                // 把每个example都加入到方法
                foreach ($responses as $response) {
                    echo PHP_EOL . 'List: ' . $response->name;
                    $response->originalRequest->name = $response->name;
                    $response->originalRequest->response_body = $response->body;
                    $result[] = $this->processCollectionFolder($response->originalRequest);
                }
            }
        }
        // 全部内容都写到这个文件中
        $filename = $this->directory . 'ApiCest.php';

        // 匹配修改{{host}}
        $pattern = '/[\{].*[\}]/';
        $replacement = Render::REQUEST_URL;
        $result = preg_replace($pattern, $replacement, implode(PHP_EOL, $result) . '}');
        // 把所有结果输入到文件中
        echo PHP_EOL . 'Result: ' . file_put_contents($filename, $result);
        echo PHP_EOL . 'END';

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

        return $this->render->renderCept($item, $function_codes);
    }

}