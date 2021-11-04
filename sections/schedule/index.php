<?php
/*************************************************************************\
//--------------Schedule page -------------------------------------------//

This page is run every minute, by cron.

\*************************************************************************/

set_time_limit(50000);
ob_end_flush();
gc_enable();

/*
 * Use this if your version of pgrep does not support the '-c' option.
 * The '-c' option requires procps-ng.
 *
 * $PCount = chop(shell_exec("/usr/bin/pgrep -f schedule.php | wc -l"));
 */
$PCount = chop(shell_exec("/usr/bin/pgrep -cf schedule.php"));
if ($PCount > 3) {
    // 3 because the cron job starts two processes and pgrep finds itself
    die();
}

if (PHP_SAPI === 'cli') {
    if (!isset($argv[1]) || $argv[1] != SCHEDULE_KEY) {
        error(403);
    }

    $sqltime = sqltime();
    echo("Current Time: $sqltime\n\n");

    $scheduler = new \Gazelle\Schedule\Scheduler;
    if (isset($argv[2])) {
        $scheduler->runTask(intval($argv[2]), true);
    } else {
        $scheduler->run();
    }
} else {
    if (!isset($Viewer) || !$Viewer->permitted('admin_schedule')) {
        error(403);
    }

    authorize();
    View::show_header('Scheduler');
?>
<div class="linkbox">
    <a class="brackets" href="tools.php?action=periodic&amp;mode=view">Status</a>
    <a class="brackets" href="tools.php?action=periodic&amp;mode=stats">Statistics</a>
<?php if ($Viewer->permitted('admin_periodic_task_manage')) { ?>
    <a class="brackets" href="tools.php?action=periodic&amp;mode=edit">Edit</a>
<?php } ?>
</div>
<pre>
<?php
    $scheduler = new \Gazelle\Schedule\Scheduler;
    if (isset($_GET['id'])) {
        $scheduler->runTask(intval($_GET['id']), true);
    } else {
        $scheduler->run();
    }
}

echo "-------------------------\n\n";
if (isset($Viewer) && $Viewer->permitted('admin_schedule')) {
    echo '</pre>';
    View::show_footer();
}
