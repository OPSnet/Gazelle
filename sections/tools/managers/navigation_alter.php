<?php

use Gazelle\Util\Arrays;

authorize();

if (!check_perms('admin_manage_navigation')) {
    error(403);
}

$P = Arrays::trim($_POST);

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $DB->prepared_query("DELETE FROM nav_items WHERE id = ?", $P['id']);
} else {
    $Val->SetFields('tag', '1', 'string', 'The key must be set, and has a max length of 20 characters', ['maxlength' => 20]);
    $Val->SetFields('title', '1', 'string', 'The title must be set, and has a max length of 50 characters', ['maxlength' => 50]);
    $Val->SetFields('target', '1', 'string', 'The target must be set, and has a max length of 200 characters', ['maxlength' => 200]);
    $Val->SetFields('tests', '0', 'string', 'The tests are optional, and have a max length of 100 characters', ['maxlength' => 200]);
    $Val->SetFields('testuser', '1', 'checkbox', '');
    $Val->SetFields('mandatory', '1', 'checkbox', '');
    $Val->SetFields('default', '1', 'checkbox', '');
    $Err = $Val->ValidateForm($_POST);
    if ($Err) {
        error($Err);
    }

    if ($_POST['submit'] == 'Create') {
        $DB->prepared_query("
            INSERT INTO nav_items (tag, title, target, tests, test_user, mandatory, initial)
            VALUES                (?,   ?,     ?,      ?,     ?,         ?,         ?)",
            $P['tag'], $P['title'], $P['target'], $P['tests'],
            $P['testuser'] == 'on' ? 1 : 0, $P['mandatory'] == 'on' ? 1 : 0,
            $P['default'] == 'on' ? 1 : 0
        );
    } elseif ($_POST['submit'] == 'Edit') {
        if (!is_number($_POST['id']) || $_POST['id'] == '') {
            error(0);
        }

        $DB->prepared_query("
            UPDATE nav_items
                SET tag = ?,
                    title = ?,
                    target = ?,
                    tests = ?,
                    test_user = ?,
                    mandatory = ?,
                    initial = ?
            WHERE id = ?",
            $P['tag'], $P['title'], $P['target'], $P['tests'],
            $P['testuser'] == 'on' ? 1 : 0, $P['mandatory'] == 'on' ? 1 : 0,
            $P['default'] == 'on' ? 1 : 0, $P['id']
        );
    }
}

$Cache->delete_value('nav_items');
header('Location: tools.php?action=navigation');
?>
