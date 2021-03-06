<?php
declare(strict_types=1);

namespace Shuiping\Postception;

use Shuiping\Postception\interfaces\Generateable;

/**
 * Class TestGenerationManager
 * @package swagpostception
 */
class TestGenerationManager
{
    /**
     * @var Generateable
     */
    protected $generator;

    /**
     * TestGenerationManager constructor.
     * @param $file
     * @param $strategy
     * @throws \Exception
     */
    public function __construct(string $file, string $strategy)
    {
        $generator = $this->getGeneratorByStrategy($strategy);
        /** @todo переписать на DIC */
        $this->setGenerator(new $generator);
        if (!$this->generator->generate($this->getDataFromFile($file))) {
            throw new \Exception('Generate failed');
        }
    }

    /**
     * @param Generateable $generator
     */
    protected function setGenerator(Generateable $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @param $file
     * @return Object
     * @throws \Exception
     */
    protected function getDataFromFile(string $file)
    {
        if (!file_exists($file)) {
            throw new \Exception('File not exists');
        }
        $data = file_get_contents($file);
        if (empty($data)) {
            throw new \Exception('Cant read file');
        }
        $data = json_decode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Wrong json input');
        }

        return $data;
    }

    /**
     * @param string $strategy
     * @return string
     * @throws \Exception
     */
    protected function getGeneratorByStrategy(string $strategy): string
    {
        $map = [
            StrategyPostman::STRATEGY => StrategyPostman::class,
        ];
        if (empty($strategy) || !is_string($strategy) || !isset($map[$strategy])) {
            throw new \Exception('Wrong generator strategy');
        }

        return $map[$strategy];
    }
}