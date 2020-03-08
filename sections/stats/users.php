<?php View::show_header('Detailed User Statistics'); ?>

<script src="<?= STATIC_SERVER ?>functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>functions/highcharts_custom.js"></script>

<?php
if (!$flow = $Cache->get_value('stat-user-timeline')) {
    $flow = [];
    /* Mysql does not implement a full outer join, so if there is a month with
     * no joiners, any banned users in that same month will not appear.
     * Mysql does not implement sequence generators as in Postgres, so if there
     * is a month without any joiners or banned, it will not appear at all.
     * Deal with it. - Spine
     */
    $DB->query("
        SELECT J.Mon, J.n as Joined, coalesce(D.n, 0) as Disabled
        FROM (
            SELECT DATE_FORMAT(JoinDate,'%Y%m') as M, DATE_FORMAT(JoinDate, '%b %Y') AS Mon, count(*) AS n
            FROM users_info
            WHERE JoinDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                AND last_day(now()) - INTERVAL 1 MONTH
            GROUP BY M) J
        LEFT JOIN (
            SELECT DATE_FORMAT(BanDate, '%Y%m') AS M, DATE_FORMAT(BanDate, '%b %Y') AS Mon, count(*) AS n
            FROM users_info
            WHERE BanDate BETWEEN last_day(now()) - INTERVAL 13 MONTH + INTERVAL 1 DAY
                AND last_day(now()) - INTERVAL 1 MONTH
            GROUP By M
        ) D USING (M)
        ORDER BY J.M;
    ");
    $flow = $DB->to_array('Mon');
    $Cache->cache_value('stat-user-timeline', $flow, mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
}
?>
<script>
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
        categories: [<?= implode(',', array_map(function ($x) { return "'$x'"; }, array_keys($flow))) ?>],
    },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    plotOptions: {
        column: { stacking: 'normal' }
    },
    series: [
        { name: 'Enabled',  data: [<?= implode(',', array_map(function ($x) use ($flow) { return  $flow[$x][1]; }, array_keys($flow))) ?>] },
        { name: 'Disabled', data: [<?= implode(',', array_map(function ($x) use ($flow) { return -$flow[$x][2]; }, array_keys($flow))) ?>] },
    ]
})});
</script>

<?php
if (!$ClassDistribution = $Cache->get_value('stat-user-class')) {
    $DB->query("
        SELECT p.Name, count(*) AS Users
        FROM users_main AS m
        INNER JOIN permissions AS p ON (m.PermissionID = p.ID)
        WHERE m.Enabled = '1'
        GROUP BY p.Name
        ORDER BY Users DESC
    ");
    $ClassDistribution = $DB->to_array('Name');
    $Cache->cache_value('stat-user-class', $ClassDistribution, 86400);
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
<?php foreach ($ClassDistribution as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
})});
</script>

<?php
if (!$PlatformDistribution = $Cache->get_value('stat-user-platform')) {
    $DB->query("
        SELECT OperatingSystem, count(*) AS Users
        FROM users_sessions
        GROUP BY OperatingSystem
        ORDER BY Users DESC
    ");
    $PlatformDistribution = $DB->to_array();
    $Cache->cache_value('stat-user-platform', $PlatformDistribution, 86400);
} 
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
<?php foreach ($PlatformDistribution as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
})});
</script>

<?php
if (!$Browsers = $Cache->get_value('stat-user-browser')) {
    $DB->query("
        SELECT Browser, count(*) AS Users
        FROM users_sessions
        GROUP BY Browser
        ORDER BY Users DESC
    ");
    $Browsers = $DB->to_array();
    $Cache->cache_value('stat-user-browser', $Browsers, 86400);
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
<?php foreach ($Browsers as $label => $value) { ?>
            { name: '<?= $value[0] ?>', y: <?= $value[1] ?> },
<?php } ?>
        ]
    }],
})});
</script>

<?php
if (!list($Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements) = $Cache->get_value('geodistribution')) {
    $DB->query('
        SELECT Code, Users
        FROM users_geodistribution');
    $Data = $DB->to_array();
    $Count = $DB->record_count() - 1;

    if ($Count < 30) {
        $CountryMinThreshold = $Count;
    } else {
        $CountryMinThreshold = 30;
    }

    $CountryMax = ceil(log(Max(1, $Data[0][1])) / log(2)) + 1;
    $CountryMin = floor(log(Max(1, $Data[$CountryMinThreshold][1])) / log(2));

    $CountryRegions = ['RS' => ['RS-KM']]; // Count Kosovo as Serbia as it doesn't have a TLD
    foreach ($Data as $Key => $Item) {
        list($Country, $UserCount) = $Item;
        $Countries[] = $Country;
        $CountryUsers[] = number_format((((log($UserCount) / log(2)) - $CountryMin) / ($CountryMax - $CountryMin)) * 100, 2);
        $Rank[] = round((1 - ($Key / $Count)) * 100);

        if (isset($CountryRegions[$Country])) {
            foreach ($CountryRegions[$Country] as $Region) {
                $Countries[] = $Region;
                $Rank[] = end($Rank);
            }
        }
    }
    reset($Rank);

    for ($i = $CountryMin; $i <= $CountryMax; $i++) {
        $LogIncrements[] = Format::human_format(pow(2, $i));
    }
    $Cache->cache_value('geodistribution', [$Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements], 0);
}
?>

<div class="linkbox">
    <a href="stats.php?action=torrents" class="brackets">Torrent Stats</a>
</div>

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
