<?php
function formatBool(bool $val) {
    return $val ? 'Yes' : 'No';
}

if (!check_perms('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new \Gazelle\Schedule\Scheduler;

if ($_REQUEST['mode'] === 'run_now' && isset($_REQUEST['id'])) {
    authorize();
    if (!check_perms('admin_schedule')) {
        error(403);
    }
    $scheduler->runNow(intval($_REQUEST['id']));
}

$tasks = $scheduler->getTaskDetails();
$canEdit = check_perms('admin_periodic_task_manage');
$canLaunch = check_perms('admin_schedule');

View::show_header('Periodic Task Status');
?>
<div class="header">
    <h2>Periodic Task Status</h2>
</div>
<?php include(__DIR__ . '/periodic_links.php'); ?>
<table width="100%" id="tasks">
    <tr class="colhead">
        <td>Name</td>
        <td>Interval</td>
        <td>Last Run <a href="#" onclick="$('#tasks .reltime').gtoggle(); $('#tasks .abstime').gtoggle(); return false;" class="brackets">Toggle</a></td>
        <td>Duration</td>
        <td>Next Run</td>
        <td>Status</td>
        <td>Runs</td>
        <td>Processed</td>
        <td>Errors</td>
        <td>Events</td>
        <td></td>
    </tr>
<?php
$row = 'b';
foreach ($tasks as $task) {
    list($id, $name, $description, $period, $isEnabled, $isSane, $runNow, $runs, $processed, $errors, $events, $lastRun, $duration, $status) = array_values($task);

    if ($runs == 0) {
        $lastRun = 'Never';
        $nextRun = sqltime();
        $duration = '-';
        $status = '-';
        $processed = '0';
        $errors = '0';
        $events = '0';
    } else {
        $duration .= 'ms';
        $nextRun = sqltime(strtotime($lastRun) + $period);
        if ($status === 'running') {
            $duration = time_diff(time() - strtotime($lastRun) + time());
        }
    }
    $period = time_diff(sqltime(time() + $period), 2, false);

    $row = $row === 'a' ? 'b' : 'a';
    $prefix = '';
    $color = null;
    if (!$isSane) {
        $color = " color:tomato;";
        $prefix .= 'Insane: ';
    }
    if (!$isEnabled && !$runNow) {
        $color = " color:sandybrown;";
        $prefix .= 'Disabled: ';
    }
    if ($runNow) {
        $color = " color:green;";
        $prefix .= 'Run Now: ';
    }
?>
    <tr class="row<?=$row?>">
        <td title="<?= $description ?>">
            <a style="<?= $color ?? '' ?>" href="tools.php?action=periodic&amp;mode=detail&amp;id=<?=$id?>"><?= $prefix . $name ?></a>
        </td>
        <td><?=$period?></td>
        <td>
            <span class="reltime"><?=time_diff($lastRun)?></span>
            <span class="abstime hidden"><?=$lastRun?></span>
        </td>
        <td><?=$duration?></td>
        <td>
            <span class="reltime"><?=time_diff($nextRun)?></span>
            <span class="abstime hidden"><?=$nextRun?></span>
        </td>
        <td><?= $status ?></td>
        <td class="number_column"><?= number_format($runs) ?></td>
        <td class="number_column"><?= number_format($processed) ?></td>
        <td class="number_column"><?= number_format($errors) ?></td>
        <td class="number_column"><?= number_format($events) ?></td>
        <td>
<?php if ($canLaunch) { ?>
            <a class="brackets" href="tools.php?action=periodic&amp;mode=run_now&amp;auth=<?= $Viewer->auth() ?>&amp;id=<?=$id?>">Run Now</a>
            <a class="brackets" href="schedule.php?auth=<?= $Viewer->auth() ?>&amp;id=<?=$id?>">Debug</a>
<?php } ?>
        </td>
    </tr>
<?php } ?>
</table>
<?php
View::show_footer();
