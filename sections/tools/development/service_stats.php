<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Gazelle\Cache $Cache
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_debug')) {
    Error403::error();
}

$inspectDb = new DB();
$MemStats = $Cache->getStats();

echo $Twig->render('admin/cache-db-stats.twig', [
    'auth'       => $Viewer->auth(),
    'can_see_db' => $Viewer->permitted('site_database_specifics'),
    'db_stats'   => $inspectDb->globalStatus(),
    'db_vars'    => $inspectDb->globalVariables(),
    'mem_stats'  => $MemStats[array_keys($MemStats)[0]],
    'now'        => time(),
]);
