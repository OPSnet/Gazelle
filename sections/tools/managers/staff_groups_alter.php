<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

authorize();

View::show_header('Staff Group Management');

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->prepared_query("
        DELETE FROM staff_groups
        WHERE ID = ?", $_POST['id']);
} else {
    $Val = new Gazelle\Util\Validator;
    $Val->setFields([
        ['sort', '1', 'number', 'Sort must be set'],
        ['name', '1', 'string', 'Name must be set, and has a max length of 50 characters', ['maxlength' => 50]],
    ]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
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
