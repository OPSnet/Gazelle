<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_periodic_task_view')) {
    Error403::error();
}

$scheduler = new TaskScheduler();
$task      = $scheduler->findById((int)($_REQUEST['id'] ?? 0));
if ($task) {
    if ($_REQUEST['mode'] === 'run_now') {
        if (!$Viewer->permitted('admin_schedule')) {
            Error403::error();
        }
        authorize();
        $scheduler->runNow($task['id_task']);
    } elseif ($_REQUEST['mode'] === 'enqueue') {
        $scheduler->enqueue($task['id_task']);
        header("Location: ?action=periodic&mode=view");
        exit;
    }
}

echo $Twig->render('admin/scheduler/view.twig', [
    'heading'   => $scheduler->heading(),
    'task_list' => $scheduler->taskDetailList(),
    'viewer'    => $Viewer,
]);
