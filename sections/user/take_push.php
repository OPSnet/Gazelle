<?php

authorize();
$UserID = (int)$_GET['userid'];
if (!$UserID) {
    error(404);
}
if (!check_perms('users_mod') && $_GET['userid'] != $LoggedUser['ID']) {
    error(403);
}

$notification = new Gazelle\Manager\Notification;
$notification->push($UserID, 'Push!', 'You have been pushed by ' . $LoggedUser['Username']);

header("Location: user.php?action=edit&userid={$UserID}");
