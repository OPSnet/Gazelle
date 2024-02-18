<?php

echo (new Gazelle\Json\Better\SingleSeeded(
    $Viewer,
    new Gazelle\Better\SingleSeeded($Viewer, 'all', new Gazelle\Manager\Torrent())
))
    ->setVersion(2)
    ->response();
