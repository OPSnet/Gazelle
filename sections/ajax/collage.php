<?php

$collage = (new Gazelle\Manager\Collage)->findById((int)($_GET['id'] ?? 0));
if (is_null($collage)) {
    json_die('bad parameters');
}

echo (new Gazelle\Json\Collage(
    $collage,
    $Viewer,
    new Gazelle\Manager\TGroup,
    new Gazelle\Manager\Torrent
))
    ->setVersion(2)
    ->response();
