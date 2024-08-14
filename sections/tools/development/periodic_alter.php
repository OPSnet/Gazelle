<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('admin_periodic_task_manage')) {
    error(403);
}

authorize();

$p = $_POST;
$scheduler = new Gazelle\TaskScheduler();

if ($p['submit'] == 'Delete') {
    if (!is_number($p['id']) || $p['id'] == '') {
        error(0);
    }

    $scheduler->deleteTask($p['id']);
} else {
    $Val = new Gazelle\Util\Validator();
    $Val->setFields([
        ['name', true, 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength' => 64]],
        ['classname', true, 'string', 'The class name must be set, and has a max length of 32 characters', ['maxlength' => 32]],
        ['description', true, 'string', 'The description must be set, and has a max length of 255 characters', ['maxlength' => 255]],
        ['interval', true, 'number', 'The interval must be a number'],
    ]);
    $err = $Val->validate($p) ? false : $Val->errorMessage();

    if (!$scheduler::isClassValid($p['classname'])) {
        $err = "Couldn't import class " . $p['classname'];
    }

    if ($err) {
        require_once('periodic_edit.php');
        exit;
    }

    if ($p['submit'] == 'Create') {
        $scheduler->createTask($p['name'], $p['classname'], $p['description'], intval($p['interval']),
            isset($p['enabled']), isset($p['sane']), isset($p['debug']));
    } elseif ($p['submit'] == 'Edit') {
        if (!is_number($p['id']) || $p['id'] == '') {
            error(0);
        }

        $task = $scheduler->getTask($p['id']);
        if ($task == null) {
            error(0);
        }

        $scheduler->updateTask(intval($p['id']), $p['name'], $p['classname'], $p['description'],
            intval($p['interval']), isset($p['enabled']), isset($p['sane']), isset($p['debug']));
    }
}

header('Location: tools.php?action=periodic&mode=edit');
