<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/../app',
        __DIR__ . '/../classes',
        __DIR__ . '/../lib',
        __DIR__ . '/../sections',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::DEAD_CODE,
    ]);

    $rectorConfig->skip([
        JsonThrowOnErrorRector::class,
        NullToStrictStringFuncCallArgRector::class,
    ]);

    $rectorConfig->disableParallel();
};
