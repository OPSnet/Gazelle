<?php

if (!$Viewer->permitted('site_debug')) {
    error(403);
}

$inspectDb = new Gazelle\DB();
$MemStats = $Cache->getStats();

echo $Twig->render('admin/cache-db-stats.twig', [
    'auth'       => $Viewer->auth(),
    'can_see_db' => $Viewer->permitted('site_database_specifics'),
    'db_stats'   => $inspectDb->globalStatus(),
    'db_vars'    => $inspectDb->globalVariables(),
    'mem_stats'  => $MemStats[array_keys($MemStats)[0]],
    'now'        => time(),
]);
