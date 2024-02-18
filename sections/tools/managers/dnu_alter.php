<?php

if (!$Viewer->permitted('admin_dnu')) {
    error(403);
}

authorize();

$manager = new Gazelle\Manager\DNU();

$db = Gazelle\DB::DB();

if ($_POST['submit'] == 'Reorder') {
    // Reorder, issued from an ajax call, see dnu.js
    echo json_encode($manager->reorder(array_map('intval', $_POST['item'] ?? [])));
    exit;
}

if ($_POST['submit'] == 'Delete') {
    // Delete
    $manager->remove((int)$_POST['id']);

} else {
    // Edit & Create, Shared Validation
    $Val = new Gazelle\Util\Validator();
    $Val->setField('name', true, 'string', 'The name must be set, have a length of between 5 and 100 characters.', ['range' => [5, 100]]);
    $Val->setField('comment', false, 'string', 'The description has a maximum length of 255 characters.', ['maxlength' => 255]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }

    if ($_POST['submit'] == 'Edit') {
        // Edit
        $manager->modify(
            id:      (int)$_POST['id'],
            name:    trim($_POST['name']),
            comment: trim($_POST['comment']),
            user:    $Viewer,
        );
    } else {
        // Create
        $manager->create(
            name:    trim($_POST['name']),
            comment: trim($_POST['comment']),
            user:    $Viewer,
        );
    }
}

header('Location: tools.php?action=dnu');
