<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_permissions')) {
    Error403::error();
}

authorize();
$manager = new Manager\StaffGroup();
$staffGroup = $manager->findById((int)($_POST['id'] ?? 0));

if ($_POST['submit'] == 'Delete') {
    if (is_null($staffGroup)) {
        Error404::error('Staff Group not found for delete');
    }
    $staffGroup->remove();
} else {
    $validator = new Util\Validator();
    $validator->setFields([
        ['sort', true, 'number', 'Sort must be set'],
        ['name', true, 'string', 'Name must be set, and has a max length of 50 characters', ['maxlength' => 50]],
    ]);
    if (!$validator->validate($_POST)) {
        Error400::error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Edit') {
        $staffGroup->setField('Sort', (int)$_POST['sort'])->setField('Name', trim($_POST['name']))->modify();
    } else {
        $manager->create(sequence: (int)$_POST['sort'], name: trim($_POST['name']));
    }
}

header('Location: tools.php?action=staff_groups');
