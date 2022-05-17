<?php
authorize();

$bookmark = new Gazelle\User\Bookmark($Viewer);
try {
    $bookmark->create($_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
