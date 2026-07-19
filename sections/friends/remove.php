<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$friend = new Manager\User()->findById((int)($_POST['friendid'] ?? 0));
if (!$friend) {
    Error404::error("no such user found");
}

new User\Friend($Viewer)->removeFriend($friend);

header('Location: friends.php');
