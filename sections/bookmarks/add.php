<?php
authorize();

$bookmark = new \Gazelle\Bookmark;
try {
    $bookmark->create($Viewer->id(), $_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
