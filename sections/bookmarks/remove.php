<?php
authorize();

$bookmark = new \Gazelle\Bookmark($Viewer);
try {
    $bookmark->remove($_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
