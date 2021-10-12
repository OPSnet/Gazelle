<?php

if (!$Viewer->permitted('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new Gazelle\Schedule\Scheduler;
$stats = $scheduler->getRuntimeStats();
$Debug->log_var($stats, 'nice');

$canEdit = $Viewer->permitted('admin_periodic_task_manage');

View::show_header('Periodic Task Statistics');
?>
<div class="header">
<h2>Periodic Task Statistics</h2>
</div>
<?php require_once('periodic_links.php'); ?>

<div class="box pad">
    <table>
        <tr class="colhead">
            <td>Runs</td>
            <td>Duration</td>
            <td>Processed</td>
            <td>Events</td>
            <td>Errors</td>
        </tr>
        <tr>
            <td><?=number_format($stats['totals']['runs'])?></td>
            <td><?=number_format($stats['totals']['duration'])?> ms</td>
            <td><?=number_format($stats['totals']['processed'])?></td>
            <td><?=number_format($stats['totals']['events'])?></td>
            <td><?=number_format($stats['totals']['errors'])?></td>
        </tr>
    </table>
    <br />
    <div id="task-averages" style="width: 100%; height: 350px;"></div>
    <br />
    <div>
        <div id="hourly-totals" style="width: 49.5%; height: 350px; float: left; padding-right: 1%"></div>
        <div id="daily-totals" style="width: 49.5%; height: 350px;"></div>
    </div>
</div>

<script src="<?=STATIC_SERVER?>/functions/highcharts.js"></script>
<script src="<?=STATIC_SERVER?>/functions/highcharts_custom.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initialiseBarChart('task-averages', 'Task Averages', [
        {
            name: 'Duration',
            yAxis: 0,
            data: [<?=implode(',', $stats['tasks'][0]['data'])?>]
        },
        {
            name: 'Processed',
            yAxis: 1,
            data: [<?=implode(',', $stats['tasks'][1]['data'])?>]
        }
    ], {
        yAxis: [
            {
                title: {
                    text: 'Duration'
                },
                type: 'logarithmic'
            }, {
                title: {
                    text: 'Items'
                },
                opposite: true
            }
        ]
    });

    initialiseChart('hourly-totals', 'Hourly', [
        {
            name: 'Duration',
            yAxis: 0,
            data: [<?=implode(',', $stats['hourly'][0]['data'])?>]
        },
        {
            name: 'Processed',
            yAxis: 1,
            data: [<?=implode(',', $stats['hourly'][1]['data'])?>]
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

    initialiseChart('daily-totals', 'Daily', [
        {
            name: 'Duration',
            yAxis: 0,
            data: [<?=implode(',', $stats['daily'][0]['data'])?>]
        },
        {
            name: 'Processed',
            yAxis: 1,
            data: [<?=implode(',', $stats['daily'][1]['data'])?>]
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
View::show_footer();
