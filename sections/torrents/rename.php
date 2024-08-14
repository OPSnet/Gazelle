<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}

$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    error('Torrent groups must have a name');
}

$tgMan = new \Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}

$tgroup->rename($name, $Viewer, $tgMan, new Gazelle\Log());
header("Location: {$tgroup->location()}");
