<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$friend = new Manager\User()->findById((int)($_POST['friendid'] ?? 0));
if (!$friend) {
    Error404::error("Not sure they could be your friend");
}

$viewerFriend = new User\Friend($Viewer);
if (!$viewerFriend->isFriend($friend)) {
    Error400::error("You are not friends with {$friend->username()}");
}

$viewerFriend->setComment($friend, trim($_POST['comment']));

header('Location: friends.php');
