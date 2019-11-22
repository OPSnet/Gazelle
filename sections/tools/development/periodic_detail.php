<?php
if (!check_perms('admin_periodic_task_view')) {
    error(403);
}

if (!isset($_GET['id']) || !is_number($_GET['id'])) {
    error(0);
}
$id = intval($_GET['id']);

define('TASKS_PER_PAGE', 10);
list($page, $limit) = Format::page_limit(TASKS_PER_PAGE);

$scheduler = new \Gazelle\Schedule\Scheduler($DB, $Cache);
$task = $scheduler->getTaskHistory($id, $limit);
$canEdit = check_perms('admin_periodic_task_manage');

View::show_header('Periodic Task Details');
?>
<div class="header">
<h2>Periodic Task Details - <?=$task->name?></h2>
</div>
<?php include(SERVER_ROOT.'/sections/tools/development/periodic_links.php');
if ($task->count > 0) { ?>
<br />
<div class="linkbox">
    <?=Format::get_pages($page, $task->count, TASKS_PER_PAGE, 11)?>
</div>
<table width="100%" id="tasks">
    <tr class="colhead">
        <td>Launch Time <a href="#" onclick="$('#tasks .reltime').gtoggle(); $('#tasks .abstime').gtoggle(); return false;" class="brackets">Toggle</a></td>
        <td>Duration</td>
        <td>Status</td>
        <td>Processed</td>
        <td>Errors</td>
    </tr>
<?php
    foreach ($task->items as $item) {
        $item->duration .= 'ms';
?>
    <tr class="rowa">
        <td>
            <span class="reltime"><?=time_diff($item->launchTime)?></span>
            <span class="abstime hidden"><?=$item->launchTime?></span>
        </td>
        <td><?=$item->duration?></td>
        <td><?=$item->status?></td>
        <td><?=$item->numItems?></td>
        <td><?=$item->numErrors?></td>
    </tr>
<?php   if (count($item->events) > 0) { ?>
    <tr class="rowb">
        <td colspan="5">
            <table>
                <tr class="colhead">
                    <td>Event Time</td>
                    <td>Severity</td>
                    <td>Event</td>
                    <td>Reference</td>
                </tr>
<?php
            foreach ($item->events as $event) {
?>
                <tr>
                    <td>
                        <span class="reltime"><?=time_diff($event->timestamp)?></span>
                        <span class="abstime hidden"><?=$event->timestamp?></span>
                    </td>
                    <td><?=$event->severity?></td>
                    <td><?=$event->event?></td>
                    <td><?=$event->reference?></td>
                </tr>
<?php       } ?>
            </table>
        </td>
<?php   } ?>
    </tr>
<?php } ?>
</table>
<?php
} else {
?>
<div class="center">
    <h2>No history found</h2>
</div>
<?php
}
View::show_footer();
?>
