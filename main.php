<?php
// $loader = require 'vendor/autoload.php';
// $loader->add('swagpostception\\', __DIR__);

/** @todo реализовать CLI комманду */

use swagpostception\TestGenerationManager;

$manager = new TestGenerationManager(
    __DIR__ . '/../postman/example.json',
    \swagpostception\StrategyPostman::STRATEGY
);