<?php

if (!$Viewer->permitted('admin_donor_log')) {
    error(403);
}

$dateSearch = !empty($_GET['after_date']) && !empty($_GET['before_date']);

$cond = [];
$args = [];
if (!empty($_GET['username'])) {
    $cond[] = "m.Username LIKE concat('%', ?, '%')";
    $args[] = trim($_GET['username']);
}
if ($dateSearch) {
    $cond[] = "d.Time BETWEEN ? AND ?";
    $args[] = trim($_GET['after_date']);
    $args[] = trim($_GET['before_date']);
}

$from = "FROM donations AS d INNER JOIN users_main AS m ON (m.ID = d.UserID)";
if ($cond) {
    $from .= " WHERE " . implode(' AND ', $cond);
}

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal(
    $DB->scalar("SELECT count(*) $from", ...$args)
);
array_push($args, $paginator->limit(), $paginator->offset());

$DB->prepared_query("
    SELECT d.UserID,
        d.Amount,
        d.Currency,
        d.xbt,
        d.Time,
        d.Source,
        m.Username,
        d.AddedBy,
        d.Reason
    $from
    ORDER BY d.Time DESC
    LIMIT ? OFFSET ?
    ", ...$args
 );
$donation = $DB->to_array(false, MYSQLI_ASSOC, false);

$DB->prepared_query("
    SELECT date_format(Time,'%b %Y') AS Month,
        sum(xbt) as Amount
    FROM donations
    GROUP BY Month
    ORDER BY Time DESC
    LIMIT 0, 17
");
$timeline = array_reverse($DB->to_array(false, MYSQLI_ASSOC, false));

View::show_header('Donation log');
?>
<script src="<?= STATIC_SERVER ?>/functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>/functions/highcharts_custom.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
Highcharts.chart('donation-timeline', {
    chart: {
        type: 'column',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
    },
    title: {
        text: 'Donations Timeline',
        style: { color: '#c0c0c0', },
    },
    credits: { enabled: false },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    yAxis: {
        title: {text: 'bitcoin'},
        plotLines: [{
            color: '#800000',
            width: 2,
            value: <?= (new Gazelle\Manager\Payment)->monthlyRental() ?>,
            zIndex: 5,
        }],
    },
    xAxis: {
        categories: [<?= implode(',', array_map(function ($x) { return "'" . $x['Month'] . "'"; }, $timeline)) ?>],
    },
    series: [
        { name: 'Donated',  data: [<?= implode(',', array_map(function ($x) { return  $x['Amount']; }, $timeline)) ?>] }
    ]

})});
</script>
<?php
echo $Twig->render('admin/xbt-log.twig', [
    'donation'    => $donation,
    'grand_total' => $DB->scalar("SELECT SUM(xbt) FROM donations "),
    'paginator'   => $paginator,
    'username'    => $_GET['username'] ?? '',
]);
View::show_footer();
