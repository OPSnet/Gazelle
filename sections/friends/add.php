<?php

authorize();
if (!$Viewer->addFriend((int)($_GET['friendid'] ?? 0))) {
    error(0);
}
header('Location: friends.php');
