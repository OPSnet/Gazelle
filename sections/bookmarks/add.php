<?php
authorize();

$bookmark = new \Gazelle\Bookmark;
try {
    // calls get_group_info from torrents/functions.php
    require(__DIR__ . '/../torrents/functions.php'); // TODO: refactor this shit
    $bookmark->create($LoggedUser['ID'], $_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
