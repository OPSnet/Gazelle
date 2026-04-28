<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_periodic_task_view')) {
    Error403::error();
}

echo $Twig->render('admin/scheduler/stats.twig', [
    'stats'  => new TaskScheduler()->runtimeStats(),
    'viewer' => $Viewer,
]);
