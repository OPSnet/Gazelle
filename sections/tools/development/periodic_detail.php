<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_periodic_task_view')) {
    Error403::error();
}

$scheduler = new TaskScheduler();
$taskId = (int)($_GET['id'] ?? 0);
if (!$scheduler->findById($taskId)) {
    Error404::error();
}

$paginator = new Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($scheduler->taskRunTotal($taskId));

echo $Twig->render('admin/scheduler/task.twig', [
    'header' => $scheduler->heading(),
    'stats'  => $scheduler->taskRuntimeStats($taskId),
    'task'   => $scheduler->taskHistory(
        $taskId, $paginator->limit(), $paginator->offset()
    ),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
