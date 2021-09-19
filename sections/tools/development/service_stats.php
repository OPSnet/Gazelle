<?php

if (!$Viewer->permittedAny('site_debug', 'admin_clear_cache')) {
    error(403);
}

if (isset($_POST['global_flush'])) {
    authorize();
    $Cache->flush();
}

$inspectDb = new Gazelle\DB;
$MemStats = $Cache->getStats();

echo $Twig->render('admin/cache-db-stats.twig', [
    'auth'       => $Viewer->auth(),
    'can_see_db' => $Viewer->permitted('site_database_specifics'),
    'db_stats'   => $inspectDb->globalStatus(),
    'db_vars'    => $inspectDb->globalVariables(),
    'mem_stats'  => $MemStats[array_keys($MemStats)[0]],
    'now'        => time(),
]);
