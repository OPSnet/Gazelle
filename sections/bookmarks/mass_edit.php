<?php
authorize();

if ($_POST['type'] === 'torrents') {
    $BU = new MASS_USER_BOOKMARKS_EDITOR;
    if ($_POST['delete']) {
        $BU->mass_remove();
    } elseif ($_POST['update']) {
        $BU->mass_update();
    }
}

header('Location: bookmarks.php?type=torrents');
