<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();
$forMan = new Gazelle\Manager\Forum;

if ($_POST['submit'] === 'Delete') {
    if (!$forMan->removeTransition((int)$_POST['id'])) {
        error(0);
    }
} else {
    $validator = new Gazelle\Util\Validator;
    $validator->setFields([
        ['source', true, 'number', 'You must set a source forum ID for the transition'],
        ['destination', true, 'number', 'You must set a destination forum ID for the transition'],
        ['label', true, 'string', 'The button label must be set, and has a max length of 20 characters', ['maxlength' => 20]],
        ['permissions', false, 'string', 'The permissions have a max length of 50 characters', ['maxlength' => 50]],
    ]);

    $P = array_map('trim', $_POST);
    if (!$validator->validate($P)) {
        error($validator->errorMessage());
    }

    if (empty($P['permissions'])) {
        $P['permissions'] = '';
    }
    if ($_POST['submit'] === 'Create') {
        if (!$forMan->createTransition($P)) {
            error(0);
        }
    } elseif ($_POST['submit'] === 'Edit') {
        if (!$forMan->modifyTransition($P)) {
            error(0);
        }

    }
}

header('Location: tools.php?action=forum_transitions' . (isset($_REQUEST['userid']) ? "&userid={$_REQUEST['userid']}" : ''));
