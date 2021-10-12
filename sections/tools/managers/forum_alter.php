<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();

$forumMan = new Gazelle\Manager\Forum;
$forum = $forumMan->findById((int)$_POST['id']);
if (is_null($forum) and in_array($_POST['submit'], ['Edit', 'Delete'])) {
    error(0);
}
if ($_POST['submit'] == 'Delete') {
    $forum->remove();
} else { //Edit & Create, Shared Validation
    if ($_POST['minclassread'] > $Viewer->classLevel() || $_POST['minclasswrite'] > $Viewer->classLevel() || $_POST['minclasscreate'] > $Viewer->classLevel()) {
        error(403);
    }

    $validator = new Gazelle\Util\Validator;
    $validator->setFields([
        ['name', '1', 'string', 'The name must be set, and has a max length of 40 characters', ['maxlength' => 40]],
        ['description', '0', 'string', 'The description has a max length of 255 characters', ['maxlength' => 255]],
        ['sort', '1', 'number', 'Sort must be set'],
        ['categoryid', '1', 'number', 'Category must be set'],
        ['minclassread', '1', 'number', 'MinClassRead must be set'],
        ['minclasswrite', '1', 'number', 'MinClassWrite must be set'],
        ['minclasscreate', '1', 'number', 'MinClassCreate must be set'],
    ]);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $forumMan->create($_POST);
    } elseif ($_POST['submit'] == 'Edit') {
        $minClassRead = $forum->minClassRead();
        if (!$minClassRead || $minClassRead > $Viewer->classLevel()) {
            error(403);
        }
        $forum->modify($_POST);
    }
    else {
        error(403);
    }
}
header("Location: tools.php?action=forum");
