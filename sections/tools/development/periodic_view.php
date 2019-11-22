<?php
function formatBool(bool $val) {
    return $val ? 'Yes' : 'No';
}

if (!check_perms('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new \Gazelle\Schedule\Scheduler($DB, $Cache);
$tasks = $scheduler->getTaskDetails();
$canEdit = check_perms('admin_periodic_task_manage');
$canLaunch = check_perms('admin_schedule');

View::show_header('Periodic Task Status');
?>
<div class="header">
    <h2>Periodic Task Status</h2>
</div>
<?php include(SERVER_ROOT.'/sections/tools/development/periodic_links.php'); ?>
<table width="100%" id="tasks">
    <tr class="colhead">
        <td>Name</td>
        <td>Description</td>
        <td>Interval</td>
        <td>Enabled</td>
        <td>Sane</td>
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
    list($id, $name, $description, $period, $isEnabled, $isSane, $runs, $processed, $errors, $events, $lastRun, $duration, $status) = array_values($task);

    if ($runs == 0) {
        $lastRun = 'Never';
        $nextRun = sqltime();
        $duration = '-';
        $status = '-';
        $processed = '-';
        $errors = '-';
        $events = '-';
    } else {
        $duration .= 'ms';
        $nextRun = sqltime(strtotime($lastRun) + $period);
        if ($status === 'running') {
            $duration = time_diff(time() - strtotime($lastRun) + time());
        }
    }
    $period = time_diff(sqltime(time() + $period), 2, false);

    $row = $row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$row?>">
        <td><?=$name?></td>
        <td><?=$description?></td>
        <td><?=$period?></td>
        <td><?=formatBool($isEnabled)?></td>
        <td><?=formatBool($isSane)?></td>
        <td>
            <span class="reltime"><?=time_diff($lastRun)?></span>
            <span class="abstime hidden"><?=$lastRun?></span>
        </td>
        <td><?=$duration?></td>
        <td>
            <span class="reltime"><?=time_diff($nextRun)?></span>
            <span class="abstime hidden"><?=$nextRun?></span>
        </td>
        <td><?=$status?></td>
        <td><?=$runs?></td>
        <td><?=$processed?></td>
        <td><?=$errors?></td>
        <td><?=$events?></td>
        <td>
            <a class="brackets" href="tools.php?action=periodic&amp;mode=detail&amp;id=<?=$id?>">Details</a>
<?php if ($canLaunch) { ?>
            <a class="brackets" href="schedule.php?auth=<?=$LoggedUser['AuthKey']?>&amp;new=&amp;id=<?=$id?>">Run Now</a>
<?php } ?>
        </td>
    </tr>
<?php } ?>
</table>
<?php
    View::show_footer();
?>
