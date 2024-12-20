<?php
/** @phpstan-var \Gazelle\User $Viewer */

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)($_GET['id'] ?? 0));
if (is_null($pm)) {
    json_die('failure');
}
$pm->markRead();

echo (new Gazelle\Json\PM($pm, new Gazelle\Manager\User()))->response();
