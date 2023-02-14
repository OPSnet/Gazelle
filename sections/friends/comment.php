<?php

authorize();

((new Gazelle\User\Friend($Viewer)))->addComment((int)($_POST['friendid'] ?? 0), trim($_POST['comment']));

header('Location: friends.php');
