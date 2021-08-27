<?php

$statsUser = new Gazelle\Stats\User;
$flow      = $statsUser->flow();
$classDist = $statsUser->classDistribution();
$platDist  = $statsUser->platformDistribution();
$browsers  = $statsUser->browserDistribution();

[$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements]
    = $statsUser->geoDistribution();

View::show_header('Detailed User Statistics');
?>

<div class="linkbox">
    <a href="stats.php?action=torrents" class="brackets">Torrent Stats</a>
</div>

<script src="<?= STATIC_SERVER ?>/functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>/functions/highcharts_custom.js"></script>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
Highcharts.chart('user-flow', {
    chart: {
        type: 'column',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'User Flow',
        style: { color: '#c0c0c0', },
    },
    credits: { enabled: false },
    xAxis: {
        categories: [<?= implode(',', array_map(fn($x) => "'$x'", array_keys($flow))) ?>],
    },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    legend: { itemStyle: {color: 'silver'}, itemHoverStyle: {color: 'gainsboro' }},
    plotOptions: { column: { stacking: 'normal' }},
    series: [
        { name: 'Enabled',  color: 'darkgreen', data: [<?= implode(',', array_map(function ($x) use ($flow) { return  $flow[$x][1]; }, array_keys($flow))) ?>] },
        { name: 'Disabled', color: 'darkred', data: [<?= implode(',', array_map(function ($x) use ($flow) { return -$flow[$x][2]; }, array_keys($flow))) ?>] },
        { type: 'spline', name: 'Change',
            marker: { lineWidth: 2, color: 'teal', fillColor: 'steelblue'},
            data: [<?= implode(',', array_map(function ($x) use ($flow) { return $flow[$x][1] - $flow[$x][2]; }, array_keys($flow))) ?>] },
    ]
});

Highcharts.chart('user-class', {
    chart: {
        type: 'pie',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'User Classes',
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
        name: 'Classes',
        data: [
<?php foreach ($classDist as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
});

Highcharts.chart('user-platform', {
    chart: {
        type: 'pie',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'User Platforms',
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
        name: 'Platforms',
        data: [
<?php foreach ($platDist as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
});

Highcharts.chart('user-browser', {
    chart: {
        type: 'pie',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'Browsers',
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
        name: 'Browsers',
        data: [
<?php foreach ($browsers as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
})});
</script>

<div class="box pad center">
    <figure class="highcharts-figure"><div id="user-flow"></div></figure>
    <br />
    <figure class="highcharts-figure"><div id="user-class"></div></figure>
    <br />
    <figure class="highcharts-figure"><div id="user-platform"></div></figure>
    <br />
    <figure class="highcharts-figure"><div id="user-browser"></div></figure>
    <br />
    <figure class="highcharts-figure"><div id="user-world"></div></figure>
</div>
<?php
View::show_footer();
