<?php

ini_set('memory_limit', -1);

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        json_error('bad parameters');
    }
    $user = (new Gazelle\Manager\User())->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        json_error('bad parameters');
    }
}

echo (new Gazelle\Json\Bookmark\TGroup(
    new Gazelle\User\Bookmark($user),
    new Gazelle\Manager\TGroup(),
    new Gazelle\Manager\Torrent())
)
    ->setVersion(2)
    ->response();
