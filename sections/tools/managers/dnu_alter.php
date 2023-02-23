<?php

if (!$Viewer->permitted('admin_dnu')) {
    error(403);
}

authorize();
$db = Gazelle\DB::DB();

if ($_POST['submit'] == 'Reorder') { // Reorder
    foreach ($_POST['item'] as $Position => $Item) {
        $db->prepared_query("
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
    $db->prepared_query('
        DELETE FROM do_not_upload
        WHERE ID = ?
        ', $_POST['id']
    );

} else { //Edit & Create, Shared Validation
    $Val = new Gazelle\Util\Validator;
    $Val->setField('name', '1', 'string', 'The name must be set, have a length of between 5 and 100 characters.', ['range' => [5, 100]]);
    $Val->setField('comment', '0', 'string', 'The description has a maximum length of 255 characters.', ['maxlength' => 255]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }
        $db->prepared_query("
            UPDATE do_not_upload SET
                Name = ?,
                Comment = ?,
                UserID = ?
            WHERE ID = ?
            ", trim($_POST['name']), trim($_POST['comment']), $Viewer->id(), $_POST['id']
        );
    } else { //Create
        $db->prepared_query("
            INSERT INTO do_not_upload
                   (Name, Comment, UserID, Sequence)
            VALUES (?,    ?,       ?,      9999)
            ", trim($_POST['name']), trim($_POST['comment']), $Viewer->id()
       );
    }
}

header('Location: tools.php?action=dnu');
