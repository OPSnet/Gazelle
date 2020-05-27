<?php

use \Gazelle\Manager\Notification;

authorize();
if (!check_perms('users_mod') && $_GET['userid'] != $LoggedUser['ID']) {
    error(403);
}

$UserID = db_string($_GET['userid']);
$notification = new Notification;
$notification->push($UserID, 'Push!', 'You\'ve been pushed by ' . $LoggedUser['Username']);

header('Location: user.php?action=edit&userid=' . $UserID . "");
