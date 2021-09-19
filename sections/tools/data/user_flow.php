<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}
$showFlow = !isset($_GET['page']) || (int)$_GET['page'] === 1;

$userMan = new Gazelle\Manager\User;
$paginator = new Gazelle\Util\Paginator(100, (int)($_GET['page'] ?? 1));
$paginator->setTotal($userMan->userflowTotal());

if ($showFlow) {
    $userflow = $userMan->userflow();
}
$userflowDetails = $userMan->userflowDetails($paginator->limit(), $paginator->offset());

View::show_header('User Flow', ['js' => 'highcharts,highcharts_custom']);

if ($showFlow) {
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
        categories: [<?= implode(',', array_map(fn($x) => "'$x'", array_keys($userflow))) ?>],
    },
    tooltip: {
        headerFormat: '<b>{point.x}</b><br/>',
        pointFormat: '{series.name}: {point.y}'
    },
    plotOptions: {
        column: { stacking: 'normal' }
    },
    series: [
        { name: 'Enabled',  data: [<?= implode(',', array_map(function ($x) use ($userflow) { return  $userflow[$x]['Joined']; }, array_keys($userflow))) ?>] },
        { name: 'Disabled', data: [<?= implode(',', array_map(function ($x) use ($userflow) { return -$userflow[$x]['Disabled']; }, array_keys($userflow))) ?>] },
    ]
})});
</script>
<?php
}

echo $Twig->render('admin/userflow.twig', [
    'paginator' => $paginator,
    'show_flow' => $showFlow,
    'details'   => $userflowDetails,
]);
