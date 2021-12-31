<?php

authorize();
(new Gazelle\User\Friend($Viewer))->remove((int)($_POST['friendid'] ?? 0));
header('Location: friends.php');
