<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_dnu')) {
    Error403::error();
}

authorize();

$manager = new Manager\DNU();

if ($_POST['submit'] == 'Reorder') {
    // Reorder, issued from an ajax call, see dnu.js
    echo json_encode($manager->reorder(array_map(intval(...), $_POST['item'] ?? [])));
    return;
}

if ($_POST['submit'] == 'Delete') {
    // Delete
    $manager->remove((int)$_POST['id']);
} else {
    // Edit & Create, Shared Validation
    $Val = new Util\Validator();
    $Val->setField('name', true, 'string', 'The name must be set, have a length of between 5 and 100 characters.', ['range' => [5, 100]]);
    $Val->setField('description', false, 'string', 'The description has a maximum length of 255 characters.', ['maxlength' => 255]);
    if (!$Val->validate($_POST)) {
        Error400::error($Val->errorMessage());
    }

    if ($_POST['submit'] == 'Edit') {
        // Edit
        $manager->modify(
            id:          (int)$_POST['id'],
            name:        trim($_POST['name']),
            description: trim($_POST['description']),
            user:        $Viewer,
        );
    } else {
        // Create
        $manager->create(
            name:        trim($_POST['name']),
            description: trim($_POST['description']),
            user:        $Viewer,
        );
    }
}

header('Location: tools.php?action=dnu');
