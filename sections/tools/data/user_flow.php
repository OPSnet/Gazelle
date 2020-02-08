<?php
if (!check_perms('site_view_flow')) {
    error(403);
}

if (!isset($_GET['page'])) {
    if (!list($Timeline) = $Cache->get_value('userflow')) {
        $DB->query("
            SELECT J.Week, J.n as Joined, coalesce(D.n, 0) as Disabled
            FROM (
                SELECT DATE_FORMAT(JoinDate, '%X-%V') AS Week, count(*) AS n
                FROM users_info
                GROUP BY Week
                ORDER BY 1 DESC
                LIMIT 52) J
            LEFT JOIN (
                SELECT DATE_FORMAT(BanDate, '%X-%V') AS Week, count(*) AS n
                FROM users_info
                GROUP By Week
                ORDER BY 1 DESC
                LIMIT 52) D USING (Week)
            ORDER BY 1
        ");
        $Timeline = $DB->to_array();
        $Cache->cache_value('userflow', [$Timeline], 3600);
    }
}

define('DAYS_PER_PAGE', 100);
list($Page, $Limit) = Format::page_limit(DAYS_PER_PAGE);

$RS = $DB->query("
        SELECT
            SQL_CALC_FOUND_ROWS
            j.Date,
            DATE_FORMAT(j.Date, '%Y-%m') AS Month,
            coalesce(j.Flow, 0) as Joined,
            coalesce(m.Flow, 0) as Manual,
            coalesce(r.Flow, 0) as Ratio,
            coalesce(i.Flow, 0) as Inactivity
        FROM (
                SELECT
                    DATE_FORMAT(JoinDate, '%Y-%m-%d') AS Date,
                    COUNT(*) AS Flow
                FROM users_info
                WHERE JoinDate != '0000-00-00 00:00:00'
                GROUP BY Date
            ) AS j
            LEFT JOIN (
                SELECT
                    DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                    COUNT(*) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                    AND BanReason = '1'
                GROUP BY Date
            ) AS m ON j.Date = m.Date
            LEFT JOIN (
                SELECT
                    DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                    COUNT(*) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                    AND BanReason = '2'
                GROUP BY Date
            ) AS r ON j.Date = r.Date
            LEFT JOIN (
                SELECT
                    DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                    COUNT(*) AS Flow
                FROM users_info
                WHERE BanDate != '0000-00-00 00:00:00'
                    AND BanReason = '3'
                GROUP BY Date
            ) AS i ON j.Date = i.Date
        ORDER BY j.Date DESC
        LIMIT $Limit");
$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();

View::show_header('User Flow');
$DB->set_query_id($RS);
?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
  google.charts.load('current', {'packages':['corechart']});
  google.charts.setOnLoadCallback(drawChart);

  function drawChart() {
    var data = google.visualization.arrayToDataTable([
      ['Week', 'New Registrations', 'Disabled Users', 'Change'],
<?  foreach ($Timeline as $t) { ?>
['<?= $t[0] ?>', <?= $t[1] ?>, <?= $t[2] ?>, <?= $t[1] - $t[2] ?>],
<? } ?>
    ]);
    var options = {
      backgroundColor: '#303030',
      colors: ['chartreuse', 'orangered', 'steelblue'],
      title: 'User Flow',
      titleTextStyle: {
        color: '#e0e0e0',
      },
      chartArea: {
        left: 40,
        top: 50,
        width: 780,
        height: 210,
      },
      legend: {
        position: 'bottom',
        textStyle: {
          color: '#e0e0e0',
        },
      },
      vAxis: {
        gridlines: {
          count: 2,
        },
        textStyle: {
          color: '#e0e0e0',
        },
      },
      hAxis: {
        baselineColor: '#e0e0e0',
        textStyle: {
          color: '#e0e0e0',
          fontSize: 11,
        },
        titleTextStyle: {
          color: '#e0e0e0',
        },
        slantedText: true,
        slantedTextAngle: 90,
        title: 'Week'
      }
    };
    var chart = new google.visualization.LineChart(document.getElementById('user_flow'));
    chart.draw(data, options);
  }
</script>

<div class="thin">
<?php
    if (!isset($_GET['page'])) { ?>
    <div class="box pad">
        <div id="user_flow" style="width: 830px; height: 390px"></div>
    </div>
<?php
    } ?>
    <div class="linkbox">
<?php
$Pages = Format::get_pages($Page, $Results, DAYS_PER_PAGE, 11);
echo $Pages;
?>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>Date</td>
            <td>(+) Joined</td>
            <td>(-) Manual</td>
            <td>(-) Ratio</td>
            <td>(-) Inactivity</td>
            <td>(-) Total</td>
            <td>Net Growth</td>
        </tr>
<?php
    while (list($Date, $Month, $Joined, $Manual, $Ratio, $Inactivity) = $DB->next_record()) {
        $TotalOut = $Ratio + $Inactivity + $Manual;
        $TotalGrowth = $Joined - $TotalOut;
?>
        <tr class="rowb">
            <td><?=$Date?></td>
            <td><?=number_format($Joined)?></td>
            <td><?=number_format($Manual)?></td>
            <td><?=number_format((float)$Ratio)?></td>
            <td><?=number_format($Inactivity)?></td>
            <td><?=number_format($TotalOut)?></td>
            <td><?=number_format($TotalGrowth)?></td>
        </tr>
<?php
    } ?>
    </table>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php View::show_footer(); ?>
