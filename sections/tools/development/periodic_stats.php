<?php

if (!$Viewer->permitted('admin_periodic_task_view')) {
    error(403);
}

$stats = (new Gazelle\Schedule\Scheduler)->getRuntimeStats();
echo $Twig->render('admin/scheduler/stats.twig', [
    'hourly' => [
        'duration'  => json_encode($stats['hourly'][0]['data']),
        'processed' => json_encode($stats['hourly'][1]['data']),
    ],
    'daily' => [
        'duration'  => json_encode($stats['daily'][0]['data']),
        'processed' => json_encode($stats['daily'][1]['data']),
    ],
    'tasks' => [
        'duration'  => json_encode($stats['tasks'][0]['data']),
        'processed' => json_encode($stats['tasks'][1]['data']),
    ],
    'totals' => $stats['totals'],
    'viewer' => $Viewer,
]);
