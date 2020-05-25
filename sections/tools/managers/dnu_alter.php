<?php
if (!check_perms('admin_dnu')) {
    error(403);
}

authorize();

if ($_POST['submit'] == 'Reorder') { // Reorder
    foreach ($_POST['item'] as $Position => $Item) {
        $Position = db_string($Position);
        $Item = db_string($Item);
        $DB->prepared_query("
            UPDATE do_not_upload SET
                Sequence = ?
            WHERE id = ?
            ", $Position, $Item
        );
    }

} elseif ($_POST['submit'] == 'Delete') { //Delete
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }
    $DB->prepared_query('
        DELETE FROM do_not_upload
        WHERE ID = ?
        ', $_POST['id']
    );

} else { //Edit & Create, Shared Validation
    $Val->SetFields('name', '1', 'string', 'The name must be set, have a length of between 5 and 100 characters.', ['maxlength' => 100, 'minlength' => 5]);
    $Val->SetFields('comment', '0', 'string', 'The description has a maximum length of 255 characters.', ['maxlength' => 255]);
    $Err = $Val->ValidateForm($_POST); // Validate the form
    if ($Err) {
        error($Err);
    }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }
        $DB->prepared_query("
            UPDATE do_not_upload SET
                Name = ?,
                Comment = ?,
                UserID = ?
            WHERE ID = ?
            ", trim($_POST['name']), trim($_POST['comment']), $LoggedUser['ID'], $_POST['id']
        );
    } else { //Create
        $DB->prepared_query("
            INSERT INTO do_not_upload
                   (Name, Comment, UserID, Sequence)
            VALUES (?,    ?,       ?,      9999)
            ", trim($_POST['name']), trim($_POST['comment']), $LoggedUser['ID']
       );
    }
}

header('Location: tools.php?action=dnu');
