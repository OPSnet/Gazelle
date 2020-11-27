<?php
authorize();

if (!check_perms('admin_manage_permissions')) {
    error(403);
}

View::show_header('Staff Group Management');

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->prepared_query("
        DELETE FROM staff_groups
        WHERE ID = ?", $_POST['id']);
} else {
    $Val = new Validate;
    $Val->SetFields('sort', '1', 'number', 'Sort must be set');
    $Val->SetFields('name', '1', 'string', 'Name must be set, and has a max length of 50 characters', ['maxlength' => 50, 'minlength' => 1]);
    $Err = $Val->ValidateForm($_POST);
    if ($Err) {
        error($Err);
    }

    if ($_POST['submit'] == 'Edit') {
        $DB->prepared_query("
            UPDATE staff_groups
            SET Sort = ?,
                Name = ?
            WHERE ID = ?", $_POST['sort'], $_POST['name'], $_POST['id']);
    } else {
        $DB->prepared_query("
            INSERT INTO staff_groups (Sort, Name)
            VALUES (?, ?)", $_POST['sort'], $_POST['name']);
    }
}

$Cache->delete_value('staff');

header('Location: tools.php?action=staff_groups');
