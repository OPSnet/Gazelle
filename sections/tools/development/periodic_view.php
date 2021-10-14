<?php

if (!$Viewer->permitted('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new Gazelle\Schedule\Scheduler;

if ($_REQUEST['mode'] === 'run_now' && isset($_REQUEST['id'])) {
    if (!$Viewer->permitted('admin_schedule')) {
        error(403);
    }
    authorize();
    $scheduler->runNow(intval($_REQUEST['id']));
}

echo $Twig->render('admin/scheduler/view.twig', [
    'task_list'=> $scheduler->getTaskDetails(),
    'viewer'   => $Viewer,
]);
