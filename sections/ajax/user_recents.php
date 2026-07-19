<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$user = new Manager\User()->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    json_error("bad userid");
}
$limit = (int)($_GET['limit'] ?? 10);
if ($limit < 1 || $limit > 50) {
    json_error("bad limit");
}

echo new Json\UserRecent($user, $Viewer)
    ->setLimit($limit)
    ->setVersion(2)
    ->response();
