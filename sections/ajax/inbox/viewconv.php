<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$pm = new Manager\PM($Viewer)->findById((int)($_GET['id'] ?? 0));
if (is_null($pm)) {
    json_die('failure');
}
$pm->markRead();

echo new Json\PM($pm)->response();
