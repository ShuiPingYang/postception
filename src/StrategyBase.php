<?php
declare(strict_types=1);

namespace Shuiping\Postception;
/**
 * Class StrategyBase
 * @package swagpostception
 */
abstract class StrategyBase
{
    /**
     * @var Render
     */
    public $render;

    /**
     * @var
     */
    protected $data;

    /**
     * @var
     */
    protected $directory;

    /**
     * @param string $name
     * @return bool
     * @throws \Exception
     */
    protected function createDir(string $name): bool
    {
        $directory = pathinfo(__DIR__, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new \Exception('Directory creation failed ' . $directory);
            }
        }

        $this->directory = $directory;

        return true;
    }

    /**
     * @return string
     */
    abstract protected function getProjectName(): string;

    /**
     * @param object $data
     * @return bool
     * @throws \Exception
     */
    public function generate($data): bool
    {
        $this->setData($data);
        $this->createDir($this->getProjectName());

        return true;
    }

    /**
     * StrategyBase constructor.
     */
    public function __construct()
    {
        /** @todo переписать на DIC */
        $this->setRender(new Render());
    }

    /**
     * @param $render
     */
    protected function setRender($render)
    {
        $this->render = $render;
    }

    /**
     * @param object $data
     */
    protected function setData($data): void
    {
        $this->data = $data;
    }

}