<?php

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

authorize();
$emailBlacklist = new Gazelle\Manager\EmailBlacklist;

if ($_POST['submit'] === 'Delete') { // Delete
    if (!$emailBlacklist->remove((int)$_POST['id'])) {
        error(0);
    }
} else { // Edit & Create, Shared Validation
    $validator = new Gazelle\Util\Validator;
    $validator->setField('email', true, 'string', 'The email must be set', ['minlength' => 6]);
    $validator->setField('comment', false, 'string', 'The description has a max length of 255 characters', ['maxlength' => 255]);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    if ($_POST['submit'] === 'Edit') { // Edit
        if (!$emailBlacklist->modify((int)$_POST['id'], [
            'email'   => trim($_POST['email']),
            'comment' => trim($_POST['comment']),
            'user_id' => $Viewer->id(),
        ])) {
            error(0);
        }
    } else { // Create
        if (!$emailBlacklist->create([
            'email'   => trim($_POST['email']),
            'comment' => trim($_POST['comment']),
            'user_id' => $Viewer->id(),
        ])) {
            error(0);
        }
    }
}

header('Location: tools.php?action=email_blacklist');
