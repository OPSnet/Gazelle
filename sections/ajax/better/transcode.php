<?php

if (!in_array((int)($_GET['type'] ?? 0), range(0, 3))) {
    error(0);
}

$search = new Gazelle\Search\Transcode($Viewer, (new Gazelle\Manager\Torrent)->setViewer($Viewer));
if (isset($_GET['search'])) {
    $search->setSearch($_GET['search']);
}

echo (new Gazelle\Json\Better\Transcode($Viewer->announceKey(), $search))
    ->setVersion(2)
    ->response();
