<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();

$manager = new Gazelle\Manager\ForumCategory();

if ($_POST['submit'] == 'Delete') {
    $forumCategory = $manager->findById((int)($_POST['id'] ?? 0));
    if (is_null($forumCategory)) {
        error(404);
    }
    if (!$forumCategory->remove()) {
        error('You must move all forums out of a category before deleting it.');
    }
} else {
    // Edit & Create
    $validator = new Gazelle\Util\Validator();
    $validator->setFields([
        ['name', true, 'string', 'The name must be set, and has a max length of 40 characters', ['range' => [1, 40]]],
        ['sort', true, 'number', 'Sequence must be set'],
    ]);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $manager->create($_POST['name'], (int)$_POST['sort']);
    } else {
        $forumCategory = $manager->findById((int)($_POST['id'] ?? 0));
        if (is_null($forumCategory)) {
            error(404);
        }
        $forumCategory
            ->setField('Sort', (int)$_POST['sort'])
            ->setField('Name', trim($_POST['name']))
            ->modify();
    }
}

header('Location: tools.php?action=categories');
