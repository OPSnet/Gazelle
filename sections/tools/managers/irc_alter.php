<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();

$_POST['submit'] = $_POST['submit'] ?? 'Create';

if ($_POST['submit'] == 'Delete') { //Delete
    $ID = intval($_POST['id'] ?? 0);
    if ($ID === 0) {
        error(0);
    }
    $DB->prepared_query('DELETE FROM irc_channels WHERE ID = ?', $ID);
}
else { //Edit & Create, Shared Validation
    $Val = new Gazelle\Util\Validator;
    $Val->setFields([
        ['name', '1', 'regex', "The name must be set, has a max length of 50 characters, start with '&', '#', '+' or '!', and not contain any spaces or commas",
            ['regex' => '/^[&|#|\+|\!][^, ]+$/i', 'range' => [2, 50]]],
        ['sort', '1', 'number', 'Sort must be set'],
        ['min_level', '1', 'number', 'MinLevel must be set'],
    ]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }
    $Sort = intval($_POST['sort']);
    $MinLevel = intval($_POST['min_level']);
    $Classes = $_POST['classes'] ?? '';
    $Classes = implode(',', array_filter(array_map('intval', explode(',', $Classes)), function($var) { return $var !== 0; }));

    if ($_POST['submit'] == 'Edit') {
        $ID = intval($_POST['id'] ?? 0);
        if ($ID === 0) {
            error(0);
        }
        $DB->prepared_query('UPDATE irc_channels SET Name=?, Sort=?, MinLevel=?, Classes=? WHERE ID=?', $_POST['name'], $Sort, $MinLevel, $Classes, $ID);
    }
    else {
        $DB->prepared_query('INSERT INTO irc_channels (Name, Sort, MinLevel, Classes) VALUES (?, ?, ?, ?)', $_POST['name'], $Sort, $MinLevel, $Classes);
    }
}

$Cache->delete_value('irc_channels'); // Clear cache

header('Location: tools.php?action=irc');
