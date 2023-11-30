<?php

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        json_die('failure');
    }
    $user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
    if (is_null($user)) {
        json_die('failure');
    }
}

echo (new Gazelle\Json\Bookmark\Artist(new Gazelle\User\Bookmark($user)))
    ->response();
