<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_forums')) {
    Error403::error();
}

authorize();

$forumMan = new Manager\Forum();
$forum = $forumMan->findById((int)($_POST['id'] ?? 0));
if (is_null($forum) && in_array($_POST['submit'], ['Edit', 'Delete'])) {
    Error400::error('Unknown forum alter action');
}
if ($_POST['submit'] == 'Delete') {
    $forum->remove();
} else { //Edit & Create, Shared Validation
    $minRead   = (int)$_POST['minclassread'];
    $minWrite  = (int)$_POST['minclasswrite'];
    $minCreate = (int)$_POST['minclasscreate'];
    if ($Viewer->classLevel() < min($minRead, $minWrite, $minCreate)) {
        Error403::error();
    }

    $validator = new Util\Validator();
    $validator->setFields([
        ['name', true, 'string', 'The name must be set, and has a max length of 40 characters', ['maxlength' => 40]],
        ['description', false, 'string', 'The description has a max length of 255 characters', ['maxlength' => 255]],
        ['sort', true, 'number', 'Sort must be set'],
        ['categoryid', true, 'number', 'Category must be set'],
        ['minclassread', true, 'number', 'MinClassRead must be set'],
        ['minclasswrite', true, 'number', 'MinClassWrite must be set'],
        ['minclasscreate', true, 'number', 'MinClassCreate must be set'],
    ]);
    if (!$validator->validate($_POST)) {
        Error400::error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $forumMan->create(
            user:           $Viewer,
            sequence:       (int)$_POST['sort'],
            categoryId:     (int)$_POST['categoryid'],
            name:           $_POST['name'],
            description:    $_POST['description'],
            minClassRead:   (int)$_POST['minclassread'],
            minClassWrite:  (int)$_POST['minclasswrite'],
            minClassCreate: (int)$_POST['minclasscreate'],
            autoLock:       isset($_POST['autolock']),
            autoLockWeeks:  (int)$_POST['autolockweeks'],
        );
    } elseif ($_POST['submit'] == 'Edit') {
        $minClassRead = $forum->minClassRead();
        if (!$minClassRead || $minClassRead > $Viewer->classLevel()) {
            Error403::error();
        }
        $forum->modifyForum($_POST);
    } else {
        Error403::error();
    }
}
header("Location: tools.php?action=forum");
