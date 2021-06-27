<?php
if (!check_perms('site_debug') || !check_perms('admin_clear_cache')) {
    error(403);
}
if (isset($_POST['global_flush'])) {
    authorize();
    $Cache->flush();
}

$DB->prepared_query('SHOW GLOBAL STATUS');
$DBStats = $DB->to_array('Variable_name');

$DB->prepared_query('SHOW GLOBAL VARIABLES');
$DBVars = $DB->to_array('Variable_name');

$MemStats = $Cache->getStats();
$MemStats = $MemStats[array_keys($MemStats)[0]];

View::show_header("Service Stats");
echo $Twig->render('admin/cache-db-stats.twig', [
    'auth'       => $Viewer->auth(),
    'can_see_db' => check_perms('site_database_specifics'),
    'db_stats'   => $DBStats,
    'db_vars'    => $DBVars,
    'mem_stats'  => $MemStats,
    'now'        => time(),
]);
View::show_footer();
