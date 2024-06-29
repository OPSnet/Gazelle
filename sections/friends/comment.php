<?php

authorize();

$friend = (new Gazelle\Manager\User())->findById((int)($_POST['friendid'] ?? 0));
if (!$friend) {
    error("no such user found");
}

$viewerFriend = new Gazelle\User\Friend($Viewer);
if (!$viewerFriend->isFriend($friend)) {
    error("you are not friends with {$friend->username()}");
}

$viewerFriend->addComment($friend, trim($_POST['comment']));

header('Location: friends.php');
