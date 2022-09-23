<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

authorize();

$_POST['submit'] = $_POST['submit'] ?? $_POST['create'] ?? '';
$manager = new Gazelle\Manager\IRC;

if ($_POST['submit'] == 'Delete') { //Delete
dump($_POST);
exit;
    $ID = intval($_POST['id'] ?? 0);
    if ($ID === 0) {
        error(0);
    }
    $DB->prepared_query('DELETE FROM irc_channels WHERE ID = ?', $ID);

} else {
    // Edit & Create have shared validation
    // we have a 'submit-234' key, so we want to pull out that 234
    $id = (int)explode('-',
        current(
            array_keys(
                array_filter(
                    $_POST,
                    fn ($key) => str_starts_with($key, 'submit-'),
                    ARRAY_FILTER_USE_KEY
                )
            )
        ) ?? 'a-0' // fallback to $id = 0, which will evaluate to false
    )[1];
    // The post fields will be either 'name' or 'name-234' (which is a bit of a hassle), so rename back to 'name' for the validator
    if ($id) {
        foreach (['classes', 'min_level', 'name', 'sort'] as $field) {
            $_POST[$field] = $_POST["$field-$id"];
        }
    }
    $Val = new Gazelle\Util\Validator;
    $Val->setFields([
        ['name', '1', 'regex', "The name must be set and has a max length of 50 characters", ['regex' => '/^[\w-]{2,50}$/i']],
        ['sort', '1', 'number', 'Sort must be set'],
        ['min_level', '1', 'number', 'MinLevel must be set'],
    ]);
    if (!$Val->validate($_POST)) {
        error($Val->errorMessage());
    }

    $sort      = (int)$_POST['sort'];
    $minLevel  = (int)$_POST['min_level'];
    $classList = array_unique(array_filter(array_map('intval', explode(',', $_POST['classes'] ?? '')), fn ($n) => $n > 0));
    if (!$id) {
        $manager->create($_POST['name'], $sort, $minLevel, $classList);
    } else {
        $manager->modify($id, $_POST['name'], $sort, $minLevel, $classList);
    }
}

header('Location: tools.php?action=irc');
