<?php
authorize();

$bookmark = new \Gazelle\Bookmark;
try {
    $bookmark->remove($Viewer->id(), $_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
