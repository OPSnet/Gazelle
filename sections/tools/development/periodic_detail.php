<?php
if (!check_perms('admin_periodic_task_view')) {
    error(403);
}

$id = (int)$_GET['id'] ?? 0;
if (!$id) {
    error(0);
}

$scheduler = new \Gazelle\Schedule\Scheduler;
if (!$scheduler->getTask($id)) {
    error(404);
}

define('TASKS_PER_PAGE', 100);
list($page, $limit, $offset) = \Gazelle\DB::pageLimit(TASKS_PER_PAGE);

$sort = in_array($_GET['order'] ?? '', ['id', 'launchtime', 'status', 'errors', 'items', 'duration']) ? $_GET['order'] : 'launchtime';
$orderWay = in_array($_GET['sort'] ?? '', ['asc', 'desc']) ? $_GET['sort'] : 'desc';

$task = $scheduler->getTaskHistory($id, $limit, $offset, $sort, $orderWay);
$stats = $scheduler->getTaskRuntimeStats($id);
$canEdit = check_perms('admin_periodic_task_manage');

View::show_header('Periodic Task Details');
?>
<div class="header">
<h2>Periodic Task Details - <?=$task->name?></h2>
</div>
<?php include(__DIR__ . '/periodic_links.php');
if ($task->count > 0) {
    $header = new \Gazelle\Util\SortableTableHeader([
        'launchtime' => 'Launch Time',
        'duration'   => 'Duration',
        'status'     => 'Status',
        'items'      => 'Processed',
        'errors'     => 'Errors'
    ], $sort, $orderWay);
?>
<br />
<div class="box pad">
    <div id="daily-totals" style="width: 100%; height: 350px;"></div>
</div>
<div class="linkbox">
    <?=Format::get_pages($page, $task->count, TASKS_PER_PAGE, 11)?>
</div>
<table width="100%" id="tasks">
    <tr class="colhead">
        <td><?=$header->emit('launchtime', 'desc')?> <a href="#" onclick="$('#tasks .reltime').gtoggle(); $('#tasks .abstime').gtoggle(); return false;" class="brackets">Toggle</a></td>
        <td><?=$header->emit('duration', 'desc')?></td>
        <td width="10%"><?=$header->emit('status', 'desc')?></td>
        <td width="10%"><?=$header->emit('items', 'desc')?></td>
        <td width="10%"><?=$header->emit('errors', 'desc')?></td>
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

<script src="<?=STATIC_SERVER?>functions/highcharts.js"></script>
<script src="<?=STATIC_SERVER?>functions/highcharts_custom.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initialiseChart('daily-totals', 'Daily', [
        {
            name: 'Duration',
            yAxis: 0,
            data: [<?=implode(',', $stats[0]['data'])?>]
        },
        {
            name: 'Processed',
            yAxis: 1,
            data: [<?=implode(',', $stats[1]['data'])?>]
        }
    ], {
        yAxis: [
            {
                title: {
                    text: 'Duration'
                }
            }, {
                title: {
                    text: 'Items'
                },
                opposite: true
            }
        ]
    });
});
</script>
<?php
} else {
?>
<div class="center">
    <h2>No history found</h2>
</div>
<?php
}
View::show_footer();
