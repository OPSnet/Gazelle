<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        json_die('failure');
    }
    $user = new Manager\User()->findById((int)$_GET['userid']);
    if (is_null($user)) {
        json_die('failure');
    }
}

echo new Json\Bookmark\Artist(new User\Bookmark($user))
    ->response();
