<?php
authorize();

if (!check_perms('admin_manage_forums')) {
    error(403);
}
if ($_POST['submit'] == 'Delete') { //Delete
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }
    $ID = intval($_POST['id']);
    $DB->prepared_query("SELECT COUNT(*) AS Count FROM forums WHERE CategoryID=? GROUP BY CategoryID", $ID);
    if (!$DB->has_results()) {
        $DB->prepared_query('DELETE FROM forums_categories WHERE ID = ?', $ID);
    } else {
        error('You must move all forums out of a category before deleting it.');
    }
} else { //Edit & Create, Shared Validation
    $Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 40 characters', array('maxlength' => 40, 'minlength' => 1));
    $Val->SetFields('sort', '1', 'number', 'Sort must be set');
    $Err = $Val->ValidateForm($_POST); // Validate the form
    if ($Err) {
        error($Err);
    }
    $Sort = intval($_POST['sort']);

    if ($_POST['submit'] == 'Edit') {
        //Edit
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }
        $ID = intval($_POST['id']);
        $DB->prepared_query('SELECT * FROM forums_categories WHERE ID = ?', $ID);
        if (!$DB->has_results()) {
            error(404);
        }

        $DB->prepared_query('UPDATE forums_categories SET Sort=?, Name=? WHERE ID=?', $Sort, $_POST['name'], $ID);
    } else {
        //Create
        $DB->prepared_query('INSERT INTO forums_categories (Sort, Name) VALUES (?, ?)', $Sort, $_POST['name']);
    }
}

$Cache->delete_value('forums_categories'); // Clear cache

// Go back
header('Location: tools.php?action=categories');
