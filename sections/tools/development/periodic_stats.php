<?php
if (!check_perms('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new \Gazelle\Schedule\Scheduler($DB, $Cache);
$canEdit = check_perms('admin_periodic_task_manage');

View::show_header('Periodic Task Statistics');
?>
<div class="header">
<h2>Periodic Task Statistics</h2>
</div>
<?php include(SERVER_ROOT.'/sections/tools/development/periodic_links.php'); ?>
<pre style="text-align: center;">TODO: stat graphs</pre>
