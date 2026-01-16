<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Php73\Rector\FuncCall\RegexDashEscapeRector;
use Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
define('BONUS_UPLOAD_BOOST_ACTIVE', true);
define('DEBUG_TWIG', true);
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
define('SECURE_COOKIE', false);

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../app',
        __DIR__ . '/../bin',
        __DIR__ . '/../classes',
        __DIR__ . '/../lib',
        __DIR__ . '/../sections',
        __DIR__ . '/../tests',
    ])
    ->withPreparedSets(
        deadCode: true,
    )
    ->withPhpSets()
    ->withSkip([
        NullToStrictStringFuncCallArgRector::class,
        RegexDashEscapeRector::class,
        StringifyStrNeedlesRector::class,
        // From the one-thing-at-a-time department
        AddTypeToConstRector::class,
        AddOverrideAttributeToOverriddenMethodsRector::class,
        // Until rector understands that defines can be dynamic, this has to be
        // ignored, otherwise rector will hack stuff out that should be untouched.
        RemoveAlwaysTrueIfConditionRector::class,
    ]);
