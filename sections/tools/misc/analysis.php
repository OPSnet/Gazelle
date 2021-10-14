<?php

if (!$Viewer->permitted('site_analysis')) {
    error(403);
}
if (!isset($_GET['case']) || !$Analysis = $Cache->get_value('analysis_'.$_GET['case'])) {
    error(404);
}

echo $Twig->render('debug/analysis.twig', [
    'analysis' => $Analysis,
    'case'     => $_GET['case'],
    'debug'    => $Debug,
    'schedule' => ($Viewer->permitted('admin_periodic_task_view') && array_key_exists('Script start', $Analysis['perf']))
        ? (new Gazelle\Schedule\Scheduler)->getTaskSnapshot(
                (float)$Analysis['perf']['Script start'],
                (float)$Analysis['perf']['Script end']
          )
        : false,
    'viewer'   => $Viewer,
]);
