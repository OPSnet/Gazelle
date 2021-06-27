<?php

authorize();
$Viewer->addFriendComment((int)($_POST['friendid'] ?? 0), trim($_POST['comment']));
header('Location: friends.php');
