<?php

authorize();
$UserID = (int)$_GET['userid'];
if (!$UserID) {
    error(404);
}
if (!check_perms('users_mod') && $_GET['userid'] != $Viewer->id()) {
    error(403);
}

$notification = new Gazelle\Manager\Notification;
$notification->push($UserID, 'Push!', 'You have been pushed by ' . $Viewer->username());

header("Location: user.php?action=edit&userid={$UserID}");
