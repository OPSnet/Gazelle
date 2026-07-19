<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$user = new Manager\User()->findById((int)$_GET['id']);
if (is_null($user)) {
    json_die("failure", "bad id parameter");
}

echo new Json\User($user, $Viewer)
    ->setVersion(2)
    ->response();
