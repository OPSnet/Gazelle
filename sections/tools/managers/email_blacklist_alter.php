<?php
if (!check_perms('users_view_email')) {
    error(403);
}

authorize();

if ($_POST['submit'] === 'Delete') { // Delete
    if (!is_number($_POST['id']) || $_POST['id'] === '') {
        error(0);
    }
    $DB->prepared_query("
        DELETE FROM email_blacklist
        WHERE ID = ?
        ", (int)$_POST['id']
    );
} else { // Edit & Create, Shared Validation
    $Val->SetFields('email', '1', 'string', 'The email must be set', ['minlength'=>1]);
    $Val->SetFields('comment', '0', 'string', 'The description has a max length of 255 characters', ['maxlength'=>255]);
    $Err = $Val->ValidateForm($_POST);
    if ($Err) {
        error($Err);
    }

    if ($_POST['submit'] === 'Edit') { // Edit
        if (!is_number($_POST['id']) || $_POST['id'] === '') {
            error(0);
        }
        $DB->prepared_query("
            UPDATE email_blacklist SET
                Email   = ?,
                Comment = ?,
                UserID  = ?,
                Time    = now()
            WHERE ID = ?
            ", trim($_POST['email']), trim($_POST['comment']), $LoggedUser['ID'],
                $_POST['id']
        );
    } else { // Create
        $DB->prepared_query("
            INSERT INTO email_blacklist
                   (Email, Comment, UserID)
            VALUES (?,     ?,       ?)
            ", trim($_POST['email']), trim($_POST['comment']), $LoggedUser['ID']
        );
    }
}

header('Location: tools.php?action=email_blacklist');
