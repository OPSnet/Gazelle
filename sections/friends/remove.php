<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$friend = (new Gazelle\Manager\User())->findById((int)($_POST['friendid'] ?? 0));
if (!$friend) {
    error("no such user found");
}

(new Gazelle\User\Friend($Viewer))->remove($friend);

header('Location: friends.php');
