<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
define('DEBUG_MODE', true);
define('DEBUG_WARNINGS', true);
define('DISABLE_IRC', false);
define('FEATURE_EMAIL_REENABLE', true);
define('GEOIP_SERVER', true);
define('HTTP_PROXY', '127.0.0.1');
define('LASTFM_API_KEY', true);
define('OPEN_EXTERNAL_REFERRALS', true);
define('PUSH_SOCKET_LISTEN_ADDRESS', true);
define('REAPER_TASK_CLAIM', true);
define('REAPER_TASK_NOTIFY', true);
define('REAPER_TASK_REMOVE_UNSEEDED', true);
define('REAPER_TASK_REMOVE_NEVER_SEEDED', true);
define('RECOVERY_AUTOVALIDATE', true);

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/../app',
        __DIR__ . '/../classes',
        __DIR__ . '/../lib',
        __DIR__ . '/../sections',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::DEAD_CODE,
    ]);

    $rectorConfig->skip([
        NullToStrictStringFuncCallArgRector::class,
    ]);

    $rectorConfig->disableParallel();
};
