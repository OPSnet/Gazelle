<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

ini_set('memory_limit', -1);

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        json_error('bad parameters');
    }
    $user = new Manager\User()->findById((int)$_GET['userid']);
    if (is_null($user)) {
        json_error('bad parameters');
    }
}

echo new Json\Bookmark\TGroup(new User\Bookmark($user))
    ->setVersion(2)
    ->response();
