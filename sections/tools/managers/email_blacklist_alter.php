<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_email')) {
    Error403::error();
}

authorize();
$emailBlacklist = new Manager\EmailBlacklist();

if ($_POST['submit'] === 'Delete') { // Delete
    if (!$emailBlacklist->remove((int)$_POST['id'])) {
        Error400::error('Unknown id for email blacklist removal');
    }
} else { // Edit & Create, Shared Validation
    $validator = new Util\Validator();
    $validator->setField('email', true, 'string', 'The email must be set', ['minlength' => 6]);
    $validator->setField('comment', false, 'string', 'The description has a max length of 255 characters', ['maxlength' => 255]);
    if (!$validator->validate($_POST)) {
        Error400::error($validator->errorMessage());
    }

    $comment = trim($_POST['comment'] ?? '');
    $email   = trim($_POST['email']);

    if ($_POST['submit'] === 'Edit') {
        if (
            !$emailBlacklist->modify(
                id:      (int)$_POST['id'],
                email   : $email,
                comment : $comment,
                user    : $Viewer,
            )
        ) {
            Error400::error('Unable to edit email blacklist entry');
        }
    } else {
        if (
            !$emailBlacklist->create(
                email   : $email,
                comment : $comment,
                user    : $Viewer,
            )
        ) {
            Error400::error('Unable to create email blacklist entry');
        }
    }
}

header('Location: tools.php?action=email_blacklist');
