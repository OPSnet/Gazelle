<?php

authorize();
$Viewer = new Gazelle\User($LoggedUser['ID']);
if (!$Viewer->addFriend((int)($_GET['friendid'] ?? 0))) {
    error(0);
}
header('Location: friends.php');
