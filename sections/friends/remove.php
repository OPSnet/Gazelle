<?php

authorize();
$Viewer->removeFriend((int)($_POST['friendid'] ?? 0));
header('Location: friends.php');
