<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new Gazelle\TaskScheduler();
$taskId = (int)($_REQUEST['id'] ?? 0);

if ($taskId && $_REQUEST['mode'] === 'run_now') {
    if (!$Viewer->permitted('admin_schedule')) {
        error(403);
    }
    authorize();
    $scheduler->runNow($taskId);
}

echo $Twig->render('admin/scheduler/view.twig', [
    'task_list' => $scheduler->getTaskDetails(),
    'viewer'   => $Viewer,
]);
