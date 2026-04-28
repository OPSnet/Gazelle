<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_periodic_task_manage')) {
    Error403::error();
}

$taskId = (int)($_POST['id'] ?? 0);
if ($taskId && $_POST['submit'] == 'Edit') {
    authorize();
    $validator = new Util\Validator();
    $validator->setFields([
        ['name', true, 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength' => 64]],
        ['description', true, 'string', 'The description must be set, and has a max length of 255 characters', ['maxlength' => 255]],
        ['interval', true, 'number', 'The interval must be a number'],
    ]);
    $err = $validator->validate($_POST) ? false : $validator->errorMessage();
    if ($err === false) {
        $scheduler = new TaskScheduler();
        $task      = $scheduler->findById($taskId);
        if ($task == null) {
            $err = "Task $taskId not found";
        }
        $scheduler->updateTask($taskId, $_POST['name'], $_POST['description'],
            (int)$_POST['interval'], isset($_POST['enabled']), isset($_POST['sane']), isset($_POST['debug'])
        );
    }
}

header('Location: ?action=periodic&mode=edit');
