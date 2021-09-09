<?php
if (!$Viewer->permitted('site_debug') || !$Viewer->permitted('admin_clear_cache')) {
    error(403);
}
if (isset($_POST['global_flush'])) {
    authorize();
    $Cache->flush();
}

$inspectDb = new Gazelle\DB;
$MemStats = $Cache->getStats();
$MemStats = $MemStats[array_keys($MemStats)[0]];

View::show_header("Service Stats");
echo $Twig->render('admin/cache-db-stats.twig', [
    'auth'       => $Viewer->auth(),
    'can_see_db' => $Viewer->permitted('site_database_specifics'),
    'db_stats'   => $inspectDb->globalStatus(),
    'db_vars'    => $inspectDb->globalVariables(),
    'mem_stats'  => $MemStats,
    'now'        => time(),
]);
View::show_footer();
