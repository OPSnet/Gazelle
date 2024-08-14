<?php
/** @phpstan-var \Gazelle\User $Viewer */

$collage = (new Gazelle\Manager\Collage())->findById((int)($_GET['id'] ?? 0));
if (is_null($collage)) {
    json_die('bad parameters');
}

echo (new Gazelle\Json\Collage(
    $collage,
    (int)($_GET['page'] ?? 1),
    $Viewer,
    new Gazelle\Manager\TGroup(),
    new Gazelle\Manager\Torrent()
))
    ->response();
