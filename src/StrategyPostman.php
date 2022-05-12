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
        foreach ($this->data->item as $item) {
            echo PHP_EOL . 'List: ' . $item->name;
            $this->processCollectionFolder($item);
        }

        return true;
    }

    /**
     * generate cept file
     * @param object $item
     * @throws \Exception
     */
    protected function processCollectionFolder($item): void
    {
        $function_codes = [];
        echo PHP_EOL . 'Request: ' . $item->name;
        $function_codes[] = $this->render->renderFunction($item);

        $result = $this->render->renderCept($item, $function_codes);
        // ddd($function_codes,$result);
        echo PHP_EOL . 'Result: ' . file_put_contents($this->directory . 'Test.php', $result, FILE_APPEND);
        echo PHP_EOL . 'END';
    }

}