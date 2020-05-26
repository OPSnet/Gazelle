<?php
authorize();

if (!check_perms('admin_periodic_task_manage')) {
    error(403);
}

$p = $_POST;
$scheduler = new \Gazelle\Schedule\Scheduler;

if ($p['submit'] == 'Delete') {
    if (!is_number($p['id']) || $p['id'] == '') {
        error(0);
    }

    $scheduler->deleteTask($p['id']);
} else {
    $Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength' => 64]);
    $Val->SetFields('classname', '1', 'string', 'The class name must be set, and has a max length of 32 characters', ['maxlength' => 32]);
    $Val->SetFields('description', '1', 'string', 'The description must be set, and has a max length of 255 characters', ['maxlength' => 255]);
    $Val->SetFields('interval', '1', 'number', 'The interval must be a number');
    $err = $Val->ValidateForm($p);

    if (!$scheduler::isClassValid($p['classname'])) {
        $err = "Couldn't import class ".$p['classname'];
    }

    if ($err !== null) {
        include(SERVER_ROOT.'/sections/tools/development/periodic_edit.php');
        die();
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
?>
