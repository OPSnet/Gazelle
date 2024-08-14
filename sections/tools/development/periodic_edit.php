<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('admin_periodic_task_manage')) {
    error(403);
}

echo $Twig->render('admin/scheduler/edit.twig', [
    'auth'      => $Viewer->auth(),
    'err'       => $err ?? null,
    'task_list' => (new Gazelle\TaskScheduler())->getTasks(),
]);
