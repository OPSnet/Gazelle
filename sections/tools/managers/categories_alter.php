<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_forums')) {
    Error403::error();
}

authorize();

$manager = new Manager\ForumCategory();

if ($_POST['submit'] == 'Delete') {
    $forumCategory = $manager->findById((int)($_POST['id'] ?? 0));
    if (is_null($forumCategory)) {
        Error404::error();
    }
    if (!$forumCategory->remove()) {
        Error400::error('You must move all forums out of a category before deleting it.');
    }
} else {
    // Edit & Create
    $validator = new Util\Validator();
    $validator->setFields([
        ['name', true, 'string', 'The name must be set, and has a max length of 40 characters', ['range' => [1, 40]]],
        ['sort', true, 'number', 'Sequence must be set'],
    ]);
    if (!$validator->validate($_POST)) {
        Error400::error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $manager->create($_POST['name'], (int)$_POST['sort']);
    } else {
        $forumCategory = $manager->findById((int)($_POST['id'] ?? 0));
        if (is_null($forumCategory)) {
            Error404::error();
        }
        $forumCategory
            ->setField('Sort', (int)$_POST['sort'])
            ->setField('Name', trim($_POST['name']))
            ->modify();
    }
}

header('Location: tools.php?action=categories');
