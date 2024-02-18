<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();
$manager = new Gazelle\Manager\ForumTransition();
$transition = $manager->findById((int)($_POST['id'] ?? 0));

if ($_POST['submit'] === 'Delete') {
    if (is_null($transition)) {
        error(404);
    }
    $transition->remove();
} else {
    $validator = new Gazelle\Util\Validator();
    $validator->setFields([
        ['source', true, 'number', 'You must set a source forum ID for the transition'],
        ['destination', true, 'number', 'You must set a destination forum ID for the transition'],
        ['label', true, 'string', 'The button label must be set, and has a max length of 20 characters', ['maxlength' => 20]],
        ['permissions', false, 'string', 'The permissions have a max length of 50 characters', ['maxlength' => 50]],
    ]);

    $_POST = array_map('trim', $_POST);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    $forumMan = new Gazelle\Manager\Forum();
    $source = $forumMan->findById((int)$_POST['source']);
    if (is_null($source)) {
        error("no such source forum id: " . (int)$_POST['source']);
    }
    $target = $forumMan->findById((int)$_POST['destination']);
    if (is_null($target)) {
        error("no such target forum id: " . (int)$_POST['source']);
    }

    if ($_POST['submit'] === 'Create') {
        $manager->create(
            $source,
            $target,
            $_POST['label'],
            (int)$_POST['permission_class'],
            $_POST['secondary_classes'],
            $_POST['permissions'],
            $_POST['user_ids']
        );
    } elseif ($_POST['submit'] === 'Edit') {
        if (is_null($transition)) {
            error(404);
        }
        $transition
            ->setField('source',            $source->id())
            ->setField('destination',       $target->id())
            ->setField('label',             $_POST['label'])
            ->setField('permission_levels', $_POST['secondary_classes'])
            ->setField('permission_class',  $_POST['permission_class'])
            ->setField('permissions',       $_POST['permissions'])
            ->setField('user_ids',          $_POST['user_ids'])
            ->modify();
    }
}

header('Location: tools.php?action=forum_transitions'
    . (isset($_REQUEST['userid']) ? "&userid={$_REQUEST['userid']}" : '')
);
