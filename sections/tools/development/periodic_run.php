<?php

if (!$Viewer->permitted('admin_schedule')) {
    error(403);
}

authorize();

$taskId = (int)($_REQUEST['id'] ?? 0);
if (!$taskId) {
    error("Task not found");
}

$scheduler = new Gazelle\TaskScheduler;
ob_start();
$processed = $scheduler->runTask($taskId, true);
$output    = ob_get_flush();

echo $Twig->render('admin/scheduler/run.twig', [
    'task'      => $scheduler->getTask($taskId),
    'output'    => $output,
    'processed' => $processed,
]);

