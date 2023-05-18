<?php

if (!$Viewer->permitted('admin_periodic_task_view')) {
    error(403);
}

$scheduler = new Gazelle\TaskScheduler;
$id = (int)($_GET['id'] ?? 0);
if (!$scheduler->getTask($id)) {
    error(404);
}

$header = new Gazelle\Util\SortableTableHeader('launchtime', [
    'id'         => ['defaultSort' => 'desc'],
    'launchtime' => ['defaultSort' => 'desc',  'text' => 'Launch Time'],
    'duration'   => ['defaultSort' => 'desc',  'text' => 'Duration'],
    'status'     => ['defaultSort' => 'desc',  'text' => 'Status'],
    'items'      => ['defaultSort' => 'desc',  'text' => 'Processed'],
    'errors'     => ['defaultSort' => 'desc',  'text' => 'Errors']
]);

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($scheduler->getTotal($id));

$stats = $scheduler->getTaskRuntimeStats($id);

echo $Twig->render('admin/scheduler/task.twig', [
    'header'    => $header,
    'stats'     => $scheduler->getTaskRuntimeStats($id),
    'duration'  => json_encode($stats[0]['data']),
    'processed' => json_encode($stats[1]['data']),
    'task'      => $scheduler->getTaskHistory($id, $paginator->limit(), $paginator->offset(), $header->getSortKey(), $header->getOrderDir()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
