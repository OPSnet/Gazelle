<?php

use Gazelle\Util\Arrays;

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}

authorize();

$P = array_map('trim', $_POST);
$db = Gazelle\DB::DB();

if ($_POST['submit'] == 'Delete') {
    if (!is_number($_POST['id']) || $_POST['id'] == '') {
        error(0);
    }

    $db->prepared_query("DELETE FROM nav_items WHERE id = ?", $P['id']);
} else {
    $Val = new Gazelle\Util\Validator;
    $Val->setFields([
        ['tag', true, 'string', 'The key must be set, and has a max length of 20 characters', ['maxlength' => 20]],
        ['title', true, 'string', 'The title must be set, and has a max length of 50 characters', ['maxlength' => 50]],
        ['target', true, 'string', 'The target must be set, and has a max length of 200 characters', ['maxlength' => 200]],
        ['tests', false, 'string', 'The tests are optional, and have a max length of 200 characters', ['maxlength' => 200]],
        ['testuser', true, 'checkbox', ''],
        ['mandatory', true, 'checkbox', ''],
        ['default', true, 'checkbox', ''],
    ]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $db->prepared_query("
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

        $db->prepared_query("
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
