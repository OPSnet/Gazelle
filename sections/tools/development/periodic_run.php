<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_schedule')) {
    Error403::error();
}

authorize();

$taskId = (int)($_REQUEST['id'] ?? 0);
if (!$taskId) {
    Error400::error("Task not found");
}

$scheduler = new TaskScheduler();
ob_start();
$processed = $scheduler->runTask($taskId, true);
$output    = ob_get_flush();

echo $Twig->render('admin/scheduler/run.twig', [
    'task'      => $scheduler->findById($taskId),
    'output'    => $output,
    'processed' => $processed,
]);
