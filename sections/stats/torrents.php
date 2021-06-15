<?php

$statsTor = new Gazelle\Stats\Torrent;
[$flow, $torrentCat] = $statsTor->yearlyFlow();

View::show_header('Detailed torrent statistics');
?>
<script src="<?= STATIC_SERVER ?>/functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>/functions/highcharts_custom.js"></script>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
Highcharts.chart('torrent-flow', {
    chart: {
        type: 'column',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'Torrent Flow',
        style: { color: '#c0c0c0', },
    },
    credits: { enabled: false },
    xAxis: {
        categories: [<?= implode(',', array_map(function ($x) { return "'$x'"; }, array_keys($flow))) ?>],
    },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    legend: { itemStyle: {color: 'silver'}, itemHoverStyle: {color: 'gainsboro' }},
    plotOptions: { column: { stacking: 'normal' } },
    series: [
        { type: 'column', name: 'Created', color: 'darkgreen', data: [<?= implode(',', array_map(function ($x) use ($flow) { return  (int)$flow[$x]['t_add'] ?? 0; }, array_keys($flow))) ?>] },
        { type: 'column', name: 'Removed', color: 'darkred', data: [<?= implode(',', array_map(function ($x) use ($flow) { return -(int)$flow[$x]['t_del'] ?? 0; }, array_keys($flow))) ?>] },
        { type: 'spline', name: 'Net',
            marker: { lineWidth: 2, color: 'teal', fillColor: 'steelblue'},
            data: [<?= implode(',', array_map(function ($x) use ($flow) { return $flow[$x]['t_net'] ?? 0; }, array_keys($flow))) ?>] },
    ]
});

Highcharts.chart('torrent-pie', {
    chart: {
        type: 'pie',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'Torrent breakdown',
        style: { color: '#c0c0c0', },
    },
    credits: { enabled: false },
    tooltip: { pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>' },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                color: 'white',
            }
        }
    },
    series: [{
        name: 'Upload types',
        data: [
<?php foreach ($torrentCat as $label => $value) { ?>
            { name: '<?= CATEGORY[$value[0] - 1] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
})});
</script>

<div class="linkbox">
    <a href="stats.php?action=users" class="brackets">User Stats</a>
</div>
<h1 id="Torrent_Upload"><a href="#Torrent_Upload">Uploads by month</a></h1>
<div class="box pad center">
    <figure class="highcharts-figure"><div id="torrent-flow"></div></figure>
    <br />
<h1 id="Torrent_Category"><a href="#Torrent_Category">Torrents by category</a></h1>
<div class="box pad center">
    <figure class="highcharts-figure"><div id="torrent-pie"></div></figure>
    <br />
</div>
<?php
View::show_footer();
