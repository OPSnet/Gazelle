<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_periodic_task_manage')) {
    Error403::error();
}

echo $Twig->render('admin/scheduler/edit.twig', [
    'err'       => $err ?? null,
    'task_list' => new TaskScheduler()->taskList(),
    'viewer'    => $Viewer,
]);
