<?php

authorize();

$friend = (new Gazelle\Manager\User())->findById((int)($_GET['friendid'] ?? 0));
if (!$friend) {
    error("no such user found");
}

if ($friend->id() === $Viewer->id()) {
    error("you cannot add yourself as a friend");
}

if (!(new Gazelle\User\Friend($Viewer))->add($friend)) {
    error("you are already friends with {$friend->username()}");
}

header('Location: friends.php');
