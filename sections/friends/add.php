<?php

authorize();
if (!(new Gazelle\User\Friend($Viewer))->add((int)($_GET['friendid'] ?? 0))) {
    error(0);
}
header('Location: friends.php');
