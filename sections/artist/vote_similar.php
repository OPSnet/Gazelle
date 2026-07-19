<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$way = trim($_GET['way']);
if (!in_array($way, ['up', 'down'])) {
    Error400::error('Missing artist vote decision');
}

$artistMan = new Manager\Artist();
$artist    = $artistMan->findById((int)($_GET['artistid'] ?? 0));
$similar   = $artistMan->findById((int)($_GET['similarid'] ?? 0));
if (is_null($artist) || is_null($similar)) {
    Error404::error("One of the artists was not found");
}
$artist->similar()->voteSimilar($Viewer, $similar, $way === 'up');

header("Location: " . redirectUrl($artist->location()));
