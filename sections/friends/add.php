<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$friend = new Manager\User()->findById((int)($_GET['friendid'] ?? 0));
if (!$friend) {
    Error404::error("no such user found");
}

if ($friend->id === $Viewer->id) {
    Error400::error("you cannot add yourself as a friend");
}

if (!new User\Friend($Viewer)->add($friend)) {
    Error400::error("you are already friends with {$friend->username()}");
}

header('Location: friends.php');
