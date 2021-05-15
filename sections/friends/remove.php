<?php

authorize();
$Viewer = new Gazelle\User($LoggedUser['ID']);
$Viewer->removeFriend((int)($_POST['friendid'] ?? 0));
header('Location: friends.php');
